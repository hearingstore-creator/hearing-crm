<?php

namespace Tests\Feature;

use App\Enums\RewardLedgerType;
use App\Models\Customer;
use App\Models\LoyaltySetting;
use App\Models\MembershipMilestone;
use App\Models\MembershipMilestoneAward;
use App\Models\MembershipTier;
use App\Models\RewardLedger;
use App\Models\SaleMaster;
use App\Models\User;
use App\Services\DashboardAnalyticsService;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

class MembershipSettingsTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
    }

    protected function createTiers(): void
    {
        MembershipTier::query()->delete();
        MembershipTier::create(['name' => 'Bronze', 'min_purchase' => 0, 'max_purchase' => 10000, 'earn_percentage' => 1.00, 'referral_reward_points' => 100]);
        MembershipTier::create(['name' => 'Silver', 'min_purchase' => 10000.01, 'max_purchase' => 24999.99, 'earn_percentage' => 2.00, 'referral_reward_points' => 200]);
        MembershipTier::create(['name' => 'Gold', 'min_purchase' => 25000.00, 'max_purchase' => 49999.99, 'earn_percentage' => 3.00, 'referral_reward_points' => 300]);
        MembershipTier::create(['name' => 'Platinum', 'min_purchase' => 50000.00, 'max_purchase' => null, 'earn_percentage' => 4.00, 'referral_reward_points' => 400]);
    }

    /**
     * 1. Tier update validation: overlap, gap, first tier not 0, last tier with a maximum,
     *    earn_percentage 101, duplicate name, each rejected; a valid change saved.
     */
    public function test_tier_update_validation(): void
    {
        $this->createTiers();
        $tiers = MembershipTier::orderBy('min_purchase')->get();

        $basePayload = [
            $tiers[0]->id => ['id' => $tiers[0]->id, 'name' => 'Bronze', 'min_purchase' => 0, 'max_purchase' => 10000, 'earn_percentage' => 1, 'referral_reward_points' => 100],
            $tiers[1]->id => ['id' => $tiers[1]->id, 'name' => 'Silver', 'min_purchase' => 10000.01, 'max_purchase' => 24999.99, 'earn_percentage' => 2, 'referral_reward_points' => 200],
            $tiers[2]->id => ['id' => $tiers[2]->id, 'name' => 'Gold', 'min_purchase' => 25000.00, 'max_purchase' => 49999.99, 'earn_percentage' => 3, 'referral_reward_points' => 300],
            $tiers[3]->id => ['id' => $tiers[3]->id, 'name' => 'Platinum', 'min_purchase' => 50000.00, 'max_purchase' => null, 'earn_percentage' => 4, 'referral_reward_points' => 400],
        ];

        // First tier not 0 -> rejected
        $p = $basePayload;
        $p[$tiers[0]->id]['min_purchase'] = 50;
        $this->actingAs($this->admin)->from('/admin/membership/tiers')->post('/admin/membership/tiers', ['tiers' => $p])
            ->assertSessionHasErrors('tiers');

        // Overlap -> rejected
        $p = $basePayload;
        $p[$tiers[1]->id]['min_purchase'] = 9000;
        $this->actingAs($this->admin)->from('/admin/membership/tiers')->post('/admin/membership/tiers', ['tiers' => $p])
            ->assertSessionHasErrors('tiers');

        // Gap -> rejected
        $p = $basePayload;
        $p[$tiers[1]->id]['min_purchase'] = 12000;
        $this->actingAs($this->admin)->from('/admin/membership/tiers')->post('/admin/membership/tiers', ['tiers' => $p])
            ->assertSessionHasErrors('tiers');

        // Last tier with a maximum -> rejected
        $p = $basePayload;
        $p[$tiers[3]->id]['max_purchase'] = 100000;
        $this->actingAs($this->admin)->from('/admin/membership/tiers')->post('/admin/membership/tiers', ['tiers' => $p])
            ->assertSessionHasErrors('tiers');

        // earn_percentage 101 -> rejected
        $p = $basePayload;
        $p[$tiers[3]->id]['earn_percentage'] = 101;
        $this->actingAs($this->admin)->from('/admin/membership/tiers')->post('/admin/membership/tiers', ['tiers' => $p])
            ->assertSessionHasErrors();

        // Duplicate name -> rejected
        $p = $basePayload;
        $p[$tiers[1]->id]['name'] = 'Bronze';
        $this->actingAs($this->admin)->from('/admin/membership/tiers')->post('/admin/membership/tiers', ['tiers' => $p])
            ->assertSessionHasErrors('tiers');

        // Valid change saved
        $validPayload = $basePayload;
        $validPayload[$tiers[0]->id]['max_purchase'] = 15000;
        $validPayload[$tiers[1]->id]['min_purchase'] = 15000.01;
        $this->actingAs($this->admin)->from('/admin/membership/tiers')->post('/admin/membership/tiers', ['tiers' => $validPayload])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/membership/tiers');

        $this->assertDatabaseHas('membership_tiers', ['id' => $tiers[0]->id, 'max_purchase' => 15000.00]);
        $this->assertDatabaseHas('membership_tiers', ['id' => $tiers[1]->id, 'min_purchase' => 15000.01]);
    }

    /**
     * 2. Tier, milestone and settings routes: forbidden for a user without the permission
     *    and for a branch login; allowed for Super Admin.
     */
    public function test_tier_milestone_and_settings_routes_permissions(): void
    {
        $this->createTiers();
        $nobody = User::forceCreate([
            'name' => 'No Right', 'email' => 'noright@test.test', 'password' => bcrypt('x'), 'status' => 1,
        ]);

        $routes = [
            ['get', '/admin/membership/tiers'],
            ['post', '/admin/membership/tiers'],
            ['get', '/admin/membership/milestones'],
            ['post', '/admin/membership/milestones'],
            ['get', '/admin/membership/settings'],
            ['post', '/admin/membership/settings'],
        ];

        // User without permission is forbidden
        $this->actingAs($nobody);
        foreach ($routes as [$method, $uri]) {
            $this->json($method, $uri)->assertStatus(403);
        }

        // Branch login is forbidden (headOfficeOnly)
        $this->actingAs($this->branch);
        foreach ($routes as [$method, $uri]) {
            $this->json($method, $uri)->assertStatus(403);
        }

        // Super Admin is allowed
        $this->actingAs($this->admin);
        $this->get('/admin/membership/tiers')->assertOk();
        $this->get('/admin/membership/milestones')->assertOk();
        $this->get('/admin/membership/settings')->assertOk();
        $this->post('/admin/membership/settings', [
            'point_value' => 1.5,
            'expiry_months' => 24,
            'min_redeem_points' => 10,
            'max_redeem_percent' => 80,
        ])->assertSessionHasNoErrors();
    }

    /**
     * 3. A lifetime_spend milestone is awarded once when a sale crosses it,
     *    not on a sale that does not cross it, and not twice when the sale is edited
     *    (also check the patient's available points include the bonus).
     */
    public function test_lifetime_spend_milestone_awarded_once_when_crossing_threshold(): void
    {
        $this->createTiers();
        $supplierId = $this->supplier();
        $productId = $this->product();
        $customerId = $this->patient();

        // 10 units in stock
        $this->postPurchase($this->admin, $supplierId, [[$productId, '', 'S1,S2,S3,S4,S5,S6,S7,S8,S9,S10', 10]], 1000);

        // Milestone at 5000 spend -> 500 bonus points
        $milestone = MembershipMilestone::create([
            'name'         => 'Spend 5k Bonus',
            'type'         => 'lifetime_spend',
            'threshold'    => 5000.00,
            'bonus_points' => 500,
            'is_active'    => true,
        ]);

        // Sale 1: 3000 taka (lifetime: 3000 < 5000)
        $this->postSale($this->admin, $customerId, [[$productId, null, ['S1'], 1]], 3000, [
            'total_amount' => 3000,
            'unit_price'   => [3000],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('membership_milestone_awards', [
            'customer_id'             => $customerId,
            'membership_milestone_id' => $milestone->id,
        ]);

        // Sale 2: 3000 taka (lifetime: 3000 -> 6000 >= 5000, crosses threshold!)
        $this->postSale($this->admin, $customerId, [[$productId, null, ['S2'], 1]], 3000, [
            'total_amount' => 3000,
            'unit_price'   => [3000],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('membership_milestone_awards', [
            'customer_id'             => $customerId,
            'membership_milestone_id' => $milestone->id,
        ]);

        $this->assertDatabaseHas('reward_ledgers', [
            'customer_id' => $customerId,
            'type'        => RewardLedgerType::MILESTONE->value,
            'points'      => 500,
        ]);

        // Patient's available points include the 500 bonus points (+ earn points from sales)
        $customer = Customer::find($customerId);
        $this->assertGreaterThanOrEqual(500, $customer->getAvailableRedeemPoints());

        // Re-process Sale 2 (simulate sale edit): award must not duplicate
        $sale2 = SaleMaster::where('customer_id', $customerId)->orderByDesc('id')->first();
        $service = new MembershipService();
        $service->processSaleRewards($customerId, $sale2->id, 3500, $sale2->sale_invoice_no);

        $awardCount = MembershipMilestoneAward::where('customer_id', $customerId)
            ->where('membership_milestone_id', $milestone->id)
            ->count();
        $this->assertEquals(1, $awardCount);

        $milestoneLedgerCount = RewardLedger::where('customer_id', $customerId)
            ->where('type', RewardLedgerType::MILESTONE->value)
            ->count();
        $this->assertEquals(1, $milestoneLedgerCount);
    }

    /**
     * 4. A purchase_count milestone fires on the Nth sale only.
     */
    public function test_purchase_count_milestone_fires_on_nth_sale_only(): void
    {
        $this->createTiers();
        $supplierId = $this->supplier();
        $productId = $this->product();
        $customerId = $this->patient();

        $this->postPurchase($this->admin, $supplierId, [[$productId, '', 'P1,P2,P3,P4,P5', 5]], 500);

        // Milestone fires on 3rd purchase
        $milestone = MembershipMilestone::create([
            'name'         => '3rd Visit Bonus',
            'type'         => 'purchase_count',
            'threshold'    => 3,
            'bonus_points' => 250,
            'is_active'    => true,
        ]);

        // Sale 1
        $this->postSale($this->admin, $customerId, [[$productId, null, ['P1'], 1]], 200)->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('membership_milestone_awards', ['customer_id' => $customerId, 'membership_milestone_id' => $milestone->id]);

        // Sale 2
        $this->postSale($this->admin, $customerId, [[$productId, null, ['P2'], 1]], 200)->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('membership_milestone_awards', ['customer_id' => $customerId, 'membership_milestone_id' => $milestone->id]);

        // Sale 3 -> fires!
        $this->postSale($this->admin, $customerId, [[$productId, null, ['P3'], 1]], 200)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('membership_milestone_awards', ['customer_id' => $customerId, 'membership_milestone_id' => $milestone->id]);
        $this->assertDatabaseHas('reward_ledgers', ['customer_id' => $customerId, 'type' => RewardLedgerType::MILESTONE->value, 'points' => 250]);

        // Sale 4 -> does not fire again
        $this->postSale($this->admin, $customerId, [[$productId, null, ['P4'], 1]], 200)->assertSessionHasNoErrors();
        $this->assertEquals(1, MembershipMilestoneAward::where('customer_id', $customerId)->where('membership_milestone_id', $milestone->id)->count());
    }

    /**
     * 5. Expiry months setting: a new earn row gets expiry_date about N months ahead;
     *    an old row is unchanged.
     */
    public function test_expiry_months_setting_applies_to_new_earn_rows(): void
    {
        $this->createTiers();
        $supplierId = $this->supplier();
        $productId = $this->product();
        $customerId = $this->patient();

        $this->postPurchase($this->admin, $supplierId, [[$productId, '', 'E1,E2', 2]], 200);

        // Pre-existing row with 12 months expiry
        $oldDate = now()->addMonths(12)->toDateString();
        $oldRow = RewardLedger::create([
            'customer_id'     => $customerId,
            'type'            => RewardLedgerType::EARN->value,
            'points'          => 50,
            'batch_reference' => 'OLD-INV',
            'expiry_date'     => $oldDate,
        ]);

        // Change loyalty setting to 6 months
        $setting = LoyaltySetting::current();
        $setting->update(['expiry_months' => 6]);

        // New sale
        $this->postSale($this->admin, $customerId, [[$productId, null, ['E1'], 1]], 200)->assertSessionHasNoErrors();

        // New earn row should have expiry ~6 months ahead
        $newRow = RewardLedger::where('customer_id', $customerId)
            ->where('batch_reference', '!=', 'OLD-INV')
            ->where('type', RewardLedgerType::EARN->value)
            ->first();

        $this->assertNotNull($newRow);
        $this->assertEquals(now()->addMonths(6)->toDateString(), $newRow->expiry_date->toDateString());

        // Old row is unchanged
        $this->assertEquals($oldDate, $oldRow->fresh()->expiry_date->toDateString());
    }

    /**
     * 6. Redeem rules: below min_redeem_points is rejected;
     *    over max_redeem_percent is rejected by the server even if the posted total is lowered;
     *    a valid redemption works; with point_value 2 the posted total must account for 2 taka per point.
     */
    public function test_redeem_rules_enforced_server_side(): void
    {
        $this->createTiers();
        $supplierId = $this->supplier();
        $productId = $this->product();
        $customerId = $this->patient();

        $this->postPurchase($this->admin, $supplierId, [[$productId, '', 'R1,R2,R3,R4', 4]], 400);

        // Loyalty setting: 1 pt = ৳2, min 100 pts, max 50% invoice
        $setting = LoyaltySetting::current();
        $setting->update([
            'point_value'        => 2.00,
            'min_redeem_points'  => 100,
            'max_redeem_percent' => 50.00,
        ]);

        // Patient has 50 points (below min_redeem_points 100)
        RewardLedger::create([
            'customer_id'     => $customerId,
            'type'            => RewardLedgerType::EARN->value,
            'points'          => 50,
            'batch_reference' => 'PREV',
            'expiry_date'     => now()->addMonths(12),
        ]);

        // Attempt redemption below min_redeem_points -> rejected
        $this->actingAs($this->admin)->from('/admin/sale/create')->post('/admin/sale', [
            'customer_id'         => $customerId,
            'payment_type_id'     => 1,
            'date'                => date('Y-m-d'),
            'total_amount'        => 100,
            'paid'                => 100,
            'product_id'          => [$productId],
            'qty'                 => [1],
            'unit_price'          => [200],
            'purchase_price'      => [100],
            'discount'            => [0],
            'redeem_points'       => 50,
            'batch_id_formatted'  => json_encode([null]),
            'serial_no_formatted' => json_encode([['R1']]),
        ])->assertSessionHasErrors('redeem_points');

        // Top up patient points to 500 (now >= min_redeem_points)
        RewardLedger::create([
            'customer_id'     => $customerId,
            'type'            => RewardLedgerType::EARN->value,
            'points'          => 450,
            'batch_reference' => 'TOPUP',
            'expiry_date'     => now()->addMonths(12),
        ]);

        // Over max_redeem_percent: gross = 0 + 200 * 2 = 400. 50% = 200 taka = 100 points.
        // Trying to redeem 200 points (> 100 points) -> rejected even with posted total lowered to 0!
        $this->actingAs($this->admin)->from('/admin/sale/create')->post('/admin/sale', [
            'customer_id'         => $customerId,
            'payment_type_id'     => 1,
            'date'                => date('Y-m-d'),
            'total_amount'        => 0,
            'paid'                => 0,
            'product_id'          => [$productId],
            'qty'                 => [1],
            'unit_price'          => [200],
            'purchase_price'      => [100],
            'discount'            => [0],
            'redeem_points'       => 200,
            'batch_id_formatted'  => json_encode([null]),
            'serial_no_formatted' => json_encode([['R1']]),
        ])->assertSessionHasErrors('redeem_points');

        // Valid redemption: redeem 50 points (at ৳2 = ৳100 discount).
        // Total amount posted = 100 (200 price - 100 points discount).
        // gross = 100 + 50*2 = 200. Max 50% = 100 taka = 50 points. 50 <= 50 -> OK!
        $this->actingAs($this->admin)->from('/admin/sale/create')->post('/admin/sale', [
            'customer_id'         => $customerId,
            'payment_type_id'     => 1,
            'date'                => date('Y-m-d'),
            'total_amount'        => 100,
            'paid'                => 100,
            'product_id'          => [$productId],
            'qty'                 => [1],
            'unit_price'          => [200],
            'purchase_price'      => [100],
            'discount'            => [0],
            'redeem_points'       => 50,
            'batch_id_formatted'  => json_encode([null]),
            'serial_no_formatted' => json_encode([['R1']]),
        ])->assertSessionHasNoErrors();

        // Check redemption recorded
        $this->assertDatabaseHas('reward_ledgers', [
            'customer_id' => $customerId,
            'type'        => RewardLedgerType::REDEEM->value,
            'points'      => -50,
        ]);
    }

    /**
     * 7. The pointsLiability dashboard widget and Customer::getAvailableRedeemPoints()
     *    include milestone points.
     */
    public function test_points_liability_and_available_points_include_milestone_points(): void
    {
        $customerId = $this->patient();

        // Add milestone points
        RewardLedger::create([
            'customer_id'     => $customerId,
            'type'            => RewardLedgerType::MILESTONE->value,
            'points'          => 750,
            'batch_reference' => 'MILE-1',
            'expiry_date'     => now()->addMonths(12),
        ]);

        // Customer available points include milestone
        $customer = Customer::find($customerId);
        $this->assertEquals(750, $customer->getAvailableRedeemPoints());

        // Dashboard pointsLiability widget includes milestone points
        $this->actingAs($this->admin);
        $analytics = app(DashboardAnalyticsService::class)->pick(['points_liability']);
        $pointsLiability = $analytics['points_liability'];

        $this->assertNotNull($pointsLiability);
        $this->assertGreaterThanOrEqual(750, $pointsLiability['earned']);
        $this->assertGreaterThanOrEqual(750, $pointsLiability['outstanding']);
    }
}
