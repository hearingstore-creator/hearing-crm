<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\DoctorLedger;
use App\Models\Ledger;
use App\Models\SaleMaster;
use App\Models\SaleReferral;
use App\Models\User;
use App\Services\ProfitStatement;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

class DualReferralTest extends TestCase
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

    private function createDualReferralSale(
        int $doctorId,
        float $doctorAmount,
        int $quackId,
        float $quackAmount,
        string $serial = 'SN-DUAL-01'
    ): SaleMaster {
        $patientId = $this->patient();
        $supplierId = $this->supplier();
        $productId = $this->product('PROD-DUAL-' . uniqid());

        $this->postPurchase($this->admin, $supplierId, [[$productId, '', $serial, 1]])->assertSessionHasNoErrors();

        $this->postSale($this->admin, $patientId, [[$productId, null, [$serial], 1]], 5000, [
            'total_amount' => 5000,
            'reference_by' => $doctorId,
            'primary_reference_amount' => $doctorAmount,
            'secondary_reference_by' => $quackId,
            'secondary_reference_amount' => $quackAmount,
            'secondary_role' => 'quack',
            'secondary_notes' => 'Escorted patient from village clinic',
        ])->assertSessionHasNoErrors();

        return SaleMaster::latest('id')->firstOrFail();
    }

    public function test_sale_creation_with_doctor_and_accompanying_quack(): void
    {
        $doc = Doctor::create([
            'name' => 'Dr. Farhan ' . uniqid(),
            'referrer_type' => Doctor::TYPE_DOCTOR,
            'status' => 1,
        ]);

        $quack = Doctor::create([
            'name' => 'Village Doctor Salam ' . uniqid(),
            'referrer_type' => Doctor::TYPE_QUACK,
            'status' => 1,
        ]);

        $sale = $this->createDualReferralSale($doc->id, 1000.00, $quack->id, 500.00, 'SN-DUAL-' . uniqid());

        // 1. SaleMaster attributes
        $this->assertSame($doc->id, $sale->reference_by);
        $this->assertEquals(1500.00, (float) $sale->reference_amount);
        $this->assertSame('settled', $sale->referral_settlement_status);

        // 2. SaleReferral records
        $referrals = SaleReferral::where('sale_master_id', $sale->id)->get();
        $this->assertCount(2, $referrals);

        $docRef = $referrals->firstWhere('doctor_id', $doc->id);
        $this->assertNotNull($docRef);
        $this->assertEquals(1000.00, (float) $docRef->commission_amount);
        $this->assertSame('doctor', $docRef->role);
        $this->assertSame('settled', $docRef->settlement_status);

        $quackRef = $referrals->firstWhere('doctor_id', $quack->id);
        $this->assertNotNull($quackRef);
        $this->assertEquals(500.00, (float) $quackRef->commission_amount);
        $this->assertSame('quack', $quackRef->role);
        $this->assertSame('settled', $quackRef->settlement_status);
        $this->assertSame('Escorted patient from village clinic', $quackRef->notes);

        // 3. Separate DoctorLedger credits
        $docLedger = DoctorLedger::where('ref_invoice_no', $sale->sale_invoice_no)
            ->where('doctor_id', $doc->id)
            ->first();
        $this->assertNotNull($docLedger);
        $this->assertEquals(1000.00, (float) $docLedger->credit);
        $this->assertEquals(0.00, (float) $docLedger->debit);

        $quackLedger = DoctorLedger::where('ref_invoice_no', $sale->sale_invoice_no)
            ->where('doctor_id', $quack->id)
            ->first();
        $this->assertNotNull($quackLedger);
        $this->assertEquals(500.00, (float) $quackLedger->credit);
        $this->assertEquals(0.00, (float) $quackLedger->debit);

        // 4. Profit statement reflects combined referral expense (৳1500)
        $date = Carbon::parse($sale->date);
        $statement = (new ProfitStatement($date->copy()->startOfMonth(), $date->copy()->endOfMonth(), 'all'))->build();
        $referralRow = collect($statement['rows'])->firstWhere('label', 'Referral commission');
        $this->assertNotNull($referralRow);
        $this->assertGreaterThanOrEqual(1500.00, (float) $referralRow['total']);
    }

    public function test_sale_with_zero_quack_commission_sets_pending_negotiation(): void
    {
        $doc = Doctor::create([
            'name' => 'Dr. Karim ' . uniqid(),
            'referrer_type' => Doctor::TYPE_DOCTOR,
            'status' => 1,
        ]);

        $quack = Doctor::create([
            'name' => 'RMP Sohel ' . uniqid(),
            'referrer_type' => Doctor::TYPE_QUACK,
            'status' => 1,
        ]);

        $sale = $this->createDualReferralSale($doc->id, 1000.00, $quack->id, 0.00, 'SN-PEND-' . uniqid());

        $this->assertSame('pending', $sale->referral_settlement_status);

        $quackRef = SaleReferral::where('sale_master_id', $sale->id)
            ->where('doctor_id', $quack->id)
            ->firstOrFail();

        $this->assertSame('pending', $quackRef->settlement_status);
        $this->assertEquals(0.00, (float) $quackRef->commission_amount);

        // Appears in pending referral negotiations list
        $response = $this->actingAs($this->admin)->get('/admin/doctors-payment');
        $response->assertOk();
        $response->assertSee('RMP Sohel');
        $response->assertSee('Accompanying Quack / RMP');
    }

    public function test_independent_settlement_of_quack_with_cash_disbursement(): void
    {
        $doc = Doctor::create([
            'name' => 'Dr. Nasim ' . uniqid(),
            'referrer_type' => Doctor::TYPE_DOCTOR,
            'status' => 1,
        ]);

        $quack = Doctor::create([
            'name' => 'Quack Jalil ' . uniqid(),
            'referrer_type' => Doctor::TYPE_QUACK,
            'status' => 1,
        ]);

        $sale = $this->createDualReferralSale($doc->id, 1200.00, $quack->id, 0.00, 'SN-SETTLE-' . uniqid());
        $this->assertSame('pending', $sale->referral_settlement_status);

        $quackRef = SaleReferral::where('sale_master_id', $sale->id)
            ->where('doctor_id', $quack->id)
            ->firstOrFail();

        // Settle Quack referral specifically via AJAX / POST
        $response = $this->actingAs($this->admin)->postJson('/admin/doctors-payment/settle/' . $sale->id, [
            'sale_referral_id' => $quackRef->id,
            'reference_amount' => 700.00,
            'action' => 'pay_now',
            'payment_method' => 'Cash',
            'details' => 'Handed ৳700 cash to Quack Jalil',
            'date' => date('Y-m-d'),
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // Quack referral is now settled
        $quackRef->refresh();
        $this->assertSame('settled', $quackRef->settlement_status);
        $this->assertEquals(700.00, (float) $quackRef->commission_amount);
        $this->assertNotNull($quackRef->doctor_payment_id);

        // SaleMaster reference_amount updated to 1900 (1200 doc + 700 quack)
        $sale->refresh();
        $this->assertEquals(1900.00, (float) $sale->reference_amount);
        $this->assertSame('settled', $sale->referral_settlement_status);

        // Quack DoctorLedger has credit 700 AND debit 700 (settled & paid)
        $quackCredit = DoctorLedger::where('ref_invoice_no', $sale->sale_invoice_no)
            ->where('doctor_id', $quack->id)
            ->where('type', 'Sales')
            ->first();
        $this->assertNotNull($quackCredit);
        $this->assertEquals(700.00, (float) $quackCredit->credit);

        $quackPayment = DoctorLedger::find($quackRef->doctor_payment_id);
        $this->assertNotNull($quackPayment);
        $this->assertSame('Payment', $quackPayment->type);
        $this->assertEquals(700.00, (float) $quackPayment->debit);
        $this->assertSame($quack->id, $quackPayment->doctor_id);

        // Company cash drawer outflow recorded
        $companyExpense = Ledger::where('ref_invoice_no', $quackPayment->ref_invoice_no)->first();
        $this->assertNotNull($companyExpense);
        $this->assertEquals(700.00, (float) $companyExpense->expense_amount);

        // Doctor's credit remains completely intact at 1200
        $docCredit = DoctorLedger::where('ref_invoice_no', $sale->sale_invoice_no)
            ->where('doctor_id', $doc->id)
            ->where('type', 'Sales')
            ->first();
        $this->assertNotNull($docCredit);
        $this->assertEquals(1200.00, (float) $docCredit->credit);
    }

    public function test_sale_edit_form_displays_dual_referrals(): void
    {
        $doc = Doctor::create([
            'name' => 'Dr. EditDoc ' . uniqid(),
            'referrer_type' => Doctor::TYPE_DOCTOR,
            'status' => 1,
        ]);

        $quack = Doctor::create([
            'name' => 'Quack EditTest ' . uniqid(),
            'referrer_type' => Doctor::TYPE_QUACK,
            'status' => 1,
        ]);

        $sale = $this->createDualReferralSale($doc->id, 1500.00, $quack->id, 750.00, 'SN-EDIT-' . uniqid());

        $response = $this->actingAs($this->admin)->get("/admin/sale/{$sale->id}/edit");
        $response->assertOk();
        $response->assertSee($doc->name);
        $response->assertSee($quack->name);
        $response->assertSee('Accompanying Referrer / Escort');
        $response->assertSee('750.00');
    }

    public function test_doctor_statement_shows_only_specific_referral_credits_for_each_party(): void
    {
        $doc = Doctor::create([
            'name' => 'Dr. StmtDoc ' . uniqid(),
            'referrer_type' => Doctor::TYPE_DOCTOR,
            'status' => 1,
        ]);

        $quack = Doctor::create([
            'name' => 'Quack StmtTest ' . uniqid(),
            'referrer_type' => Doctor::TYPE_QUACK,
            'status' => 1,
        ]);

        $sale = $this->createDualReferralSale($doc->id, 1000.00, $quack->id, 500.00, 'SN-STMT-' . uniqid());

        // 1. Doctor Statement for Doctor: shows 1000 credit
        $respDoc = $this->actingAs($this->admin)->get("/admin/report/doctor-statement?doctor_id={$doc->id}");
        $respDoc->assertOk();
        $respDoc->assertSee($sale->sale_invoice_no);
        $respDoc->assertSee('1,000.00');

        // 2. Doctor Statement for Quack: shows 500 credit
        $respQuack = $this->actingAs($this->admin)->get("/admin/report/doctor-statement?doctor_id={$quack->id}");
        $respQuack->assertOk();
        $respQuack->assertSee($sale->sale_invoice_no);
        $respQuack->assertSee('500.00');
    }
}
