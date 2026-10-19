<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\MembershipTier;
use App\Models\MembershipUpgradeHistory;
use App\Models\RewardLedger;
use App\Models\Referral;
use App\Enums\RewardLedgerType;
use App\Enums\ReferralStatus;
use App\Models\SaleMaster;
use App\Models\LoyaltySetting;
use App\Models\MembershipMilestone;
use App\Models\MembershipMilestoneAward;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MembershipService
{
    /**
     * Process membership rewards after a sale is completed
     *
     * @param int $customerId
     * @param int $saleMasterId
     * @param float $totalAmount
     * @param string $invoiceNo
     * @return void
     */
    /** @return bool false when the points could not be posted (the sale itself is kept) */
    public function processSaleRewards(int $customerId, int $saleMasterId, float $totalAmount, string $invoiceNo): bool
    {
        try {
            DB::beginTransaction();

            $customer = Customer::with('membershipTier')->find($customerId);

            if (!$customer) {
                Log::warning("Customer not found for reward processing: {$customerId}");
                DB::rollBack();
                return false;
            }

            // 1. Award points based on membership tier
            $this->awardPurchasePoints($customer, $saleMasterId, $totalAmount, $invoiceNo);

            // 2. Check and upgrade membership tier if needed
            $this->checkAndUpgradeMembership($customer);

            // 3. Check and award milestone bonus points
            $this->checkMilestones($customer, $saleMasterId, $totalAmount, $invoiceNo);

            // 4. Process referral if this is customer's first purchase
            $this->processReferralReward($customer, $saleMasterId, $invoiceNo);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error processing sale rewards: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Award points to customer based on their membership tier
     *
     * @param Customer $customer
     * @param int $saleMasterId
     * @param float $totalAmount
     * @param string $invoiceNo
     * @return void
     */
    private function awardPurchasePoints(Customer $customer, int $saleMasterId, float $totalAmount, string $invoiceNo): void
    {
        // Check if earn points already exist for this sale
        $existingEarnEntry = RewardLedger::where('sale_master_id', $saleMasterId)
            ->where('type', RewardLedgerType::EARN->value)
            ->first();

        // Get membership tier or assign default (Bronze)
        $tier = $customer->membershipTier;
        if (!$tier) {
            $tier = MembershipTier::orderBy('min_purchase')->first();
            if ($tier) {
                $customer->update(['membership_tier_id' => $tier->id]);
            }
        }

        if (!$tier) {
            Log::warning("No membership tier found for customer: {$customer->id}");
            return;
        }

        // Calculate points: 1 point per currency unit * earn percentage
        $pointsEarned = (int) ($totalAmount * ($tier->earn_percentage / 100));

        // Sale edited: keep one earn entry per sale and bring it in line with the new total
        if ($existingEarnEntry) {
            if ($pointsEarned <= 0) {
                $existingEarnEntry->delete();
            } elseif ((int) $existingEarnEntry->points !== $pointsEarned) {
                Log::info("Earn points for sale {$saleMasterId} changed from {$existingEarnEntry->points} to {$pointsEarned}");
                $existingEarnEntry->points = $pointsEarned;
                $existingEarnEntry->save();
            }
            return;
        }

        if ($pointsEarned > 0) {
            // Create reward ledger entry
            RewardLedger::create([
                'customer_id' => $customer->id,
                'sale_master_id' => $saleMasterId,
                'type' => RewardLedgerType::EARN->value,
                'points' => $pointsEarned,
                'batch_reference' => $invoiceNo,
                'expiry_date' => now()->addMonths((int) LoyaltySetting::current()->expiry_months),
            ]);

            Log::info("Awarded {$pointsEarned} points to customer {$customer->id} for sale {$saleMasterId}");
        }
    }

    /**
     * Check if customer qualifies for membership tier upgrade
     *
     * @param Customer $customer
     * @return void
     */
    private function checkAndUpgradeMembership(Customer $customer): void
    {
        // Calculate total purchases for this customer
        $totalPurchases = SaleMaster::where('customer_id', $customer->id)
            ->sum('total_amount');

        // Find appropriate tier based on total purchases
        $newTier = MembershipTier::where('min_purchase', '<=', $totalPurchases)
            ->where(function ($query) use ($totalPurchases) {
                $query->whereNull('max_purchase')
                      ->orWhere('max_purchase', '>=', $totalPurchases);
            })
            ->orderByDesc('min_purchase')
            ->first();

        if ($newTier && $customer->membership_tier_id != $newTier->id) {
            $oldTierId = $customer->membership_tier_id;

            // Upgrade customer's tier
            $customer->update(['membership_tier_id' => $newTier->id]);

            // Record upgrade history
            MembershipUpgradeHistory::create([
                'customer_id' => $customer->id,
                'old_tier_id' => $oldTierId,
                'new_tier_id' => $newTier->id,
                'upgraded_at' => now(),
            ]);

            Log::info("Customer {$customer->id} upgraded from tier {$oldTierId} to {$newTier->id}");
        }
    }

    /**
     * Process referral reward if customer was referred
     *
     * @param Customer $customer
     * @param int $saleMasterId
     * @param string $invoiceNo
     * @return void
     */
    private function processReferralReward(Customer $customer, int $saleMasterId, string $invoiceNo): void
    {
        // Check if this customer has a pending referral
        $referral = Referral::where('referred_customer_id', $customer->id)
            ->where('status', ReferralStatus::PENDING->value)
            ->first();

        if (!$referral) {
            return; // No referral or already processed
        }

        // Check if this is customer's first sale
        $saleCount = SaleMaster::where('customer_id', $customer->id)
            ->count();

        if ($saleCount !== 1) {
            return; // Only process on first sale
        }

        // Get referrer and their tier
        $referrer = Customer::with('membershipTier')->find($referral->referrer_id);
        if (!$referrer || !$referrer->membershipTier) {
            Log::warning("Referrer or tier not found for referral: {$referral->id}");
            return;
        }

        $tier = $referrer->membershipTier;
        $rewardPoints = $tier->referral_reward_points;

        // Award referral points
        RewardLedger::create([
            'customer_id' => $referrer->id,
            'referral_id' => $referral->id,
            'type' => RewardLedgerType::REFERRAL->value,
            'sale_master_id' => $saleMasterId,
            'points' => $rewardPoints,
            'batch_reference' => $invoiceNo,
            'expiry_date' => now()->addMonths((int) LoyaltySetting::current()->expiry_months),
        ]);

        // Mark referral as completed
        $referral->status = ReferralStatus::COMPLETED->value;
        $referral->rewarded_at = now();
        $referral->save();

        Log::info("Awarded {$rewardPoints} referral points to referrer {$referrer->id} for referral {$referral->id}");
    }

    /**
     * Redeem points from customer's wallet
     *
     * @param Customer $customer
     * @param int $saleMasterId
     * @param float $pointsToRedeem
     * @param string $invoiceNo
     * @return array Redeemed points and remaining amount
     */
    public function redeemPoints(Customer $customer, int $saleMasterId, float $pointsToRedeem, string $invoiceNo): array
    {
        // Lock the patient while reading the balance, so two sales at once cannot spend the same points
        Customer::whereKey($customer->id)->lockForUpdate()->first();

        // Get available (non-expired) points using the customer model method
        $availablePoints = $customer->getAvailableRedeemPoints();

        // Check if redeem entry already exists for this sale
        $existingRedeemEntry = RewardLedger::where('sale_master_id', $saleMasterId)
            ->where('type', RewardLedgerType::REDEEM->value)
            ->first();

        // If there's an existing entry, add its points back to available
        if ($existingRedeemEntry) {
            $existingRedeemedPoints = abs($existingRedeemEntry->points);
            $availablePoints += $existingRedeemedPoints; // Add back the already redeemed points
        }

        // Ensure we don't redeem more than available
        $actualPointsToRedeem = min($pointsToRedeem, $availablePoints);

        if ($actualPointsToRedeem > 0) {
            if ($existingRedeemEntry) {
                // Update existing entry
                $oldPoints = abs($existingRedeemEntry->points);
                $existingRedeemEntry->points = -$actualPointsToRedeem;
                $existingRedeemEntry->save();

                Log::info("Updated redeem points from {$oldPoints} to {$actualPointsToRedeem} for customer {$customer->id} on sale {$saleMasterId}");
            } else {
                // Create new redemption ledger entry
                RewardLedger::create([
                    'customer_id' => $customer->id,
                    'sale_master_id' => $saleMasterId,
                    'type' => RewardLedgerType::REDEEM->value,
                    'points' => -$actualPointsToRedeem,
                    'batch_reference' => $invoiceNo,
                ]);

                Log::info("Redeemed {$actualPointsToRedeem} points for customer {$customer->id} on sale {$saleMasterId}");
            }
        } elseif ($existingRedeemEntry && $pointsToRedeem == 0) {
            // If points set to 0, delete the redeem entry to return points to customer
            $existingRedeemEntry->delete();
            Log::info("Removed redeem entry for customer {$customer->id} on sale {$saleMasterId}, points returned");
        }

        return [
            'points_redeemed' => $actualPointsToRedeem,
            'remaining_amount' => $actualPointsToRedeem,
        ];
    }

    /**
     * Check and award one-time milestone bonuses
     *
     * @param Customer $customer
     * @param int $saleMasterId
     * @param float $totalAmount
     * @param string $invoiceNo
     * @return void
     */
    private function checkMilestones(Customer $customer, int $saleMasterId, float $totalAmount, string $invoiceNo): void
    {
        $awardedMilestoneIds = MembershipMilestoneAward::where('customer_id', $customer->id)
            ->pluck('membership_milestone_id')
            ->all();

        $activeMilestones = MembershipMilestone::where('is_active', true)
            ->whereNotIn('id', $awardedMilestoneIds)
            ->get();

        if ($activeMilestones->isEmpty()) {
            return;
        }

        $lifetimeAfter = (float) SaleMaster::where('customer_id', $customer->id)->sum('total_amount');
        $lifetimeBefore = $lifetimeAfter - (float) $totalAmount;
        $saleCount = (int) SaleMaster::where('customer_id', $customer->id)->count();

        $setting = LoyaltySetting::current();

        foreach ($activeMilestones as $milestone) {
            $qualifies = false;

            if ($milestone->type === 'lifetime_spend') {
                $threshold = (float) $milestone->threshold;
                // Award only if THIS sale crosses the threshold
                if ($lifetimeBefore < $threshold && $lifetimeAfter >= $threshold) {
                    $qualifies = true;
                }
            } elseif ($milestone->type === 'purchase_count') {
                $threshold = (int) $milestone->threshold;
                // Award only if the patient's sale count after this sale equals the threshold
                if ($saleCount === $threshold) {
                    $qualifies = true;
                }
            }

            if ($qualifies) {
                // Ensure idempotency: check if already awarded
                $alreadyAwarded = MembershipMilestoneAward::where('customer_id', $customer->id)
                    ->where('membership_milestone_id', $milestone->id)
                    ->exists();

                if (!$alreadyAwarded) {
                    // Create reward ledger row for milestone bonus
                    $ledger = RewardLedger::create([
                        'customer_id'     => $customer->id,
                        'sale_master_id'  => $saleMasterId,
                        'type'            => RewardLedgerType::MILESTONE->value,
                        'points'          => (int) $milestone->bonus_points,
                        'batch_reference' => $invoiceNo,
                        'expiry_date'     => now()->addMonths((int) $setting->expiry_months),
                    ]);

                    // Record award
                    // Bonus points stay if the sale is later deleted
                    MembershipMilestoneAward::create([
                        'customer_id'             => $customer->id,
                        'membership_milestone_id' => $milestone->id,
                        'reward_ledger_id'        => $ledger->id,
                        'awarded_at'              => now(),
                    ]);

                    Log::info("Milestone '{$milestone->name}' awarded to customer {$customer->id} on sale {$saleMasterId}");
                }
            }
        }
    }

    /**
     * Calculate the maximum redeemable points for a customer on an invoice.
     *
     * Returns 0 if customer's available balance is below min_redeem_points;
     * otherwise the smaller of available balance and floor(invoiceGross * max_redeem_percent / 100 / point_value).
     * For an edited sale, adds back that sale's own existing redemption.
     *
     * @param Customer $customer
     * @param float $invoiceGross
     * @param int|null $ignoreSaleId
     * @return int
     */
    public static function maxRedeemablePoints(Customer $customer, float $invoiceGross, ?int $ignoreSaleId = null): int
    {
        $setting = LoyaltySetting::current();
        $availablePoints = (float) $customer->getAvailableRedeemPoints();

        if ($ignoreSaleId) {
            $existingRedeemEntry = RewardLedger::where('sale_master_id', $ignoreSaleId)
                ->where('type', RewardLedgerType::REDEEM->value)
                ->first();
            if ($existingRedeemEntry) {
                $availablePoints += abs((float) $existingRedeemEntry->points);
            }
        }

        if ($availablePoints < (float) $setting->min_redeem_points) {
            return 0;
        }

        $pointValue = (float) $setting->point_value > 0 ? (float) $setting->point_value : 1.0;
        $maxAllowedByInvoice = (int) floor(($invoiceGross * ((float) $setting->max_redeem_percent / 100)) / $pointValue);

        return (int) max(0, min((int) floor($availablePoints), $maxAllowedByInvoice));
    }
}
