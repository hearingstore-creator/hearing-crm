<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\MembershipTier;
use App\Models\MembershipUpgradeHistory;
use App\Models\Referral;
use App\Models\RewardLedger;
use App\Models\SaleMaster;
use App\Enums\RewardLedgerType;
use App\Models\LoyaltySetting;
use App\Models\MembershipMilestone;
use App\Models\MembershipMilestoneAward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MembershipController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:membership_tiers.index')->only(['tiers']);
        $this->middleware('permission:membership_tiers.edit')->only(['updateTiers']);
        $this->middleware('permission:milestones.index')->only(['milestones']);
        $this->middleware('permission:milestones.create')->only(['storeMilestone']);
        $this->middleware('permission:milestones.edit')->only(['updateMilestone', 'toggleMilestone']);
        $this->middleware('permission:milestones.delete')->only(['destroyMilestone']);
        $this->middleware('permission:loyalty_settings.index')->only(['loyaltySettings']);
        $this->middleware('permission:loyalty_settings.edit')->only(['updateLoyaltySettings']);
        $this->middleware(fn ($request, $next) => \App\Classes\Helper::headOfficeOnly($request, $next))->only([
            'updateTiers',
            'milestones', 'storeMilestone', 'updateMilestone', 'toggleMilestone', 'destroyMilestone',
            'loyaltySettings', 'updateLoyaltySettings',
        ]);
        $this->middleware('permission:reward_wallets.index')->only(['wallets', 'walletDetails']);
        $this->middleware('permission:reward_ledger.index')->only(['rewardLedger']);
        $this->middleware('permission:referrals.index')->only(['referrals']);
        $this->middleware('permission:referrals.delete')->only(['deleteReferral']);
        $this->middleware('permission:tier_upgrades.index')->only(['upgrades']);
    }

    /**
     * Display membership tiers list
     */
    public function tiers(Request $request): View
    {
        $query = MembershipTier::withCount('customers');

        $data['tiers'] = $query->orderBy('min_purchase')->get();

        // Calculate statistics for each tier
        $data['tiers']->each(function ($tier) {
            $tier->total_points_issued = RewardLedger::whereHas('customer', function ($q) use ($tier) {
                $q->where('membership_tier_id', $tier->id);
            })->whereIn('type', [RewardLedgerType::EARN->value, RewardLedgerType::REFERRAL->value, RewardLedgerType::MILESTONE->value])
            ->where('points', '>', 0)
            ->sum('points');

            // Count customers with this tier who have positive balance
            $tier->active_wallets = Customer::where('membership_tier_id', $tier->id)
                ->whereHas('rewardLedgers', function ($q) {
                    $q->where(function ($query) {
                        $query->whereNull('expiry_date')
                              ->orWhere('expiry_date', '>', now());
                    });
                })
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('reward_ledgers')
                        ->whereColumn('reward_ledgers.customer_id', 'customers.id')
                        ->whereIn('reward_ledgers.type', [RewardLedgerType::EARN->value, RewardLedgerType::REFERRAL->value, RewardLedgerType::MILESTONE->value])
                        ->where('reward_ledgers.points', '>', 0);
                })
                ->count();
        });

        $data['loyaltySetting'] = LoyaltySetting::current();

        return view('admin.membership.tiers', $data);
    }

    /**
     * Display reward wallets list
     */
    public function wallets(Request $request): View
    {
        $query = Customer::with('lead', 'membershipTier');

        // Filter by customer
        if ($request->filled('customer_id')) {
            $query->where('id', $request->customer_id);
        }

        // Filter by tier
        if ($request->filled('tier_id')) {
            $query->where('membership_tier_id', $request->tier_id);
        }

        $customers = $query->orderByDesc('id')->paginate(20);

        // Calculate wallet statistics from reward ledger for each customer
        $customers->getCollection()->transform(function ($customer) {
            // Total earned points
            $customer->total_earned = RewardLedger::where('customer_id', $customer->id)
                ->whereIn('type', [RewardLedgerType::EARN->value, RewardLedgerType::REFERRAL->value, RewardLedgerType::MILESTONE->value])
                ->where('points', '>', 0)
                ->sum('points');

            // Total redeemed points (absolute value of negative points)
            $customer->total_redeemed = abs(RewardLedger::where('customer_id', $customer->id)
                ->where('type', RewardLedgerType::REDEEM->value)
                ->where('points', '<', 0)
                ->sum('points'));

            // Total expired points (points past their expiry_date)
            $customer->total_expired = RewardLedger::where('customer_id', $customer->id)
                ->whereIn('type', [RewardLedgerType::EARN->value, RewardLedgerType::REFERRAL->value, RewardLedgerType::MILESTONE->value])
                ->where('points', '>', 0)
                ->where('expiry_date', '<', now())
                ->sum('points');

            // Available balance (sum of all points)
            $customer->balance = RewardLedger::where('customer_id', $customer->id)
                ->sum('points');

            // Real available balance (non-expired only)
            $customer->real_available_balance = Customer::find($customer->id)->getAvailableRedeemPoints();

            // Expiring soon (within 7 days)
            $customer->expiring_soon = RewardLedger::where('customer_id', $customer->id)
                ->whereIn('type', [RewardLedgerType::EARN->value, RewardLedgerType::REFERRAL->value, RewardLedgerType::MILESTONE->value])
                ->where('points', '>', 0)
                ->whereBetween('expiry_date', [now(), now()->addDays(7)])
                ->sum('points');

            return $customer;
        });

        $data['wallets'] = $customers;
        $data['tiers'] = MembershipTier::orderBy('min_purchase')->get();
        $data['customers'] = Customer::with('lead')->select('id', 'lead_id')->get();

        return view('admin.membership.wallets', $data);
    }

    /**
     * Display wallet details
     */
    public function walletDetails($id): View
    {
        $customer = Customer::with('lead', 'membershipTier')->findOrFail($id);

        // Calculate all wallet statistics from reward ledger
        $customer->total_earned = RewardLedger::where('customer_id', $customer->id)
            ->whereIn('type', [RewardLedgerType::EARN->value, RewardLedgerType::REFERRAL->value, RewardLedgerType::MILESTONE->value])
            ->where('points', '>', 0)
            ->sum('points');

        $customer->total_redeemed = abs(RewardLedger::where('customer_id', $customer->id)
            ->where('type', RewardLedgerType::REDEEM->value)
            ->where('points', '<', 0)
            ->sum('points'));

        $customer->total_expired = RewardLedger::where('customer_id', $customer->id)
            ->whereIn('type', [RewardLedgerType::EARN->value, RewardLedgerType::REFERRAL->value, RewardLedgerType::MILESTONE->value])
            ->where('points', '>', 0)
            ->where('expiry_date', '<', now())
            ->sum('points');

        $customer->balance = RewardLedger::where('customer_id', $customer->id)
            ->sum('points');

        $customer->real_available_balance = Customer::find($customer->id)->getAvailableRedeemPoints();

        $customer->expiring_soon = RewardLedger::where('customer_id', $customer->id)
            ->whereIn('type', [RewardLedgerType::EARN->value, RewardLedgerType::REFERRAL->value, RewardLedgerType::MILESTONE->value])
            ->where('points', '>', 0)
            ->whereBetween('expiry_date', [now(), now()->addDays(7)])
            ->sum('points');

        // Get recent transactions
        $data['recent_transactions'] = RewardLedger::with('saleMaster', 'referral')
            ->where('customer_id', $customer->id)
            ->latest()
            ->paginate(20);

        // Get expiring points
        $data['expiring_points'] = $customer->expiring_soon;

        // Pass customer instead of wallet
        $data['wallet'] = $customer;

        return view('admin.membership.wallet-details', $data);
    }

    /**
     * Display reward ledger
     */
    public function rewardLedger(Request $request): View
    {
        $query = RewardLedger::with('customer.lead', 'customer.membershipTier', 'saleMaster', 'referral');

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by customer
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by tier
        if ($request->filled('tier_id')) {
            $query->whereHas('customer', function ($q) use ($request) {
                $q->where('membership_tier_id', $request->tier_id);
            });
        }

        $data['start_date'] = $request->start_date ?? null;
        $data['end_date'] = $request->end_date ?? null;

        if ($data['start_date'] && $data['end_date']) {
            $start = date('Y-m-d', strtotime($data['start_date']));
            $end = date('Y-m-d', strtotime($data['end_date']));
            $query->whereBetween('created_at', [$start, $end]);
        }

        $data['ledgers'] = $query->latest()->paginate(20);
        $data['tiers'] = MembershipTier::orderBy('min_purchase')->get();
        $data['customers'] = Customer::with('lead')->select('id', 'lead_id')->get();

        return view('admin.membership.reward-ledger', $data);
    }

    /**
     * Display referrals list
     */
    public function referrals(Request $request)
    {
        $query = Referral::with('referrer.lead', 'referrer.membershipTier', 'referredCustomer.lead');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by referrer
        if ($request->filled('referrer_id')) {
            $query->where('referrer_id', $request->referrer_id);
        }

        $data['start_date'] = $request->start_date ?? null;
        $data['end_date'] = $request->end_date ?? null;

        if ($data['start_date'] && $data['end_date']) {
            $start = date('Y-m-d', strtotime($data['start_date']));
            $end = date('Y-m-d', strtotime($data['end_date']));
            $query->whereBetween('created_at', [$start, $end]);
        }

        $data['referrals'] = $query->latest()->paginate(20);
        $data['customers'] = Customer::with('lead')->select('id', 'lead_id')->get();

        return view('admin.membership.referrals', $data);
    }

    /**
     * Delete referral
     */
    public function deleteReferral($id)
    {
        $referral = Referral::findOrFail($id);

        // Once a referral paid reward points, deleting it would leave those points without a source
        $rewarded = $referral->rewarded_at !== null
            || \Illuminate\Support\Facades\DB::table('reward_ledgers')->where('referral_id', $referral->id)->whereNull('deleted_at')->exists();
        if ($rewarded) {
            return redirect()->back()->with('warning', 'This referral has already paid reward points, so it is kept for the points history.');
        }

        $referral->delete();

        return redirect()->back()->with('success', 'Referral deleted successfully');
    }

    /**
     * Display tier upgrades history
     */
    public function upgrades(Request $request): View
    {
        $query = MembershipUpgradeHistory::with('customer.lead', 'oldTier', 'newTier');

        // Filter by customer
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by tier
        if ($request->filled('tier_id')) {
            $query->where('new_tier_id', $request->tier_id);
        }

        $data['start_date'] = $request->start_date ?? null;
        $data['end_date'] = $request->end_date ?? null;

        if ($data['start_date'] && $data['end_date']) {
            $start = date('Y-m-d', strtotime($data['start_date']));
            $end = date('Y-m-d', strtotime($data['end_date']));
            $query->whereBetween('upgraded_at', [$start, $end]);
        }

        $upgrades = $query->latest()->paginate(20);

        // Calculate total purchases at upgrade time for each record
        $upgrades->getCollection()->transform(function ($upgrade) {
            $upgrade->total_purchases_at_upgrade = SaleMaster::where('customer_id', $upgrade->customer_id)
                ->where('created_at', '<=', $upgrade->upgraded_at)
                ->sum('total_amount');
            return $upgrade;
        });

        $data['upgrades'] = $upgrades;
        $data['tiers'] = MembershipTier::orderBy('min_purchase')->get();
        $data['customers'] = Customer::with('lead')->select('id', 'lead_id')->get();

        return view('admin.membership.upgrades', $data);
    }

    /**
     * Update existing membership tiers configuration
     */
    public function updateTiers(Request $request)
    {
        $request->validate([
            'tiers'                           => ['required', 'array'],
            'tiers.*.id'                      => ['required', 'exists:membership_tiers,id'],
            'tiers.*.name'                    => ['required', 'string', 'max:255'],
            'tiers.*.min_purchase'            => ['required', 'numeric', 'min:0'],
            'tiers.*.max_purchase'            => ['nullable', 'numeric'],
            'tiers.*.earn_percentage'         => ['required', 'numeric', 'min:0', 'max:100'],
            'tiers.*.referral_reward_points'  => ['required', 'integer', 'min:0'],
        ], [
            'tiers.*.earn_percentage.max'     => 'The earn percentage may not be greater than 100%.',
            'tiers.*.earn_percentage.min'     => 'The earn percentage must be at least 0%.',
            'tiers.*.referral_reward_points.min' => 'Referral points must be 0 or more.',
            'tiers.*.min_purchase.min'        => 'Minimum purchase must be 0 or more.',
        ]);

        $submitted = array_values($request->tiers);

        // Check unique names
        $names = array_map(fn ($t) => trim(strtolower($t['name'])), $submitted);
        if (count($names) !== count(array_unique($names))) {
            throw ValidationException::withMessages([
                'tiers' => 'Each tier must have a unique name.',
            ]);
        }

        // Sort by min_purchase ascending
        usort($submitted, fn ($a, $b) => ((float) $a['min_purchase']) <=> ((float) $b['min_purchase']));

        $count = count($submitted);
        $existingCount = MembershipTier::count();
        if ($count !== $existingCount) {
            throw ValidationException::withMessages([
                'tiers' => "Exactly {$existingCount} tiers must be configured (no creating or deleting tiers).",
            ]);
        }

        // 1. First tier starts at 0
        if (round((float) $submitted[0]['min_purchase'], 2) !== 0.0) {
            throw ValidationException::withMessages([
                'tiers' => 'The first tier must start with a minimum purchase of 0.',
            ]);
        }

        // 2. Last tier has max_purchase empty (null or empty string)
        $lastMax = $submitted[$count - 1]['max_purchase'] ?? null;
        if ($lastMax !== null && $lastMax !== '') {
            throw ValidationException::withMessages([
                'tiers' => 'The highest tier must have an empty maximum purchase (no upper limit).',
            ]);
        }

        // 3. Middle tiers and first tier must have max_purchase > min_purchase, and no gaps or overlaps
        for ($i = 0; $i < $count - 1; $i++) {
            $current = $submitted[$i];
            $next = $submitted[$i + 1];

            $currMax = $current['max_purchase'] ?? null;
            if ($currMax === null || $currMax === '') {
                throw ValidationException::withMessages([
                    'tiers' => "Tier '{$current['name']}' must have a maximum purchase amount.",
                ]);
            }

            $currMin = (float) $current['min_purchase'];
            $currMaxVal = (float) $currMax;
            $nextMin = (float) $next['min_purchase'];

            if ($currMaxVal <= $currMin) {
                throw ValidationException::withMessages([
                    'tiers' => "Maximum purchase for '{$current['name']}' must be greater than its minimum purchase.",
                ]);
            }

            $expectedNextMin = round($currMaxVal + 0.01, 2);
            $actualNextMin = round($nextMin, 2);

            if ($actualNextMin < $expectedNextMin) {
                throw ValidationException::withMessages([
                    'tiers' => "Purchase ranges overlap between '{$current['name']}' (max: {$currMaxVal}) and '{$next['name']}' (min: {$nextMin}).",
                ]);
            }

            if ($actualNextMin > $expectedNextMin) {
                throw ValidationException::withMessages([
                    'tiers' => "There is a gap in purchase ranges between '{$current['name']}' (max: {$currMaxVal}) and '{$next['name']}' (min: {$nextMin}). '{$next['name']}' must start at {$expectedNextMin}.",
                ]);
            }
        }

        DB::transaction(function () use ($submitted) {
            foreach ($submitted as $item) {
                $tier = MembershipTier::findOrFail($item['id']);
                $tier->update([
                    'name'                   => $item['name'],
                    'min_purchase'           => $item['min_purchase'],
                    'max_purchase'           => ($item['max_purchase'] !== null && $item['max_purchase'] !== '') ? $item['max_purchase'] : null,
                    'earn_percentage'        => $item['earn_percentage'],
                    'referral_reward_points' => $item['referral_reward_points'],
                ]);
            }
        });

        return redirect()->back()->with('success', 'Membership tiers updated successfully.');
    }

    /**
     * Display milestone bonus points list
     */
    public function milestones(Request $request): View
    {
        $milestones = MembershipMilestone::withCount('awards')->orderBy('threshold')->get();
        return view('admin.membership.milestones', compact('milestones'));
    }

    /**
     * Store a new milestone rule
     */
    public function storeMilestone(Request $request)
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'type'         => ['required', 'in:lifetime_spend,purchase_count'],
            'threshold'    => ['required', 'numeric', 'gt:0'],
            'bonus_points' => ['required', 'integer', 'between:1,1000000'],
            'is_active'    => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        MembershipMilestone::create($validated);

        return redirect()->route('membership.milestones')->with('success', 'Milestone created successfully.');
    }

    /**
     * Update an existing milestone rule
     */
    public function updateMilestone(Request $request, $id)
    {
        $milestone = MembershipMilestone::findOrFail($id);

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'type'         => ['required', 'in:lifetime_spend,purchase_count'],
            'threshold'    => ['required', 'numeric', 'gt:0'],
            'bonus_points' => ['required', 'integer', 'between:1,1000000'],
            'is_active'    => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $milestone->update($validated);

        return redirect()->route('membership.milestones')->with('success', 'Milestone updated successfully.');
    }

    /**
     * Toggle milestone active status
     */
    public function toggleMilestone(Request $request, $id)
    {
        $milestone = MembershipMilestone::findOrFail($id);
        $milestone->update(['is_active' => !$milestone->is_active]);

        return redirect()->route('membership.milestones')->with('success', "Milestone '{$milestone->name}' status updated.");
    }

    /**
     * Delete (soft delete) a milestone rule
     */
    public function destroyMilestone($id)
    {
        $milestone = MembershipMilestone::findOrFail($id);
        $milestone->delete();

        return redirect()->route('membership.milestones')->with('success', 'Milestone deleted successfully.');
    }

    /**
     * Display loyalty settings screen
     */
    public function loyaltySettings(Request $request): View
    {
        $setting = LoyaltySetting::current();
        return view('admin.membership.loyalty-settings', compact('setting'));
    }

    /**
     * Update loyalty settings
     */
    public function updateLoyaltySettings(Request $request)
    {
        $validated = $request->validate([
            'point_value'        => ['required', 'numeric', 'between:0.01,1000'],
            'expiry_months'      => ['required', 'integer', 'between:1,60'],
            'min_redeem_points'  => ['required', 'integer', 'min:0'],
            'max_redeem_percent' => ['required', 'numeric', 'between:1,100'],
        ]);

        $setting = LoyaltySetting::current();
        $setting->update($validated);

        return redirect()->back()->with('success', 'Loyalty settings updated successfully.');
    }
}
