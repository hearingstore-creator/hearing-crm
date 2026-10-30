<?php

namespace Tests\Feature;

use App\Models\LeadCaptureForm;
use App\Models\MarketingCampaign;
use App\Models\MarketingSpend;
use App\Services\MarketingRoi;
use App\Services\MarketingSpendImport;
use App\Services\MarketingSpendService;
use App\Services\WebLeadIntake;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Marketing phase 1: campaigns, monthly spend booked once as an expense, attribution, and the return report. */
class MarketingRoiTest extends TestCase
{
    use DatabaseTransactions, CrmFixtures;

    protected MarketingCampaign $fb;
    protected Carbon $month;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        $this->actingAs($this->admin);
        $this->month = now()->startOfMonth();
        $this->fb = MarketingCampaign::create(['name' => 'Eid FB offer', 'code' => 'eid_fb', 'channel' => 'facebook', 'status' => 'active']);
    }

    private function ledgerNet(string $ref): float
    {
        return (float) DB::table('ledgers')->whereNull('deleted_at')->where('ref_invoice_no', $ref)->sum('expense_amount');
    }

    // ── spend and the accounts ──

    public function test_spend_books_one_expense_and_corrections_reverse_it(): void
    {
        $svc = app(MarketingSpendService::class);
        $spend = $svc->save($this->fb, $this->month->format('Y-m'), 10000, null);
        $ref = 'MKT-' . $spend->id;

        $row = DB::table('ledgers')->where('id', $spend->ledger_id)->first();
        $this->assertSame('Expense', $row->type);
        $this->assertSame(MarketingSpendService::HEAD_NAME, DB::table('income_expense_heads')->where('id', $row->income_expense_head_id)->value('head_name'));
        $this->assertEquals(10000, $this->ledgerNet($ref));

        $svc->save($this->fb, $this->month->format('Y-m'), 10000, null);
        $this->assertSame(1, DB::table('ledgers')->where('ref_invoice_no', $ref)->count(), 'same amount: nothing booked again');

        $svc->save($this->fb, $this->month->format('Y-m'), 12500, null);
        $this->assertSame(1, MarketingSpend::count(), 'one row per campaign, month and branch');
        $this->assertEquals(12500, $this->ledgerNet($ref), 'old expense reversed, new one booked');

        $svc->remove($spend->fresh(), 'Entered by mistake');
        $this->assertEquals(0, $this->ledgerNet($ref));
        $this->assertSame(0, MarketingSpend::count());
    }

    public function test_the_profit_statement_sees_the_advertising_expense(): void
    {
        app(MarketingSpendService::class)->save($this->fb, $this->month->format('Y-m'), 7000, null);
        $statement = (new \App\Services\ProfitStatement($this->month->copy(), $this->month->copy()->endOfMonth()))->build();

        $this->assertStringContainsString(MarketingSpendService::HEAD_NAME, json_encode($statement));
    }

    public function test_an_old_advertising_expense_can_be_counted_without_booking_it_twice(): void
    {
        $head = DB::table('income_expense_heads')->insertGetId(['head_type' => 'Expense', 'head_name' => 'Facebook Boost', 'status' => 1, 'created_at' => now()]);
        $ledger = DB::table('ledgers')->insertGetId(['income_expense_head_id' => $head, 'invoice_no' => 'X1', 'date' => $this->month->toDateString(),
            'expense_amount' => 3000, 'income_amount' => 0, 'amount_type' => 'Expense', 'details' => 'Boost', 'created_at' => now()]);
        $svc = app(MarketingSpendService::class);

        $this->assertTrue($svc->unlinkedAdvertisingExpenses()->pluck('id')->contains($ledger));
        $this->post('/admin/marketing/spend/link-expense', ['ledger_id' => $ledger, 'campaign_id' => $this->fb->id])->assertSessionHas('success');

        $this->assertSame(1, DB::table('ledgers')->where('amount_type', 'Expense')->where('expense_amount', '>', 0)->count(), 'no second expense');
        $this->assertEquals(3000, (float) MarketingSpend::first()->amount);
        $this->assertFalse($svc->unlinkedAdvertisingExpenses()->pluck('id')->contains($ledger));
        $this->post('/admin/marketing/spend/link-expense', ['ledger_id' => $ledger, 'campaign_id' => $this->fb->id])->assertSessionHas('warning');
    }

    // ── imports ──

    public function test_a_meta_export_is_added_up_by_campaign_and_month(): void
    {
        $csv = "Campaign name,Reporting starts,Reporting ends,Amount spent (BDT),Results\n"
            . "Eid FB offer,2026-09-01,2026-09-01,1200.50,4\n"
            . "Eid FB offer,2026-09-02,2026-09-02,\"1,000.00\",3\n"
            . "New Hearing Camp,2026-10-05,2026-10-05,500,1\n";
        $path = tempnam(sys_get_temp_dir(), 'meta');
        file_put_contents($path, $csv);

        $r = app(MarketingSpendImport::class)->parse($path, 'meta');
        $this->assertSame('BDT', $r['currency']);
        $rows = collect($r['rows'])->keyBy(fn ($x) => $x['name'] . '|' . $x['month']);
        $this->assertEquals(2200.5, $rows['Eid FB offer|2026-09']['amount']);
        $this->assertSame($this->fb->id, $rows['Eid FB offer|2026-09']['campaign_id'], 'matched by name');
        $this->assertNull($rows['New Hearing Camp|2026-10']['campaign_id']);

        $this->post('/admin/marketing/spend/import/confirm', ['platform' => 'meta', 'rows' => [
            ['name' => 'Eid FB offer', 'month' => '2026-09', 'amount' => 2200.5, 'campaign_id' => $this->fb->id, 'include' => 1],
            ['name' => 'New Hearing Camp', 'month' => '2026-10', 'amount' => 500, 'campaign_id' => '', 'include' => 1],
        ]])->assertSessionHas('success');

        $new = MarketingCampaign::where('code', 'new_hearing_camp')->first();
        $this->assertNotNull($new);
        $this->assertSame('facebook', $new->channel);
        $this->assertSame(2, MarketingSpend::where('source', 'meta_csv')->count());
    }

    public function test_a_google_export_with_title_lines_and_dollars_is_read(): void
    {
        $csv = "Campaign report\n\"October 1, 2026 - October 31, 2026\"\nCampaign,Month,Currency code,Cost\nSearch - Hearing aids,Oct 2026,USD,25.00\nTotal: Account,,USD,25.00\n";
        $path = tempnam(sys_get_temp_dir(), 'gads');
        file_put_contents($path, $csv);

        $r = app(MarketingSpendImport::class)->parse($path, 'google', 120);
        $this->assertSame('USD', $r['currency']);
        $this->assertCount(1, $r['rows']);
        $this->assertSame('2026-10', $r['rows'][0]['month']);
        $this->assertEquals(3000, $r['rows'][0]['amount'], '25 USD × 120');
    }

    public function test_the_import_screen_previews_before_saving(): void
    {
        $file = UploadedFile::fake()->createWithContent('meta.csv', "Campaign name,Reporting starts,Amount spent (BDT)\nEid FB offer,2026-09-03,900\n");
        $this->post('/admin/marketing/spend/import', ['platform' => 'meta', 'file' => $file])->assertSessionHas('marketing_import');
        $this->assertSame(0, MarketingSpend::count(), 'nothing saved before confirming');
    }

    // ── attribution ──

    public function test_a_website_lead_from_an_ad_link_is_tied_to_its_campaign(): void
    {
        $form = LeadCaptureForm::create(['name' => 'Site', 'key' => 'k' . uniqid(), 'secret' => 's', 'is_active' => true, 'sla_minutes' => 15]);
        app(WebLeadIntake::class)->accept($form, [
            'name' => 'Rahima Begum', 'phone' => '01711223344', 'utm_source' => 'facebook', 'utm_medium' => 'paid', 'utm_campaign' => 'EID_FB',
            'utm_content' => 'video-1', 'fbclid' => 'abc123', 'landing_page' => 'https://site/offer', 'referrer_url' => 'https://facebook.com',
            'client_ip' => '1.2.3.4', 'elapsed_seconds' => 30,
        ], '01711223344');

        $lead = DB::table('leads')->where('phone_number', '01711223344')->first();
        $deal = DB::table('deals')->where('lead_id', $lead->id)->first();
        $this->assertSame($this->fb->id, (int) $lead->campaign_id, 'first touch on the lead');
        $this->assertSame('abc123', $lead->fbclid);
        $this->assertSame('https://facebook.com', $lead->referrer_url);
        $this->assertSame($this->fb->id, (int) $deal->campaign_id, 'and on the enquiry');
        $this->assertSame('video-1', $deal->utm_content);
    }

    public function test_the_lead_form_needs_a_source_and_keeps_the_campaign(): void
    {
        $stage = \App\Models\Pipeline::default()->activeStages()->first();
        $base = ['full_name' => 'Karim Mia', 'phone_number' => '01800000011', 'inquiry_for' => ['hearing_aid'], 'stage_id' => $stage->id,
            'activity_type' => 'call', 'due_at' => now()->addDay()->toDateTimeString()];

        $this->post('/admin/leads', $base)->assertSessionHasErrors('source_of_entry');

        $this->post('/admin/leads', $base + ['source_of_entry' => 'facebook', 'campaign_id' => $this->fb->id])->assertSessionHasNoErrors();
        $lead = DB::table('leads')->where('phone_number', '01800000011')->first();
        $this->assertSame($this->fb->id, (int) $lead->campaign_id);
        $this->assertSame('eid_fb', $lead->utm_campaign);
        $this->assertSame($this->fb->id, (int) DB::table('deals')->where('lead_id', $lead->id)->value('campaign_id'));
    }

    // ── the report ──

    /** A patient (lead + customer) with one enquiry, and optionally a sale linked to it. */
    private function journey(array $lead, array $deal = [], ?int $saleTotal = null, array $saleExtra = []): array
    {
        $leadId = DB::table('leads')->insertGetId($lead + ['full_name' => 'P ' . uniqid(), 'phone_number' => '019' . random_int(10000000, 99999999), 'created_at' => now()]);
        $customerId = DB::table('customers')->insertGetId(['customer_uid' => 'C-' . $leadId, 'lead_id' => $leadId, 'referral_code' => 'R' . $leadId, 'created_at' => now()]);
        $pipeline = \App\Models\Pipeline::default();
        $dealId = DB::table('deals')->insertGetId($deal + ['lead_id' => $leadId, 'pipeline_id' => $pipeline->id, 'stage_id' => $pipeline->activeStages()->first()->id,
            'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        $saleId = null;
        if ($saleTotal !== null) {
            $saleId = DB::table('sale_masters')->insertGetId($saleExtra + ['sale_invoice_no' => 'INV' . uniqid(), 'customer_id' => $customerId, 'date' => now()->toDateString(),
                'total_amount' => $saleTotal, 'paid' => $saleTotal, 'created_at' => now()]);
            DB::table('sale_details')->insert(['sale_master_id' => $saleId, 'product_id' => $this->product('Aid ' . uniqid()), 'qty' => 1, 'mrp_price' => $saleTotal, 'purchase_price' => $saleTotal * 0.6, 'created_at' => now()]);
            DB::table('deals')->where('id', $dealId)->update(['status' => 'won', 'won_at' => now(), 'sale_master_id' => $saleId]);
        }

        return compact('leadId', 'customerId', 'dealId', 'saleId');
    }

    private function report(string $group = 'channel', string $basis = 'period', string $touch = 'last', ?Carbon $from = null): array
    {
        return (new MarketingRoi($from ?? $this->month->copy(), now()->endOfDay(), $group, $basis, $touch))->build();
    }

    public function test_spend_enquiries_sales_and_ratios_add_up_per_channel(): void
    {
        app(MarketingSpendService::class)->save($this->fb, $this->month->format('Y-m'), 10000, null);
        $this->journey(['source_of_entry' => 'facebook', 'campaign_id' => $this->fb->id], ['campaign_id' => $this->fb->id, 'source' => 'facebook'], 80000);
        $this->journey(['source_of_entry' => 'facebook'], ['campaign_id' => $this->fb->id, 'source' => 'facebook']);
        $this->journey([], []);   // nobody wrote down where this one came from

        $r = $this->report();
        $fb = collect($r['rows'])->firstWhere('key', 'ch:facebook');
        $this->assertEquals(10000, $fb['spend']);
        $this->assertEquals(2, $fb['enquiries']);
        $this->assertEquals(1, $fb['sales']);
        $this->assertEquals(80000, $fb['revenue']);
        $this->assertEquals(32000, $fb['gross_profit']);
        $this->assertEquals(5000, $fb['cost_per_enquiry']);
        $this->assertEquals(10000, $fb['cost_per_sale']);
        $this->assertEquals(8, $fb['roas']);
        $this->assertEquals(50.0, $fb['enquiry_to_sale']);

        $unknown = collect($r['rows'])->firstWhere('key', 'ch:unknown');
        $this->assertEquals(1, $unknown['enquiries']);
        $this->assertSame(67, $r['quality']['pct'], '2 of 3 enquiries have a source');
        $this->assertCount(1, $r['unattributed']);

        $byCampaign = collect($this->report('campaign')['rows'])->firstWhere('key', 'c:' . $this->fb->id);
        $this->assertSame('Eid FB offer', $byCampaign['label']);
        $this->assertEquals(10000, $byCampaign['spend']);
    }

    public function test_returns_reduce_revenue_and_commission_is_a_cost(): void
    {
        $doctor = \App\Models\Doctor::create(['name' => 'Dr Ref', 'referrer_type' => 'doctor', 'status' => 1]);
        $j = $this->journey(['source_of_entry' => 'facebook'], ['campaign_id' => $this->fb->id], 50000, ['reference_by' => $doctor->id, 'reference_amount' => 5000]);
        DB::table('sale_returns')->insert(['credit_note_no' => 'CN-T1', 'sale_master_id' => $j['saleId'], 'customer_id' => $j['customerId'], 'date' => now()->toDateString(),
            'return_subtotal' => 10000, 'credit_note_amount' => 10000, 'due_reduced_amount' => 0, 'status' => 'completed', 'created_at' => now()]);

        $fb = collect($this->report()['rows'])->firstWhere('key', 'ch:facebook');
        $this->assertEquals(40000, $fb['revenue']);
        $this->assertEquals(5000, $fb['commission']);
        $this->assertEquals(5000, $fb['cost_per_sale'], 'no ad spend, so the commission is the whole cost');
    }

    public function test_a_sale_with_no_source_but_a_doctor_goes_to_the_doctor_channel(): void
    {
        $doctor = \App\Models\Doctor::create(['name' => 'Dr Walk', 'referrer_type' => 'doctor', 'status' => 1]);
        $leadId = DB::table('leads')->insertGetId(['full_name' => 'Walk In', 'phone_number' => '01912340000', 'created_at' => now()]);
        $customerId = DB::table('customers')->insertGetId(['customer_uid' => 'C-W', 'lead_id' => $leadId, 'referral_code' => 'RW', 'created_at' => now()]);
        DB::table('sale_masters')->insert(['sale_invoice_no' => 'INV-W', 'customer_id' => $customerId, 'date' => now()->toDateString(), 'total_amount' => 30000, 'paid' => 30000,
            'reference_by' => $doctor->id, 'reference_amount' => 3000, 'created_at' => now()]);

        $doc = collect($this->report()['rows'])->firstWhere('key', 'ch:doctor');
        $this->assertEquals(1, $doc['sales']);
        $this->assertEquals(3000, $doc['commission']);
    }

    public function test_by_enquiry_month_counts_later_sales_of_earlier_enquiries(): void
    {
        $j = $this->journey(['source_of_entry' => 'facebook'], ['campaign_id' => $this->fb->id], 60000);
        DB::table('deals')->where('id', $j['dealId'])->update(['created_at' => $this->month->copy()->subMonth()->addDays(3)]);
        $lastMonth = $this->month->copy()->subMonth();

        $period = (new MarketingRoi($lastMonth, $lastMonth->copy()->endOfMonth(), 'channel', 'period', 'last'))->build();
        $this->assertEquals(0, $period['total']['sales'], 'the sale was made this month, not last month');

        $cohort = (new MarketingRoi($lastMonth, $lastMonth->copy()->endOfMonth(), 'channel', 'cohort', 'last'))->build();
        $this->assertEquals(1, $cohort['total']['sales'], "last month's enquiry bought this month");
        $this->assertEquals(60000, $cohort['total']['revenue']);
    }

    public function test_first_touch_credits_how_the_patient_first_came(): void
    {
        $google = MarketingCampaign::create(['name' => 'Search', 'code' => 'search', 'channel' => 'google', 'status' => 'active']);
        // first came from Google, the enquiry that bought came from Facebook
        $this->journey(['campaign_id' => $google->id, 'source_of_entry' => 'google'], ['campaign_id' => $this->fb->id], 40000);

        $this->assertEquals(1, collect($this->report('channel', 'period', 'last')['rows'])->firstWhere('key', 'ch:facebook')['sales']);
        $this->assertEquals(1, collect($this->report('channel', 'period', 'first')['rows'])->firstWhere('key', 'ch:google')['sales']);
    }

    // ── screens and access ──

    public function test_the_screens_open_and_are_protected(): void
    {
        app(MarketingSpendService::class)->save($this->fb, $this->month->format('Y-m'), 4500, null);
        $this->get('/admin/marketing/campaigns')->assertSuccessful()->assertSee('Eid FB offer')->assertSee('utm_campaign=eid_fb', false);
        $this->get('/admin/marketing/spend')->assertSuccessful()->assertSee('4,500.00');
        $this->get('/admin/marketing/roi')->assertSuccessful()->assertSee('Marketing Return')->assertSee('Facebook');
        $this->get('/admin/marketing/roi/export')->assertSuccessful();

        $viewer = $this->userWithPermissions(['marketing_roi.index']);
        $this->actingAs($viewer)->get('/admin/marketing/roi')->assertSuccessful();
        $this->actingAs($viewer)->get('/admin/marketing/spend')->assertForbidden();
        $this->actingAs($viewer)->post('/admin/marketing/campaigns', ['name' => 'X', 'channel' => 'facebook', 'status' => 'active'])->assertForbidden();
    }

    public function test_campaign_codes_are_unique_and_link_safe_and_used_campaigns_are_only_ended(): void
    {
        $this->post('/admin/marketing/campaigns', ['name' => 'Dupe', 'code' => 'EID_FB', 'channel' => 'facebook', 'status' => 'active'])->assertSessionHasErrors('code');
        $this->post('/admin/marketing/campaigns', ['name' => 'Bad', 'code' => 'a&b', 'channel' => 'facebook', 'status' => 'active'])->assertSessionHasErrors('code');
        $this->post('/admin/marketing/campaigns', ['name' => 'Radio Spot October', 'channel' => 'radio_tv', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertTrue(MarketingCampaign::where('code', 'radio_spot_october')->exists(), 'code made from the name');

        app(MarketingSpendService::class)->save($this->fb, $this->month->format('Y-m'), 100, null);
        $this->delete('/admin/marketing/campaigns/' . $this->fb->id)->assertSessionHas('warning');
        $this->assertSame('ended', $this->fb->fresh()->status);
    }

    public function test_the_trial_stage_no_longer_asks_for_a_return_date(): void
    {
        $fields = json_decode((string) DB::table('pipeline_stages')->where('status_key', 'trial')->value('required_fields'), true) ?? [];
        $this->assertNotContains('trial_return_date', $fields);
    }
}
