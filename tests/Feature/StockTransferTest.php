<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\StockTransfer;
use App\Services\StockGuard;
use App\Support\DataScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Stock transfers between head office and branches. */
class StockTransferTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        config(['crm.stock_transfer' => true]);
    }

    protected function as($user)
    {
        DataScope::reset();

        return $this->actingAs($user);
    }

    /** POST /admin/stock-transfers as Super Admin, head office to the branch unless told otherwise. */
    protected function transfer(array $lines, array $over = [], $user = null)
    {
        return $this->as($user ?? $this->admin)->post('/admin/stock-transfers', array_merge([
            'from' => 'hq', 'to' => (string) $this->branch->id, 'date' => now()->toDateString(), 'note' => '', 'lines' => $lines,
        ], $over));
    }

    protected function stockOf(int $productId, ?int $vendorId = null): float
    {
        return (float) DB::table('stocks')->where('product_id', $productId)->whereNull('deleted_at')
            ->when($vendorId, fn ($q) => $q->where('vendor_id', $vendorId), fn ($q) => $q->whereNull('vendor_id'))
            ->selectRaw('COALESCE(SUM(in_qty - out_qty), 0) as q')->value('q');
    }

    protected function balance(int $productId, string $serial, ?int $vendorId = null): int
    {
        return (int) (app(StockGuard::class)->serialBalances($productId, $vendorId)['balance'][StockGuard::key($serial)] ?? 0);
    }

    protected function pickerFor($user, int $productId): array
    {
        $this->as($user);

        return collect($this->getJson('/admin/product-serial-numbers/' . $productId)->assertOk()->json())->pluck('serial_number')->all();
    }

    protected function check(): void
    {
        $this->artisan('inventory:check')->expectsOutputToContain('All checks passed')->assertExitCode(0);
    }

    // ── switch and access ──

    public function test_screens_are_off_until_switched_on_and_closed_to_branches_and_unpermitted_users(): void
    {
        config(['crm.stock_transfer' => false]);
        $this->as($this->admin)->get('/admin/stock-transfers')->assertNotFound();

        config(['crm.stock_transfer' => true]);
        $this->as($this->admin)->get('/admin/stock-transfers')->assertOk()->assertSee('Stock Transfers');
        $this->get('/admin/stock-transfers/create')->assertOk()->assertSee('Add product line');

        $this->as($this->branch);
        foreach (['/admin/stock-transfers', '/admin/stock-transfers/create'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/admin/stock-transfers', [])->assertForbidden();
        $this->post('/admin/stock-transfers/1/cancel', ['cancel_reason' => 'x'])->assertForbidden();

        $this->as($this->userWithPermissions(['dashboard.index']))->get('/admin/stock-transfers')->assertForbidden();
        $viewer = $this->userWithPermissions(['stock_transfer.index']);
        $this->as($viewer)->get('/admin/stock-transfers')->assertOk();
        $this->get('/admin/stock-transfers/create')->assertForbidden();
    }

    // ── serial items ──

    public function test_a_transferred_serial_moves_to_the_branch_and_can_only_be_sold_there(): void
    {
        $p = $this->product('TR-SERIAL');
        $this->postPurchase($this->admin, $this->supplier(), [[$p, '', 'SN-T-1,SN-T-2', 2]])->assertSessionHasNoErrors();

        $this->transfer([['product_id' => $p, 'serials' => 'SN-T-1']])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertEquals(1, $this->stockOf($p));
        $this->assertEquals(1, $this->stockOf($p, $this->branch->id));
        $this->assertSame(1, DB::table('stocks')->where('type', 'Transfer Out')->whereNull('vendor_id')->count());
        $this->assertSame(1, DB::table('stocks')->where('type', 'Transfer In')->where('vendor_id', $this->branch->id)->count());
        $this->assertSame(0, $this->balance($p, 'SN-T-1'));
        $this->assertSame(1, $this->balance($p, 'SN-T-1', $this->branch->id));
        $this->assertSame(1, $this->balance($p, 'SN-T-2'));

        // The sale-form picker follows the unit: gone from head office, listed at the branch with the original price
        $this->assertSame(['SN-T-2'], $this->pickerFor($this->admin, $p));
        $branchPicker = collect($this->as($this->branch)->getJson('/admin/product-serial-numbers/' . $p)->assertOk()->json());
        $this->assertSame(['SN-T-1'], $branchPicker->pluck('serial_number')->all());
        $this->assertEquals(100, $branchPicker->first()['purchase_price']);
        $this->assertEquals(200, $branchPicker->first()['mrp_price']);
        $this->assertNotEmpty($branchPicker->first()['purchase_detail_id'], 'still points at the original purchase line');

        // Head office can no longer sell it; the branch can
        $patient = $this->patient();
        $this->postSale($this->admin, $patient, [[$p, null, ['SN-T-1'], 1]])->assertSessionHasErrors();
        $this->postSale($this->branch, $patient, [[$p, null, ['SN-T-1'], 1]])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->balance($p, 'SN-T-1', $this->branch->id));
        $this->check();
    }

    public function test_a_serial_can_be_moved_back_and_sold_by_head_office_again(): void
    {
        $p = $this->product('TR-BACK');
        $this->postPurchase($this->admin, $this->supplier(), [[$p, '', 'SN-B-1', 1]])->assertSessionHasNoErrors();
        $this->transfer([['product_id' => $p, 'serials' => 'SN-B-1']])->assertSessionHasNoErrors();
        $this->transfer([['product_id' => $p, 'serials' => 'SN-B-1']], ['from' => (string) $this->branch->id, 'to' => 'hq'])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->balance($p, 'SN-B-1'));
        $this->assertSame(0, $this->balance($p, 'SN-B-1', $this->branch->id));
        $this->assertSame(['SN-B-1'], $this->pickerFor($this->admin, $p));
        $this->assertSame([], $this->pickerFor($this->branch, $p));
        $this->postSale($this->admin, $this->patient(), [[$p, null, ['SN-B-1'], 1]])->assertSessionHasNoErrors();
        $this->check();
    }

    // ── batch items ──

    public function test_a_transferred_batch_creates_a_destination_batch_that_points_back_to_the_purchase(): void
    {
        $p = $this->product('TR-BATCH');
        $this->postPurchase($this->admin, $this->supplier(), [[$p, 'B-T', '', 5]])->assertSessionHasNoErrors();
        $source = Batch::where('product_id', $p)->first();

        $this->transfer([['product_id' => $p, 'batch_id' => $source->id, 'qty' => 3]])->assertSessionHasNoErrors();

        $this->assertEquals(-3, (float) DB::table('batches')->where('id', $source->id)->value('adjust_qty'));
        $this->assertEquals(2, (float) Batch::find($source->id)->stock);
        $dest = Batch::withoutGlobalScopes()->where('vendor_id', $this->branch->id)->first();
        $this->assertSame('B-T-B' . $this->branch->id, $dest->batch_no, 'batch numbers are unique, so the branch batch gets a location suffix');
        $this->assertSame('Transfer', $dest->type);
        $this->assertEquals(3, (float) $dest->adjust_qty);
        $this->assertEquals(0, (float) $dest->purchase_qty);
        $this->assertEquals(100, (float) $dest->purchase_price);
        $this->assertSame($source->id, (int) $dest->origin_batch_id);
        $this->assertEquals(3, $this->stockOf($p, $this->branch->id));
        $this->assertEquals(2, $this->stockOf($p));

        // A second transfer of the same batch tops up the same destination batch, and a transfer back keeps pointing at the original
        $this->transfer([['product_id' => $p, 'batch_id' => $source->id, 'qty' => 1]])->assertSessionHasNoErrors();
        $this->assertSame(1, Batch::withoutGlobalScopes()->where('vendor_id', $this->branch->id)->count());
        $this->assertEquals(4, (float) Batch::withoutGlobalScopes()->find($dest->id)->adjust_qty);

        // The branch sells from its own batch; it cannot sell more than it has
        $patient = $this->patient();
        $this->postSale($this->branch, $patient, [[$p, $dest->id, null, 5]])->assertSessionHasErrors();
        $this->postSale($this->branch, $patient, [[$p, $dest->id, null, 4]])->assertSessionHasNoErrors();
        $this->check();

        // Batch info on the sale form reads cost and selling price through the original purchase
        $this->as($this->branch)->getJson('/admin/batch-wise-product-info/' . $dest->id);
        $info = app(\App\Http\Controllers\Admin\ProductController::class)->batchWiseProductInfo($dest->id);
        $this->assertEquals(100, $info->purchase_price);
        $this->assertEquals(200, $info->mrp_price);
    }

    public function test_one_transfer_can_carry_a_serial_line_and_a_batch_line_of_the_same_product(): void
    {
        $p = $this->product('TR-MIXED');
        $this->postPurchase($this->admin, $this->supplier(), [[$p, '', 'SN-M-1', 1], [$p, 'B-M', '', 4]])->assertSessionHasNoErrors();
        $batchId = (int) DB::table('batches')->where('product_id', $p)->value('id');

        $this->transfer([
            ['product_id' => $p, 'serials' => 'SN-M-1'],
            ['product_id' => $p, 'batch_id' => $batchId, 'qty' => 2],
        ])->assertSessionHasNoErrors();

        $this->assertEquals(3, $this->stockOf($p, $this->branch->id));
        $this->assertEquals(2, $this->stockOf($p));
        $this->assertSame(1, $this->balance($p, 'SN-M-1', $this->branch->id));
        $this->check();
    }

    // ── validation ──

    public function test_bad_transfers_are_refused_and_change_nothing(): void
    {
        $p = $this->product('TR-BAD');
        $this->postPurchase($this->admin, $this->supplier(), [[$p, '', 'SN-X-1,SN-X-2', 2], [$p, 'B-X', '', 2]])->assertSessionHasNoErrors();
        $batchId = (int) DB::table('batches')->where('product_id', $p)->value('id');
        $stocks = DB::table('stocks')->count();

        $this->transfer([['product_id' => $p, 'serials' => 'SN-X-1']], ['to' => 'hq'])->assertSessionHasErrors('stock');                          // same place
        $this->transfer([['product_id' => $p, 'serials' => 'SN-NEVER']])->assertSessionHasErrors('stock');                                           // unknown serial
        $this->transfer([['product_id' => $p, 'batch_id' => $batchId, 'qty' => 9]])->assertSessionHasErrors('stock');                                  // more than available
        $this->transfer([['product_id' => $p, 'serials' => 'SN-X-1,SN-X-2', 'qty' => 5]])->assertSessionHasErrors('stock');                            // qty and serials disagree
        $this->transfer([['product_id' => $p, 'qty' => 1]])->assertSessionHasErrors('stock');                                                          // nothing picked
        $this->transfer([['product_id' => $p, 'serials' => 'SN-X-1']], ['from' => (string) $this->branch->id, 'to' => 'hq'])->assertSessionHasErrors('stock'); // not at the branch
        $this->transfer([['product_id' => $p, 'serials' => 'SN-X-1,SN-X-1']])->assertSessionHasErrors('stock');                                        // listed twice

        $this->assertSame($stocks, DB::table('stocks')->count());
        $this->assertSame(0, StockTransfer::count());
        $this->assertSame(0, (int) DB::table('batches')->where('adjust_qty', '!=', 0)->count());
    }

    // ── cancel ──

    public function test_cancelling_an_untouched_transfer_puts_everything_back(): void
    {
        $p = $this->product('TR-CANCEL');
        $this->postPurchase($this->admin, $this->supplier(), [[$p, '', 'SN-C-1', 1], [$p, 'B-C', '', 4]])->assertSessionHasNoErrors();
        $batchId = (int) DB::table('batches')->where('product_id', $p)->value('id');
        $this->transfer([['product_id' => $p, 'serials' => 'SN-C-1'], ['product_id' => $p, 'batch_id' => $batchId, 'qty' => 3]])->assertSessionHasNoErrors();
        $t = StockTransfer::first();

        $this->post("/admin/stock-transfers/{$t->id}/cancel", [])->assertSessionHasErrors('cancel_reason');
        $this->post("/admin/stock-transfers/{$t->id}/cancel", ['cancel_reason' => 'Sent by mistake'])->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $t->fresh()->status);
        $this->assertEquals(5, $this->stockOf($p));
        $this->assertEquals(0, $this->stockOf($p, $this->branch->id));
        $this->assertSame(1, $this->balance($p, 'SN-C-1'));
        $this->assertSame(0, $this->balance($p, 'SN-C-1', $this->branch->id));
        $this->assertEquals(0, (float) DB::table('batches')->where('id', $batchId)->value('adjust_qty'));
        $this->assertEquals(0, (float) Batch::withoutGlobalScopes()->where('vendor_id', $this->branch->id)->value('adjust_qty'));
        $this->assertSame(2, DB::table('stocks')->where('type', 'like', 'Transfer % Reversal')->count() / 2);
        $this->post("/admin/stock-transfers/{$t->id}/cancel", ['cancel_reason' => 'again'])->assertSessionHasErrors('stock');
        $this->check();
    }

    public function test_cancelling_is_refused_once_a_unit_was_sold_and_only_a_super_admin_may_cancel(): void
    {
        $p = $this->product('TR-CANCEL2');
        $this->postPurchase($this->admin, $this->supplier(), [[$p, '', 'SN-K-1,SN-K-2', 2]])->assertSessionHasNoErrors();
        $this->transfer([['product_id' => $p, 'serials' => 'SN-K-1,SN-K-2']])->assertSessionHasNoErrors();
        $t = StockTransfer::first();

        $manager = $this->userWithPermissions(['stock_transfer.index', 'stock_transfer.view', 'stock_transfer.cancel']);
        $this->as($manager)->post("/admin/stock-transfers/{$t->id}/cancel", ['cancel_reason' => 'x'])->assertSessionHasErrors('stock');
        $this->assertSame('completed', $t->fresh()->status);

        $this->postSale($this->branch, $this->patient(), [[$p, null, ['SN-K-1'], 1]])->assertSessionHasNoErrors();
        $this->as($this->admin)->post("/admin/stock-transfers/{$t->id}/cancel", ['cancel_reason' => 'Mistake'])->assertSessionHasErrors('stock');
        $this->assertSame('completed', $t->fresh()->status);
        $this->assertEquals(1, $this->stockOf($p, $this->branch->id));
    }

    // ── purchases and returns ──

    public function test_a_purchase_with_transferred_stock_cannot_be_deleted_and_a_moved_unit_cannot_go_back_to_the_supplier(): void
    {
        $p = $this->product('TR-PUR');
        $b = $this->product('TR-PUR-B');
        $this->postPurchase($this->admin, $this->supplier(), [[$p, '', 'SN-P-1,SN-P-2', 2], [$b, 'B-P', '', 3]])->assertSessionHasNoErrors();
        $purchaseId = (int) DB::table('purchase_masters')->orderByDesc('id')->value('id');
        $batchId = (int) DB::table('batches')->where('product_id', $b)->value('id');

        $this->transfer([['product_id' => $p, 'serials' => 'SN-P-1']])->assertSessionHasNoErrors();
        $this->as($this->admin)->delete('/admin/purchase/' . $purchaseId, ['reason' => 'Cancelling for test'])->assertSessionHas('warning');
        $this->assertNull(DB::table('purchase_masters')->where('id', $purchaseId)->value('deleted_at'));

        // The moved unit has to come back before it can be returned to the supplier; the one still here can go
        $this->getJson('/admin/purchase-returns/serials/lookup?serial_no=SN-P-1')->assertStatus(422)->assertJsonFragment(['success' => false]);
        $this->assertStringContainsString('Move it back', $this->getJson('/admin/purchase-returns/serials/lookup?serial_no=SN-P-1')->json('message'));
        $this->getJson('/admin/purchase-returns/serials/lookup?serial_no=SN-P-2')->assertOk()->assertJsonFragment(['success' => true]);

        $this->transfer([['product_id' => $p, 'serials' => 'SN-P-1']], ['from' => (string) $this->branch->id, 'to' => 'hq'])->assertSessionHasNoErrors();
        $this->getJson('/admin/purchase-returns/serials/lookup?serial_no=SN-P-1')->assertOk()->assertJsonFragment(['success' => true]);

        // Batch stock moved out of a purchase also keeps the purchase in place
        $this->transfer([['product_id' => $b, 'batch_id' => $batchId, 'qty' => 1]])->assertSessionHasNoErrors();
        $problems = app(StockGuard::class)->purchaseDeleteProblems($purchaseId);
        $this->assertNotEmpty($problems);
    }

    // ── warranty ──

    public function test_supplier_warranty_still_follows_the_original_purchase_date_after_a_transfer(): void
    {
        $p = $this->product('TR-WARR');
        DB::table('products')->where('id', $p)->update(['warranty_inventory_months' => 27, 'warranty_sales_months' => 24]);
        $this->postPurchase($this->admin, $this->supplier(), [[$p, '', 'SN-W-1', 1], [$p, 'W-B', '', 2]])->assertSessionHasNoErrors();

        // Bought 24 months ago less 10 days: the supplier-warranty alert date is today
        $purchaseDate = now()->subMonths(24)->addDays(10)->toDateString();
        DB::table('purchase_masters')->update(['date' => $purchaseDate]);
        $batchId = (int) DB::table('batches')->where('product_id', $p)->value('id');
        $this->transfer([['product_id' => $p, 'serials' => 'SN-W-1'], ['product_id' => $p, 'batch_id' => $batchId, 'qty' => 2]])->assertSessionHasNoErrors();

        // Staff of the branch with the report permission see the units they now hold, with the ORIGINAL purchase date
        $staff = $this->userWithPermissions(['warranty_expiry.index']);
        $staff->forceFill(['vendor_id' => $this->branch->id])->save();
        $html = $this->as($staff)->get('/admin/report/warranty-expiry')->assertOk()->getContent();
        $this->assertStringContainsString('SN-W-1', $html);
        $this->assertStringContainsString('W-B', $html);
        $this->assertStringContainsString(\Illuminate\Support\Carbon::parse($purchaseDate)->format('d M Y'), $html);

        // Head office no longer lists the units it sent away
        $headOffice = $this->as($this->admin)->get('/admin/report/warranty-expiry')->assertOk()->getContent();
        $this->assertStringNotContainsString('SN-W-1', $headOffice);
    }

    public function test_customer_warranty_and_cost_after_a_transfer_follow_the_sale(): void
    {
        config(['crm.sale_cost_mode' => 'server']);
        $p = $this->product('TR-CUST');
        DB::table('products')->where('id', $p)->update(['warranty_sales_months' => 12]);
        $this->postPurchase($this->admin, $this->supplier(), [[$p, '', 'SN-U-1', 1]])->assertSessionHasNoErrors();
        $this->transfer([['product_id' => $p, 'serials' => 'SN-U-1']])->assertSessionHasNoErrors();

        $this->postSale($this->branch, $this->patient(), [[$p, null, ['SN-U-1'], 1]], 0, ['purchase_price' => [0]])->assertSessionHasNoErrors();

        // Cost comes from the original purchase line, and the customer warranty starts at the sale date
        $this->assertEquals(100, DB::table('sale_details')->where('product_id', $p)->value('purchase_price'));
        $saleDate = DB::table('sale_masters')->orderByDesc('id')->value('date');
        $expiry = \Illuminate\Support\Carbon::parse($saleDate)->addMonths(12);
        $this->assertSame($expiry->toDateString(), \Illuminate\Support\Carbon::parse($saleDate)->addMonths((int) DB::table('products')->where('id', $p)->value('warranty_sales_months'))->toDateString());

        // Warranty & Serial Check finds the unit through its sale, as for any other unit
        $found = app(\App\Services\WarrantyVerificationService::class)->checkBySerial('SN-U-1');
        $this->assertTrue($found['found']);
        $this->assertSame(DB::table('sale_masters')->orderByDesc('id')->value('sale_invoice_no'), $found['sale_invoice_no']);
        $this->assertSame(12, $found['product']['warranty_months']);
    }
}
