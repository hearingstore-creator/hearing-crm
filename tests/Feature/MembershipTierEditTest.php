<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoyaltySetting;
use App\Models\MembershipTier;
use App\Services\MembershipService;
use App\Services\MembershipSettingsTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Editing a membership tier: name, lifetime spend range, earn rate, what a point is worth, referral bonus. */
class MembershipTierEditTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        MembershipTier::query()->forceDelete();
        foreach ([['Bronze', 0, 10000], ['Silver', 10000.01, 24999.99], ['Gold', 25000, 49999.99], ['Platinum', 50000, null]] as $i => [$n, $min, $max]) {
            MembershipTier::create(['name' => $n, 'min_purchase' => $min, 'max_purchase' => $max, 'earn_percentage' => $i + 1, 'referral_reward_points' => 100 * ($i + 1)]);
        }
        LoyaltySetting::current()->update(['point_value' => 1, 'min_redeem_points' => 0, 'max_redeem_percent' => 100]);
    }

    protected function payload(array $override = []): array
    {
        $tiers = [];
        foreach (MembershipTier::orderBy('min_purchase')->get() as $t) {
            $tiers[$t->id] = [
                'id' => $t->id, 'name' => $t->name, 'min_purchase' => $t->min_purchase, 'max_purchase' => $t->max_purchase,
                'earn_percentage' => $t->earn_percentage, 'point_value' => $t->point_value, 'referral_reward_points' => $t->referral_reward_points,
            ];
        }
        foreach ($override as $name => $changes) {
            $id = MembershipTier::where('name', $name)->value('id');
            $tiers[$id] = array_merge($tiers[$id], $changes);
        }

        return ['tiers' => $tiers];
    }

    protected function customerIn(string $tierName): Customer
    {
        $customer = Customer::findOrFail($this->patient());
        $customer->update(['membership_tier_id' => MembershipTier::where('name', $tierName)->value('id')]);

        return $customer->fresh();
    }

    public function test_every_tier_detail_can_be_edited_including_the_name_and_point_value(): void
    {
        $this->actingAs($this->admin)->post(route('membership.tiers.update'), $this->payload([
            'Silver' => ['name' => 'Silver Plus', 'min_purchase' => 10000.01, 'max_purchase' => 29999.99, 'earn_percentage' => 2.5, 'point_value' => 1.25, 'referral_reward_points' => 350],
            'Gold'   => ['min_purchase' => 30000.00],
        ]))->assertSessionHasNoErrors();

        $silver = MembershipTier::where('name', 'Silver Plus')->firstOrFail();
        $this->assertEquals(29999.99, (float) $silver->max_purchase);
        $this->assertEquals(2.5, (float) $silver->earn_percentage);
        $this->assertEquals(1.25, (float) $silver->point_value);
        $this->assertSame(350, (int) $silver->referral_reward_points);
        $this->assertEquals(30000, (float) MembershipTier::where('name', 'Gold')->value('min_purchase'));
    }

    public function test_point_value_is_optional_and_checked(): void
    {
        $this->actingAs($this->admin)->post(route('membership.tiers.update'), $this->payload(['Gold' => ['point_value' => 1.5]]))->assertSessionHasNoErrors();
        $this->assertEquals(1.5, (float) MembershipTier::where('name', 'Gold')->value('point_value'));

        // empty again = use the loyalty setting
        $this->actingAs($this->admin)->post(route('membership.tiers.update'), $this->payload(['Gold' => ['point_value' => '']]))->assertSessionHasNoErrors();
        $this->assertNull(MembershipTier::where('name', 'Gold')->value('point_value'));

        $this->actingAs($this->admin)->post(route('membership.tiers.update'), $this->payload(['Gold' => ['point_value' => 0]]))->assertSessionHasErrors('tiers.'.MembershipTier::where('name', 'Gold')->value('id').'.point_value');
        $this->assertNull(MembershipTier::where('name', 'Gold')->value('point_value'));
    }

    public function test_redemption_uses_the_customers_tier_value_or_the_loyalty_setting(): void
    {
        MembershipTier::where('name', 'Gold')->update(['point_value' => 2]);
        $gold = $this->customerIn('Gold');
        $bronze = $this->customerIn('Bronze');

        $this->assertEquals(2.0, LoyaltySetting::pointValueFor($gold));
        $this->assertEquals(1.0, LoyaltySetting::pointValueFor($bronze));   // no own value: loyalty setting
        $this->assertEquals(1.0, LoyaltySetting::pointValueFor(null));

        $this->actingAs($this->admin)->getJson(route('sale.customer.redeem-points', $gold->id))->assertJsonPath('point_value', 2);
        $this->actingAs($this->admin)->getJson(route('sale.customer.redeem-points', $bronze->id))->assertJsonPath('point_value', 1);

        // 1000 invoice, points worth 2 each, all of it payable: at most 500 points. Worth 1: 1000 points.
        foreach ([$gold, $bronze] as $c) {
            \App\Models\RewardLedger::create(['customer_id' => $c->id, 'type' => \App\Enums\RewardLedgerType::EARN->value, 'points' => 5000, 'batch_reference' => 'T', 'expiry_date' => now()->addYear()]);
        }
        $this->assertSame(500, MembershipService::maxRedeemablePoints($gold, 1000));
        $this->assertSame(1000, MembershipService::maxRedeemablePoints($bronze, 1000));
    }

    public function test_the_tiers_screen_shows_the_new_columns_and_each_tiers_valuation(): void
    {
        MembershipTier::where('name', 'Gold')->update(['point_value' => 1.75]);

        $this->actingAs($this->admin)->get(route('membership.tiers'))->assertSuccessful()
            ->assertSee('1 Point Is Worth')->assertSee('Lifetime Spend From')->assertSee('Referral Bonus')->assertSee('1 pt = ৳1.75');
    }

    public function test_the_point_value_travels_in_the_settings_file(): void
    {
        MembershipTier::where('name', 'Gold')->update(['point_value' => 1.75]);
        $file = (new MembershipSettingsTransfer())->export();
        $this->assertEquals(1.75, $file['tiers'][2]['point_value']);
        $this->assertNull($file['tiers'][0]['point_value']);

        MembershipTier::where('name', 'Gold')->update(['point_value' => null]);
        $plan = (new MembershipSettingsTransfer())->plan($file);
        $this->assertContains('1 point is worth', array_column($plan['changes'], 'what'));

        (new MembershipSettingsTransfer())->apply($file);
        $this->assertEquals(1.75, (float) MembershipTier::where('name', 'Gold')->value('point_value'));

        // files exported before this field existed still import (tier point value stays "use the loyalty setting")
        unset($file['tiers'][0]['point_value']);
        (new MembershipSettingsTransfer())->apply($file);
        $this->assertNull(MembershipTier::where('name', 'Bronze')->value('point_value'));
    }
}
