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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MembershipController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:membership_tiers.index')->only(['tiers']);
        $this->middleware('permission:reward_wallets.index')->only(['wallets', 'walletDetails']);
        $this->middleware('permission:reward_ledger.index')->only(['rewardLedger']);
        $this->middleware('permission:referrals.index')->only(['referrals', 'deleteReferral']);
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
            })->whereIn('type', [RewardLedgerType::EARN->value, RewardLedgerType::REFERRAL->value])
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
                        ->whereIn('reward_ledgers.type', [RewardLedgerType::EARN->value, RewardLedgerType::REFERRAL->value])
                        ->where('reward_ledgers.points', '>', 0);
                })
                ->count();
        });

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
                ->whereIn('type', [RewardLedgerType::EARN->value, RewardLedgerType::REFERRAL->value])
                ->where('points', '>', 0)
                ->sum('points');

            // Total redeemed points (absolute value of negative points)
            $customer->total_redeemed = abs(RewardLedger::where('customer_id', $customer->id)
                ->where('type', RewardLedgerType::REDEEM->value)
                ->where('points', '<', 0)
                ->sum('points'));

            // Total expired points (points past their expiry_date)
            $customer->total_expired = RewardLedger::where('customer_id', $customer->id)
                ->whereIn('type', [RewardLedgerType::EARN->value, RewardLedgerType::REFERRAL->value])
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
                ->whereIn('type', [RewardLedgerType::EARN->value, RewardLedgerType::REFERRAL->value])
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
            ->whereIn('type', [RewardLedgerType::EARN->value, RewardLedgerType::REFERRAL->value])
            ->where('points', '>', 0)
            ->sum('points');

        $customer->total_redeemed = abs(RewardLedger::where('customer_id', $customer->id)
            ->where('type', RewardLedgerType::REDEEM->value)
            ->where('points', '<', 0)
            ->sum('points'));

        $customer->total_expired = RewardLedger::where('customer_id', $customer->id)
            ->whereIn('type', [RewardLedgerType::EARN->value, RewardLedgerType::REFERRAL->value])
            ->where('points', '>', 0)
            ->where('expiry_date', '<', now())
            ->sum('points');

        $customer->balance = RewardLedger::where('customer_id', $customer->id)
            ->sum('points');

        $customer->real_available_balance = Customer::find($customer->id)->getAvailableRedeemPoints();

        $customer->expiring_soon = RewardLedger::where('customer_id', $customer->id)
            ->whereIn('type', [RewardLedgerType::EARN->value, RewardLedgerType::REFERRAL->value])
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
}
