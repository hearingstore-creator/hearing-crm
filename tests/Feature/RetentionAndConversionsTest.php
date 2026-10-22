<?php

namespace Tests\Feature;

use App\Classes\SMS;
use App\Models\NpsResponse;
use App\Models\Setting;
use App\Services\ConversionFeedback;
use App\Services\RetentionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Marketing phase 6: upgrade and service reminders, satisfaction survey, sales sent back to Meta and Google. */
class RetentionAndConversionsTest extends TestCase
{
    use DatabaseTransactions, CrmFixtures;

    protected int $device;
    protected int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        $this->app->bind(SMS::class, fn () => new class extends SMS {
            public function callApi($url, $params)
            {
                return json_encode(['status' => 'SUCCESS']);
            }
        });
        DB::table('settings')->where('id', '!=', 1)->delete();
        if (!DB::table('settings')->where('id', 1)->exists()) {
            DB::table('settings')->insert(['id' => 1, 'company_name' => 'Zen Hearing', 'created_at' => now()]);
        }
        Setting::find(1)->update(['company_name' => 'Zen Hearing', 'sms_automation_mode' => 'all', 'upgrade_after_months' => 36, 'upgrade_opens_deal' => false, 'nps_after_days' => 7]);
        DB::table('sms_automations')->update(['is_active' => false]);
        DB::table('sms_logs')->delete();
        DB::table('automation_logs')->delete();

        $cat = DB::table('categories')->insertGetId(['category_name' => 'Hearing Aid', 'status' => 1, 'created_at' => now()]);
        $this->device = $this->product('Audeo L50');
        DB::table('products')->where('id', $this->device)->update(['category_id' => $cat]);
    }

    private function on(string $trigger): void
    {
        DB::table('sms_automations')->where('trigger_type', $trigger)->update(['is_active' => true]);
    }

    /** A patient who bought the device $monthsAgo months ago. Returns [customerId, leadId, saleId]. */
    private function owner(int $monthsAgo, array $lead = [], array $sale = []): array
    {
        $leadId = DB::table('leads')->insertGetId($lead + ['full_name' => 'Owner ' . (++$this->seq), 'phone_number' => '0174' . str_pad((string) $this->seq, 7, '0', STR_PAD_LEFT), 'created_at' => now()]);
        $customer = DB::table('customers')->insertGetId(['customer_uid' => 'C-' . $leadId, 'lead_id' => $leadId, 'referral_code' => 'R' . $leadId, 'created_at' => now()]);
        $date = now()->subMonths($monthsAgo);
        $saleId = DB::table('sale_masters')->insertGetId($sale + ['sale_invoice_no' => 'INV-R' . $this->seq, 'customer_id' => $customer, 'date' => $date->toDateString(),
            'total_amount' => 90000, 'paid' => 90000, 'created_by' => $this->admin->id, 'created_at' => $date]);
        DB::table('sale_details')->insert(['sale_master_id' => $saleId, 'product_id' => $this->device, 'qty' => 1, 'mrp_price' => 90000, 'created_at' => $date]);

        return [$customer, $leadId, $saleId];
    }

    // ── upgrades and service ──

    public function test_old_hearing_aids_get_one_upgrade_reminder_and_optionally_a_deal(): void
    {
        $this->on('upgrade_due');
        Setting::find(1)->update(['upgrade_opens_deal' => true]);
        [, $oldLead] = $this->owner(40);
        $this->owner(12);   // too new

        $svc = app(RetentionService::class);
        $this->assertSame([1, 1], $svc->upgrades());
        $sms = DB::table('sms_logs')->first();
        $this->assertStringContainsString('Audeo L50 is now 3 years old', $sms->message);
        $deal = DB::table('deals')->where('lead_id', $oldLead)->first();
        $this->assertSame((int) $this->admin->id, (int) $deal->owner_id, 'the seller gets the upgrade deal');
        $this->assertTrue(DB::table('lead_follow_ups')->where('deal_id', $deal->id)->where('activity_type', 'call')->where('follow_up_notes', 'like', 'Upgrade: Audeo L50%')->exists());

        $this->assertSame([0, 0], $svc->upgrades(), 'once per device');
    }

    public function test_the_yearly_service_reminder_goes_on_the_purchase_anniversary(): void
    {
        $this->on('service_due');
        $this->owner(24);   // exactly two years ago today
        $this->owner(25);

        $this->assertSame(1, app(RetentionService::class)->service());
        $this->assertSame(0, app(RetentionService::class)->service(), 'once a year');
    }

    // ── survey ──

    public function test_a_survey_goes_out_after_delivery_and_a_low_score_alerts(): void
    {
        $this->on('nps_survey');
        [$customer, , $sale] = $this->owner(1, [], ['delivered_at' => now()->subDays(8)]);

        $this->assertSame(1, app(RetentionService::class)->surveys());
        $survey = NpsResponse::where('customer_id', $customer)->first();
        $this->assertNotNull($survey->sent_at);
        $this->assertStringContainsString('/s/' . $survey->token, DB::table('sms_logs')->value('message'));
        $this->assertSame(0, app(RetentionService::class)->surveys(), 'not asked again');

        $this->get('/s/' . $survey->token)->assertSuccessful()->assertSee('How likely are you to recommend us');
        $this->post('/s/' . $survey->token, [])->assertSessionHasErrors('score');
        $this->post('/s/' . $survey->token, ['score' => 4, 'comment' => 'Whistling sound'])->assertSessionHas('done');
        $this->assertSame(4, $survey->fresh()->score);
        $this->assertTrue(DB::table('notifications')->where('data', 'like', '%Unhappy patient: 4%')->exists());
        $this->post('/s/' . $survey->token, ['score' => 10])->assertSessionHas('done');
        $this->assertSame(4, $survey->fresh()->score, 'the first answer stays');

        $summary = RetentionService::summary(now()->subDay(), now());
        $this->assertSame(-100, $summary['nps']);
        $this->actingAs($this->admin)->get('/admin/deal-reports')->assertSee('Patient feedback (NPS)')->assertSee('Whistling sound');
    }

    // ── sales back to the ad platforms ──

    public function test_facebook_sales_are_sent_to_meta_once_with_hashed_details(): void
    {
        config(['connectors.meta.pixel_id' => '998877', 'connectors.meta.capi_token' => 'capi']);
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);
        [, $lead, $sale] = $this->owner(0, ['source_of_entry' => 'facebook', 'fbclid' => 'IwAR123', 'email' => 'Owner@Example.com']);
        $this->owner(0, ['source_of_entry' => 'phone_call']);   // not from Meta: not sent

        $r = app(ConversionFeedback::class)->sendMeta();
        $this->assertSame(1, $r['sent']);
        Http::assertSent(function ($req) use ($sale) {
            $e = $req['data'][0];
            return str_contains($req->url(), '/998877/events') && count($req['data']) === 1
                && $e['event_name'] === 'Purchase' && $e['event_id'] === 'sale-' . $sale
                && $e['custom_data']['value'] == 90000 && $e['user_data']['em'][0] === hash('sha256', 'owner@example.com')
                && str_ends_with($e['user_data']['fbc'], '.IwAR123') && strlen($e['user_data']['ph'][0]) === 64;
        });
        $this->assertSame('sent', DB::table('conversion_events')->where('sale_master_id', $sale)->value('status'));

        $this->assertSame(0, app(ConversionFeedback::class)->sendMeta()['sent'], 'never twice');

        config(['connectors.meta.pixel_id' => null]);
        $this->assertArrayHasKey('skipped', app(ConversionFeedback::class)->sendMeta());
    }

    public function test_google_conversions_csv_lists_sales_with_a_click_id(): void
    {
        $this->owner(0, ['gclid' => 'Cj0KCQ-test']);
        $this->owner(0);

        $this->get('/exports/google-ads-conversions.csv')->assertNotFound();   // no key set, not signed in
        config(['connectors.google.export_key' => 'k-123']);
        $this->get('/exports/google-ads-conversions.csv?key=wrong')->assertForbidden();
        $csv = $this->get('/exports/google-ads-conversions.csv?key=k-123')->assertSuccessful()->streamedContent();
        $this->assertStringContainsString('"Google Click ID","Conversion Name","Conversion Time","Conversion Value","Conversion Currency"', $csv);
        $this->assertStringContainsString('Cj0KCQ-test,"CRM purchase"', $csv);
        $this->assertSame(2, substr_count(trim($csv), "\n") + 1, 'header + one sale');

        $this->actingAs($this->admin)->get('/admin/web-leads?tab=connections')->assertSee('Sending sales back to the ad platforms');
    }

    public function test_settings_save_the_retention_options(): void
    {
        $s = Setting::find(1);
        $this->actingAs($this->admin)->put('/admin/setting/' . $s->id, ['company_name' => $s->company_name, 'mobile' => '01700000000',
            'upgrade_after_months' => 42, 'upgrade_opens_deal' => 1, 'nps_after_days' => 5])->assertSessionHasNoErrors();
        $s->refresh();
        $this->assertSame(42, (int) $s->upgrade_after_months);
        $this->assertTrue((bool) $s->upgrade_opens_deal);
        $this->assertSame(5, (int) $s->nps_after_days);
    }
}
