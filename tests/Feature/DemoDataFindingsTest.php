<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Bugs found by filling the CRM with realistic data and using every screen. */
class DemoDataFindingsTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
    }

    public function test_general_settings_actually_save(): void
    {
        Setting::updateOrCreate(['id' => 1], ['company_name' => 'Demo Company']);

        $this->actingAs($this->admin)->put('/admin/setting/1', [
            'company_name' => 'Zenhear Hearing Care', 'mobile' => '09613-820820', 'address' => 'Dhanmondi, Dhaka', 'vat_registration_no' => 'BIN 123',
        ])->assertSessionHasNoErrors();

        $setting = Setting::find(1);
        $this->assertSame('Zenhear Hearing Care', $setting->company_name);
        $this->assertSame('Dhanmondi, Dhaka', $setting->address);
        $this->assertSame('BIN 123', $setting->vat_registration_no);
        $this->assertSame($this->admin->id, (int) $setting->updated_by, 'tracking columns still work');
    }

    public function test_models_that_use_guarded_accept_mass_assignment_and_fillable_models_stay_restricted(): void
    {
        $setting = new Setting();
        $this->assertTrue($setting->isFillable('company_name'), 'a $guarded model accepts its normal columns');
        $this->assertFalse($setting->isFillable('id'));

        // a model with an explicit $fillable list keeps its own list, plus the tracking columns
        $lead = new \App\Models\Lead();
        $this->assertTrue($lead->isFillable('full_name'));
        $this->assertTrue($lead->isFillable('created_by'));
        $this->assertFalse($lead->isFillable('deleted_at'));
    }

    public function test_manual_income_records_its_amount(): void
    {
        $head = DB::table('income_expense_heads')->insertGetId(['head_type' => 'Income', 'head_name' => 'Camp fees', 'status' => 1, 'created_at' => now()]);
        $expenseHead = DB::table('income_expense_heads')->insertGetId(['head_type' => 'Expense', 'head_name' => 'Rent', 'status' => 1, 'created_at' => now()]);

        $this->actingAs($this->admin)->post('/admin/income', ['income_expense_head_id' => $head, 'date' => '2026-09-14', 'paid_amount' => 18500, 'details' => 'Camp'])->assertSessionHasNoErrors();
        $this->assertEquals(18500, (float) DB::table('ledgers')->where('income_expense_head_id', $head)->value('income_amount'));

        // an expense head, a zero amount and a text amount are all refused
        $this->actingAs($this->admin)->post('/admin/income', ['income_expense_head_id' => $expenseHead, 'date' => '2026-09-14', 'paid_amount' => 100])->assertSessionHasErrors('income_expense_head_id');
        $this->actingAs($this->admin)->post('/admin/income', ['income_expense_head_id' => $head, 'date' => '2026-09-14', 'paid_amount' => 0])->assertSessionHasErrors('paid_amount');
        $this->assertSame(1, DB::table('ledgers')->where('amount_type', 'Income')->whereNull('type')->count());
    }

    public function test_two_supplier_payments_in_the_same_second_both_save(): void
    {
        $supplier = $this->supplier();
        $product = $this->product();
        $this->postPurchase($this->admin, $supplier, [[$product, '', 'SN-FIND-1', 1]], 0)->assertSessionHasNoErrors();

        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));
        foreach ([1, 2] as $n) {
            $this->actingAs($this->admin)->post('/admin/suppliers-payment', [
                'supplier_id' => $supplier, 'date' => '2026-10-20', 'debit' => 10, 'payment_method' => 'Cash', 'details' => "same second $n",
            ])->assertSessionHasNoErrors()->assertSessionMissing('warning');
        }
        Carbon::setTestNow();

        $this->assertSame(2, DB::table('ledgers')->where('type', 'Supplier Payment')->count());
        $this->assertSame(2, DB::table('ledgers')->where('type', 'Supplier Payment')->distinct()->count('invoice_no'));
    }

    public function test_a_branch_wide_break_can_be_saved_without_naming_a_person(): void
    {
        $this->actingAs($this->admin)->post('/admin/roster/break', [
            'vendor_id' => $this->branch->id, 'name' => 'Lunch', 'start_time' => '13:00', 'end_time' => '13:45',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('staff_breaks', ['vendor_id' => $this->branch->id, 'name' => 'Lunch', 'user_id' => null]);
    }
}
