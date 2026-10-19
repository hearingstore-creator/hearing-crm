<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\StockAdjustment;
use App\Services\SaleCost;
use App\Services\StockGuard;
use App\Support\DataScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Stock adjustments: damaged / lost / found / count correction / opening stock. */
class StockAdjustmentTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        config(['crm.stock_adjustment' => true]);
    }

    protected function as($user)
    {
        DataScope::reset();

        return $this->actingAs($user);
    }

    /** POST /admin/stock-adjustments as Super Admin. */
    protected function adjust(array $lines, array $over = [], $user = null)
    {
        return $this->as($user ?? $this->admin)->post('/admin/stock-adjustments', array_merge([
            'date' => now()->toDateString(), 'reason' => 'damaged', 'location' => 'hq', 'note' => '', 'lines' => $lines,
        ], $over));
    }

    protected function stockOf(int $productId, ?int $vendorId = null): float
    {
        return (float) DB::table('stocks')->where('product_id', $productId)->whereNull('deleted_at')
            ->when($vendorId, fn ($q) => $q->where('vendor_id', $vendorId), fn ($q) => $q->whereNull('vendor_id'))
            ->selectRaw('COALESCE(SUM(in_qty - out_qty), 0) as q')->value('q');
    }

    protected function serialBalance(int $productId, string $serial, ?int $vendorId = null): int
    {
        return (int) (app(StockGuard::class)->serialBalances($productId, $vendorId)['balance'][StockGuard::key($serial)] ?? 0);
    }

    // ── switch and access ──

    public function test_screens_are_off_until_switched_on_and_closed_to_branches_and_unpermitted_users(): void
    {
        config(['crm.stock_adjustment' => false]);
        $this->as($this->admin)->get('/admin/stock-adjustments')->assertNotFound();

        config(['crm.stock_adjustment' => true]);
        $this->as($this->admin)->get('/admin/stock-adjustments')->assertOk()->assertSee('Stock Adjustments');
        $this->get('/admin/stock-adjustments/create')->assertOk()->assertSee('Add product line');

        $this->as($this->branch);
        foreach (['/admin/stock-adjustments', '/admin/stock-adjustments/create'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/admin/stock-adjustments', [])->assertForbidden();
        $this->post('/admin/stock-adjustments/1/cancel', ['cancel_reason' => 'x'])->assertForbidden();

        $noPermission = $this->userWithPermissions(['dashboard.index']);
        $this->as($noPermission)->get('/admin/stock-adjustments')->assertForbidden();
        $viewer = $this->userWithPermissions(['stock_adjustment.index']);
        $this->as($viewer)->get('/admin/stock-adjustments')->assertOk();
        $this->get('/admin/stock-adjustments/create')->assertForbidden();
    }

    // ── removing stock ──

    public function test_removing_a_serial_takes_it_out_of_stock_and_blocks_its_sale(): void
    {
        $p = $this->product('ADJ-SERIAL');
        $this->postPurchase($this->admin, $this->supplier(), [[$p, '', 'SN-A-1,SN-A-2', 2]])->assertSessionHasNoErrors();
        $this->assertEquals(2, $this->stockOf($p));

        $this->adjust([['product_id' => $p, 'direction' => 'out', 'serials' => 'SN-A-1']])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertEquals(1, $this->stockOf($p));
        $row = DB::table('stocks')->where('type', 'Adjustment Out')->first();
        $this->assertSame(1.0, (float) $row->out_qty);
        $this->assertStringStartsWith('ADJ-', $row->ref_invoice_no);
        $this->assertSame(0, $this->serialBalance($p, 'SN-A-1'));
        $this->assertSame(1, $this->serialBalance($p, 'SN-A-2'));

        $patient = $this->patient();
        $this->postSale($this->admin, $patient, [[$p, null, ['SN-A-1'], 1]])->assertSessionHasErrors();
        $this->postSale($this->admin, $patient, [[$p, null, ['SN-A-2'], 1]])->assertSessionHasNoErrors();

        // The sale picker no longer lists the written-off unit
        $serials = collect($this->getJson('/admin/product-serial-numbers/' . $p)->assertOk()->json())->pluck('serial_number')->all();
        $this->assertNotContains('SN-A-1', $serials);
        $this->artisan('inventory:check')->expectsOutputToContain('All checks passed')->assertExitCode(0);
    }

    public function test_removing_batch_stock_uses_adjust_qty_and_respects_availability(): void
    {
        $p = $this->product('ADJ-BATCH');
        $this->postPurchase($this->admin, $this->supplier(), [[$p, 'B-A', '', 5]])->assertSessionHasNoErrors();
        $batchId = (int) DB::table('batches')->where('product_id', $p)->value('id');

        $this->adjust([['product_id' => $p, 'direction' => 'out', 'batch_id' => $batchId, 'qty' => 2]], ['reason' => 'lost'])->assertSessionHasNoErrors();

        $this->assertEquals(-2, (float) DB::table('batches')->where('id', $batchId)->value('adjust_qty'));
        $this->assertEquals(3, (float) Batch::find($batchId)->stock);
        $this->assertEquals(3, $this->stockOf($p));

        $patient = $this->patient();
        $this->postSale($this->admin, $patient, [[$p, $batchId, null, 4]])->assertSessionHasErrors();
        $this->postSale($this->admin, $patient, [[$p, $batchId, null, 3]])->assertSessionHasNoErrors();
        $this->artisan('inventory:check')->expectsOutputToContain('All checks passed')->assertExitCode(0);
    }

    public function test_removing_more_than_exists_or_not_in_stock_is_refused_and_changes_nothing(): void
    {
        $p = $this->product('ADJ-BAD');
        $this->postPurchase($this->admin, $this->supplier(), [[$p, 'B-X', '', 2], [$p, '', 'SN-X-1', 1]])->assertSessionHasNoErrors();
        $batchId = (int) DB::table('batches')->where('product_id', $p)->value('id');
        $before = DB::table('stocks')->count();

        $this->adjust([['product_id' => $p, 'direction' => 'out', 'batch_id' => $batchId, 'qty' => 9]])->assertSessionHasErrors('stock');
        $this->adjust([['product_id' => $p, 'direction' => 'out', 'serials' => 'SN-NEVER']])->assertSessionHasErrors('stock');
        $this->adjust([['product_id' => $p, 'direction' => 'out', 'qty' => 1]])->assertSessionHasErrors('stock');

        // A serial that is in stock at head office is not in stock at a branch
        $this->adjust([['product_id' => $p, 'direction' => 'out', 'serials' => 'SN-X-1']], ['location' => (string) $this->branch->id])->assertSessionHasErrors('stock');

        $this->assertSame($before, DB::table('stocks')->count());
        $this->assertSame(0, StockAdjustment::count());
    }

    // ── adding stock ──

    public function test_adding_a_serial_makes_it_sellable_with_its_cost_and_price(): void
    {
        $p = $this->product('ADJ-FOUND');
        $this->adjust([['product_id' => $p, 'direction' => 'in', 'serials' => 'SN-F-1', 'unit_cost' => 150, 'mrp_price' => 400]], ['reason' => 'found'])
            ->assertSessionHasNoErrors();

        $this->assertEquals(1, $this->stockOf($p));
        $this->assertSame(1, $this->serialBalance($p, 'SN-F-1'));
        $this->assertSame(['cost' => 150.0, 'source' => 'serial'], SaleCost::lookup($p, null, ['SN-F-1']));

        $picker = collect($this->getJson('/admin/product-serial-numbers/' . $p)->assertOk()->json())->firstWhere('serial_number', 'SN-F-1');
        $this->assertNotNull($picker);
        $this->assertEquals(400, $picker['mrp_price']);

        $this->postSale($this->admin, $this->patient(), [[$p, null, ['SN-F-1'], 1]])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->serialBalance($p, 'SN-F-1'));
        $this->artisan('inventory:check')->expectsOutputToContain('All checks passed')->assertExitCode(0);
    }

    public function test_serials_can_be_typed_one_per_line(): void
    {
        $p = $this->product('ADJ-LINES');
        $this->adjust([['product_id' => $p, 'direction' => 'in', 'serials' => "SN-L-1\nSN-L-2;SN-L-3,SN-L-4", 'unit_cost' => 10]], ['reason' => 'found'])->assertSessionHasNoErrors();

        $this->assertEquals(4, $this->stockOf($p));
        $this->assertEquals(4, (float) DB::table('stock_adjustment_details')->where('product_id', $p)->value('qty'));
        $this->assertSame(1, $this->serialBalance($p, 'SN-L-2'));

        // Units that came in by adjustment only (no purchase line) are part of the stock check too
        $this->artisan('inventory:check')->expectsOutputToContain('All checks passed')->assertExitCode(0);
    }

    public function test_adding_rules_cost_required_duplicate_serials_and_notes(): void
    {
        $p = $this->product('ADJ-RULES');
        $this->postPurchase($this->admin, $this->supplier(), [[$p, '', 'SN-R-1', 1]])->assertSessionHasNoErrors();

        // Missing cost, duplicate of a unit already in stock, repeated serial in one adjustment
        $this->adjust([['product_id' => $p, 'direction' => 'in', 'serials' => 'SN-R-2']], ['reason' => 'found'])->assertSessionHasErrors('stock');
        $this->adjust([['product_id' => $p, 'direction' => 'in', 'serials' => 'sn-r-1', 'unit_cost' => 10]], ['reason' => 'found'])->assertSessionHasErrors('stock');
        $this->adjust([['product_id' => $p, 'direction' => 'in', 'serials' => 'SN-R-3,SN-R-3', 'unit_cost' => 10]], ['reason' => 'found'])->assertSessionHasErrors('stock');

        // A note is needed for "other" and "count correction"; an unknown reason is refused
        $line = [['product_id' => $p, 'direction' => 'in', 'serials' => 'SN-R-4', 'unit_cost' => 10]];
        $this->adjust($line, ['reason' => 'other'])->assertSessionHasErrors('stock');
        $this->adjust($line, ['reason' => 'count_correction'])->assertSessionHasErrors('stock');
        $this->adjust($line, ['reason' => 'nonsense'])->assertSessionHasErrors('reason');
        $this->adjust($line, ['reason' => 'other', 'note' => 'Recount after audit'])->assertSessionHasNoErrors();
        $this->assertSame(1, StockAdjustment::count());
    }

    public function test_adding_batch_stock_creates_a_batch_that_sells_and_shows_cost_and_price(): void
    {
        $p = $this->product('ADJ-OPEN');
        $this->adjust([['product_id' => $p, 'direction' => 'in', 'batch_no' => 'OPEN-1', 'qty' => 6, 'unit_cost' => 30, 'mrp_price' => 90]], ['reason' => 'opening_stock'])
            ->assertSessionHasNoErrors();

        $batch = Batch::where('product_id', $p)->first();
        $this->assertSame('OPEN-1', $batch->batch_no);
        $this->assertSame('Adjustment', $batch->type);
        $this->assertEquals(6, (float) $batch->adjust_qty);
        $this->assertEquals(0, (float) $batch->purchase_qty);
        $this->assertEquals(6, (float) $batch->stock);
        $this->assertEquals(6, $this->stockOf($p));

        // The batch screen on the sale form has no purchase line to read: cost and price come from the adjustment
        $info = $this->getJson('/admin/product-info/' . $p)->assertOk()->json('batch');
        $this->assertEquals(30, $info['purchase_price']);
        $this->assertEquals(90, $info['mrp_price']);
        $this->assertSame(['cost' => 30.0, 'source' => 'batch'], SaleCost::lookup($p, $batch->id, []));

        $this->postSale($this->admin, $this->patient(), [[$p, $batch->id, null, 4]])->assertSessionHasNoErrors();
        $this->assertEquals(2, (float) Batch::find($batch->id)->stock);
        $this->artisan('inventory:check')->expectsOutputToContain('All checks passed')->assertExitCode(0);
    }

    // ── locations ──

    public function test_an_adjustment_at_a_branch_changes_that_branch_only(): void
    {
        $p = $this->product('ADJ-BRANCH');
        $this->adjust([['product_id' => $p, 'direction' => 'in', 'batch_no' => 'BR-1', 'qty' => 3, 'unit_cost' => 10]], ['reason' => 'opening_stock', 'location' => (string) $this->branch->id])
            ->assertSessionHasNoErrors();

        $this->assertEquals(3, $this->stockOf($p, $this->branch->id));
        $this->assertEquals(0, $this->stockOf($p));
        $this->assertSame($this->branch->id, (int) Batch::withoutGlobalScopes()->where('product_id', $p)->value('vendor_id'));
        $this->assertSame($this->branch->id, (int) StockAdjustment::first()->vendor_id);
    }

    // ── cancel ──

    public function test_cancelling_a_write_off_puts_the_unit_back(): void
    {
        $p = $this->product('ADJ-CANCEL');
        $this->postPurchase($this->admin, $this->supplier(), [[$p, '', 'SN-C-1', 1], [$p, 'B-C', '', 4]])->assertSessionHasNoErrors();
        $batchId = (int) DB::table('batches')->where('product_id', $p)->value('id');
        $this->adjust([
            ['product_id' => $p, 'direction' => 'out', 'serials' => 'SN-C-1'],
            ['product_id' => $p, 'direction' => 'out', 'batch_id' => $batchId, 'qty' => 3],
        ])->assertSessionHasNoErrors();
        $a = StockAdjustment::first();
        $this->assertEquals(1, $this->stockOf($p));

        $this->post("/admin/stock-adjustments/{$a->id}/cancel", [])->assertSessionHasErrors('cancel_reason');
        $this->post("/admin/stock-adjustments/{$a->id}/cancel", ['cancel_reason' => 'Found it again'])->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $a->fresh()->status);
        $this->assertEquals(5, $this->stockOf($p));
        $this->assertSame(1, $this->serialBalance($p, 'SN-C-1'));
        $this->assertEquals(0, (float) DB::table('batches')->where('id', $batchId)->value('adjust_qty'));
        $this->assertSame(2, DB::table('stocks')->where('type', 'Adjustment Out Reversal')->count());
        $this->post("/admin/stock-adjustments/{$a->id}/cancel", ['cancel_reason' => 'again'])->assertSessionHasErrors('stock');
        $this->artisan('inventory:check')->expectsOutputToContain('All checks passed')->assertExitCode(0);
    }

    public function test_cancelling_added_stock_is_refused_once_a_unit_was_sold_and_only_a_super_admin_may_cancel(): void
    {
        $p = $this->product('ADJ-CANCEL2');
        $this->adjust([['product_id' => $p, 'direction' => 'in', 'serials' => 'SN-K-1,SN-K-2', 'unit_cost' => 50]], ['reason' => 'found'])->assertSessionHasNoErrors();
        $a = StockAdjustment::first();

        // Someone who may cancel by permission but is not a Super Admin
        $manager = $this->userWithPermissions(['stock_adjustment.index', 'stock_adjustment.view', 'stock_adjustment.cancel']);
        $this->as($manager)->post("/admin/stock-adjustments/{$a->id}/cancel", ['cancel_reason' => 'x'])->assertSessionHasErrors('stock');
        $this->assertSame('completed', $a->fresh()->status);

        $this->postSale($this->admin, $this->patient(), [[$p, null, ['SN-K-1'], 1]])->assertSessionHasNoErrors();
        $this->as($this->admin)->post("/admin/stock-adjustments/{$a->id}/cancel", ['cancel_reason' => 'Mistake'])->assertSessionHasErrors('stock');
        $this->assertSame('completed', $a->fresh()->status);
        $this->assertEquals(1, $this->stockOf($p));
    }

    // ── effects on purchases, returns and the warranty report ──

    public function test_purchase_with_a_written_off_unit_cannot_be_deleted_and_the_unit_cannot_go_back_to_the_supplier(): void
    {
        $p = $this->product('ADJ-PUR');
        $supplier = $this->supplier();
        $this->postPurchase($this->admin, $supplier, [[$p, '', 'SN-P-1,SN-P-2', 2]])->assertSessionHasNoErrors();
        $purchaseId = (int) DB::table('purchase_masters')->orderByDesc('id')->value('id');
        $this->adjust([['product_id' => $p, 'direction' => 'out', 'serials' => 'SN-P-1']])->assertSessionHasNoErrors();

        $this->as($this->admin)->delete('/admin/purchase/' . $purchaseId, ['reason' => 'Cancelling for test'])->assertSessionHas('warning');
        $this->assertNull(DB::table('purchase_masters')->where('id', $purchaseId)->value('deleted_at'));

        $this->getJson('/admin/purchase-returns/serials/lookup?serial_no=SN-P-1')->assertStatus(422)->assertJsonFragment(['success' => false]);
    }

    public function test_warranty_report_handles_adjusted_stock_without_a_purchase_line(): void
    {
        $p = $this->product('ADJ-WARR');
        DB::table('products')->where('id', $p)->update(['warranty_inventory_months' => 27, 'warranty_sales_months' => 24]);

        // Supplier warranty started 24 months ago: its alert date (start + 24 months - 10 days) is today
        $start = now()->subMonths(24)->addDays(10)->toDateString();
        $this->adjust([
            ['product_id' => $p, 'direction' => 'in', 'serials' => 'SN-W-1', 'unit_cost' => 10, 'warranty_start_date' => $start],
            ['product_id' => $p, 'direction' => 'in', 'serials' => 'SN-W-2', 'unit_cost' => 10],
            ['product_id' => $p, 'direction' => 'in', 'batch_no' => 'W-B', 'qty' => 2, 'unit_cost' => 10, 'warranty_start_date' => $start],
        ], ['reason' => 'found'])->assertSessionHasNoErrors();

        $html = $this->get('/admin/report/warranty-expiry')->assertOk()->getContent();
        $this->assertStringContainsString('SN-W-1', $html);
        $this->assertStringNotContainsString('SN-W-2', $html, 'no warranty start date: left out of the alerts');
        $this->assertStringContainsString('W-B', $html);
    }
}
