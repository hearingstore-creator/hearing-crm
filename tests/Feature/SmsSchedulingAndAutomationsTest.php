<?php

namespace Tests\Feature;

use App\Classes\Helper;
use App\Classes\SMS;
use App\Models\Customer;
use App\Models\Doctor;
use App\Models\DoctorLedger;
use App\Models\Setting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** SMS Center phase 3: scheduled campaigns, test send, resend, and the payment / due / referral / birthday automations. */
class SmsSchedulingAndAutomationsTest extends TestCase
{
    use DatabaseTransactions, CrmFixtures;

    public static bool $gatewayFails = false;

    protected int $customerId;
    protected string $phone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        self::$gatewayFails = false;

        $this->app->bind(SMS::class, fn () => new class extends SMS {
            public function callApi($url, $params)
            {
                if (SmsSchedulingAndAutomationsTest::$gatewayFails) {
                    return json_encode(['status' => 'FAILED', 'error_message' => 'Insufficient balance']);
                }
                $p = json_decode($params, true);
                if (isset($p['sms']) && is_array($p['sms'])) {
                    return json_encode(['status' => 'SUCCESS', 'smsinfo' => array_map(fn ($s) => ['csms_id' => $s['csms_id'], 'sms_status' => 'SUCCESS'], $p['sms'])]);
                }

                return json_encode(['status' => 'SUCCESS']);
            }
        });

        DB::table('settings')->where('id', '!=', 1)->delete();
        if (! DB::table('settings')->where('id', 1)->exists()) {
            DB::table('settings')->insert(['id' => 1, 'company_name' => 'Test Co', 'created_at' => now()]);
        }
        Setting::find(1)->update(['sms_automation_mode' => 'all']);
        DB::table('sms_automations')->update(['is_active' => false]);
        DB::table('sms_logs')->delete();
        DB::table('automation_logs')->delete();

        $this->customerId = $this->patient();
        $this->phone = (string) DB::table('leads')->where('id', Customer::find($this->customerId)->lead_id)->value('phone_number');
    }

    protected function on(string $trigger): void
    {
        DB::table('sms_automations')->where('trigger_type', $trigger)->update(['is_active' => true]);
    }

    protected function teamCampaign(array $extra = [])
    {
        Doctor::create(['name' => 'Dr Sched', 'referrer_type' => 'doctor', 'contact_number' => '01712345678', 'status' => 1]);

        return $this->actingAs($this->admin)->post('/admin/sms/send', array_merge([
            'campaign_name' => 'Scheduled hello', 'message_body' => 'Hello {{name}}', 'audience_type' => 'team',
            'filter_criteria' => json_encode([['field' => 'group', 'op' => 'in', 'values' => ['doctor']]]),
        ], $extra));
    }

    // ── scheduling ──

    public function test_a_scheduled_campaign_waits_and_is_started_once_when_due(): void
    {
        $at = now('Asia/Dhaka')->addHours(3)->format('Y-m-d\TH:i');
        $this->teamCampaign(['scheduled_at' => $at])->assertSessionHas('success');

        $camp = DB::table('sms_campaigns')->first();
        $this->assertSame('scheduled', $camp->status);
        $this->assertSame(0, DB::table('sms_logs')->count());

        Artisan::call('sms:send-scheduled');
        $this->assertSame(0, DB::table('sms_logs')->count(), 'not due yet');

        DB::table('sms_campaigns')->where('id', $camp->id)->update(['scheduled_at' => now()->subMinute()]);
        Artisan::call('sms:send-scheduled');
        $this->assertSame(1, DB::table('sms_logs')->count());
        $this->assertSame('completed', DB::table('sms_campaigns')->where('id', $camp->id)->value('status'));

        Artisan::call('sms:send-scheduled');
        $this->assertSame(1, DB::table('sms_logs')->count(), 'never sent twice');
    }

    public function test_a_time_in_the_past_is_refused_and_a_scheduled_campaign_can_be_cancelled(): void
    {
        $this->teamCampaign(['scheduled_at' => now('Asia/Dhaka')->subHour()->format('Y-m-d\TH:i')])->assertSessionHas('warning');
        $this->assertSame(0, DB::table('sms_campaigns')->count());

        $this->teamCampaign(['scheduled_at' => now('Asia/Dhaka')->addDay()->format('Y-m-d\TH:i')])->assertSessionHas('success');
        $id = DB::table('sms_campaigns')->value('id');
        $this->actingAs($this->admin)->get('/admin/sms')->assertSee('Scheduled campaigns')->assertSee('Scheduled hello');

        $this->actingAs($this->admin)->delete("/admin/sms/campaigns/{$id}/cancel")->assertSessionHas('success');
        Artisan::call('sms:send-scheduled');
        $this->assertSame(0, DB::table('sms_logs')->count());
    }

    // ── test send ──

    public function test_test_send_goes_to_the_given_number_with_sample_details(): void
    {
        $this->actingAs($this->admin)->postJson('/admin/sms/test-send', ['message' => 'Hi {{name}}, your points: {{points}}', 'phone' => '01799999999'])
            ->assertSuccessful()->assertJsonPath('success', true)->assertJsonPath('preview', '[TEST] Hi Sample Patient, your points: 120');

        $log = DB::table('sms_logs')->first();
        $this->assertSame('01799999999', $log->phone_number);
        $this->assertSame('manual', $log->type);

        $this->actingAs($this->admin)->postJson('/admin/sms/test-send', ['message' => 'Hi'])->assertStatus(422);   // admin has no mobile saved
    }

    // ── resend / retry ──

    protected function failedLog(string $phone = '01711111111', string $message = 'Please call us'): int
    {
        return DB::table('sms_logs')->insertGetId([
            'recipient_type' => 'customer', 'recipient_id' => $this->customerId, 'phone_number' => $phone, 'message' => $message,
            'type' => 'bulk', 'status' => 'failed', 'error_message' => 'Insufficient balance', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_failed_message_can_be_sent_again_once(): void
    {
        $id = $this->failedLog();

        $this->actingAs($this->admin)->post("/admin/sms-log/{$id}/resend")->assertSessionHas('success');
        $this->assertNotNull(DB::table('sms_logs')->where('id', $id)->value('retried_at'));
        $this->assertSame(1, DB::table('sms_logs')->where('status', 'sent')->where('phone_number', '01711111111')->count());

        $this->actingAs($this->admin)->post("/admin/sms-log/{$id}/resend")->assertSessionHas('warning');
        $this->assertSame(1, DB::table('sms_logs')->where('status', 'sent')->count(), 'no double send');
    }

    public function test_retry_all_skips_duplicates_and_opted_out_numbers(): void
    {
        $this->failedLog('01711111111', 'Same text');
        $this->failedLog('01711111111', 'Same text');
        $this->failedLog('01722222222', 'Other text');
        $this->failedLog('01733333333', 'Opted out text');
        DB::table('blocked_contacts')->insert(['type' => \App\Models\BlockedContact::SMS_OPT_OUT, 'value' => \App\Models\BlockedContact::normalize('phone', '01733333333'), 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->admin)->post('/admin/sms-log/retry-failed', ['status' => 'failed'])->assertSessionHas('success');
        $this->assertSame(2, DB::table('sms_logs')->where('status', 'sent')->count());
        $this->assertSame(0, DB::table('sms_logs')->where('status', 'sent')->where('phone_number', '01733333333')->count());

        $this->actingAs($this->admin)->get('/admin/sms-log?status=failed')->assertSuccessful()->assertSee('Retry all failed')->assertSee('Resend');
    }

    // ── automations ──

    public function test_payment_received_sends_only_when_switched_on(): void
    {
        $request = (object) ['customer_id' => $this->customerId, 'date' => date('Y-m-d'), 'paid' => 2500, 'details' => 'Cash'];

        Helper::customerLedgerCredit($request, 'INV-100', Helper::$receive);
        $this->assertSame(0, DB::table('sms_logs')->count(), 'off by default');

        $this->on('payment_received');
        Helper::customerLedgerCredit($request, 'INV-101', Helper::$receive);
        $log = DB::table('sms_logs')->first();
        $this->assertSame('payment_received', $log->automation_trigger);
        $this->assertStringContainsString('Tk 2,500.00', $log->message);
        $this->assertStringContainsString('INV-101', $log->message);

        Helper::customerLedgerCredit($request, 'INV-102', Helper::$returnToCustomer);
        $this->assertSame(1, DB::table('sms_logs')->count(), 'refunds are not "payments received"');
    }

    public function test_the_referrer_hears_once_when_a_sale_earns_commission(): void
    {
        $this->on('referral_earned');
        $doctor = Doctor::create(['name' => 'Dr Earn', 'referrer_type' => 'doctor', 'contact_number' => '01755555555', 'status' => 1]);
        $product = $this->product('Aid E');
        $this->postPurchase($this->admin, $this->supplier(), [[$product, '', 'SN-E1', 1]])->assertSessionHasNoErrors();
        DB::table('sms_logs')->delete();
        DB::table('automation_logs')->delete();

        $this->postSale($this->admin, $this->customerId, [[$product, null, ['SN-E1'], 1]], 0, ['reference_by' => $doctor->id, 'reference_amount' => 500])->assertSessionHasNoErrors();

        $logs = DB::table('sms_logs')->where('automation_trigger', 'referral_earned')->get();
        $this->assertCount(1, $logs);
        $this->assertSame('doctor', $logs[0]->recipient_type);
        $this->assertSame('01755555555', $logs[0]->phone_number);
        $this->assertStringContainsString('Tk 500.00', $logs[0]->message);

        // re-saving the same sale does not message the doctor again
        $sale = \App\Models\SaleMaster::orderByDesc('id')->first();
        Helper::syncSaleReferrals($sale, (object) ['reference_by' => $doctor->id, 'reference_amount' => 500, 'doctor_chamber_id' => null]);
        $this->assertSame(1, DB::table('sms_logs')->where('automation_trigger', 'referral_earned')->count());
    }

    public function test_the_referrer_hears_when_a_commission_payment_is_made(): void
    {
        $this->on('referral_paid');
        $doctor = Doctor::create(['name' => 'Dr Paid', 'referrer_type' => 'quack', 'contact_number' => '01766666666', 'status' => 1]);
        $ledger = new DoctorLedger();
        $ledger->doctor_id = $doctor->id;
        $ledger->date = date('Y-m-d');
        $ledger->details = 'seed';
        $ledger->debit = 0;
        $ledger->credit = 3000;
        $ledger->type = 'Sales';
        $ledger->ref_invoice_no = 'INV-SEED';
        $ledger->save();

        $this->actingAs($this->admin)->post('/admin/doctors-payment', [
            'doctor_id' => $doctor->id, 'date' => date('Y-m-d'), 'debit' => 1200, 'payment_method' => 'Cash', 'payment_type_id' => 1,
        ])->assertSessionHasNoErrors();

        $log = DB::table('sms_logs')->where('automation_trigger', 'referral_paid')->first();
        $this->assertNotNull($log, 'a payment SMS was logged');
        $this->assertSame('referrer', $log->recipient_type);
        $this->assertStringContainsString('Tk 1,200.00', $log->message);
    }

    public function test_due_reminders_go_out_once_per_due_date_and_weekly_when_overdue(): void
    {
        $this->on('due_reminder');
        $product = $this->product('Aid D');
        $this->postPurchase($this->admin, $this->supplier(), [[$product, '', 'SN-D1', 1]])->assertSessionHasNoErrors();
        $this->postSale($this->admin, $this->customerId, [[$product, null, ['SN-D1'], 1]], 50, ['due_date' => now('Asia/Dhaka')->addDay()->toDateString()])->assertSessionHasNoErrors();
        $saleId = (int) DB::table('sale_masters')->orderByDesc('id')->value('id');
        DB::table('sms_logs')->delete();
        DB::table('automation_logs')->delete();

        Artisan::call('crm:automations', ['task' => 'dues']);
        Artisan::call('crm:automations', ['task' => 'dues']);
        $this->assertSame(1, DB::table('sms_logs')->where('automation_trigger', 'due_reminder')->count(), 'once for that due date');
        $this->assertStringContainsString('Tk 150.00', DB::table('sms_logs')->value('message'));

        DB::table('sale_masters')->where('id', $saleId)->update(['due_date' => now('Asia/Dhaka')->subDays(3)->toDateString()]);
        Artisan::call('crm:automations', ['task' => 'dues']);
        Artisan::call('crm:automations', ['task' => 'dues']);
        $this->assertSame(2, DB::table('sms_logs')->where('automation_trigger', 'due_reminder')->count(), 'overdue: one reminder this week');

        DB::table('sale_masters')->where('id', $saleId)->update(['paid' => DB::raw('total_amount')]);
        Artisan::call('crm:automations', ['task' => 'dues']);
        $this->assertSame(2, DB::table('sms_logs')->count(), 'paid invoices are left alone');
    }

    public function test_birthday_greeting_goes_out_once_a_year(): void
    {
        $this->on('birthday');
        $leadId = Customer::find($this->customerId)->lead_id;
        DB::table('leads')->where('id', $leadId)->update(['date_of_birth' => now('Asia/Dhaka')->subYears(60)->toDateString()]);

        Artisan::call('crm:automations', ['task' => 'birthdays']);
        Artisan::call('crm:automations', ['task' => 'birthdays']);

        $this->assertSame(1, DB::table('sms_logs')->where('automation_trigger', 'birthday')->count());
    }

    public function test_the_new_automations_exist_but_start_switched_off(): void
    {
        $this->assertSame(5, DB::table('sms_automations')->whereIn('trigger_type', ['payment_received', 'due_reminder', 'referral_earned', 'referral_paid', 'birthday'])->count());

        $this->actingAs($this->admin)->get('/admin/sms')->assertSuccessful()->assertSee('abScheduledAt', false)->assertSee('abTestSend', false);
    }
}
