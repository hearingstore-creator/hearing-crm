<?php

namespace Tests\Feature;

use App\Services\DashboardService;
use App\Services\SaleCost;
use App\Support\DataScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Inventory safety net: inventory:check, small product fixes, branch-safe stock value, sale cost from purchase records. */
class InventoryStabilityTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
    }

    protected function as($user)
    {
        DataScope::reset();

        return $this->actingAs($user);
    }

    protected function master(): array
    {
        return [
            'category' => DB::table('categories')->insertGetId(['category_name' => 'Hearing Aid', 'status' => 1, 'created_at' => now()]),
            'type' => DB::table('types')->insertGetId(['type_name' => 'BTE', 'status' => 1, 'created_at' => now()]),
            'brand' => DB::table('brands')->insertGetId(['brand_name' => 'Acme', 'status' => 1, 'created_at' => now()]),
            'brand2' => DB::table('brands')->insertGetId(['brand_name' => 'Zeta', 'status' => 1, 'created_at' => now()]),
        ];
    }

    protected function productForm(array $m, array $over = []): array
    {
        return array_merge(['category_id' => $m['category'], 'type_id' => $m['type'], 'brand_id' => $m['brand'], 'model' => 'Alpha 1', 'warranty_inventory_months' => 27], $over);
    }

    // ── inventory:check ──

    public function test_check_passes_on_consistent_stock_and_flags_a_stray_ledger_row(): void
    {
        $serialProduct = $this->product('CHK-SERIAL');
        $batchProduct = $this->product('CHK-BATCH');
        $supplier = $this->supplier();
        $this->postPurchase($this->admin, $supplier, [[$serialProduct, '', 'SN-CHK-1,SN-CHK-2', 2], [$batchProduct, 'B-CHK', '', 5]])->assertSessionHasNoErrors();
        $patient = $this->patient();
        $this->postSale($this->admin, $patient, [[$serialProduct, null, ['SN-CHK-1'], 1]])->assertSessionHasNoErrors();

        $this->artisan('inventory:check', ['--strict' => true])
            ->expectsOutputToContain('All checks passed')
            ->assertExitCode(0);

        // A stock row with no purchase or sale behind it
        DB::table('stocks')->insert(['product_id' => $batchProduct, 'vendor_id' => null, 'date' => today(), 'in_qty' => 3, 'out_qty' => 0, 'type' => 'Purchase', 'ref_invoice_no' => 'STRAY', 'created_at' => now()]);

        $this->artisan('inventory:check', ['--strict' => true])
            ->expectsOutputToContain('Ledger does not match batches + serials')
            ->expectsOutputToContain('CHK-BATCH')
            ->assertExitCode(1);

        // Without --strict it reports but exits 0, and it never changes data
        $before = DB::table('stocks')->count();
        $this->artisan('inventory:check')->assertExitCode(0);
        $this->assertSame($before, DB::table('stocks')->count());
    }

    public function test_check_flags_a_serial_count_that_differs_from_the_quantity(): void
    {
        $p = $this->product('CHK-LINE');
        $this->postPurchase($this->admin, $this->supplier(), [[$p, '', 'SN-L-1,SN-L-2', 2]])->assertSessionHasNoErrors();
        DB::table('purchase_details')->where('product_id', $p)->update(['qty' => 3]);

        $this->artisan('inventory:check')->expectsOutputToContain('Serial count differs from the quantity on a line')->assertExitCode(0);
    }

    // ── product fixes ──

    public function test_product_duplicate_model_is_rejected_per_brand_and_warranty_zero_is_allowed(): void
    {
        $m = $this->master();
        $this->as($this->admin);

        $this->post('/admin/product', $this->productForm($m))->assertSessionHasNoErrors();
        $this->post('/admin/product', $this->productForm($m, ['model' => 'alpha 1']))->assertSessionHasErrors('model');
        $this->post('/admin/product', $this->productForm($m, ['brand_id' => $m['brand2']]))->assertSessionHasNoErrors();

        // No supplier warranty at all
        $this->post('/admin/product', $this->productForm($m, ['model' => 'Plain Tube', 'warranty_inventory_months' => 0]))->assertSessionHasNoErrors();
        $this->assertSame(0, (int) DB::table('products')->where('model', 'Plain Tube')->value('warranty_inventory_months'));
        $this->post('/admin/product', $this->productForm($m, ['model' => 'Bad', 'warranty_inventory_months' => -1]))->assertSessionHasErrors('warranty_inventory_months');

        // Editing keeps its own name; renaming onto another product of the brand is refused
        $id = DB::table('products')->where('model', 'Alpha 1')->where('brand_id', $m['brand'])->value('id');
        $this->put('/admin/product/' . $id, $this->productForm($m, ['warranty_inventory_months' => 12]))->assertSessionHasNoErrors();
        $this->put('/admin/product/' . $id, $this->productForm($m, ['model' => 'Plain Tube']))->assertSessionHasErrors('model');
    }

    public function test_product_search_keeps_its_shape_is_limited_and_follows_branch_stock(): void
    {
        $inStock = $this->product('SRCH-IN');
        $empty = $this->product('SRCH-EMPTY');
        $this->postPurchase($this->admin, $this->supplier(), [[$inStock, 'B1', '', 4]])->assertSessionHasNoErrors();

        $this->as($this->admin);
        $sale = $this->getJson('/admin/product-search?search=SRCH&type=sale')->assertOk()->json();
        $this->assertSame(['SRCH-IN'], collect($sale)->map(fn ($r) => substr($r['text'], -7))->all());
        $this->assertEqualsCanonicalizing(['id', 'text', 'category_id'], array_keys($sale[0]));

        $purchase = $this->getJson('/admin/product-search?search=SRCH&type=purchase')->assertOk()->json();
        $this->assertCount(2, $purchase, 'purchase mode lists products without stock too');

        // A branch has none of head office's stock
        $this->as($this->branch);
        $this->assertSame([], $this->getJson('/admin/product-search?search=SRCH&type=sale')->assertOk()->json());

        // At most 50 rows
        $rows = [];
        for ($i = 0; $i < 60; $i++) {
            $rows[] = ['model' => 'BULK-' . $i, 'warranty_inventory_months' => 0, 'created_at' => now()];
        }
        DB::table('products')->insert($rows);
        $this->as($this->admin);
        $this->assertCount(50, $this->getJson('/admin/product-search?search=BULK&type=purchase')->assertOk()->json());
    }

    // ── dashboard stock value ──

    public function test_stock_value_is_branch_scoped_and_ignores_deleted_purchase_lines(): void
    {
        $p = $this->product('VAL-AID');
        $supplier = $this->supplier();
        $this->postPurchase($this->admin, $supplier, [[$p, '', 'SN-V-1,SN-V-2', 2]])->assertSessionHasNoErrors();
        $this->postPurchase($this->branch, $supplier, [[$p, '', 'SN-V-3', 1]])->assertSessionHasNoErrors();

        $value = function ($user) {
            $this->as($user);

            return (float) app(DashboardService::class)->getDashboardStatistics()['inventory']['stock_value'];
        };

        $this->assertEquals(200, $value($this->admin), 'head office: its own 2 units at cost 100');
        $this->assertEquals(100, $value($this->branch), 'a branch login sees only its own unit, not company stock');

        // Cost comes from purchase lines still in the books
        DB::table('purchase_details')->where('product_id', $p)->update(['deleted_at' => now()]);
        $this->assertEquals(0, $value($this->admin));
    }

    // ── sale cost ──

    public function test_sale_cost_shadow_mode_keeps_the_posted_cost_and_logs_the_difference(): void
    {
        $serial = $this->product('COST-SERIAL');
        $this->postPurchase($this->admin, $this->supplier(), [[$serial, '', 'SN-C-1', 1]])->assertSessionHasNoErrors();
        Log::spy();

        $this->postSale($this->admin, $this->patient(), [[$serial, null, ['SN-C-1'], 1]], 0, ['purchase_price' => [999]])->assertSessionHasNoErrors();

        $this->assertEquals(999, DB::table('sale_details')->where('product_id', $serial)->value('purchase_price'));
        Log::shouldHaveReceived('info')->withArgs(fn ($m, $ctx = []) => $m === 'Sale cost differs from purchase records' && ($ctx['database'] ?? null) == 100.0)->once();
    }

    public function test_sale_cost_server_mode_takes_serial_and_batch_cost_from_purchases(): void
    {
        config(['crm.sale_cost_mode' => 'server']);
        $serial = $this->product('COST-S2');
        $batchProduct = $this->product('COST-B2');
        $this->postPurchase($this->admin, $this->supplier(), [[$serial, '', 'SN-C-2', 1], [$batchProduct, 'B-C2', '', 5]])->assertSessionHasNoErrors();
        $batchId = (int) DB::table('batches')->where('product_id', $batchProduct)->value('id');
        $patient = $this->patient();

        $this->postSale($this->admin, $patient, [[$serial, null, ['SN-C-2'], 1]], 0, ['purchase_price' => [0]])->assertSessionHasNoErrors();
        $this->postSale($this->admin, $patient, [[$batchProduct, $batchId, null, 2]], 0, ['purchase_price' => [99999]])->assertSessionHasNoErrors();

        $this->assertEquals(100, DB::table('sale_details')->where('product_id', $serial)->value('purchase_price'));
        $this->assertEquals(100, DB::table('sale_details')->where('product_id', $batchProduct)->value('purchase_price'));
    }

    public function test_sale_cost_lookup_sources_and_fallback_to_posted_when_nothing_is_known(): void
    {
        config(['crm.sale_cost_mode' => 'server']);
        $p = $this->product('COST-NONE');

        // No purchase records at all: the posted value is kept
        $this->assertSame(55.0, SaleCost::resolve($p, null, null, 55.0));
        $this->assertSame('none', SaleCost::lookup($p, null, [])['source']);

        $this->postPurchase($this->admin, $this->supplier(), [[$p, 'B-N', '', 3]])->assertSessionHasNoErrors();
        $this->assertSame('latest', SaleCost::lookup($p, null, [])['source'], 'no batch or serial: latest purchase price');
        $this->assertSame('batch', SaleCost::lookup($p, (int) DB::table('batches')->where('product_id', $p)->value('id'), [])['source']);
    }
}
