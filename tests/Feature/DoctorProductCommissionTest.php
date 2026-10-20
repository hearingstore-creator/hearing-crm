<?php

namespace Tests\Feature;

use App\Classes\Helper;
use App\Models\Doctor;
use App\Models\DoctorProductCommission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** A doctor's commission can be set for one model: it beats the category rate, which beats the default. */
class DoctorProductCommissionTest extends TestCase
{
    use DatabaseTransactions, CrmFixtures;

    protected int $aids;
    protected int $batteries;
    protected int $modelA;
    protected int $modelB;
    protected int $battery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();

        $this->aids = DB::table('categories')->insertGetId(['category_name' => 'Hearing Aids', 'status' => 1, 'created_at' => now()]);
        $this->batteries = DB::table('categories')->insertGetId(['category_name' => 'Batteries', 'status' => 1, 'created_at' => now()]);
        $this->modelA = $this->product('Model A');
        $this->modelB = $this->product('Model B');
        $this->battery = $this->product('Battery 312');
        DB::table('products')->where('id', $this->modelA)->update(['category_id' => $this->aids]);
        DB::table('products')->where('id', $this->modelB)->update(['category_id' => $this->aids]);
        DB::table('products')->where('id', $this->battery)->update(['category_id' => $this->batteries]);
    }

    protected function doctorWithRules(): Doctor
    {
        $doctor = Doctor::create(['name' => 'Dr Rules', 'status' => 1, 'product_commission_percent' => 5]);
        DB::table('doctor_category_commissions')->insert(['doctor_id' => $doctor->id, 'category_id' => $this->aids, 'commission_type' => 'percent', 'commission_value' => 10, 'created_at' => now()]);
        DoctorProductCommission::create(['doctor_id' => $doctor->id, 'product_id' => $this->modelA, 'commission_type' => 'fixed', 'commission_value' => 3000]);

        return $doctor;
    }

    public function test_model_rule_beats_category_rule_beats_default(): void
    {
        $doctor = $this->doctorWithRules();

        $amount = Helper::calculateDoctorCommission($doctor->id, [
            ['product_id' => $this->modelA, 'qty' => 2, 'line_total' => 100000],   // model rule: 3,000 per unit
            ['product_id' => $this->modelB, 'qty' => 1, 'line_total' => 50000],    // category rule: 10%
            ['product_id' => $this->battery, 'qty' => 1, 'line_total' => 20000],   // default: 5%
        ]);

        $this->assertEquals(6000 + 5000 + 1000, $amount);
    }

    public function test_a_percent_model_rule_works_on_the_line_value(): void
    {
        $doctor = Doctor::create(['name' => 'Dr Percent', 'status' => 1]);
        DoctorProductCommission::create(['doctor_id' => $doctor->id, 'product_id' => $this->modelA, 'commission_type' => 'percent', 'commission_value' => 3]);

        $this->assertEquals(300, Helper::calculateDoctorCommission($doctor->id, [['product_id' => $this->modelA, 'qty' => 1, 'line_total' => 10000]]));
        $this->assertEquals(0, Helper::calculateDoctorCommission($doctor->id, [['product_id' => $this->modelB, 'qty' => 1, 'line_total' => 10000]]), 'no rule, no default');
    }

    public function test_rules_are_saved_replaced_and_deduplicated_from_the_doctor_forms(): void
    {
        $base = ['name' => 'Dr Form', 'status' => 1, 'product_commission_percent' => 4];

        $this->actingAs($this->admin)->post('/admin/doctor', $base + ['product_commissions' => [
            ['product_id' => $this->modelA, 'commission_type' => 'fixed', 'commission_value' => 2500],
            ['product_id' => $this->modelA, 'commission_type' => 'fixed', 'commission_value' => 2800],   // listed twice: last wins
            ['product_id' => $this->modelB, 'commission_type' => 'percent', 'commission_value' => 6],
        ]])->assertSessionHasNoErrors();

        $doctor = Doctor::where('name', 'Dr Form')->firstOrFail();
        $this->assertSame(2, $doctor->productCommissions()->count());
        $this->assertEquals(2800, $doctor->productCommissions()->where('product_id', $this->modelA)->value('commission_value'));

        // editing replaces the whole list
        $this->actingAs($this->admin)->put('/admin/doctor/' . $doctor->id, $base + ['product_commissions' => [
            ['product_id' => $this->battery, 'commission_type' => 'percent', 'commission_value' => 1],
        ]])->assertSessionHasNoErrors();
        $this->assertSame([$this->battery], $doctor->productCommissions()->pluck('product_id')->all());

        // and an empty list clears it
        $this->actingAs($this->admin)->put('/admin/doctor/' . $doctor->id, $base)->assertSessionHasNoErrors();
        $this->assertSame(0, $doctor->productCommissions()->count());
    }

    public function test_a_rule_needs_a_real_product_and_a_valid_type_and_value(): void
    {
        $this->actingAs($this->admin)->post('/admin/doctor', ['name' => 'Dr Bad', 'product_commissions' => [['product_id' => 999999, 'commission_type' => 'fixed', 'commission_value' => 100]]])->assertSessionHasErrors('product_commissions.0.product_id');
        $this->actingAs($this->admin)->post('/admin/doctor', ['name' => 'Dr Bad', 'product_commissions' => [['product_id' => $this->modelA, 'commission_type' => 'bonus', 'commission_value' => 100]]])->assertSessionHasErrors('product_commissions.0.commission_type');
        $this->actingAs($this->admin)->post('/admin/doctor', ['name' => 'Dr Bad', 'product_commissions' => [['product_id' => $this->modelA, 'commission_type' => 'fixed', 'commission_value' => -5]]])->assertSessionHasErrors('product_commissions.0.commission_value');
        $this->assertSame(0, Doctor::where('name', 'Dr Bad')->count());
    }

    public function test_screens_show_the_model_rules_and_the_sale_form_gets_them(): void
    {
        $doctor = $this->doctorWithRules();

        $this->actingAs($this->admin)->get('/admin/doctor/create')->assertSuccessful()->assertSee('Model-Wise Commission')->assertSee('btn-add-product-comm', false);
        $this->actingAs($this->admin)->get('/admin/doctor/' . $doctor->id . '/edit')->assertSuccessful()->assertSee('Model A');

        $rules = $this->actingAs($this->admin)->getJson('/admin/doctor/' . $doctor->id . '/commission-rules')->assertSuccessful()->json();
        $this->assertSame('fixed', $rules['product_overrides'][$this->modelA]['type']);
        $this->assertEquals(3000, $rules['product_overrides'][$this->modelA]['value']);
    }

    public function test_a_sale_uses_the_model_rule_and_takes_the_discount_percent_off_the_base(): void
    {
        $doctor = Doctor::create(['name' => 'Dr Sale', 'status' => 1, 'product_commission_percent' => 10]);
        $supplier = $this->supplier();
        $this->postPurchase($this->admin, $supplier, [[$this->modelB, '', 'SN-DC-1,SN-DC-2', 2]])->assertSessionHasNoErrors();
        $patient = $this->patient();

        // default rule 10%; the line is 200 less 10% = 180, so the commission is 18 (not 10% of 200 - 10 = 19)
        $this->postSale($this->admin, $patient, [[$this->modelB, null, ['SN-DC-1'], 1]], 0, [
            'reference_by' => $doctor->id, 'reference_amount' => 0, 'discount' => [10], 'total_amount' => 180, 'total_discount' => 20, 'is_percent_product_commission' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertEquals(18, (float) DB::table('sale_masters')->orderByDesc('id')->value('reference_amount'));

        // now a fixed 40 for this exact model
        DoctorProductCommission::create(['doctor_id' => $doctor->id, 'product_id' => $this->modelB, 'commission_type' => 'fixed', 'commission_value' => 40]);
        $this->postSale($this->admin, $patient, [[$this->modelB, null, ['SN-DC-2'], 1]], 0, [
            'reference_by' => $doctor->id, 'reference_amount' => 0, 'discount' => [10], 'total_amount' => 180, 'total_discount' => 20,
        ])->assertSessionHasNoErrors();
        $this->assertEquals(40, (float) DB::table('sale_masters')->orderByDesc('id')->value('reference_amount'));
    }

    public function test_a_product_with_a_commission_rule_cannot_be_deleted_silently(): void
    {
        $this->doctorWithRules();

        $problems = \App\Services\UsageGuard::usedIn(\App\Services\UsageGuard::PRODUCT, $this->modelA);
        $this->assertTrue(collect($problems)->contains(fn ($p) => str_contains($p, 'doctor commission rules')));
    }
}
