<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Audit 29 Oct 2026, stage 2: sale form amounts are numbers (F-02 step 1), totals tie-out is logged (F-02 step 2), one save per form (F-05). */
class AuditStage2Test extends TestCase
{
    use DatabaseTransactions, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
    }

    private function stockedSale(): array
    {
        $productId = $this->product('AUD-S2');
        $this->postPurchase($this->admin, $this->supplier(), [[$productId, 'LOT-S2', '', 5]])->assertSessionHasNoErrors();
        $batchId = (int) DB::table('batches')->where('product_id', $productId)->value('id');

        return [$productId, $batchId, $this->patient()];
    }

    // ── F-02 step 1: amounts must be numbers ──

    public function test_prices_vat_and_discounts_must_be_amounts(): void
    {
        [$productId, $batchId, $customerId] = $this->stockedSale();
        $line = [[$productId, $batchId, null, 1]];
        $before = DB::table('sale_masters')->count();

        $this->postSale($this->admin, $customerId, $line, 0, ['unit_price' => ['-200']])->assertSessionHasErrors('unit_price.0');
        $this->postSale($this->admin, $customerId, $line, 0, ['unit_price' => ['two hundred']])->assertSessionHasErrors('unit_price.0');
        $this->postSale($this->admin, $customerId, $line, 0, ['vat' => '-5'])->assertSessionHasErrors('vat');
        $this->postSale($this->admin, $customerId, $line, 0, ['discount' => ['150']])->assertSessionHasErrors('discount.0');
        $this->postSale($this->admin, $customerId, $line, 0, ['invoice_discount' => 'abc'])->assertSessionHasErrors('invoice_discount');

        $this->assertSame($before, DB::table('sale_masters')->count());
    }

    public function test_a_normal_sale_still_saves(): void
    {
        [$productId, $batchId, $customerId] = $this->stockedSale();

        $this->postSale($this->admin, $customerId, [[$productId, $batchId, null, 1]], 0, [
            'vat' => '', 'invoice_discount' => '0.00', 'discount_taka' => ['0.00'],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
    }

    // ── F-02 step 2: log-only tie-out ──

    public function test_a_total_that_differs_from_the_lines_is_logged_but_saved(): void
    {
        [$productId, $batchId, $customerId] = $this->stockedSale();
        Log::spy();

        // One line at ৳200, but the form says ৳150
        $this->postSale($this->admin, $customerId, [[$productId, $batchId, null, 1]], 0, ['total_amount' => 150])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertEquals(150, (float) DB::table('sale_masters')->orderByDesc('id')->value('total_amount'), 'the sale is saved as posted');
        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context = []) => $message === 'Sale total differs from its lines'
            && $context['saved_total'] == 150 && $context['expected_total'] == 200)->once();
    }

    public function test_a_matching_total_logs_nothing(): void
    {
        [$productId, $batchId, $customerId] = $this->stockedSale();
        Log::spy();

        // ৳200 − ৳20 line discount + ৳10 VAT − ৳5 invoice discount = ৳185; discount 20 + 5 = 25
        $this->postSale($this->admin, $customerId, [[$productId, $batchId, null, 1]], 0, [
            'total_amount' => 185, 'total_discount' => 25, 'discount' => [10], 'discount_taka' => [20], 'vat' => 10, 'invoice_discount' => 5,
        ])->assertSessionHasNoErrors();

        Log::shouldNotHaveReceived('warning', fn ($message) => $message === 'Sale total differs from its lines');
    }

    // ── F-05: one save per form ──

    public function test_the_same_form_cannot_save_two_sales(): void
    {
        [$productId, $batchId, $customerId] = $this->stockedSale();
        $token = (string) Str::uuid();
        $before = DB::table('sale_masters')->count();

        $this->postSale($this->admin, $customerId, [[$productId, $batchId, null, 1]], 0, ['submission_token' => $token])->assertSessionHas('success');
        $this->postSale($this->admin, $customerId, [[$productId, $batchId, null, 1]], 0, ['submission_token' => $token])
            ->assertRedirect('admin/sale')->assertSessionHas('warning');

        $this->assertSame($before + 1, DB::table('sale_masters')->count());
    }

    public function test_a_failed_save_lets_the_same_form_try_again(): void
    {
        [$productId, $batchId, $customerId] = $this->stockedSale();
        $token = (string) Str::uuid();

        // Only 5 in stock: the first try fails on stock and must not use up the token
        $this->postSale($this->admin, $customerId, [[$productId, $batchId, null, 99]], 0, ['submission_token' => $token])->assertSessionHasErrors('stock');
        $this->postSale($this->admin, $customerId, [[$productId, $batchId, null, 1]], 0, ['submission_token' => $token])->assertSessionHas('success');
    }

    public function test_forms_without_a_token_still_save(): void
    {
        [$productId, $batchId, $customerId] = $this->stockedSale();
        $before = DB::table('sale_masters')->count();

        $this->postSale($this->admin, $customerId, [[$productId, $batchId, null, 1]])->assertSessionHas('success');
        $this->postSale($this->admin, $customerId, [[$productId, $batchId, null, 1]])->assertSessionHas('success');

        $this->assertSame($before + 2, DB::table('sale_masters')->count());
    }

    public function test_the_create_form_carries_a_fresh_token(): void
    {
        $page = $this->actingAs($this->admin)->get('/admin/sale/create')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/name="submission_token" value="[0-9a-f-]{36}"/', $page);
    }
}
