<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Cancelling money records by reversal, doctor payments, proposals (audit Batch 4). */
class MoneyRecordsTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected int $supplierId;
    protected int $productId;
    protected int $customerId;
    protected int $doctorId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        $this->supplierId = $this->supplier();
        $this->productId = $this->product();
        $this->customerId = $this->patient('patient@example.test');
        $this->doctorId = $this->doctor();
        $this->postPurchase($this->admin, $this->supplierId, [[$this->productId, '', 'SN-1,SN-2', 2]], 40)->assertSessionHasNoErrors();
    }

    protected function saleWithCommission(): object
    {
        $this->postSale($this->admin, $this->customerId, [[$this->productId, null, ['SN-1'], 1]], 150, ['reference_by' => $this->doctorId, 'reference_amount' => 20])
            ->assertSessionHasNoErrors();

        return DB::table('sale_masters')->orderByDesc('id')->first();
    }

    public function test_sale_cancel_needs_super_admin_and_reason_and_reverses_ledgers(): void
    {
        $sale = $this->saleWithCommission();
        $url = "/admin/sale/{$sale->id}";

        $this->actingAs($this->admin)->from('/admin/sale')->delete($url)->assertSessionHasErrors('reason');
        $this->actingAs($this->branch)->delete($url, ['reason' => 'Entered twice'])->assertStatus(403);

        $this->actingAs($this->admin)->from('/admin/sale')->delete($url, ['reason' => 'Patient changed mind'])->assertSessionHas('success');

        $inv = $sale->sale_invoice_no;
        $this->assertSoftDeleted('sale_masters', ['id' => $sale->id]);
        $this->assertDatabaseHas('customer_ledgers', ['ref_invoice_no' => $inv, 'type' => 'Sales (Reversed)', 'deleted_at' => null]);
        $this->assertDatabaseHas('customer_ledgers', ['ref_invoice_no' => $inv, 'type' => 'Sales Reversal', 'deleted_at' => null]);
        $this->assertEqualsWithDelta(0, $this->partyNet('customer_ledgers', $inv), 0.01);
        $this->assertEqualsWithDelta(0, $this->partyNet('doctor_ledgers', $inv), 0.01);
        $this->assertEqualsWithDelta(0, $this->companyNet($inv), 0.01);
        $this->assertTrue(DB::table('activity_logs')->where('action', 'sale_cancelled')->where('new_values', 'like', '%Patient changed mind%')->exists());
    }

    public function test_sale_cancel_also_reverses_due_collected_later(): void
    {
        $this->postSale($this->admin, $this->customerId, [[$this->productId, null, ['SN-1'], 1]], 80)->assertSessionHasNoErrors();
        $sale = DB::table('sale_masters')->orderByDesc('id')->first();
        $inv = $sale->sale_invoice_no;

        $this->actingAs($this->admin)->from('/admin/sales/due-list')->post('/admin/sales/collect-due', [
            'sale_invoice_no' => $inv, 'paid_amount' => 120, 'date' => date('Y-m-d'),
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('customer_ledgers', ['ref_invoice_no' => $inv, 'type' => 'Receive', 'credit' => 120]);
        $this->assertEqualsWithDelta(200, $this->companyNet($inv), 0.01);

        $this->actingAs($this->admin)->from('/admin/sale')->delete("/admin/sale/{$sale->id}", ['reason' => 'Entered for wrong patient'])
            ->assertSessionHas('success');

        // Neither the first payment nor the later collection stays as income or as patient credit
        $this->assertEqualsWithDelta(0, $this->partyNet('customer_ledgers', $inv), 0.01);
        $this->assertEqualsWithDelta(0, $this->companyNet($inv), 0.01);
        $this->assertDatabaseHas('customer_ledgers', ['ref_invoice_no' => $inv, 'type' => 'Receive (Reversed)']);
        $this->assertDatabaseHas('ledgers', ['ref_invoice_no' => $inv, 'type' => 'Receive Reversal', 'income_amount' => -120]);
    }

    public function test_purchase_and_supplier_payment_cancel_reverse_ledgers(): void
    {
        $this->postPurchase($this->admin, $this->supplierId, [[$this->productId, '', 'SN-9', 1]], 40)->assertSessionHasNoErrors();
        $purchase = DB::table('purchase_masters')->orderByDesc('id')->first();
        $this->actingAs($this->admin)->from('/admin/purchase')->delete("/admin/purchase/{$purchase->id}", ['reason' => 'Wrong supplier'])
            ->assertSessionHas('success');
        $this->assertEqualsWithDelta(0, $this->partyNet('supplier_ledgers', $purchase->purchase_invoice_no), 0.01);
        $this->assertEqualsWithDelta(0, $this->companyNet($purchase->purchase_invoice_no), 0.01);

        $paymentId = DB::table('supplier_ledgers')->insertGetId(['supplier_id' => $this->supplierId, 'date' => date('Y-m-d'), 'details' => 'Payment', 'debit' => 50, 'credit' => 0, 'type' => 'Payment', 'ref_invoice_no' => 'SP-T-1', 'created_at' => now()]);
        DB::table('ledgers')->insert(['income_expense_head_id' => 1, 'invoice_no' => 'T-SP-1', 'expense_amount' => 50, 'income_amount' => 0, 'amount_type' => 'Expense', 'date' => date('Y-m-d'), 'type' => 'Supplier Payment', 'ref_invoice_no' => 'SP-T-1', 'created_at' => now()]);
        $this->actingAs($this->admin)->from('/admin/suppliers-payment')->delete("/admin/suppliers-payment/$paymentId", ['reason' => 'Cheque bounced'])
            ->assertSessionHas('success');
        $this->assertSame('Payment (Reversed)', DB::table('supplier_ledgers')->where('id', $paymentId)->value('type'));
        $this->assertEqualsWithDelta(0, $this->partyNet('supplier_ledgers', 'SP-T-1'), 0.01);
        $this->assertEqualsWithDelta(0, $this->companyNet('SP-T-1'), 0.01);
    }

    public function test_doctor_payment_cannot_exceed_due_and_cancel_restores_due(): void
    {
        $this->saleWithCommission();
        $due = fn () => (float) DB::table('doctor_ledgers')->where('doctor_id', $this->doctorId)->whereNull('deleted_at')->selectRaw('SUM(credit)-SUM(debit) as d')->value('d');
        $pay = fn ($amount) => $this->actingAs($this->admin)->from('/admin/doctors-payment/create')->post('/admin/doctors-payment', ['doctor_id' => $this->doctorId, 'date' => date('Y-m-d'), 'debit' => $amount, 'payment_method' => 'Cash']);

        $pay(25)->assertSessionHasErrors('debit');
        $pay(15)->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(5, $due(), 0.01);

        $paymentId = (int) DB::table('doctor_ledgers')->where('type', 'Payment')->value('id');
        $this->actingAs($this->admin)->from('/admin/doctors-payment')->delete("/admin/doctors-payment/$paymentId", ['reason' => 'Paid twice'])->assertSessionHas('success');
        $this->assertEqualsWithDelta(20, $due(), 0.01);

        $this->actingAs($this->admin)->from('/admin/doctor')->delete("/admin/doctor/{$this->doctorId}")
            ->assertSessionHas('warning', fn ($w) => str_contains($w, 'Inactive'));
        $this->assertDatabaseHas('doctors', ['id' => $this->doctorId, 'deleted_at' => null]);
    }

    public function test_proposal_is_converted_only_when_sale_is_saved_and_email_goes_to_patient(): void
    {
        $leadId = (int) DB::table('customers')->where('id', $this->customerId)->value('lead_id');
        $proposalId = DB::table('proposals')->insertGetId(['title' => 'Quote', 'proposal_no' => 'PRP-T-1', 'lead_id' => $leadId, 'customer_id' => $this->customerId, 'status' => 'Sent', 'created_at' => now()]);

        $this->actingAs($this->admin)->post("/admin/proposal/$proposalId/convert-to-sale")->assertRedirect();
        $this->assertSame('Sent', DB::table('proposals')->where('id', $proposalId)->value('status'));

        $this->postSale($this->admin, $this->customerId, [[$this->productId, null, ['SN-2'], 1]], 200, ['from_proposal_id' => $proposalId])->assertSessionHasNoErrors();
        $this->assertSame('Converted', DB::table('proposals')->where('id', $proposalId)->value('status'));

        Mail::fake();
        $this->actingAs($this->admin)->postJson("/admin/proposal/$proposalId/email", ['email' => 'someone@else.test', 'message' => 'Hello'])
            ->assertOk()->assertJsonFragment(['success' => true])
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'patient@example.test') && !str_contains($m, 'else.test'));
    }
}
