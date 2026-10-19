<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\DoctorLedger;
use App\Models\Ledger;
use App\Models\SaleMaster;
use App\Models\User;
use App\Services\ProfitStatement;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

class ReferralSettlementTest extends TestCase
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

    private function createSaleWithReferral(int $docId, float $refAmount, string $serial): SaleMaster
    {
        $patientId = $this->patient();
        $supplierId = $this->supplier();
        $productId = $this->product('TEST-HA-' . uniqid());

        $this->postPurchase($this->admin, $supplierId, [[$productId, '', $serial, 1]])->assertSessionHasNoErrors();

        $this->postSale($this->admin, $patientId, [[$productId, null, [$serial], 1]], 200, [
            'reference_by' => $docId,
            'reference_amount' => $refAmount,
        ])->assertSessionHasNoErrors();

        return SaleMaster::latest('id')->firstOrFail();
    }

    public function test_doctor_types_can_be_created_and_have_correct_labels(): void
    {
        $doc = Doctor::create([
            'name' => 'Dr. Rahman ' . uniqid(),
            'referrer_type' => Doctor::TYPE_DOCTOR,
            'status' => 1,
        ]);
        $this->assertSame('Qualified Doctor / ENT', $doc->referrer_type_label);
        $this->assertSame('badge-primary', $doc->referrer_badge_class);

        $quack = Doctor::create([
            'name' => 'Palli Chikitshok Karim ' . uniqid(),
            'referrer_type' => Doctor::TYPE_QUACK,
            'status' => 1,
        ]);
        $this->assertSame('Village Doctor / Quack / RMP', $quack->referrer_type_label);
        $this->assertSame('badge-info', $quack->referrer_badge_class);

        $agent = Doctor::create([
            'name' => 'Broker Rafiq ' . uniqid(),
            'referrer_type' => Doctor::TYPE_AGENT,
            'status' => 1,
        ]);
        $this->assertSame('RF / Referral Agent', $agent->referrer_type_label);
        $this->assertSame('badge-warning', $agent->referrer_badge_class);
    }

    public function test_sale_creation_with_zero_commission_sets_pending_negotiation(): void
    {
        $docId = $this->doctor('Dr Test Pending');
        $sale = $this->createSaleWithReferral($docId, 0, 'SN-PEND-01');

        $this->assertSame((int) $docId, (int) $sale->reference_by);
        $this->assertEquals(0, (float) $sale->reference_amount);
        $this->assertSame('pending', $sale->referral_settlement_status);

        // Doctor ledger should NOT have commission yet
        $this->assertDatabaseMissing('doctor_ledgers', [
            'ref_invoice_no' => $sale->sale_invoice_no,
            'doctor_id' => $docId,
        ]);
    }

    public function test_sale_creation_with_upfront_commission_sets_settled(): void
    {
        $docId = $this->doctor('Dr Test Settled Upfront');
        $sale = $this->createSaleWithReferral($docId, 500, 'SN-SETT-01');

        $this->assertSame('settled', $sale->referral_settlement_status);
        $this->assertEquals(500, (float) $sale->reference_amount);

        // Doctor ledger should have credit entry
        $this->assertDatabaseHas('doctor_ledgers', [
            'ref_invoice_no' => $sale->sale_invoice_no,
            'doctor_id' => $docId,
            'credit' => 500,
        ]);
    }

    public function test_pending_referral_sales_appear_in_doctor_payment_index(): void
    {
        $docId = $this->doctor('Dr Test Pending Index');
        $sale = $this->createSaleWithReferral($docId, 0, 'SN-PIDX-01');

        $response = $this->actingAs($this->admin)->get('/admin/doctors-payment');
        $response->assertStatus(200);
        $response->assertSee($sale->sale_invoice_no);
        $response->assertSee('Dr Test Pending Index');
        $response->assertSee('Pending Negotiations');
    }

    public function test_settle_referral_with_pay_now_creates_ledgers_and_cash_outflow(): void
    {
        $docId = $this->doctor('Dr Settle Cash');
        $sale = $this->createSaleWithReferral($docId, 0, 'SN-CASH-01');
        $this->assertSame('pending', $sale->referral_settlement_status);

        $response = $this->actingAs($this->admin)->post('/admin/doctors-payment/settle/' . $sale->id, [
            'reference_amount' => 1200,
            'action' => 'pay_now',
            'payment_method' => 'Cash',
            'details' => 'Negotiated at day close',
        ]);

        $payment = DoctorLedger::where('doctor_id', $docId)->where('type', 'Payment')->latest('id')->firstOrFail();
        $response->assertRedirect(route('doctors.payment.slip', $payment->id));
        $response->assertSessionHas('success');

        $sale->refresh();
        $this->assertEquals(1200, (float) $sale->reference_amount);
        $this->assertSame('settled', $sale->referral_settlement_status);

        // DoctorLedger credit entry (commission earned for sale invoice)
        $this->assertDatabaseHas('doctor_ledgers', [
            'ref_invoice_no' => $sale->sale_invoice_no,
            'doctor_id' => $docId,
            'credit' => 1200,
            'debit' => 0,
        ]);

        // DoctorLedger debit entry (cash payout voucher)
        $this->assertDatabaseHas('doctor_ledgers', [
            'doctor_id' => $docId,
            'type' => 'Payment',
            'debit' => 1200,
            'credit' => 0,
        ]);

        // Company ledger expense entry
        $this->assertDatabaseHas('ledgers', [
            'type' => 'Doctor Payment',
            'expense_amount' => 1200,
            'income_amount' => 0,
        ]);
    }

    public function test_settle_referral_with_add_to_due_creates_credit_only(): void
    {
        $docId = $this->doctor('Dr Settle Due');
        $sale = $this->createSaleWithReferral($docId, 0, 'SN-DUE-01');

        $response = $this->actingAs($this->admin)->post('/admin/doctors-payment/settle/' . $sale->id, [
            'reference_amount' => 1800,
            'action' => 'add_to_due',
            'details' => 'Will pay at end of month',
        ]);

        $response->assertRedirect('/admin/doctors-payment');

        $sale->refresh();
        $this->assertEquals(1800, (float) $sale->reference_amount);
        $this->assertSame('settled', $sale->referral_settlement_status);

        // DoctorLedger credit entry only
        $this->assertDatabaseHas('doctor_ledgers', [
            'ref_invoice_no' => $sale->sale_invoice_no,
            'doctor_id' => $docId,
            'credit' => 1800,
            'debit' => 0,
        ]);

        // No DoctorLedger debit entry for 1800
        $this->assertDatabaseMissing('doctor_ledgers', [
            'doctor_id' => $docId,
            'debit' => 1800,
        ]);

        // Company ledger cash outflow should NOT be created
        $this->assertDatabaseMissing('ledgers', [
            'expense_amount' => 1800,
        ]);
    }

    public function test_profit_statement_deducts_settled_referral_commission(): void
    {
        $docId = $this->doctor('Dr Settle Profit');
        $sale = $this->createSaleWithReferral($docId, 0, 'SN-PROF-01');

        $this->actingAs($this->admin)->post('/admin/doctors-payment/settle/' . $sale->id, [
            'reference_amount' => 500,
            'action' => 'pay_now',
            'payment_method' => 'Cash',
        ]);

        $this->actingAs($this->admin);
        $statement = (new ProfitStatement(now()->startOfMonth(), now()->endOfMonth()))->build();
        $row = collect($statement['rows'])->firstWhere('label', 'Referral commission');

        $this->assertNotNull($row, 'Referral commission row exists');
        $this->assertEquals(500, (float) $row['total']);
    }

    public function test_doctor_statement_filters_summary_by_category(): void
    {
        $doc = Doctor::create([
            'name' => 'Dr Category Filter Doc ' . uniqid(),
            'referrer_type' => Doctor::TYPE_DOCTOR,
            'status' => 1,
        ]);
        $agent = Doctor::create([
            'name' => 'Agent Category Filter ' . uniqid(),
            'referrer_type' => Doctor::TYPE_AGENT,
            'status' => 1,
        ]);

        $this->createSaleWithReferral($doc->id, 500, 'SN-CAT-DOC-1');
        $this->createSaleWithReferral($agent->id, 800, 'SN-CAT-AGT-1');

        // Test filtering by agent
        $response = $this->actingAs($this->admin)->get('/admin/report/doctor-statement?referrer_type=agent');
        $response->assertStatus(200);
        $summary = $response->viewData('summary');
        $this->assertTrue($summary->contains('id', $agent->id));
        $this->assertFalse($summary->contains('id', $doc->id));

        // Test filtering by qualified doctor
        $responseDoc = $this->actingAs($this->admin)->get('/admin/report/doctor-statement?referrer_type=doctor');
        $responseDoc->assertStatus(200);
        $summaryDoc = $responseDoc->viewData('summary');
        $this->assertTrue($summaryDoc->contains('id', $doc->id));
        $this->assertFalse($summaryDoc->contains('id', $agent->id));
    }

    public function test_doctor_statement_summary_csv_export_contains_category(): void
    {
        $agent = Doctor::create([
            'name' => 'Agent CSV Export ' . uniqid(),
            'referrer_type' => Doctor::TYPE_AGENT,
            'status' => 1,
        ]);
        $this->createSaleWithReferral($agent->id, 650, 'SN-CSV-AGT-1');

        $response = $this->actingAs($this->admin)->get('/admin/report/doctor-statement?referrer_type=agent&export=csv');
        $response->assertStatus(200);
        $this->assertStringContainsString('Category', $response->streamedContent());
        $this->assertStringContainsString('RF / Referral Agent', $response->streamedContent());
        $this->assertStringContainsString('Agent CSV Export', $response->streamedContent());
    }
}
