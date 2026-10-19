<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LoyaltySetting;
use App\Models\MembershipTier;
use App\Models\Referral;
use App\Models\RewardLedger;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** "Referred by (member code or phone)" on the new-patient and Add Lead forms starts the referral program. */
class ReferredByTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    private int $referrerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        MembershipTier::query()->forceDelete();
        $tier = MembershipTier::create(['name' => 'Gold', 'min_purchase' => 0, 'max_purchase' => null, 'earn_percentage' => 1, 'referral_reward_points' => 250]);
        LoyaltySetting::current()->update(['point_value' => 1, 'min_redeem_points' => 0, 'max_redeem_percent' => 100]);
        $this->referrerId = $this->patient();
        DB::table('customers')->where('id', $this->referrerId)->update(['membership_tier_id' => $tier->id, 'referral_code' => 'ABC123']);
    }

    private function newPatient(array $extra = [], string $phone = '01711000111')
    {
        return $this->actingAs($this->admin)->post('/admin/customers', array_merge(['full_name' => 'New Patient', 'phone_number' => $phone], $extra));
    }

    private function customerByPhone(string $phone): Customer
    {
        $lead = Lead::withoutGlobalScopes()->where('phone_number', $phone)->firstOrFail();

        return Customer::withoutGlobalScopes()->where('lead_id', $lead->id)->firstOrFail();
    }

    public function test_patient_with_referral_code_gets_pending_referral(): void
    {
        $this->newPatient(['referred_by' => 'abc123'])->assertSessionHasNoErrors();
        $patient = $this->customerByPhone('01711000111');

        $referral = Referral::where('referred_customer_id', $patient->id)->firstOrFail();
        $this->assertSame($this->referrerId, (int) $referral->referrer_id);
        $this->assertSame('pending', $referral->status->value);
        $this->assertSame('ABC123', $referral->referral_code_used);
        $this->assertSame($this->referrerId, (int) $patient->referred_by_customer_id);
    }

    public function test_referrer_can_be_given_by_phone(): void
    {
        $phone = Lead::withoutGlobalScopes()->whereKey(Customer::withoutGlobalScopes()->find($this->referrerId)->lead_id)->value('phone_number');
        $this->newPatient(['referred_by' => $phone]);

        $this->assertSame(1, Referral::where('referrer_id', $this->referrerId)->count());
    }

    public function test_unknown_code_is_rejected_and_nothing_is_created(): void
    {
        $this->newPatient(['referred_by' => 'NOPE999'])->assertSessionHasErrors('referred_by');
        $this->assertSame(0, Lead::withoutGlobalScopes()->where('phone_number', '01711000111')->count());
        $this->assertSame(0, Referral::count());
    }

    public function test_blank_field_creates_no_referral(): void
    {
        $this->newPatient(['referred_by' => ''])->assertSessionHasNoErrors();
        $this->assertSame(0, Referral::count());
    }

    public function test_self_referral_and_duplicates_are_ignored(): void
    {
        $svc = app(MembershipService::class);
        $referrer = Customer::withoutGlobalScopes()->find($this->referrerId);
        $this->assertNull($svc->recordReferral($referrer, $referrer));

        $this->newPatient(['referred_by' => 'ABC123']);
        $patient = $this->customerByPhone('01711000111');
        $other = $this->patient();
        $this->assertNull($svc->recordReferral($patient, Customer::withoutGlobalScopes()->find($other)));
        $this->assertSame(1, Referral::where('referred_customer_id', $patient->id)->count());
        $this->assertSame($this->referrerId, (int) Referral::where('referred_customer_id', $patient->id)->value('referrer_id'));
    }

    public function test_add_lead_form_carries_referrer_to_the_customer(): void
    {
        $lead = Lead::withoutGlobalScopes()->create(['full_name' => 'Lead Person', 'phone_number' => '01722000222', 'referrer_customer_id' => $this->referrerId]);
        $customer = Customer::withoutGlobalScopes()->create(['lead_id' => $lead->id, 'referral_code' => 'LEAD01']);

        $this->assertSame('pending', Referral::where('referred_customer_id', $customer->id)->firstOrFail()->status->value);
    }

    public function test_forms_show_the_field_and_customer_page_shows_the_referrer(): void
    {
        $this->actingAs($this->admin)->get('/admin/customers/create')->assertOk()->assertSee('name="referred_by"', false);
        $this->actingAs($this->admin)->get('/admin/leads/create')->assertOk()->assertSee('name="referred_by"', false);

        $this->newPatient(['referred_by' => 'ABC123']);
        $patient = $this->customerByPhone('01711000111');
        $this->actingAs($this->admin)->get('/admin/customers/' . $patient->id)->assertOk()->assertSee('Referred By')->assertSee('Test Patient');
    }

    public function test_first_sale_awards_the_referrer_the_tier_points(): void
    {
        $this->newPatient(['referred_by' => 'ABC123']);
        $patient = $this->customerByPhone('01711000111');
        $saleId = DB::table('sale_masters')->insertGetId(['customer_id' => $patient->id, 'sale_invoice_no' => 'INV-REF-1', 'date' => date('Y-m-d'), 'total_amount' => 200, 'paid' => 200, 'created_at' => now()]);

        $this->assertTrue((new MembershipService())->processSaleRewards($patient->id, $saleId, 200, 'INV-REF-1'));

        $this->assertSame(250.0, (float) RewardLedger::where('customer_id', $this->referrerId)->where('type', 'referral')->sum('points'));
        $this->assertSame('completed', Referral::where('referred_customer_id', $patient->id)->firstOrFail()->status->value);
    }
}
