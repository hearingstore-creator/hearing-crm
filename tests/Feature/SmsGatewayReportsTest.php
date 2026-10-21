<?php

namespace Tests\Feature;

use App\Classes\SMS;
use App\Models\Doctor;
use App\Models\Setting;
use App\Services\SmsGateway;
use App\Services\SmsService;
use App\Services\SmsUsage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** SMS phase 4: gateway balance, delivery reports, and cost per month / campaign. The gateway is faked. */
class SmsGatewayReportsTest extends TestCase
{
    use DatabaseTransactions, CrmFixtures;

    /** @var array<string, string|callable> answers by endpoint name: send, balance, status */
    public static array $answers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        self::$answers = [];

        $this->app->bind(SMS::class, fn () => new class extends SMS {
            public function callApi($url, $params)
            {
                $key = str_contains($url, '/balance') ? 'balance' : (str_contains($url, 'sms-status') ? 'status' : 'send');
                $answer = SmsGatewayReportsTest::$answers[$key] ?? null;
                if (is_callable($answer)) {
                    return $answer(json_decode($params, true));
                }
                if ($answer !== null) {
                    return $answer;
                }
                $p = json_decode($params, true);
                if (isset($p['sms']) && is_array($p['sms'])) {
                    return json_encode(['status' => 'SUCCESS', 'smsinfo' => array_map(fn ($s) => ['csms_id' => $s['csms_id'], 'sms_status' => 'SUCCESS', 'reference_id' => 'REF-' . $s['csms_id']], $p['sms'])]);
                }

                return json_encode(['status' => 'SUCCESS', 'smsinfo' => [['sms_status' => 'SUCCESS', 'reference_id' => 'REF-1']]]);
            }
        });

        DB::table('settings')->where('id', '!=', 1)->delete();
        if (! DB::table('settings')->where('id', 1)->exists()) {
            DB::table('settings')->insert(['id' => 1, 'company_name' => 'Test Co', 'created_at' => now()]);
        }
        Setting::find(1)->update(['sms_balance_last' => null, 'sms_balance_checked_at' => null, 'sms_low_balance' => null, 'sms_cost_per_sms' => 0.5]);
        DB::table('sms_logs')->delete();
        DB::table('automation_logs')->delete();
    }

    protected function sentLog(string $csmsId, array $extra = []): int
    {
        return DB::table('sms_logs')->insertGetId($extra + [
            'recipient_type' => 'customer', 'recipient_id' => 1, 'phone_number' => '01711111111', 'message' => 'm', 'type' => 'manual',
            'status' => 'sent', 'csms_id' => $csmsId, 'sms_parts' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ── reading the gateway's replies ──

    public function test_balance_is_found_in_the_shapes_a_gateway_might_use(): void
    {
        $this->assertSame(1234.5, SmsGateway::extractBalance(['status' => 'SUCCESS', 'balance' => '1,234.50']));
        $this->assertSame(99.0, SmsGateway::extractBalance(['status' => 'SUCCESS', 'data' => ['available_balance' => 99]]));
        $this->assertSame(250.0, SmsGateway::extractBalance(['status' => 'SUCCESS', 'smsinfo' => [['credit' => 'BDT 250']]]));
        $this->assertNull(SmsGateway::extractBalance(['status' => 'SUCCESS', 'message' => 'ok']));
    }

    public function test_message_state_is_read_from_the_message_entry_not_the_api_status(): void
    {
        $this->assertSame('delivered', SmsGateway::mapState(['status' => 'SUCCESS', 'smsinfo' => [['sms_status' => 'Delivered']]])['state']);
        $this->assertSame('failed', SmsGateway::mapState(['status' => 'SUCCESS', 'smsinfo' => [['sms_status' => 'UNDELIVERED', 'status_message' => 'handset off']]])['state']);
        $this->assertSame('pending', SmsGateway::mapState(['status' => 'SUCCESS', 'smsinfo' => [['sms_status' => 'SUCCESS']]])['state'], 'accepted is not delivered');
        $this->assertSame('unknown', SmsGateway::mapState(['status' => 'SUCCESS'])['state']);
    }

    // ── balance ──

    public function test_the_balance_button_stores_the_figure_and_reports_a_refusal_plainly(): void
    {
        self::$answers['balance'] = json_encode(['status' => 'SUCCESS', 'balance' => '742.25']);
        $this->actingAs($this->admin)->postJson('/admin/sms/gateway-balance')->assertSuccessful()->assertJsonPath('balance', '742.25');
        $this->assertEquals(742.25, (float) Setting::find(1)->sms_balance_last);

        self::$answers['balance'] = json_encode(['status' => 'FAILED', 'status_code' => 4003, 'error_message' => 'IP Blacklisted']);
        $this->actingAs($this->admin)->postJson('/admin/sms/gateway-balance')->assertStatus(502)->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'IP Blacklisted The gateway only answers from the server addresses it has whitelisted.']);
        $this->assertEquals(742.25, (float) Setting::find(1)->sms_balance_last, 'the last good figure is kept');

        $this->actingAs($this->admin)->get('/admin/sms')->assertSuccessful()->assertSee('Gateway balance')->assertSee('742.25');
    }

    public function test_a_low_balance_warns_the_owner_once_a_day(): void
    {
        self::$answers['balance'] = json_encode(['status' => 'SUCCESS', 'balance' => 80]);
        Setting::find(1)->update(['sms_low_balance' => 100]);

        Artisan::call('sms:check-balance');
        Artisan::call('sms:check-balance');
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $this->admin->id)->where('data', 'like', '%SMS balance is low%')->count());

        DB::table('notifications')->where('data', 'like', '%SMS balance is low%')->delete();
        DB::table('automation_logs')->delete();
        self::$answers['balance'] = json_encode(['status' => 'SUCCESS', 'balance' => 400]);
        Artisan::call('sms:check-balance');
        $this->assertSame(0, DB::table('notifications')->where('data', 'like', '%SMS balance is low%')->count(), 'enough credit, no alert');
    }

    public function test_the_low_balance_level_is_saved_from_the_automation_panel(): void
    {
        $manager = $this->userWithPermissions(['sms_automation.index', 'sms_automation.edit']);
        $this->actingAs($manager)->post('/admin/sms-automations/mode', ['mode' => 'all', 'cost_per_sms' => '0.4', 'low_balance' => '350'])->assertSessionHas('success');
        $this->assertEquals(350, (float) Setting::find(1)->sms_low_balance);

        $this->actingAs($manager)->post('/admin/sms-automations/mode', ['mode' => 'all', 'low_balance' => ''])->assertSessionHas('success');
        $this->assertNull(Setting::find(1)->sms_low_balance, 'empty switches the warning off');
    }

    // ── delivery reports ──

    public function test_sends_keep_the_message_id_for_later_delivery_checks(): void
    {
        app(SmsService::class)->sendAndLogSms('01712345678', 'Hello', 'customer', 1);
        $log = DB::table('sms_logs')->first();
        $this->assertNotNull($log->csms_id);
        $this->assertSame('REF-1', $log->gateway_ref);

        Doctor::create(['name' => 'Dr Bulk', 'referrer_type' => 'doctor', 'contact_number' => '01798765432', 'status' => 1]);
        DB::table('sms_logs')->delete();
        $this->actingAs($this->admin)->post('/admin/sms/send', [
            'campaign_name' => 'ID test', 'message_body' => 'Hi {{name}}', 'audience_type' => 'team',
            'filter_criteria' => json_encode([['field' => 'group', 'op' => 'in', 'values' => ['doctor']]]),
        ])->assertSessionHas('success');
        $bulk = DB::table('sms_logs')->first();
        $this->assertStringStartsWith('BULK-', $bulk->csms_id);
        $this->assertSame('REF-' . $bulk->csms_id, $bulk->gateway_ref);
    }

    public function test_the_hourly_check_records_what_the_gateway_says_and_skips_old_or_fresh_checks(): void
    {
        $a = $this->sentLog('CS-A');
        $b = $this->sentLog('CS-B');
        $c = $this->sentLog('CS-C');
        $old = $this->sentLog('CS-OLD', ['created_at' => now()->subDays(5)]);
        $fresh = $this->sentLog('CS-FRESH', ['delivery_status' => 'pending', 'delivery_checked_at' => now()->subMinutes(5)]);

        self::$answers['status'] = fn ($p) => json_encode(['status' => 'SUCCESS', 'smsinfo' => [['sms_status' => match ($p['csms_id']) {
            'CS-A' => 'Delivered', 'CS-B' => 'Undelivered', default => 'Submitted',
        }]]]);

        Artisan::call('sms:check-delivery');

        $status = fn ($id) => DB::table('sms_logs')->where('id', $id)->value('delivery_status');
        $this->assertSame('delivered', $status($a));
        $this->assertSame('failed', $status($b));
        $this->assertSame('pending', $status($c));
        $this->assertNull($status($old), 'older than 3 days: not asked');
        $this->assertSame('pending', $status($fresh));
        $this->assertNull(DB::table('sms_logs')->where('id', $fresh)->value('delivery_detail'), 'checked 5 minutes ago: not asked again');
    }

    public function test_a_gateway_level_refusal_stops_the_run_instead_of_repeating_it(): void
    {
        $this->sentLog('CS-1');
        $this->sentLog('CS-2');
        $calls = 0;
        self::$answers['status'] = function () use (&$calls) {
            $calls++;

            return json_encode(['status' => 'FAILED', 'status_code' => 4003, 'error_message' => 'IP Blacklisted']);
        };

        Artisan::call('sms:check-delivery');

        $this->assertSame(1, $calls);
        $this->assertSame(0, DB::table('sms_logs')->whereNotNull('delivery_status')->count());
    }

    public function test_the_log_page_shows_and_filters_delivery(): void
    {
        $this->sentLog('CS-OK', ['message' => 'arrived text', 'delivery_status' => 'delivered']);
        $this->sentLog('CS-BAD', ['message' => 'lost text', 'delivery_status' => 'failed', 'delivery_detail' => 'handset off']);

        $this->actingAs($this->admin)->get('/admin/sms-log')->assertSuccessful()->assertSee('Delivered')->assertSee('Not delivered')->assertSee('Check delivery now');
        $this->actingAs($this->admin)->get('/admin/sms-log?delivery=failed')->assertSee('lost text')->assertDontSee('arrived text');

        self::$answers['status'] = json_encode(['status' => 'FAILED', 'error_message' => 'IP Blacklisted']);
        $this->sentLog('CS-NEW');
        $this->actingAs($this->admin)->post('/admin/sms-log/refresh-delivery')->assertSessionHas('warning');
    }

    // ── cost reports ──

    public function test_monthly_and_per_campaign_costs_add_up(): void
    {
        $campaign = DB::table('sms_campaigns')->insertGetId(['name' => 'Eid offer', 'message_body' => 'x', 'recipient_type' => 'team', 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
        $this->sentLog('K1', ['sms_campaign_id' => $campaign, 'sms_parts' => 2]);
        $this->sentLog('K2', ['sms_campaign_id' => $campaign, 'sms_parts' => 1]);
        $this->sentLog('K3', ['created_at' => now()->subMonth()->startOfMonth()->addDays(2), 'sms_parts' => 4]);

        $usage = app(SmsUsage::class);
        $this->assertEquals(['messages' => 2, 'parts' => 3, 'cost' => 1.5], $usage->campaignCosts([$campaign])[$campaign]);

        $months = $usage->monthly(2);
        $this->assertSame(4, $months[0]['parts']);
        $this->assertEquals(2.0, $months[0]['cost']);
        $this->assertSame(3, $months[1]['parts']);

        $this->actingAs($this->admin)->get('/admin/sms-analytics')->assertSuccessful()->assertSee('Delivery and monthly cost')->assertSee('Eid offer')->assertSee('৳1.50');
    }
}
