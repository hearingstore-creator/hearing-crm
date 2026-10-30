<?php

namespace Tests\Feature;

use App\Classes\Helper;
use App\Classes\SMS;
use App\Enums\SmsAutomationTrigger;
use App\Models\LeadCaptureForm;
use App\Models\Setting;
use App\Services\SmsAutomationService;
use App\Services\SmsService;
use App\Services\WebLeadIntake;
use App\Support\Region;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Marketing phase 7: one installation per clinic anywhere – region settings, consent, Twilio, data export and erase. */
class RegionAndPrivacyTest extends TestCase
{
    use DatabaseTransactions, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        DB::table('settings')->where('id', '!=', 1)->delete();
        if (!DB::table('settings')->where('id', 1)->exists()) {
            DB::table('settings')->insert(['id' => 1, 'company_name' => 'Clinic', 'created_at' => now()]);
        }
        $this->region([]);
        DB::table('sms_automations')->update(['is_active' => false]);
        DB::table('sms_logs')->delete();
        DB::table('automation_logs')->delete();
    }

    protected function tearDown(): void
    {
        $this->region([]);
        parent::tearDown();
    }

    private function region(array $values): void
    {
        Setting::find(1)->update($values + ['currency_code' => 'BDT', 'currency_symbol' => '৳', 'country_code' => 'BD', 'phone_prefix' => '880',
            'timezone' => 'Asia/Dhaka', 'marketing_needs_consent' => false, 'sms_automation_mode' => 'all']);
        Region::forget();
    }

    private function fakeGateway(): void
    {
        $this->app->bind(SMS::class, fn () => new class extends SMS {
            public function callApi($url, $params)
            {
                return json_encode(['status' => 'SUCCESS']);
            }
        });
    }

    // ── region ──

    public function test_bangladesh_stays_the_default(): void
    {
        $this->assertSame('৳', Region::currencySymbol());
        $this->assertSame('8801711000000', Helper::normalizePhone('01711000000'));
        $this->assertSame('8801711000000', app(WebLeadIntake::class)->phone('+880 1711-000000'));
        $this->assertNull(app(WebLeadIntake::class)->phone('+44 7700 900123'), 'Bangladesh installs take BD mobiles only');
    }

    public function test_another_country_changes_phone_numbers_currency_and_time_zone(): void
    {
        $this->region(['currency_code' => 'GBP', 'currency_symbol' => '£', 'country_code' => 'GB', 'phone_prefix' => '44', 'timezone' => 'Europe/London']);

        $this->assertSame('447700900123', Helper::normalizePhone('07700 900123'), 'local number gets the country code');
        $this->assertSame('12025550100', Helper::normalizePhone('+1 202 555 0100'), 'an international number is kept');
        $this->assertSame('447700900123', app(WebLeadIntake::class)->phone('07700900123'));
        $this->assertSame('Europe/London', Region::timezone());
        $this->assertSame('GBP', Region::currencyCode());

        $page = $this->actingAs($this->admin)->get('/admin/deal-reports')->assertSuccessful();
        $page->assertSee('£')->assertDontSee('৳');
        $this->actingAs($this->admin)->get('/admin/deals')->assertSee('window.CRM_CURRENCY', false);
    }

    public function test_region_settings_are_saved_from_the_settings_page(): void
    {
        $s = Setting::find(1);
        $this->actingAs($this->admin)->put('/admin/setting/' . $s->id, ['company_name' => $s->company_name, 'mobile' => '01700000000',
            'currency_code' => 'USD', 'currency_symbol' => '$', 'country_code' => 'US', 'phone_prefix' => '1', 'timezone' => 'America/New_York', 'marketing_needs_consent' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame('$', Region::currencySymbol(), 'the cache is cleared on save');
        $this->assertTrue(Region::needsConsent());
    }

    // ── consent ──

    public function test_with_the_consent_rule_on_promotional_sms_go_only_to_people_who_agreed(): void
    {
        $this->fakeGateway();
        DB::table('sms_automations')->whereIn('trigger_type', ['customer_first_sale', 'ticket_created'])->update(['is_active' => true]);
        $no = DB::table('leads')->insertGetId(['full_name' => 'No', 'phone_number' => '01711000101', 'created_at' => now()]);
        $yes = DB::table('leads')->insertGetId(['full_name' => 'Yes', 'phone_number' => '01711000102', 'marketing_consent' => true, 'created_at' => now()]);
        $sms = app(SmsAutomationService::class);

        $this->assertTrue($sms->fire(SmsAutomationTrigger::CUSTOMER_FIRST_SALE, '01711000101', 'lead', $no, ['name' => 'No'], 'k1'), 'rule off: everyone');

        $this->region(['marketing_needs_consent' => true]);
        $this->assertFalse($sms->fire(SmsAutomationTrigger::CUSTOMER_FIRST_SALE, '01711000101', 'lead', $no, ['name' => 'No'], 'k2'));
        $this->assertTrue($sms->fire(SmsAutomationTrigger::CUSTOMER_FIRST_SALE, '01711000102', 'lead', $yes, ['name' => 'Yes'], 'k3'));
        $this->assertTrue($sms->fire(SmsAutomationTrigger::TICKET_CREATED, '01711000101', 'lead', $no, ['name' => 'No'], 'k4'), 'service messages still go');
    }

    public function test_consent_from_the_website_and_the_lead_form_is_kept(): void
    {
        $form = LeadCaptureForm::create(['name' => 'Site', 'key' => LeadCaptureForm::newKey(), 'secret' => 's', 'is_active' => true, 'sla_minutes' => 15]);
        app(WebLeadIntake::class)->accept($form, ['name' => 'Web Agree', 'phone' => '01711000201', 'consent' => true, 'client_ip' => '1.1.1.1', 'elapsed_seconds' => 50], '8801711000201');
        $lead = DB::table('leads')->where('normalized_phone', '8801711000201')->first();
        $this->assertTrue((bool) $lead->marketing_consent);
        $this->assertSame('Website form', $lead->consent_source);

        $stage = \App\Models\Pipeline::default()->activeStages()->first();
        $this->actingAs($this->admin)->post('/admin/leads', ['full_name' => 'Desk Agree', 'phone_number' => '01811000202', 'inquiry_for' => ['hearing_aid'],
            'stage_id' => $stage->id, 'activity_type' => 'call', 'due_at' => now()->addHour()->toDateTimeString(), 'source_of_entry' => 'phone_call', 'marketing_consent' => 1])
            ->assertSessionHasNoErrors();
        $this->assertTrue((bool) DB::table('leads')->where('phone_number', '01811000202')->value('marketing_consent'));
    }

    // ── Twilio ──

    public function test_twilio_sends_reports_balance_and_delivery(): void
    {
        config(['sms.driver' => 'twilio', 'sms.twilio.sid' => 'AC1', 'sms.twilio.token' => 't', 'sms.twilio.from' => '+15550001111']);
        $this->region(['phone_prefix' => '1', 'country_code' => 'US']);
        Http::fake([
            'api.twilio.com/*/Messages.json' => Http::response(['sid' => 'SM123', 'status' => 'queued'], 201),
            'api.twilio.com/*/Balance.json' => Http::response(['balance' => '12.50', 'currency' => 'USD']),
            'api.twilio.com/*/Messages/SM123.json' => Http::response(['status' => 'delivered']),
        ]);

        app(SmsService::class)->sendAndLogSms('(202) 555-0100', 'Hello', 'lead', 1);
        $log = DB::table('sms_logs')->first();
        $this->assertSame('sent', $log->status);
        $this->assertSame('SM123', $log->gateway_ref);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/Accounts/AC1/Messages.json') && $r['To'] === '+12025550100' && $r['From'] === '+15550001111');

        $this->assertSame(12.5, app(\App\Services\SmsGateway::class)->balance()['balance']);
        $this->assertSame('delivered', app(\App\Services\SmsGateway::class)->deliveryStatus($log->csms_id)['state']);
    }

    // ── data requests ──

    public function test_a_persons_data_can_be_exported_and_erased_by_super_admin_only(): void
    {
        $customer = $this->patient('rahela@example.com');
        $lead = (int) DB::table('customers')->where('id', $customer)->value('lead_id');
        $phone = DB::table('leads')->where('id', $lead)->value('phone_number');
        DB::table('sms_logs')->insert(['recipient_type' => 'lead', 'recipient_id' => $lead, 'phone_number' => $phone, 'message' => 'Hello Rahela', 'type' => 'manual', 'status' => 'sent', 'created_at' => now(), 'updated_at' => now()]);
        $sale = DB::table('sale_masters')->insertGetId(['sale_invoice_no' => 'INV-ERASE', 'customer_id' => $customer, 'date' => now()->toDateString(), 'total_amount' => 5000, 'paid' => 5000, 'created_at' => now()]);

        $staff = $this->userWithPermissions(['lead_list.index', 'lead_list.view']);
        $this->actingAs($staff)->get('/admin/leads/' . $lead . '/data-export')->assertForbidden();

        $json = $this->actingAs($this->admin)->get('/admin/leads/' . $lead . '/data-export')->assertSuccessful()->streamedContent();
        $data = json_decode($json, true);
        $this->assertSame('rahela@example.com', $data['person']['email']);
        $this->assertSame('Hello Rahela', $data['messages'][0]['message']);
        $this->assertSame('INV-ERASE', $data['purchases'][0]['sale_invoice_no']);

        $this->actingAs($this->admin)->post('/admin/leads/' . $lead . '/erase', ['reason' => 'Asked to be removed'])->assertSessionHasErrors('confirm');
        $this->actingAs($this->admin)->post('/admin/leads/' . $lead . '/erase', ['reason' => 'Asked to be removed', 'confirm' => 1])->assertSessionHas('success');

        $after = DB::table('leads')->find($lead);
        $this->assertStringStartsWith('Erased person', $after->full_name);
        $this->assertNull($after->email);
        $this->assertNotNull($after->erased_at);
        $this->assertSame('[erased]', DB::table('sms_logs')->where('recipient_id', $lead)->value('message'));
        $this->assertNotNull(DB::table('sale_masters')->find($sale), 'the invoice stays');
        $this->assertTrue(DB::table('activity_logs')->where('action', 'person_erased')->where('subject_id', $lead)->exists());
        $this->actingAs($this->admin)->get('/admin/leads/' . $lead)->assertSuccessful()->assertSee('Personal details were erased');
    }
}
