<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\IncomeExpenseHead;
use App\Models\Ledger;
use App\Models\PaymentReconciliation;
use App\Models\SaleMaster;
use App\Models\User;
use App\Services\ProfitStatement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

class PaymentReconciliationTest extends TestCase
{
    use DatabaseTransactions, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        if (! User::where('email', 'admin@gmail.com')->exists()) {
            $this->seed(\Database\Seeders\AclSeeder::class);
        }
        $this->admin = User::where('email', 'admin@gmail.com')->firstOrFail();
    }

    private function createDigitalSale(int $paymentId, float $amount, string $serial, array $extra = []): SaleMaster
    {
        $patientId = $this->patient();
        $supplierId = $this->supplier();
        $productId = $this->product('TEST-REC-' . uniqid());

        $this->postPurchase($this->admin, $supplierId, [[$productId, '', $serial, 1]])->assertSessionHasNoErrors();

        $this->postSale($this->admin, $patientId, [[$productId, null, [$serial], 1]], $amount, array_merge([
            'payment_type_id' => $paymentId,
            'paid'            => $amount,
            'total_amount'    => $amount,
        ], $extra))->assertSessionHasNoErrors();

        return SaleMaster::latest('id')->firstOrFail();
    }

    public function test_cash_sale_sets_not_applicable_gateway_status(): void
    {
        $sale = $this->createDigitalSale(1, 1000, 'SN-CASH-REC-1');
        $this->assertSame('not_applicable', $sale->gateway_settlement_status);
        $this->assertEquals(0, (float) $sale->gateway_charge_amount);
    }

    public function test_card_and_bkash_sales_set_pending_gateway_status(): void
    {
        $cardSale = $this->createDigitalSale(5, 5000, 'SN-CARD-REC-1', [
            'payment_trx_no'    => 'APPR-09418',
            'pos_terminal_name' => 'City Bank POS',
        ]);
        $this->assertSame('pending', $cardSale->gateway_settlement_status);
        $this->assertSame('APPR-09418', $cardSale->payment_trx_no);
        $this->assertSame('City Bank POS', $cardSale->pos_terminal_name);

        $bkashSale = $this->createDigitalSale(6, 3000, 'SN-BKASH-REC-1', [
            'payment_trx_no'    => 'TRX-BK-8891',
            'pos_terminal_name' => 'bKash Merchant QR',
        ]);
        $this->assertSame('pending', $bkashSale->gateway_settlement_status);
    }

    public function test_pending_sales_appear_in_reconciliation_hub(): void
    {
        $sale = $this->createDigitalSale(5, 8000, 'SN-HUB-REC-1');

        $response = $this->actingAs($this->admin)->get('/admin/payment-reconciliation');
        $response->assertStatus(200);
        $response->assertSee($sale->sale_invoice_no);
        $response->assertSee('Pending Settlements');
        $response->assertSee('POS Card');
    }

    public function test_settle_single_deducts_fee_and_posts_to_general_ledger(): void
    {
        $sale = $this->createDigitalSale(6, 10000, 'SN-SINGLE-SETTLE-1', [
            'payment_trx_no' => 'BK-TRX-1001',
        ]);
        $this->assertSame('pending', $sale->gateway_settlement_status);

        $response = $this->actingAs($this->admin)->post("/admin/payment-reconciliation/settle-single/{$sale->id}", [
            'settlement_date' => date('Y-m-d'),
            'fee_amount'      => 185.00, // 1.85%
            'fee_rate'        => 1.85,
            'batch_no'        => 'BK-SETTLE-01',
            'notes'           => 'bKash merchant auto settlement',
        ]);

        $response->assertRedirect(route('payment-reconciliation.index', ['tab' => 'history']));
        $response->assertSessionHas('success');

        $sale->refresh();
        $this->assertSame('reconciled', $sale->gateway_settlement_status);
        $this->assertEquals(185.00, (float) $sale->gateway_charge_amount);
        $this->assertEquals(9815.00, (float) $sale->gateway_net_amount);
        $this->assertNotNull($sale->gateway_settlement_id);

        // Assert Company General Ledger has expense entry for gateway fee
        $this->assertDatabaseHas('ledgers', [
            'amount_type'    => 'Expense',
            'type'           => 'Expense',
            'expense_amount' => 185.00,
            'ref_invoice_no' => $sale->sale_invoice_no,
        ]);

        // Assert Customer ledger still has full 10,000 payment (patient is not charged the fee)
        $this->assertDatabaseHas('customer_ledgers', [
            'customer_id'    => $sale->customer_id,
            'ref_invoice_no' => $sale->sale_invoice_no,
            'credit'         => 10000.00,
        ]);
    }

    public function test_settle_batch_from_pos_terminal_slip_pro_rates_fee(): void
    {
        $sale1 = $this->createDigitalSale(5, 4000, 'SN-BATCH-S1');
        $sale2 = $this->createDigitalSale(5, 6000, 'SN-BATCH-S2');

        $response = $this->actingAs($this->admin)->post('/admin/payment-reconciliation/settle-batch', [
            'sale_ids'          => [$sale1->id, $sale2->id],
            'settlement_date'   => date('Y-m-d'),
            'batch_no'          => 'BATCH-TERM-0042',
            'pos_terminal_name' => 'City Bank POS Terminal #1',
            'channel'           => 'card',
            'batch_fee'         => 150.00, // 1.5% of 10,000
            'notes'             => 'Evening terminal closing batch',
        ]);

        $response->assertRedirect(route('payment-reconciliation.index', ['tab' => 'history']));
        $response->assertSessionHas('success');

        $sale1->refresh();
        $sale2->refresh();

        $this->assertSame('reconciled', $sale1->gateway_settlement_status);
        $this->assertSame('reconciled', $sale2->gateway_settlement_status);

        // Pro-rated: 4,000 is 40% -> 60 fee, 6,000 is 60% -> 90 fee
        $this->assertEquals(60.00, (float) $sale1->gateway_charge_amount);
        $this->assertEquals(3940.00, (float) $sale1->gateway_net_amount);

        $this->assertEquals(90.00, (float) $sale2->gateway_charge_amount);
        $this->assertEquals(5910.00, (float) $sale2->gateway_net_amount);

        // Single ledger entry for batch fee of 150
        $this->assertDatabaseHas('ledgers', [
            'amount_type'    => 'Expense',
            'type'           => 'Expense',
            'expense_amount' => 150.00,
        ]);
    }

    public function test_profit_statement_includes_gateway_charges_in_operating_expenses(): void
    {
        $sale = $this->createDigitalSale(5, 20000, 'SN-PROFIT-GATEWAY-1');

        $this->actingAs($this->admin)->post("/admin/payment-reconciliation/settle-single/{$sale->id}", [
            'settlement_date' => date('Y-m-d'),
            'fee_amount'      => 300.00,
        ]);

        $statement = (new ProfitStatement(now()->startOfMonth(), now()->endOfMonth()))->build();
        $row = collect($statement['rows'])->firstWhere('label', 'Payment Gateway & Bank Charges');

        $this->assertNotNull($row, 'Payment Gateway & Bank Charges row exists in Profit Statement');
        $this->assertGreaterThanOrEqual(300.00, (float) $row['total']);
    }

    public function test_rollback_reconciliation_resets_sales_and_removes_ledger(): void
    {
        $sale = $this->createDigitalSale(5, 12000, 'SN-ROLLBACK-1');

        $this->actingAs($this->admin)->post("/admin/payment-reconciliation/settle-single/{$sale->id}", [
            'settlement_date' => date('Y-m-d'),
            'fee_amount'      => 180.00,
        ]);

        $rec = PaymentReconciliation::latest('id')->firstOrFail();
        $ledgerId = $rec->ledger_id;

        // Super Admin rolls back
        $response = $this->actingAs($this->admin)->delete("/admin/payment-reconciliation/{$rec->id}");
        $response->assertSessionHas('success');

        $sale->refresh();
        $this->assertSame('pending', $sale->gateway_settlement_status);
        $this->assertEquals(0, (float) $sale->gateway_charge_amount);
        $this->assertEquals(0, (float) $sale->gateway_net_amount);
        $this->assertNull($sale->gateway_settlement_id);

        // Ledger row should be deleted
        $this->assertDatabaseMissing('ledgers', ['id' => $ledgerId]);
    }
}
