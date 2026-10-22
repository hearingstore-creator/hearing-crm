<?php

namespace Tests\Feature;

use App\Classes\SMS;
use App\Models\BlockedContact;
use App\Models\Doctor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** SMS phase 5: big broadcasts to doctors / staff wait for approval; STOP replies from the gateway opt the number out. */
class SmsApprovalAndRepliesTest extends TestCase
{
    use DatabaseTransactions, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();

        $this->app->bind(SMS::class, fn () => new class extends SMS {
            public function callApi($url, $params)
            {
                $p = json_decode($params, true);
                if (isset($p['sms']) && is_array($p['sms'])) {
                    return json_encode(['status' => 'SUCCESS', 'smsinfo' => array_map(fn ($s) => ['csms_id' => $s['csms_id'], 'sms_status' => 'SUCCESS'], $p['sms'])]);
                }

                return json_encode(['status' => 'SUCCESS']);
            }
        });

        DB::table('sms_logs')->delete();
        DB::table('sms_inbound')->delete();
        config(['crm.sms_team_approval_threshold' => 2]);

        foreach (['One', 'Two', 'Three'] as $i => $name) {
            Doctor::create(['name' => "Dr {$name}", 'referrer_type' => 'doctor', 'contact_number' => '0171000000' . $i, 'status' => 1]);
        }
    }

    protected function broadcast($user, array $groups = ['doctor'], array $extra = [])
    {
        return $this->actingAs($user)->post('/admin/sms/send', array_merge([
            'campaign_name' => 'Doctors meeting', 'message_body' => 'Dear {{name}}, meeting on Friday.', 'audience_type' => 'team',
            'filter_criteria' => json_encode([['field' => 'group', 'op' => 'in', 'values' => $groups]]),
        ], $extra));
    }

    protected function sender()
    {
        return $this->userWithPermissions(['compose_&_send.index', 'compose_&_send.create', 'compose_&_send.team']);
    }

    protected function approver()
    {
        return $this->userWithPermissions(['compose_&_send.index', 'compose_&_send.create', 'compose_&_send.team', 'compose_&_send.approve']);
    }

    // ── approval ──

    public function test_a_large_broadcast_waits_and_nothing_is_sent(): void
    {
        $this->broadcast($this->sender())->assertSessionHas('success');

        $camp = DB::table('sms_campaigns')->first();
        $this->assertSame('pending_approval', $camp->status);
        $this->assertSame(3, (int) $camp->total_recipients);
        $this->assertSame(0, DB::table('sms_logs')->count());
        $this->assertSame(1, DB::table('notifications')->where('data', 'like', '%awaiting approval%')->count() > 0 ? 1 : 0, 'the owner is told');
    }

    public function test_small_broadcasts_and_approvers_are_not_held_up(): void
    {
        // 1 of the 3 doctors picked by name: under the limit
        $dr = Doctor::where('name', 'Dr One')->value('id');
        $this->actingAs($this->sender())->post('/admin/sms/send', [
            'campaign_name' => 'Just one', 'message_body' => 'Hi', 'audience_type' => 'team',
            'filter_criteria' => json_encode([['field' => 'person', 'op' => 'in', 'values' => ["doctor:$dr"]]]),
        ])->assertSessionHas('success');
        $this->assertSame(1, DB::table('sms_logs')->count());

        // an approver sending 3 goes straight out
        DB::table('sms_logs')->delete();
        $this->broadcast($this->approver());
        $this->assertSame(3, DB::table('sms_logs')->count());
    }

    public function test_an_approver_can_approve_but_the_writer_cannot_approve_their_own(): void
    {
        $writer = $this->approver();   // holds the permission, but is also the author of this one
        $camp = DB::table('sms_campaigns')->insertGetId([
            'name' => 'Held', 'message_body' => 'Dear {{name}}', 'recipient_type' => 'team', 'status' => 'pending_approval', 'total_recipients' => 3,
            'filter_criteria' => json_encode([['field' => 'group', 'op' => 'in', 'values' => ['doctor']]]), 'created_by' => $writer->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($writer)->post("/admin/sms/campaigns/{$camp}/approve")->assertSessionHas('warning');
        $this->assertSame('pending_approval', DB::table('sms_campaigns')->where('id', $camp)->value('status'));

        $this->actingAs($this->sender())->post("/admin/sms/campaigns/{$camp}/approve")->assertForbidden();

        $other = $this->approver();
        $this->actingAs($other)->post("/admin/sms/campaigns/{$camp}/approve")->assertSessionHas('success');
        $row = DB::table('sms_campaigns')->where('id', $camp)->first();
        $this->assertSame('completed', $row->status);
        $this->assertSame($other->id, (int) $row->approved_by);
        $this->assertSame(3, DB::table('sms_logs')->count());

        $this->actingAs($other)->post("/admin/sms/campaigns/{$camp}/approve")->assertNotFound();
        $this->assertSame(3, DB::table('sms_logs')->count(), 'never sent twice');
    }

    public function test_a_rejected_broadcast_sends_nothing(): void
    {
        $this->broadcast($this->sender());
        $id = DB::table('sms_campaigns')->value('id');

        $this->actingAs($this->approver())->post("/admin/sms/campaigns/{$id}/reject", ['note' => 'Wrong date'])->assertSessionHas('success');
        $row = DB::table('sms_campaigns')->where('id', $id)->first();
        $this->assertSame('rejected', $row->status);
        $this->assertSame('Wrong date', $row->approval_note);
        $this->assertSame(0, DB::table('sms_logs')->count());
    }

    public function test_the_compose_page_lists_waiting_broadcasts_with_buttons_only_for_approvers(): void
    {
        $this->broadcast($this->sender());

        $this->actingAs($this->approver())->get('/admin/sms')->assertSee('Waiting for approval')->assertSee('Doctors meeting')->assertSee('Approve &amp; send', false);
        $this->actingAs($this->sender())->get('/admin/sms')->assertSee('Doctors meeting')->assertSee('Waiting for a manager')->assertDontSee('Approve &amp; send', false);
    }

    // ── replies ──

    public function test_the_reply_endpoint_is_off_until_a_secret_is_set_and_checks_it(): void
    {
        config(['sms.inbound_secret' => null]);
        $this->postJson('/api/v1/sms/inbound', ['msisdn' => '01711111111', 'sms' => 'STOP'])->assertNotFound();

        config(['sms.inbound_secret' => 's3cret-key']);
        $this->postJson('/api/v1/sms/inbound', ['msisdn' => '01711111111', 'sms' => 'STOP'])->assertForbidden();
        $this->postJson('/api/v1/sms/inbound?key=wrong', ['msisdn' => '01711111111', 'sms' => 'STOP'])->assertForbidden();
        $this->assertSame(0, DB::table('sms_inbound')->count());
        $this->assertSame(0, BlockedContact::where('type', BlockedContact::SMS_OPT_OUT)->count());
    }

    public function test_a_stop_reply_opts_the_number_out_and_other_replies_are_only_recorded(): void
    {
        config(['sms.inbound_secret' => 's3cret-key']);
        DB::table('sms_logs')->insert([
            'recipient_type' => 'customer', 'recipient_id' => 1, 'phone_number' => '01711111111', 'message' => 'offer', 'type' => 'bulk', 'status' => 'sent',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/sms/inbound?key=s3cret-key', ['msisdn' => '8801711111111', 'sms' => ' stop! '])->assertSuccessful()->assertJsonPath('action', 'stop');
        $this->assertTrue(\App\Services\SmsAutomationService::isOptedOut('01711111111'));
        $row = DB::table('sms_inbound')->first();
        $this->assertSame('stop', $row->action);
        $this->assertNotNull($row->sms_log_id, 'tied to the last message we sent that number');

        $this->withHeader('X-Sms-Secret', 's3cret-key')->postJson('/api/v1/sms/inbound', ['from' => '01822222222', 'text' => 'বন্ধ'])->assertJsonPath('action', 'stop');
        $this->assertTrue(\App\Services\SmsAutomationService::isOptedOut('01822222222'));

        $this->postJson('/api/v1/sms/inbound?key=s3cret-key', ['msisdn' => '01933333333', 'sms' => 'What time are you open?'])->assertJsonPath('action', null);
        $this->assertFalse(\App\Services\SmsAutomationService::isOptedOut('01933333333'));
        $this->assertSame(3, DB::table('sms_inbound')->count());

        $this->postJson('/api/v1/sms/inbound?key=s3cret-key', ['msisdn' => '01933333333'])->assertStatus(422);

        $this->actingAs($this->admin)->get('/admin/sms')->assertSuccessful()->assertSee('Replies from patients')->assertSee('What time are you open?');
    }
}
