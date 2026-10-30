<?php

namespace Tests\Feature;

use App\Classes\SMS;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** SMS Centre: doctors, referrers, employees and branch managers as recipients (managers only). */
class SmsTeamAudienceTest extends TestCase
{
    use DatabaseTransactions, CrmFixtures;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();

        // a gateway that accepts everything and remembers what it was asked to send
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

        $this->manager = $this->userWithPermissions(['compose_&_send.index', 'compose_&_send.create', 'compose_&_send.team', 'sms_log.index']);
        $this->doctor('Dr Rahim', 'doctor', '01711000001');
        $this->doctor('Dr NoPhone', 'doctor', null);
        $this->doctor('Karim Quack', 'quack', '01711000002');
        $this->doctor('Salma Agent', 'agent', '01711000003');
        $this->doctor('Dr Gone', 'doctor', '01711000004', 0);
        User::forceCreate(['name' => 'Staff Nasrin', 'email' => 'nasrin@test.test', 'password' => bcrypt('x'), 'mobile_no' => '01811000001', 'status' => 1]);
        User::forceCreate(['name' => 'Staff Left', 'email' => 'left@test.test', 'password' => bcrypt('x'), 'mobile_no' => '01811000002', 'status' => 0]);
        User::forceCreate(['name' => 'Uttara Branch', 'email' => 'uttara@test.test', 'password' => bcrypt('x'), 'mobile_no' => '01911000001', 'status' => 1, 'type' => 'vendor']);
    }

    protected function doctor(string $name, string $type, ?string $phone, int $status = 1): Doctor
    {
        return Doctor::create(['name' => $name, 'referrer_type' => $type, 'contact_number' => $phone, 'status' => $status]);
    }

    protected function team(array $filters)
    {
        return $this->actingAs($this->manager)->postJson('/admin/sms/audience-filter', ['type' => 'team', 'filters' => $filters]);
    }

    public function test_groups_pick_the_right_active_people(): void
    {
        $res = $this->team([['field' => 'group', 'op' => 'in', 'values' => ['doctor']]])->assertSuccessful()->json();
        $names = collect($res['preview'])->pluck('name')->all();
        $this->assertContains('Dr Rahim', $names);
        $this->assertNotContains('Dr Gone', $names, 'inactive doctors are left out');
        $this->assertNotContains('Karim Quack', $names);
        $this->assertSame(2, $res['stats']['total']);
        $this->assertSame(1, $res['stats']['ready_to_send'], 'the doctor without a number cannot be sent to');

        $res = $this->team([['field' => 'group', 'op' => 'in', 'values' => ['quack', 'agent']]])->json();
        $this->assertEqualsCanonicalizing(['Karim Quack', 'Salma Agent'], collect($res['preview'])->pluck('name')->all());

        $res = $this->team([['field' => 'group', 'op' => 'in', 'values' => ['employee']]])->json();
        $names = collect($res['preview'])->pluck('name')->all();
        $this->assertContains('Staff Nasrin', $names);
        $this->assertNotContains('Staff Left', $names);
        $this->assertNotContains('Uttara Branch', $names, 'branch logins are their own group');

        $res = $this->team([['field' => 'group', 'op' => 'in', 'values' => ['branch']]])->json();
        $this->assertEqualsCanonicalizing(['Uttara Branch', 'Test Branch'], collect($res['preview'])->pluck('name')->all());

        $this->assertSame(0, $this->team([])->json('stats.total'), 'no group, nobody');
    }

    public function test_individuals_can_be_picked(): void
    {
        $dr = Doctor::where('name', 'Dr Rahim')->value('id');
        $user = User::where('email', 'nasrin@test.test')->value('id');
        $res = $this->team([['field' => 'person', 'op' => 'in', 'values' => ["doctor:$dr", "user:$user"]]])->json();
        $this->assertEqualsCanonicalizing(['Dr Rahim', 'Staff Nasrin'], collect($res['preview'])->pluck('name')->all());
    }

    public function test_a_campaign_to_the_team_is_sent_and_logged_per_person_type(): void
    {
        $this->actingAs($this->manager)->post('/admin/sms/send', [
            'campaign_name' => 'Eid greeting', 'message_body' => 'Dear {{name}}, Eid Mubarak from Zenhear.', 'audience_type' => 'team',
            'filter_criteria' => json_encode([['field' => 'group', 'op' => 'in', 'values' => ['doctor', 'quack', 'employee']]]),
        ])->assertSessionHas('success');

        $logs = DB::table('sms_logs')->get()->keyBy('phone_number');
        $this->assertSame('doctor', $logs['01711000001']->recipient_type);
        $this->assertSame('referrer', $logs['01711000002']->recipient_type);
        $this->assertSame('employee', $logs['01811000001']->recipient_type);
        $this->assertSame('Dear Karim Quack, Eid Mubarak from Zenhear.', $logs['01711000002']->message);
        $this->assertSame('sent', $logs['01711000001']->status);
        $this->assertFalse($logs->has('01711000004'), 'inactive doctor');
        $this->assertFalse($logs->has('01911000001'), 'branch not selected');
    }

    public function test_the_team_audience_needs_its_own_permission(): void
    {
        $reception = $this->userWithPermissions(['compose_&_send.index', 'compose_&_send.create']);

        $this->actingAs($reception)->getJson('/admin/sms/audience-filter-config?type=team')->assertForbidden();
        $this->actingAs($reception)->postJson('/admin/sms/audience-filter', ['type' => 'team', 'filters' => []])->assertForbidden();
        $this->actingAs($reception)->post('/admin/sms/send', [
            'campaign_name' => 'x', 'message_body' => 'x', 'audience_type' => 'team',
            'filter_criteria' => json_encode([['field' => 'group', 'op' => 'in', 'values' => ['doctor']]]),
        ])->assertForbidden();
        $this->assertSame(0, DB::table('sms_logs')->count());

        // patients and leads still work for the same person
        $this->actingAs($reception)->getJson('/admin/sms/audience-filter-config?type=leads')->assertSuccessful();
        $this->actingAs($this->manager)->getJson('/admin/sms/audience-filter-config?type=team')->assertSuccessful()->assertJsonPath('categories.0.id', 'group');
    }

    public function test_quick_send_to_one_person(): void
    {
        $dr = Doctor::where('name', 'Dr Rahim')->value('id');
        $this->actingAs($this->manager)->postJson('/admin/sms/quick-send', ['person_type' => 'doctor', 'person_id' => $dr, 'message' => 'Please call the clinic.'])
            ->assertSuccessful()->assertJsonPath('success', true);

        $log = DB::table('sms_logs')->latest('id')->first();
        $this->assertSame('doctor', $log->recipient_type);
        $this->assertSame('manual', $log->type);
        $this->assertSame('01711000001', $log->phone_number);

        $noPhone = Doctor::where('name', 'Dr NoPhone')->value('id');
        $this->actingAs($this->manager)->postJson('/admin/sms/quick-send', ['person_type' => 'doctor', 'person_id' => $noPhone, 'message' => 'Hi'])->assertStatus(422);

        $reception = $this->userWithPermissions(['compose_&_send.index', 'compose_&_send.create']);
        $this->actingAs($reception)->postJson('/admin/sms/quick-send', ['person_type' => 'doctor', 'person_id' => $dr, 'message' => 'Hi'])->assertForbidden();
    }

    public function test_the_log_can_be_filtered_by_who_it_was_sent_to(): void
    {
        DB::table('sms_logs')->insert([
            ['recipient_type' => 'doctor', 'recipient_id' => 1, 'phone_number' => '01700000001', 'message' => 'to doctor', 'type' => 'manual', 'status' => 'sent', 'created_at' => now(), 'updated_at' => now()],
            ['recipient_type' => 'customer', 'recipient_id' => 1, 'phone_number' => '01700000002', 'message' => 'to patient', 'type' => 'manual', 'status' => 'sent', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($this->manager)->get('/admin/sms-log?recipient=doctor')->assertSuccessful()->assertSee('to doctor')->assertDontSee('to patient');
    }

    public function test_doctor_and_people_pages_show_the_sms_button_only_to_managers(): void
    {
        $this->actingAs($this->admin)->get('/admin/doctor')->assertSuccessful()->assertSee('btn-quick-sms', false);
        $this->actingAs($this->admin)->get('/admin/user')->assertSuccessful()->assertSee('btn-quick-sms', false);

        $viewer = $this->userWithPermissions(['doctor_list.index', 'user_list.index']);
        $this->actingAs($viewer)->get('/admin/doctor')->assertDontSee('btn-quick-sms', false);
    }
}
