<?php

namespace Tests\Feature;

use App\Classes\SMS;
use App\Models\CustomerAdvance;
use App\Models\Deal;
use App\Models\DealTrial;
use App\Models\Pipeline;
use App\Services\DealService;
use App\Services\TrialService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Marketing phase 5: in-session and take-home trials, and quotations the patient answers online. */
class TrialsAndOnlineQuotesTest extends TestCase
{
    use DatabaseTransactions, CrmFixtures;

    protected int $model;

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
        $brand = DB::table('brands')->insertGetId(['brand_name' => 'Phonak', 'created_at' => now()]);
        $this->model = $this->product('Audeo L70');
        DB::table('products')->where('id', $this->model)->update(['brand_id' => $brand]);
    }

    /** A patient (lead + customer) and an open deal owned by admin. */
    private function deal(string $phone = '01733000001'): Deal
    {
        $lead = \App\Models\Lead::create(['lead_uid' => \App\Models\Lead::generateUid(), 'full_name' => 'Trial ' . $phone, 'phone_number' => $phone, 'lead_status' => 'new']);
        DB::table('customers')->insert(['customer_uid' => 'C-' . $lead->id, 'lead_id' => $lead->id, 'referral_code' => 'R' . $lead->id, 'created_at' => now()]);

        return app(DealService::class)->openInitialDeal($lead, ['owner_id' => $this->admin->id]);
    }

    private function customerOf(Deal $deal): int
    {
        return (int) DB::table('customers')->where('lead_id', $deal->lead_id)->value('id');
    }

    // ── in-session trial ──

    public function test_moving_to_trial_records_the_model_and_how_it_went(): void
    {
        $this->actingAs($this->admin);
        $deal = $this->deal();
        $trial = Pipeline::default()->activeStages()->where('status_key', 'trial')->first();

        app(DealService::class)->move($deal->fresh(), $trial, ['trial_product' => 'Phonak Audeo L70 (right ear)', 'trial_result' => 'price']);

        $t = DealTrial::where('deal_id', $deal->id)->first();
        $this->assertSame($this->model, $t->product_id, 'the typed model is matched to the product');
        $this->assertSame('Phonak Audeo L70', $t->product_label);
        $this->assertSame('price', $t->result);
        $this->assertSame('in_session', $t->kind);
        $this->assertTrue(DB::table('lead_status_histories')->where('deal_id', $deal->id)->where('notes', 'like', '%Phonak Audeo L70 – Price concern%')->exists());
    }

    public function test_a_trial_can_be_recorded_from_the_drawer_and_the_report_counts_it(): void
    {
        $deal = $this->deal();
        $this->actingAs($this->admin)->post('/admin/deals/' . $deal->id . '/trials', ['product_id' => $this->model, 'result' => 'liked'])->assertSessionHas('success');
        $other = $this->deal('01733000002');
        $this->actingAs($this->admin)->post('/admin/deals/' . $other->id . '/trials', ['product_id' => $this->model, 'result' => 'thinking']);
        app(DealService::class)->markWon($deal->fresh());

        $this->actingAs($this->admin)->get('/admin/deals/' . $deal->id)->assertSee('dd-trial-', false)->assertSee('Liked it');
        $this->actingAs($this->admin)->get('/admin/deal-reports')->assertSuccessful()
            ->assertSee('2 patients tried a device, 1 bought (50%)')->assertSee('Phonak Audeo L70')->assertSee('Needs to think / ask family');
    }

    // ── take-home trial ──

    private function stockSerial(string $serial): void
    {
        $this->postPurchase($this->admin, $this->supplier(), [[$this->model, '', $serial, 1]])->assertSessionHasNoErrors();
    }

    public function test_a_take_home_trial_holds_the_serial_takes_the_deposit_and_closes_when_bought(): void
    {
        $this->stockSerial('SN-TH-1');
        $deal = $this->deal();

        $this->actingAs($this->admin)->post('/admin/deals/' . $deal->id . '/trials/take-home', [
            'product_id' => $this->model, 'serial_no' => 'SN-TH-1', 'deposit' => 5000, 'method' => 'bkash', 'return_due' => now()->addDays(5)->toDateString(),
        ])->assertSessionHas('success');

        $t = DealTrial::where('kind', 'take_home')->first();
        $this->assertSame('out', $t->status);
        $advance = CustomerAdvance::find($t->advance_id);
        $this->assertEquals(5000, (float) $advance->balance);
        $this->assertTrue(DB::table('ledgers')->where('ref_invoice_no', $advance->receipt_no)->where('income_amount', 5000)->exists(), 'deposit is in the cash book');
        $this->assertTrue(DB::table('lead_follow_ups')->where('deal_id', $deal->id)->where('status', 'pending')->where('follow_up_notes', 'like', 'Take-home trial:%SN-TH-1%')->exists());

        // someone else cannot be sold this unit
        $stranger = $this->patient();
        $this->postSale($this->admin, $stranger, [[$this->model, null, ['SN-TH-1'], 1]], 200)->assertSessionHasErrors();

        // the patient buys it: allowed, and the trial closes by itself
        $this->postSale($this->admin, $this->customerOf($deal), [[$this->model, null, ['SN-TH-1'], 1]], 200)->assertSessionHasNoErrors();
        $this->assertSame(1, app(TrialService::class)->sweep()['bought']);
        $this->assertSame('bought', $t->fresh()->status);
        $this->assertNotNull($t->fresh()->sale_master_id);
    }

    public function test_a_returned_device_frees_the_serial_and_refunds_the_deposit(): void
    {
        $this->stockSerial('SN-TH-2');
        $deal = $this->deal();
        $this->actingAs($this->admin)->post('/admin/deals/' . $deal->id . '/trials/take-home', [
            'product_id' => $this->model, 'serial_no' => 'SN-TH-2', 'deposit' => 3000, 'return_due' => now()->addDays(3)->toDateString(),
        ]);
        $t = DealTrial::where('serial_no', 'SN-TH-2')->first();

        $this->actingAs($this->admin)->post('/admin/deal-trials/' . $t->id . '/close', ['outcome' => 'bought'])->assertSessionHas('warning');   // no sale yet

        $this->actingAs($this->admin)->post('/admin/deal-trials/' . $t->id . '/close', ['outcome' => 'returned', 'deposit' => 'refund', 'method' => 'cash'])->assertSessionHas('success');
        $this->assertSame('returned', $t->fresh()->status);
        $this->assertSame(CustomerAdvance::STATUS_REFUNDED, CustomerAdvance::find($t->advance_id)->status);
        $this->assertSame(0, DB::table('lead_follow_ups')->where('deal_id', $deal->id)->where('status', 'pending')->where('follow_up_notes', 'like', 'Take-home trial:%')->count());

        $this->postSale($this->admin, $this->patient(), [[$this->model, null, ['SN-TH-2'], 1]], 200)->assertSessionHasNoErrors();
    }

    public function test_unknown_or_busy_serials_are_refused_and_overdue_returns_alert(): void
    {
        $deal = $this->deal();
        $this->actingAs($this->admin)->post('/admin/deals/' . $deal->id . '/trials/take-home', [
            'product_id' => $this->model, 'serial_no' => 'NOT-IN-STOCK', 'return_due' => now()->addDays(3)->toDateString(),
        ])->assertSessionHas('warning');

        $this->stockSerial('SN-TH-3');
        $this->actingAs($this->admin)->post('/admin/deals/' . $deal->id . '/trials/take-home', ['product_id' => $this->model, 'serial_no' => 'SN-TH-3', 'return_due' => now()->addDay()->toDateString()]);
        $other = $this->deal('01733000009');
        $this->actingAs($this->admin)->post('/admin/deals/' . $other->id . '/trials/take-home', ['product_id' => $this->model, 'serial_no' => 'SN-TH-3', 'return_due' => now()->addDay()->toDateString()])
            ->assertSessionHas('warning');

        DealTrial::where('serial_no', 'SN-TH-3')->update(['return_due' => now()->subDay()->toDateString()]);
        $this->assertSame(1, app(TrialService::class)->sweep()['overdue']);
        $this->assertSame(0, app(TrialService::class)->sweep()['overdue'], 'alerted once');
        $this->assertTrue(DB::table('notifications')->where('data', 'like', '%Trial device not returned%')->exists());
    }

    // ── online quotation ──

    private function proposal(array $extra = []): int
    {
        $deal = $this->deal('01733000077');
        $id = DB::table('proposals')->insertGetId($extra + ['proposal_no' => 'QT-' . uniqid(), 'title' => 'Hearing aid quotation', 'status' => 'Draft',
            'lead_id' => $deal->lead_id, 'deal_id' => $deal->id, 'recipient_name' => 'Rokeya Begum', 'recipient_phone' => '01733000077',
            'proposal_date' => now()->toDateString(), 'valid_until' => now()->addDays(15)->toDateString(), 'grand_total' => 85000, 'total_amount' => 85000,
            'created_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('proposal_items')->insert(['proposal_id' => $id, 'item_type' => 'product', 'item_name' => 'Phonak Audeo L70 (pair)', 'qty' => 1, 'unit_price' => 85000, 'total_price' => 85000, 'created_at' => now()]);

        return $id;
    }

    public function test_a_quote_link_can_be_opened_and_accepted_once(): void
    {
        $id = $this->proposal();
        $this->actingAs($this->admin)->post('/admin/proposal/' . $id . '/share', ['payment_url' => 'https://pay.example.com/abc', 'send_sms' => 1])->assertSessionHas('success');
        $p = DB::table('proposals')->find($id);
        $this->assertSame('Sent', $p->status);
        $this->assertSame(48, strlen($p->public_token));
        $this->assertTrue(DB::table('sms_logs')->where('message', 'like', '%/q/' . $p->public_token . '%')->exists());

        auth()->logout();
        $this->get('/q/' . $p->public_token)->assertSuccessful()->assertSee('Phonak Audeo L70 (pair)')->assertSee('85,000.00')->assertSee('Pay advance');
        $this->assertNotNull(DB::table('proposals')->where('id', $id)->value('viewed_at'));

        $this->post('/q/' . $p->public_token . '/accept', ['name' => 'Rokeya Begum'])->assertSessionHasErrors('agree');
        $this->post('/q/' . $p->public_token . '/accept', ['name' => 'Rokeya Begum', 'agree' => 1])->assertSessionHas('done');
        $p = DB::table('proposals')->find($id);
        $this->assertSame('Accepted', $p->status);
        $this->assertSame('Rokeya Begum', $p->responded_name);
        $this->assertTrue(DB::table('lead_follow_ups')->where('deal_id', $p->deal_id)->where('follow_up_notes', 'like', '%accepted online by Rokeya Begum%')->exists());
        $this->assertTrue(DB::table('notifications')->where('data', 'like', '%Quotation accepted%')->exists());

        $this->post('/q/' . $p->public_token . '/decline', ['reason' => 'changed mind'])->assertSessionHas('info');
        $this->assertSame('Accepted', DB::table('proposals')->where('id', $id)->value('status'));
    }

    public function test_declining_and_expired_quotes(): void
    {
        $a = $this->proposal(['public_token' => str_repeat('a', 48), 'status' => 'Sent']);
        $this->post('/q/' . str_repeat('a', 48) . '/decline', ['reason' => 'Too expensive'])->assertSessionHas('done');
        $this->assertSame('Declined', DB::table('proposals')->where('id', $a)->value('status'));
        $this->assertSame('Too expensive', DB::table('proposals')->where('id', $a)->value('response_note'));

        $b = $this->proposal(['public_token' => str_repeat('b', 48), 'status' => 'Sent', 'valid_until' => now()->subDay()->toDateString()]);
        $this->get('/q/' . str_repeat('b', 48))->assertSee('This quotation has expired');
        $this->post('/q/' . str_repeat('b', 48) . '/accept', ['name' => 'Xavier', 'agree' => 1])->assertSessionHas('info');
        $this->assertSame('Sent', DB::table('proposals')->where('id', $b)->value('status'));

        $this->get('/q/' . str_repeat('z', 48))->assertNotFound();
        $this->get('/q/short')->assertNotFound();
    }
}
