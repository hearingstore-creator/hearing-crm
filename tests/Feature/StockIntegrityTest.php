<?php

namespace Tests\Feature;

use App\Classes\Helper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Sale / purchase stock and serial checks (audit Batch 2). */
class StockIntegrityTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected int $supplierId;
    protected int $serialProduct;
    protected int $batchProduct;
    protected int $customerId;
    protected int $batchId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        $this->supplierId = $this->supplier();
        $this->serialProduct = $this->product('TEST-AID');
        $this->batchProduct = $this->product('TEST-BATTERY');
        $this->customerId = $this->patient();

        $this->postPurchase($this->admin, $this->supplierId, [
            [$this->serialProduct, '', 'SN-A, SN-B', 2],
            [$this->batchProduct, 'LOT-1', '', 5],
        ])->assertSessionHasNoErrors();
        $this->batchId = (int) DB::table('batches')->where('product_id', $this->batchProduct)->value('id');
    }

    public function test_purchase_rejects_serial_already_in_stock_in_any_case(): void
    {
        $this->postPurchase($this->admin, $this->supplierId, [[$this->serialProduct, '', 'sn-a', 1]])
            ->assertSessionHasErrors('stock');
    }

    public function test_purchase_rejects_repeated_serials_and_count_mismatch(): void
    {
        $this->postPurchase($this->admin, $this->supplierId, [[$this->serialProduct, '', 'SN-Q,SN-Q', 2]])
            ->assertSessionHasErrors('stock');
        $this->postPurchase($this->admin, $this->supplierId, [[$this->serialProduct, '', 'SN-X,SN-Y', 3]])
            ->assertSessionHasErrors('stock');
    }

    public function test_serial_cannot_be_sold_twice(): void
    {
        $this->postSale($this->admin, $this->customerId, [[$this->serialProduct, null, ['SN-A'], 1]])
            ->assertSessionHasNoErrors();
        $this->postSale($this->admin, $this->customerId, [[$this->serialProduct, null, ['SN-A'], 1]])
            ->assertSessionHasErrors('stock');
        $this->postSale($this->admin, $this->customerId, [[$this->serialProduct, null, ['NEVER-BOUGHT'], 1]])
            ->assertSessionHasErrors('stock');
    }

    public function test_batch_cannot_be_oversold_and_edit_respects_stock(): void
    {
        $this->postSale($this->admin, $this->customerId, [[$this->batchProduct, $this->batchId, null, 6]])
            ->assertSessionHasErrors('stock');
        $this->postSale($this->admin, $this->customerId, [[$this->batchProduct, $this->batchId, null, 3]])
            ->assertSessionHasNoErrors();
        $this->assertEquals(3, (float) DB::table('batches')->where('id', $this->batchId)->value('sale_qty'));

        $saleId = $this->lastId('sale_masters');
        $detail = DB::table('sale_details')->where('sale_master_id', $saleId)->first();
        $edit = fn ($qty) => $this->actingAs($this->admin)->from('/admin/sale')->put("/admin/sale/$saleId", [
            'customer_id' => $this->customerId, 'payment_type_id' => 1, 'date' => date('Y-m-d'),
            'total_amount' => 200, 'paid' => 0, 'vat' => 0, 'total_discount' => 0,
            'old' => [['id' => $detail->id, 'product_id' => $this->batchProduct, 'qty' => $qty, 'purchase_price' => 100, 'unit_price' => 200, 'discount' => 0]],
            'old_batch_formatted' => json_encode([$this->batchId]), 'old_serial_formatted' => json_encode([null]),
            'batch_id_formatted' => '[]', 'serial_no_formatted' => '[]',
        ]);

        $edit(6)->assertSessionHasErrors('stock');
        $edit(5)->assertSessionHasNoErrors();
        $this->assertEquals(5, (float) DB::table('batches')->where('id', $this->batchId)->value('sale_qty'));
        $edit(2)->assertSessionHasNoErrors();
        $this->assertEquals(2, (float) DB::table('batches')->where('id', $this->batchId)->value('sale_qty'));
    }

    public function test_purchase_with_sold_units_cannot_be_cancelled_or_stripped(): void
    {
        $purchaseId = (int) DB::table('purchase_masters')->min('id');
        $this->postSale($this->admin, $this->customerId, [[$this->serialProduct, null, ['SN-A'], 1]])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->from('/admin/purchase')->delete("/admin/purchase/$purchaseId", ['reason' => 'Test cancel'])
            ->assertSessionHas('warning', fn ($w) => str_contains($w, 'SN-A'));

        $details = DB::table('purchase_details')->where('purchase_master_id', $purchaseId)->orderBy('id')->get();
        $this->actingAs($this->admin)->from('/admin/purchase')->put("/admin/purchase/$purchaseId", [
            'supplier_id' => $this->supplierId, 'payment_type_id' => 4, 'date' => date('Y-m-d'), 'total_amount' => 100, 'paid' => 0,
            'old' => [
                ['id' => $details[0]->id, 'product_id' => $this->serialProduct, 'batch_id' => '', 'batch_no' => '', 'serial_no' => 'SN-B', 'qty' => 1, 'purchase_price' => 100, 'mrp_price' => 200],
                ['id' => $details[1]->id, 'product_id' => $this->batchProduct, 'batch_id' => $this->batchId, 'batch_no' => 'LOT-1', 'serial_no' => '', 'qty' => 5, 'purchase_price' => 100, 'mrp_price' => 200],
            ],
        ])->assertSessionHasErrors('stock');
    }

    public function test_returned_serial_can_be_sold_again_once(): void
    {
        $this->postSale($this->admin, $this->customerId, [[$this->serialProduct, null, ['SN-A'], 1]])->assertSessionHasNoErrors();
        $saleId = $this->lastId('sale_masters');
        $returnId = DB::table('sale_returns')->insertGetId(['credit_note_no' => 'CN-T-1', 'sale_master_id' => $saleId, 'customer_id' => $this->customerId, 'date' => date('Y-m-d'), 'status' => 'completed', 'created_at' => now()]);
        DB::table('sale_return_details')->insert(['sale_return_id' => $returnId, 'item_type' => 'product', 'product_id' => $this->serialProduct, 'serial_no' => json_encode(['SN-A']), 'sold_qty' => 1, 'return_qty' => 1, 'created_at' => now()]);

        $this->actingAs($this->admin);
        $this->assertContains('SN-A', array_column(Helper::getAvailableSerialNumbers($this->serialProduct), 'serial_number'));
        $this->postSale($this->admin, $this->customerId, [[$this->serialProduct, null, ['SN-A'], 1]])->assertSessionHasNoErrors();
        $this->assertNotContains('SN-A', array_column(Helper::getAvailableSerialNumbers($this->serialProduct), 'serial_number'));

        // the first sale has a credit note now, so it cannot be cancelled
        $this->actingAs($this->admin)->from('/admin/sale')->delete("/admin/sale/$saleId", ['reason' => 'Test cancel'])
            ->assertSessionHas('warning', fn ($w) => str_contains($w, 'CN-T-1'));
    }

    public function test_cancelled_sale_returns_advance_and_batch_stock(): void
    {
        $advanceId = DB::table('customer_advances')->insertGetId(['receipt_no' => 'ADV-T-1', 'customer_id' => $this->customerId, 'date' => date('Y-m-d'), 'amount' => 50, 'applied_amount' => 0, 'refunded_amount' => 0, 'balance' => 50, 'status' => 'active', 'created_at' => now()]);
        $this->postSale($this->admin, $this->customerId, [[$this->batchProduct, $this->batchId, null, 1]], 30, ['applied_advance_id' => $advanceId, 'applied_advance_amount' => 20])
            ->assertSessionHasNoErrors();
        $sale = DB::table('sale_masters')->orderByDesc('id')->first();

        $this->assertEquals(30.0, (float) DB::table('customer_advances')->where('id', $advanceId)->value('balance'));
        $this->assertEqualsWithDelta(10, (float) DB::table('customer_ledgers')->where('ref_invoice_no', $sale->sale_invoice_no)->sum('credit'), 0.01, 'only cash is credited, not the advance');

        $this->actingAs($this->admin)->from('/admin/sale')->delete("/admin/sale/{$sale->id}", ['reason' => 'Entered by mistake'])
            ->assertSessionHas('success');

        $advance = DB::table('customer_advances')->find($advanceId);
        $this->assertEquals(50.0, (float) $advance->balance);
        $this->assertSame('active', $advance->status);
        $this->assertEquals(0, (float) DB::table('batches')->where('id', $this->batchId)->value('sale_qty'));
    }
}
