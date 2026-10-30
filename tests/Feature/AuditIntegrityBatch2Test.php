<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Audit 22 Oct 2026, batch 2: editing a sale without a referring doctor (BUG-01), sale totals must be amounts (DAT-01). */
class AuditIntegrityBatch2Test extends TestCase
{
    use DatabaseTransactions, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
    }

    private function stockedSale(): array
    {
        $productId = $this->product('AUD-B2');
        $this->postPurchase($this->admin, $this->supplier(), [[$productId, 'LOT-B2', '', 5]])->assertSessionHasNoErrors();
        $batchId = (int) DB::table('batches')->where('product_id', $productId)->value('id');

        return [$productId, $batchId, $this->patient()];
    }

    public function test_a_sale_without_a_referring_doctor_can_be_edited(): void
    {
        [$productId, $batchId, $customerId] = $this->stockedSale();
        $this->postSale($this->admin, $customerId, [[$productId, $batchId, null, 1]], 0, ['total_amount' => 1000])->assertSessionHasNoErrors();
        $sale = DB::table('sale_masters')->orderByDesc('id')->first();
        $detail = DB::table('sale_details')->where('sale_master_id', $sale->id)->first();

        $this->actingAs($this->admin)->from('/admin/sale')->put("/admin/sale/{$sale->id}", [
            'customer_id' => $customerId, 'payment_type_id' => 1, 'date' => date('Y-m-d'), 'total_amount' => 1500, 'paid' => 0, 'vat' => 0, 'total_discount' => 0,
            'old' => [['id' => $detail->id, 'product_id' => $productId, 'qty' => 1, 'purchase_price' => 100, 'unit_price' => 1500, 'discount' => 0]],
            'old_batch_formatted' => json_encode([$batchId]), 'old_serial_formatted' => json_encode([null]),
            'batch_id_formatted' => '[]', 'serial_no_formatted' => '[]',
        ])->assertSessionHasNoErrors()->assertSessionHas('success')->assertSessionMissing('warning');

        $this->assertEquals(1500, (float) DB::table('sale_masters')->where('id', $sale->id)->value('total_amount'), 'the edit is saved, not rolled back');
    }

    public function test_sale_totals_must_be_amounts_of_zero_or_more(): void
    {
        [$productId, $batchId, $customerId] = $this->stockedSale();
        $before = DB::table('sale_masters')->count();

        $this->postSale($this->admin, $customerId, [[$productId, $batchId, null, 1]], 0, ['paid' => -500])->assertSessionHasErrors('paid');
        $this->postSale($this->admin, $customerId, [[$productId, $batchId, null, 1]], 0, ['total_amount' => 'abc'])->assertSessionHasErrors('total_amount');
        $this->postSale($this->admin, $customerId, [[$productId, $batchId, null, 1]], 0, ['total_discount' => -10])->assertSessionHasErrors('total_discount');
        $this->assertSame($before, DB::table('sale_masters')->count());

        $this->postSale($this->admin, $customerId, [[$productId, $batchId, null, 1]], 200)->assertSessionHasNoErrors();
        $this->assertSame($before + 1, DB::table('sale_masters')->count(), 'a normal sale still goes through');
    }
}
