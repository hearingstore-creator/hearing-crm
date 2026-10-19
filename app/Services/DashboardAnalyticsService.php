<?php

namespace App\Services;

use App\Classes\Helper;
use App\Enums\LeadStatus;
use App\Enums\TicketStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\PurchaseMaster;
use App\Models\SaleMaster;
use App\Models\Supplier;
use App\Models\Ticket;
use App\Models\User;
use App\Support\DataScope;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Dashboard Phase-1 analytics widgets (read-only).
 *
 * Adds new widgets next to the existing DashboardService without touching it.
 * Scope rules mirror the existing dashboard:
 *   - Branch: vendor users see their own vendor rows; everyone else sees Head Office rows (vendor_id NULL)
 *   - Ownership: users without "view all data" only see records they created
 * Each widget is computed only when the user holds its `dashboard.<key>` permission, and a failure in
 * one widget never breaks the page (it is logged and the card shows a soft error).
 */
class DashboardAnalyticsService
{
    public const PERIODS = [
        'month' => 'This Month',
        '30d'   => 'Last 30 Days',
        '90d'   => 'Last 90 Days',
        'year'  => 'This Year',
        'all'   => 'All Time',
    ];

    public const DEFAULT_PERIOD = 'year';

    /** widget key => builder method */
    protected const WIDGETS = [
        'smart_alerts'            => 'smartAlerts',
        'top_customers'           => 'topCustomers',
        'customer_rfm'            => 'customerRfm',
        'new_vs_returning'        => 'newVsReturning',
        'dormant_customers'       => 'dormantCustomers',
        'membership_breakdown'    => 'membershipBreakdown',
        'points_liability'        => 'pointsLiability',
        'upgrade_due'             => 'upgradeDue',
        'binaural_opportunity'    => 'binauralOpportunity',
        'top_products'            => 'topProducts',
        'gross_profit'            => 'grossProfit',
        'aov_trend'               => 'aovTrend',
        'receivables_aging'       => 'receivablesAging',
        'discount_leakage'        => 'discountLeakage',
        'payment_mix'             => 'paymentMix',
        'branch_leaderboard'      => 'branchLeaderboard',
        'audience_builder'        => 'audienceBuilder',
        'top_doctors'             => 'topDoctors',
        'inactive_doctors'        => 'inactiveDoctors',
        'referral_trend'          => 'referralTrend',
        'dead_stock'              => 'deadStock',
        'branch_stock'            => 'branchStock',
        'supplier_aging'          => 'supplierAging',
        'appointment_utilisation' => 'appointmentUtilisation',
        'ticket_sla'              => 'ticketSla',
        'expense_revenue'         => 'expenseRevenue',
        'cash_today'              => 'cashToday',
        // Phase 2
        'birthdays'               => 'birthdays',
        'customer_geography'      => 'customerGeography',
        'service_due'             => 'serviceDue',
        'test_to_sale_funnel'     => 'testToSaleFunnel',
        'lead_response_time'      => 'leadResponseTime',
        'lost_reasons'            => 'lostReasons',
        'commission_due'          => 'commissionDue',
    ];

    /** Lost-lead reason groups. Used by the lead form quick-picks and the Lost Reasons widget (keyword match on free text). */
    public const LOST_REASONS = [
        'price'       => ['label' => 'Price too high',       'icon' => 'fas fa-tag',          'color' => '#e11d48', 'match' => '/price|expens|cost|budget|afford|money|costly|দাম/iu'],
        'competitor'  => ['label' => 'Bought elsewhere',     'icon' => 'fas fa-store',        'color' => '#7c3aed', 'match' => '/competitor|elsewhere|other (shop|centre|center|clinic)|another|bought/i'],
        'no_need'     => ['label' => 'No longer needed',     'icon' => 'fas fa-ban',          'color' => '#64748b', 'match' => '/no (longer )?need|not need|not interested|no interest/i'],
        'unreachable' => ['label' => 'Not reachable',        'icon' => 'fas fa-phone-slash',  'color' => '#d97706', 'match' => '/reach|respond|switch(ed)? off|no answer|not pick|wrong number/i'],
        'trust'       => ['label' => 'Trust / quality doubt', 'icon' => 'fas fa-shield-alt', 'color' => '#0284c7', 'match' => '/trust|quality|doubt|review|satisf/i'],
        'family'      => ['label' => 'Family decision',      'icon' => 'fas fa-users',        'color' => '#0d9488', 'match' => '/family|son|daughter|husband|wife|decide|decision/i'],
        'later'       => ['label' => 'Will buy later',       'icon' => 'fas fa-clock',        'color' => '#ca8a04', 'match' => '/later|postpone|next (month|year)|future|wait/i'],
    ];

    /** Customer value segments (RFM). Order = display order. */
    public const SEGMENTS = [
        'champions'  => ['label' => 'Champions',  'color' => '#7c3aed', 'icon' => 'fas fa-crown',          'hint' => '3+ purchases, bought in the last 6 months'],
        'loyal'      => ['label' => 'Loyal',      'color' => '#2563eb', 'icon' => 'fas fa-heart',          'hint' => 'Repeat buyers, active in the last 6 months'],
        'new'        => ['label' => 'New',        'color' => '#0d9488', 'icon' => 'fas fa-seedling',       'hint' => 'First purchase in the last 60 days'],
        'occasional' => ['label' => 'Occasional', 'color' => '#64748b', 'icon' => 'fas fa-user',           'hint' => 'One purchase, still within 6 months'],
        'at_risk'    => ['label' => 'At Risk',    'color' => '#d97706', 'icon' => 'fas fa-exclamation',    'hint' => 'No purchase for 6–12 months'],
        'lost'       => ['label' => 'Lost',       'color' => '#e11d48', 'icon' => 'fas fa-user-slash',     'hint' => 'No purchase for over 12 months'],
    ];

    /** Audience-builder segments: key => [label, icon, hint] */
    public const AUDIENCES = [
        'champions'   => ['VIP / Champions',        'fas fa-crown',         'Thank-you offers, referral asks'],
        'loyal'       => ['Loyal customers',        'fas fa-heart',         'Membership upgrade, accessories'],
        'new'         => ['New customers',          'fas fa-seedling',      'Welcome & fitting follow-up'],
        'at_risk'     => ['At-risk customers',      'fas fa-exclamation',   'Check-up reminder, service offer'],
        'lost'        => ['Lost customers',         'fas fa-user-slash',    'Win-back campaign'],
        'dormant'     => ['Dormant 6+ months',      'fas fa-bed',           'Free hearing check invitation'],
        'upgrade_due' => ['Device upgrade due',     'fas fa-sync-alt',      'New model / trade-in offer'],
        'binaural'    => ['Single-device owners',   'fas fa-assistive-listening-systems', 'Second-ear offer'],
        'debtors'     => ['Customers with dues',    'fas fa-hand-holding-usd', 'Payment reminder'],
    ];

    /** Product categories treated as hearing devices (upgrade / binaural widgets). */
    protected const DEVICE_CATEGORY_PATTERN = '/hearing\s*aid|\bbte\b|\bite\b|\bitc\b|\biic\b|\bcic\b|\bric\b|\brie\b/i';

    protected array $memo = [];
    protected string $period = self::DEFAULT_PERIOD;
    protected Carbon $from;
    protected Carbon $to;

    // ==================================================================
    //  Entry point
    // ==================================================================

    public function build(?string $period = null): array
    {
        $this->setPeriod($period);
        $can = fn (string $k) => DataScope::can('dashboard.' . $k);

        $out = [
            'period'       => $this->period,
            'period_label' => self::PERIODS[$this->period],
            'periods'      => self::PERIODS,
            'range'        => $this->period === 'all' ? 'All time' : $this->from->format('d M Y') . ' – ' . $this->to->format('d M Y'),
            'is_company'   => $this->isCompanyAdmin(),
        ];

        foreach (self::WIDGETS as $key => $method) {
            $out[$key] = $can($key) ? $this->safe($key, fn () => $this->{$method}()) : null;
        }

        // Upgrades to existing widgets (gated by the existing widget permission)
        $out['revenue_extended'] = $can('revenue_chart') ? $this->safe('revenue_extended', fn () => $this->revenueExtended()) : null;
        $out['source_roi']       = $can('lead_charts')   ? $this->safe('source_roi', fn () => $this->sourceRoi()) : null;
        $out['lead_temperature'] = $can('lead_pipeline') ? $this->safe('lead_temperature', fn () => $this->leadTemperature()) : null;
        $out['staff_extra']      = $can('performance')   ? $this->safe('staff_extra', fn () => $this->staffExtra()) : null;

        return $out;
    }

    /** Only the named widgets (same permission rule and error handling as build()). */
    public function pick(array $keys, ?string $period = null): array
    {
        $this->setPeriod($period);
        $out = [];
        foreach (array_intersect_key(self::WIDGETS, array_flip($keys)) as $key => $method) {
            $out[$key] = DataScope::can('dashboard.' . $key) ? $this->safe($key, fn () => $this->{$method}()) : null;
        }

        return $out;
    }

    /** Members of an audience segment (used by the dashboard and the CSV export). */
    public function segmentMembers(string $segment): Collection
    {
        $fmt = fn ($r, string $note = '') => [
            'customer_id'   => $r->customer_id,
            'name'          => $r->name ?: 'Unknown',
            'phone'         => $r->phone ?: '',
            'last_purchase' => $r->last_date ?? null,
            'spend'         => (float) ($r->spend ?? 0),
            'orders'        => (int) ($r->orders ?? 0),
            'note'          => $note,
        ];

        if (isset(self::SEGMENTS[$segment])) {
            $median = $this->medianSpend();
            return $this->customerAgg()
                ->filter(fn ($r) => $this->rfmSegment($r, $median) === $segment)
                ->sortByDesc('spend')->values()->map(fn ($r) => $fmt($r, self::SEGMENTS[$segment]['label']));
        }

        $agg = $this->customerAgg()->keyBy('customer_id');

        return match ($segment) {
            'dormant' => $this->customerAgg()->filter(fn ($r) => $r->recency > 180)->sortByDesc('spend')->values()
                ->map(fn ($r) => $fmt($r, $r->recency . ' days since last purchase')),
            'upgrade_due' => $this->upgradeCandidates()->where('stage', 'due')->values()
                ->map(fn ($d) => $fmt((object) array_merge((array) ($agg[$d['customer_id']] ?? []), $d, ['last_date' => $d['date']]), $d['model'] . ' · ' . $d['age_label'])),
            'binaural' => $this->binauralCandidates()->values()
                ->map(fn ($d) => $fmt((object) array_merge((array) ($agg[$d['customer_id']] ?? []), $d, ['last_date' => $d['date']]), $d['model'])),
            'debtors' => collect($this->receivablesRaw()['parties'])->map(function ($p) use ($agg, $fmt) {
                $row = $agg[$p['id']] ?? (object) ['customer_id' => $p['id'], 'name' => $p['name'], 'phone' => $p['phone']];
                return $fmt($row, 'Due ৳' . number_format($p['balance']) . ' · oldest ' . $p['oldest_days'] . ' days');
            }),
            default => collect(),
        };
    }

    // ==================================================================
    //  Period & scope helpers
    // ==================================================================

    protected function setPeriod(?string $period): void
    {
        $this->period = array_key_exists((string) $period, self::PERIODS) ? $period : self::DEFAULT_PERIOD;
        $this->to = now()->endOfDay();
        $this->from = match ($this->period) {
            'month' => now()->startOfMonth(),
            '30d'   => now()->subDays(29)->startOfDay(),
            '90d'   => now()->subDays(89)->startOfDay(),
            'year'  => now()->startOfYear(),
            default => Carbon::create(2000, 1, 1),
        };
    }

    protected function safe(string $key, callable $fn): array
    {
        try {
            return $fn() ?? [];
        } catch (\Throwable $e) {
            Log::error("Dashboard widget [$key] failed: " . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return ['error' => true];
        }
    }

    /** Branch user: the branch login itself or branch staff (users.vendor_id set). */
    protected function isVendor(): bool
    {
        return (bool) auth()->user()?->branchId();
    }

    /** Company-wide admin (Super Admin / view-all) who is not a vendor — may see cross-branch widgets. */
    protected function isCompanyAdmin(): bool
    {
        return DataScope::seesAll() && !$this->isVendor();
    }

    protected function vendorUsers(): Collection
    {
        return $this->memo['vendors'] ??= User::where('type', 'vendor')->orderBy('name')->pluck('name', 'id');
    }

    /** Branch rule identical to Helper::vendorScope. */
    protected function branch(Builder $q, string $col): Builder
    {
        return $this->isVendor() ? $q->where($col, auth()->user()->branchId()) : $q->whereNull($col);
    }

    /** For tables without vendor_id (customer/supplier/doctor ledgers): scope by who created the row. */
    protected function ledgerOwner(Builder $q, string $col): Builder
    {
        if ($this->isVendor() || !DataScope::seesAll()) {
            return $q->where($col, DataScope::uid());
        }
        $vendorIds = $this->vendorUsers()->keys()->all();

        return $vendorIds ? $q->where(fn ($w) => $w->whereNotIn($col, $vendorIds)->orWhereNull($col)) : $q;
    }

    protected function sales(string $a = 'sm'): Builder
    {
        $q = DB::table("sale_masters as $a")->whereNull("$a.deleted_at");
        $this->branch($q, "$a.vendor_id");
        if (!DataScope::seesAll()) {
            $q->where("$a.created_by", DataScope::uid());
        }

        return $q;
    }

    protected function purchases(string $a = 'pm'): Builder
    {
        $q = DB::table("purchase_masters as $a")->whereNull("$a.deleted_at");
        $this->branch($q, "$a.vendor_id");
        if (!DataScope::seesAll()) {
            $q->where("$a.created_by", DataScope::uid());
        }

        return $q;
    }

    protected function inPeriod(Builder $q, string $col): Builder
    {
        return $this->period === 'all' ? $q : $q->whereBetween($col, [$this->from->toDateString(), $this->to->toDateString()]);
    }

    protected function withCustomer(Builder $q, string $a = 'sm'): Builder
    {
        return $q->join('customers as c', 'c.id', '=', "$a.customer_id")->leftJoin('leads as l', 'l.id', '=', 'c.lead_id');
    }

    /** Net line revenue (sale_details.discount is a percentage). */
    protected function lineRevenueSql(string $a = 'sd'): string
    {
        return "($a.qty * $a.mrp_price * (1 - COALESCE($a.discount, 0) / 100))";
    }

    protected function avgCostSub(): Builder
    {
        return DB::table('purchase_details')->whereNull('deleted_at')->where('purchase_price', '>', 0)
            ->groupBy('product_id')->selectRaw('product_id, SUM(qty * purchase_price) / NULLIF(SUM(qty), 0) as avg_cost');
    }

    protected function days($date): int
    {
        return $date ? (int) floor(abs(Carbon::parse($date)->startOfDay()->diffInDays(today()))) : 0;
    }

    protected function months($date): int
    {
        return $date ? (int) floor(abs(Carbon::parse($date)->startOfDay()->diffInMonths(today()))) : 0;
    }

    protected function fmtDate($date): string
    {
        return $date ? Carbon::parse($date)->format('d M Y') : '—';
    }

    protected function pct(float $part, float $whole, int $dp = 1): float
    {
        return $whole > 0 ? round($part / $whole * 100, $dp) : 0.0;
    }

    protected function monthAxis(int $count): Collection
    {
        return collect(range($count - 1, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i)->format('Y-m'));
    }

    protected function monthLabel(string $ym): string
    {
        return Carbon::createFromFormat('Y-m-d', $ym . '-01')->format('M y');
    }

    protected function scopedCustomerIds(): array
    {
        return $this->memo['cust_ids'] ??= DataScope::customers(Customer::query())->pluck('customers.id')->all();
    }

    protected function customerNames(array $ids): Collection
    {
        if (!$ids) {
            return collect();
        }

        return DB::table('customers as c')->leftJoin('leads as l', 'l.id', '=', 'c.lead_id')
            ->whereIn('c.id', $ids)->get(['c.id', 'l.full_name', 'l.phone_number'])->keyBy('id');
    }

    /** All-time per-customer purchase aggregates in scope (memoised; base of several widgets). */
    protected function customerAgg(): Collection
    {
        return $this->memo['cagg'] ??= $this->withCustomer($this->sales())
            ->leftJoin('membership_tiers as mt', 'mt.id', '=', 'c.membership_tier_id')
            ->groupBy('sm.customer_id', 'l.full_name', 'l.phone_number', 'mt.name')
            ->selectRaw('sm.customer_id, l.full_name as name, l.phone_number as phone, mt.name as tier,
                COUNT(*) as orders, SUM(sm.total_amount) as spend, MIN(sm.date) as first_date, MAX(sm.date) as last_date')
            ->get()
            ->map(function ($r) {
                $r->orders = (int) $r->orders;
                $r->spend = (float) $r->spend;
                $r->recency = $this->days($r->last_date);
                $r->age = $this->days($r->first_date);
                return $r;
            });
    }

    protected function medianSpend(): float
    {
        return $this->memo['median'] ??= (float) ($this->customerAgg()->pluck('spend')->median() ?? 0);
    }

    protected function rfmSegment(object $r, float $median): string
    {
        if ($r->recency > 365) return 'lost';
        if ($r->recency > 180) return 'at_risk';
        if ($r->orders >= 3 || ($r->orders >= 2 && $r->spend >= $median * 1.5)) return 'champions';
        if ($r->orders >= 2) return 'loyal';
        if ($r->age <= 60) return 'new';

        return 'occasional';
    }

    protected function customerRow(object $r): array
    {
        return [
            'id'     => $r->customer_id,
            'name'   => $r->name ?: 'Unknown',
            'phone'  => $r->phone ?: '',
            'tier'   => $r->tier ?? null,
            'orders' => (int) $r->orders,
            'spend'  => (float) $r->spend,
            'last'   => $this->fmtDate($r->last_date),
            'days'   => $r->recency ?? $this->days($r->last_date),
        ];
    }

    /**
     * FIFO aging: payments are applied to the oldest charges first; what remains is aged by charge date.
     * $rows must be ordered by date ascending.
     */
    protected function fifoAging(Collection $rows, string $partyCol, string $owedCol, string $paidCol): array
    {
        $buckets = ['0_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, '90_plus' => 0.0];
        $parties = [];

        foreach ($rows->groupBy($partyCol) as $pid => $list) {
            $paid = (float) $list->sum($paidCol);
            $balance = 0.0;
            $oldest = null;

            foreach ($list as $r) {
                $owed = (float) $r->{$owedCol};
                if ($owed <= 0) {
                    continue;
                }
                $use = min($paid, $owed);
                $paid -= $use;
                $left = $owed - $use;
                if ($left <= 0.009) {
                    continue;
                }
                $age = $this->days($r->date);
                $key = $age <= 30 ? '0_30' : ($age <= 60 ? '31_60' : ($age <= 90 ? '61_90' : '90_plus'));
                $buckets[$key] += $left;
                $balance += $left;
                $oldest ??= $age;
            }

            if ($balance > 0.009) {
                $parties[] = ['id' => $pid, 'balance' => round($balance, 2), 'oldest_days' => (int) $oldest];
            }
        }

        usort($parties, fn ($a, $b) => $b['balance'] <=> $a['balance']);

        return [
            'buckets' => array_map(fn ($v) => round($v, 2), $buckets),
            'total'   => round(array_sum($buckets), 2),
            'count'   => count($parties),
            'parties' => $parties,
        ];
    }

    // ==================================================================
    //  1. Customer intelligence
    // ==================================================================

    protected function topCustomers(): array
    {
        $rows = $this->inPeriod($this->withCustomer($this->sales()), 'sm.date')
            ->leftJoin('membership_tiers as mt', 'mt.id', '=', 'c.membership_tier_id')
            ->groupBy('sm.customer_id', 'l.full_name', 'l.phone_number', 'mt.name')
            ->selectRaw('sm.customer_id, l.full_name as name, l.phone_number as phone, mt.name as tier,
                COUNT(*) as orders, SUM(sm.total_amount) as spend, MAX(sm.date) as last_date')
            ->get();

        return [
            'by_spend'  => $rows->sortByDesc('spend')->take(10)->values()->map(fn ($r) => $this->customerRow($r))->all(),
            'by_visits' => $rows->sortByDesc(fn ($r) => [(int) $r->orders, (float) $r->spend])->take(10)->values()->map(fn ($r) => $this->customerRow($r))->all(),
            'buyers'    => $rows->count(),
        ];
    }

    protected function customerRfm(): array
    {
        $agg = $this->customerAgg();
        $median = $this->medianSpend();
        $total = max($agg->count(), 1);
        $grouped = $agg->groupBy(fn ($r) => $this->rfmSegment($r, $median));

        $segments = [];
        foreach (self::SEGMENTS as $key => $meta) {
            $list = $grouped->get($key, collect());
            $segments[] = $meta + [
                'key'     => $key,
                'count'   => $list->count(),
                'revenue' => (float) $list->sum('spend'),
                'share'   => $this->pct($list->count(), $total),
            ];
        }

        return ['segments' => $segments, 'customers' => $agg->count(), 'median_spend' => $median];
    }

    protected function newVsReturning(): array
    {
        $buyers = $this->inPeriod($this->sales(), 'sm.date')
            ->groupBy('sm.customer_id')
            ->selectRaw('sm.customer_id, COUNT(*) as orders, SUM(sm.total_amount) as spend')
            ->get();
        $first = $this->customerAgg()->pluck('first_date', 'customer_id');

        $isNew = fn ($b) => $this->period === 'all'
            ? (int) $b->orders === 1
            : (isset($first[$b->customer_id]) && Carbon::parse($first[$b->customer_id])->gte($this->from->copy()->startOfDay()));

        [$new, $ret] = $buyers->partition($isNew);

        return [
            'new_count'   => $new->count(),
            'new_revenue' => (float) $new->sum('spend'),
            'ret_count'   => $ret->count(),
            'ret_revenue' => (float) $ret->sum('spend'),
            'repeat_rate' => $this->pct($ret->count(), $buyers->count()),
            'total'       => $buyers->count(),
        ];
    }

    protected function dormantCustomers(): array
    {
        $agg = $this->customerAgg();
        $d6 = $agg->filter(fn ($r) => $r->recency > 180);

        return [
            'count_6m'  => $d6->count(),
            'count_12m' => $agg->filter(fn ($r) => $r->recency > 365)->count(),
            'value'     => (float) $d6->sum('spend'),
            'list'      => $d6->sortByDesc('spend')->take(12)->values()->map(fn ($r) => $this->customerRow($r))->all(),
        ];
    }

    protected function membershipBreakdown(): array
    {
        $ids = $this->scopedCustomerIds();
        $tiers = DB::table('membership_tiers')->orderBy('min_purchase')->get(['id', 'name', 'min_purchase']);

        $counts = DB::table('customers')->whereNull('deleted_at')->whereIn('id', $ids)
            ->groupBy('membership_tier_id')->selectRaw('membership_tier_id, COUNT(*) as n')->get()
            ->mapWithKeys(fn ($r) => [(string) ($r->membership_tier_id ?? 'none') => (int) $r->n]);

        $revenue = $this->sales()->join('customers as c', 'c.id', '=', 'sm.customer_id')
            ->groupBy('c.membership_tier_id')->selectRaw('c.membership_tier_id, SUM(sm.total_amount) as rev')->get()
            ->mapWithKeys(fn ($r) => [(string) ($r->membership_tier_id ?? 'none') => (float) $r->rev]);

        $totalRev = max((float) $revenue->sum(), 1);
        $palette = ['#b45309', '#64748b', '#ca8a04', '#7c3aed', '#0d9488', '#2563eb'];

        $rows = $tiers->values()->map(fn ($t, $i) => [
            'name'      => $t->name,
            'color'     => $palette[$i % count($palette)],
            'customers' => $counts[(string) $t->id] ?? 0,
            'revenue'   => $revenue[(string) $t->id] ?? 0,
            'share'     => $this->pct($revenue[(string) $t->id] ?? 0, $totalRev),
        ])->all();

        if (($counts['none'] ?? 0) > 0) {
            $rows[] = ['name' => 'No tier', 'color' => '#cbd5e1', 'customers' => $counts['none'], 'revenue' => $revenue['none'] ?? 0, 'share' => $this->pct($revenue['none'] ?? 0, $totalRev)];
        }

        return [
            'tiers'          => $rows,
            'members'        => array_sum(array_column($rows, 'customers')),
            'upgrades_month' => DB::table('membership_upgrade_histories')->whereIn('customer_id', $ids)
                ->where('upgraded_at', '>=', now()->startOfMonth())->count(),
        ];
    }

    protected function pointsLiability(): array
    {
        $ids = $this->scopedCustomerIds();
        $base = fn () => DB::table('reward_ledgers')->whereNull('deleted_at')->whereIn('customer_id', $ids);

        $earned = (float) $base()->where('points', '>', 0)->sum('points');
        $redeemed = abs((float) $base()->where('points', '<', 0)->sum('points'));
        $expiring = (float) $base()->where('points', '>', 0)
            ->whereBetween('expiry_date', [today()->toDateString(), today()->addDays(30)->toDateString()])->sum('points');

        $holders = $base()->groupBy('customer_id')->selectRaw('customer_id, SUM(points) as bal')
            ->havingRaw('SUM(points) > 0')->orderByDesc('bal')->limit(5)->get();
        $names = $this->customerNames($holders->pluck('customer_id')->all());

        return [
            'earned'      => $earned,
            'redeemed'    => $redeemed,
            'outstanding' => max($earned - $redeemed, 0),
            'expiring_30' => $expiring,
            'redeem_rate' => $this->pct($redeemed, $earned),
            'top_holders' => $holders->map(fn ($h) => [
                'id'     => $h->customer_id,
                'name'   => $names[$h->customer_id]->full_name ?? 'Unknown',
                'phone'  => $names[$h->customer_id]->phone_number ?? '',
                'points' => (float) $h->bal,
            ])->all(),
        ];
    }

    // ==================================================================
    //  2. Hearing-business specific
    // ==================================================================

    protected function deviceProductIds(): array
    {
        return $this->memo['device_ids'] ??= (function () {
            $cats = DB::table('categories')->whereNull('deleted_at')->get(['id', 'category_name'])
                ->filter(fn ($c) => preg_match(self::DEVICE_CATEGORY_PATTERN, (string) $c->category_name))
                ->pluck('id')->all();

            return $cats ? DB::table('products')->whereNull('deleted_at')->whereIn('category_id', $cats)->pluck('id')->all() : [];
        })();
    }

    protected function deviceSales(): Collection
    {
        return $this->memo['device_sales'] ??= (function () {
            $ids = $this->deviceProductIds();
            if (!$ids) {
                return collect();
            }

            return $this->withCustomer($this->sales())
                ->join('sale_details as sd', 'sd.sale_master_id', '=', 'sm.id')->whereNull('sd.deleted_at')
                ->join('products as p', 'p.id', '=', 'sd.product_id')
                ->whereIn('sd.product_id', $ids)
                ->orderBy('sm.date')->orderBy('sm.id')
                ->get(['sm.id as sale_id', 'sm.customer_id', 'sm.date', 'sd.qty', 'p.model', 'l.full_name as name', 'l.phone_number as phone']);
        })();
    }

    /** Latest device per customer with its age; stage = due (3+ yrs) | upcoming (2.5–3 yrs). */
    protected function upgradeCandidates(): Collection
    {
        return $this->memo['upgrade'] ??= $this->deviceSales()->groupBy('customer_id')->map(function ($rows) {
            $last = $rows->last();
            $age = $this->months($last->date);
            $stage = $age >= 36 ? 'due' : ($age >= 30 ? 'upcoming' : null);
            if (!$stage) {
                return null;
            }

            return [
                'customer_id' => $last->customer_id,
                'name'        => $last->name ?: 'Unknown',
                'phone'       => $last->phone ?: '',
                'model'       => $last->model,
                'sale_id'     => $last->sale_id,
                'date'        => $last->date,
                'date_label'  => $this->fmtDate($last->date),
                'age_months'  => $age,
                'age_label'   => intdiv($age, 12) . 'y ' . ($age % 12) . 'm',
                'stage'       => $stage,
            ];
        })->filter()->sortByDesc('age_months')->values();
    }

    /** Customers who own exactly one hearing device in total. */
    protected function binauralCandidates(): Collection
    {
        return $this->memo['binaural'] ??= $this->deviceSales()->groupBy('customer_id')->map(function ($rows) {
            if ((float) $rows->sum('qty') != 1.0) {
                return null;
            }
            $r = $rows->last();

            return [
                'customer_id' => $r->customer_id,
                'name'        => $r->name ?: 'Unknown',
                'phone'       => $r->phone ?: '',
                'model'       => $r->model,
                'sale_id'     => $r->sale_id,
                'date'        => $r->date,
                'date_label'  => $this->fmtDate($r->date),
            ];
        })->filter()->sortByDesc('date')->values();
    }

    protected function upgradeDue(): array
    {
        $all = $this->upgradeCandidates();

        return [
            'due_count'      => $all->where('stage', 'due')->count(),
            'upcoming_count' => $all->where('stage', 'upcoming')->count(),
            'owners'         => $this->deviceSales()->pluck('customer_id')->unique()->count(),
            'list'           => $all->take(12)->all(),
        ];
    }

    protected function binauralOpportunity(): array
    {
        $all = $this->binauralCandidates();
        $owners = $this->deviceSales()->pluck('customer_id')->unique()->count();

        return [
            'count'  => $all->count(),
            'owners' => $owners,
            'share'  => $this->pct($all->count(), $owners),
            'list'   => $all->take(12)->all(),
        ];
    }

    // ==================================================================
    //  3. Sales & revenue
    // ==================================================================

    protected function lineBase(): Builder
    {
        return $this->inPeriod($this->sales(), 'sm.date')
            ->join('sale_details as sd', 'sd.sale_master_id', '=', 'sm.id')->whereNull('sd.deleted_at')
            ->join('products as p', 'p.id', '=', 'sd.product_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('categories as cat', 'cat.id', '=', 'p.category_id');
    }

    protected function topProducts(): array
    {
        $rev = $this->lineRevenueSql();

        $products = $this->lineBase()->groupBy('p.id', 'p.model', 'cat.category_name')
            ->selectRaw("p.id, p.model as name, cat.category_name as category, SUM(sd.qty) as qty, SUM($rev) as revenue")
            ->orderByDesc('revenue')->limit(8)->get();

        $brands = $this->lineBase()->groupBy('b.brand_name')
            ->selectRaw("COALESCE(b.brand_name, 'Unbranded') as name, SUM(sd.qty) as qty, SUM($rev) as revenue")
            ->orderByDesc('revenue')->limit(8)->get();

        $map = fn ($rows) => $rows->map(fn ($r) => [
            'name' => $r->name ?: 'N/A', 'category' => $r->category ?? null,
            'qty' => (int) $r->qty, 'revenue' => (float) $r->revenue,
        ])->all();

        return ['products' => $map($products), 'brands' => $map($brands)];
    }

    protected function grossProfit(): array
    {
        $rev = $this->lineRevenueSql();
        $unitCost = 'COALESCE(NULLIF(sd.purchase_price, 0), pc.avg_cost)';
        $base = fn () => $this->lineBase()->leftJoinSub($this->avgCostSub(), 'pc', 'pc.product_id', '=', 'sd.product_id');

        $t = $base()->selectRaw("SUM($rev) as revenue, SUM(sd.qty * COALESCE($unitCost, 0)) as cost,
                SUM(CASE WHEN $unitCost IS NULL THEN 1 ELSE 0 END) as missing")->first();

        $brands = $base()->groupBy('b.brand_name')
            ->selectRaw("COALESCE(b.brand_name, 'Unbranded') as name, SUM($rev) as revenue, SUM(sd.qty * COALESCE($unitCost, 0)) as cost")
            ->get()
            ->map(fn ($r) => [
                'name'    => $r->name,
                'revenue' => (float) $r->revenue,
                'profit'  => (float) $r->revenue - (float) $r->cost,
                'margin'  => $this->pct((float) $r->revenue - (float) $r->cost, (float) $r->revenue),
            ])->sortByDesc('profit')->take(6)->values()->all();

        $service = (float) $this->inPeriod($this->sales(), 'sm.date')
            ->join('service_details as sv', 'sv.sale_master_id', '=', 'sm.id')->whereNull('sv.deleted_at')
            ->selectRaw('SUM(sv.qty * sv.service_price * (1 - COALESCE(sv.discount, 0) / 100)) as v')->value('v');

        $revenue = (float) ($t->revenue ?? 0);
        $cost = (float) ($t->cost ?? 0);

        return [
            'revenue'         => $revenue,
            'cost'            => $cost,
            'profit'          => $revenue - $cost,
            'margin'          => $this->pct($revenue - $cost, $revenue),
            'service_revenue' => $service,
            'missing_cost'    => (int) ($t->missing ?? 0),
            'brands'          => $brands,
        ];
    }

    protected function aovTrend(): array
    {
        $axis = $this->monthAxis(12);
        $rows = $this->sales()->where('sm.date', '>=', now()->startOfMonth()->subMonths(11)->toDateString())
            ->groupByRaw("DATE_FORMAT(sm.date, '%Y-%m')")
            ->selectRaw("DATE_FORMAT(sm.date, '%Y-%m') as ym, COUNT(*) as n, SUM(sm.total_amount) as total")
            ->get()->keyBy('ym');

        $series = $axis->map(fn ($m) => [
            'month'  => $this->monthLabel($m),
            'aov'    => isset($rows[$m]) && $rows[$m]->n > 0 ? round($rows[$m]->total / $rows[$m]->n, 2) : 0,
            'orders' => (int) ($rows[$m]->n ?? 0),
        ])->values();

        $cur = $series->last()['aov'] ?? 0;
        $prev = $series->count() > 1 ? $series[$series->count() - 2]['aov'] : 0;
        $all = $this->sales()->selectRaw('COUNT(*) as n, SUM(sm.total_amount) as t')->first();

        return [
            'series'   => $series->all(),
            'current'  => $cur,
            'previous' => $prev,
            'change'   => $prev > 0 ? round(($cur - $prev) / $prev * 100, 1) : 0,
            'lifetime' => ($all->n ?? 0) > 0 ? round($all->t / $all->n, 2) : 0,
        ];
    }

    protected function receivablesRaw(): array
    {
        return $this->memo['receivables'] ??= (function () {
            $custIds = $this->sales()->distinct()->pluck('sm.customer_id')->filter()->all();
            if (!$custIds) {
                return ['buckets' => ['0_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0], 'total' => 0, 'count' => 0, 'parties' => []];
            }

            $rows = $this->ledgerOwner(DB::table('customer_ledgers')->whereNull('deleted_at')->whereIn('customer_id', $custIds), 'created_by')
                ->orderBy('date')->orderBy('id')->get(['customer_id', 'date', 'debit', 'credit']);

            $res = $this->fifoAging($rows, 'customer_id', 'debit', 'credit');
            $names = $this->customerNames(array_column($res['parties'], 'id'));
            $res['parties'] = array_map(fn ($p) => $p + [
                'name'  => $names[$p['id']]->full_name ?? 'Unknown',
                'phone' => $names[$p['id']]->phone_number ?? '',
            ], $res['parties']);

            return $res;
        })();
    }

    protected function receivablesAging(): array
    {
        $r = $this->receivablesRaw();
        $r['top'] = array_slice($r['parties'], 0, 8);
        unset($r['parties']);

        return $r;
    }

    protected function discountLeakage(): array
    {
        $t = $this->inPeriod($this->sales(), 'sm.date')->selectRaw('
                SUM(sm.total_discount) as disc, SUM(sm.total_amount) as net, COUNT(*) as n,
                SUM(CASE WHEN sm.total_discount > 0 THEN 1 ELSE 0 END) as discounted,
                SUM(CASE WHEN sm.total_discount > 0.1 * (sm.total_amount + sm.total_discount) THEN 1 ELSE 0 END) as heavy')->first();

        $staff = $this->inPeriod($this->sales(), 'sm.date')->leftJoin('users as u', 'u.id', '=', 'sm.created_by')
            ->groupBy('sm.created_by', 'u.name')
            ->selectRaw('u.name, SUM(sm.total_discount) as disc, SUM(sm.total_amount + sm.total_discount) as gross, COUNT(*) as n')
            ->havingRaw('SUM(sm.total_discount) > 0')
            ->orderByDesc('disc')->limit(6)->get()
            ->map(fn ($r) => ['name' => $r->name ?? 'Unknown', 'discount' => (float) $r->disc, 'invoices' => (int) $r->n, 'pct' => $this->pct((float) $r->disc, (float) $r->gross)])
            ->all();

        $disc = (float) ($t->disc ?? 0);
        $gross = $disc + (float) ($t->net ?? 0);

        return [
            'discount'   => $disc,
            'gross'      => $gross,
            'pct'        => $this->pct($disc, $gross),
            'invoices'   => (int) ($t->n ?? 0),
            'discounted' => (int) ($t->discounted ?? 0),
            'heavy'      => (int) ($t->heavy ?? 0),
            'staff'      => $staff,
        ];
    }

    protected function paymentMix(): array
    {
        $labels = Helper::paymentType();
        $rows = $this->inPeriod($this->sales(), 'sm.date')->groupBy('sm.payment_id')
            ->selectRaw('sm.payment_id, COUNT(*) as n, SUM(sm.total_amount) as total, SUM(sm.paid) as paid')->get();
        $sum = max((float) $rows->sum('total'), 1);

        return [
            'methods' => $rows->map(fn ($r) => [
                'label' => str_replace(' Payment', '', $labels[(string) $r->payment_id] ?? 'Other'),
                'count' => (int) $r->n,
                'total' => (float) $r->total,
                'paid'  => (float) $r->paid,
                'share' => $this->pct((float) $r->total, $sum),
            ])->sortByDesc('total')->values()->all(),
        ];
    }

    /** Cross-branch ranking — company admins only. */
    protected function branchLeaderboard(): array
    {
        if (!$this->isCompanyAdmin()) {
            return ['restricted' => true];
        }

        $m0 = now()->startOfMonth()->toDateString();
        $lm0 = now()->startOfMonth()->subMonth()->toDateString();
        $lm1 = now()->startOfMonth()->subDay()->toDateString();

        $sales = DB::table('sale_masters')->whereNull('deleted_at')->groupBy('vendor_id')
            ->selectRaw('vendor_id,
                SUM(CASE WHEN date >= ? THEN total_amount ELSE 0 END) as this_month,
                SUM(CASE WHEN date BETWEEN ? AND ? THEN total_amount ELSE 0 END) as last_month,
                SUM(CASE WHEN date >= ? THEN 1 ELSE 0 END) as inv_month,
                SUM(total_amount) as all_time', [$m0, $lm0, $lm1, $m0])
            ->get()->keyBy(fn ($r) => (string) ($r->vendor_id ?? 'hq'));

        $leads = DB::table('leads')->whereNull('deleted_at')->groupBy('vendor_id')
            ->selectRaw('vendor_id, COUNT(*) as total, SUM(CASE WHEN lead_status = ? THEN 1 ELSE 0 END) as won', [LeadStatus::WON->value])
            ->get()->keyBy(fn ($r) => (string) ($r->vendor_id ?? 'hq'));

        $branches = collect(['hq' => 'Head Office'])->union($this->vendorUsers()->mapWithKeys(fn ($n, $id) => [(string) $id => $n]));

        $rows = $branches->map(function ($name, $key) use ($sales, $leads) {
            $s = $sales[$key] ?? null;
            $l = $leads[$key] ?? null;
            $tm = (float) ($s->this_month ?? 0);
            $lm = (float) ($s->last_month ?? 0);

            return [
                'name'       => $name,
                'this_month' => $tm,
                'last_month' => $lm,
                'growth'     => $lm > 0 ? round(($tm - $lm) / $lm * 100, 1) : null,
                'invoices'   => (int) ($s->inv_month ?? 0),
                'all_time'   => (float) ($s->all_time ?? 0),
                'leads'      => (int) ($l->total ?? 0),
                'conversion' => $this->pct((float) ($l->won ?? 0), (float) ($l->total ?? 0)),
            ];
        })->sortByDesc(fn ($r) => [$r['this_month'], $r['all_time']])->values();

        return ['rows' => $rows->all(), 'max' => max((float) $rows->max('this_month'), 1)];
    }

    // ==================================================================
    //  4. Marketing
    // ==================================================================

    protected function audienceBuilder(): array
    {
        $out = [];
        foreach (self::AUDIENCES as $key => [$label, $icon, $hint]) {
            $members = $this->segmentMembers($key);
            $out[] = [
                'key'    => $key,
                'label'  => $label,
                'icon'   => $icon,
                'hint'   => $hint,
                'count'  => $members->count(),
                'phones' => $members->pluck('phone')->filter()->unique()->take(1000)->values()->all(),
            ];
        }

        return ['segments' => $out];
    }

    // ==================================================================
    //  5. Doctor & referral
    // ==================================================================

    protected function doctors(): Builder
    {
        return $this->branch(DB::table('doctors')->whereNull('deleted_at'), 'vendor_id');
    }

    protected function topDoctors(): array
    {
        $rows = $this->inPeriod($this->sales(), 'sm.date')
            ->join('doctors as d', 'd.id', '=', 'sm.reference_by')
            ->groupBy('d.id', 'd.name', 'd.contact_number')
            ->selectRaw('d.id, d.name, d.contact_number as phone, COUNT(*) as referrals,
                COUNT(DISTINCT sm.customer_id) as patients, SUM(sm.total_amount) as revenue, MAX(sm.date) as last_date')
            ->orderByDesc('revenue')->limit(8)->get();

        $due = $rows->isEmpty() ? collect() : DB::table('doctor_ledgers')->whereNull('deleted_at')
            ->whereIn('doctor_id', $rows->pluck('id'))->groupBy('doctor_id')
            ->selectRaw('doctor_id, SUM(credit) - SUM(debit) as due')->pluck('due', 'doctor_id');

        return [
            'list' => $rows->map(fn ($r) => [
                'id'        => $r->id,
                'name'      => $r->name,
                'phone'     => $r->phone,
                'referrals' => (int) $r->referrals,
                'patients'  => (int) $r->patients,
                'revenue'   => (float) $r->revenue,
                'last'      => $this->fmtDate($r->last_date),
                'due'       => (float) ($due[$r->id] ?? 0),
            ])->all(),
            'total_revenue' => (float) $rows->sum('revenue'),
        ];
    }

    protected function inactiveDoctors(): array
    {
        $doctors = $this->doctors()->get(['id', 'name', 'contact_number']);
        $last = $this->sales()->whereNotNull('sm.reference_by')->groupBy('sm.reference_by')
            ->selectRaw('sm.reference_by, MAX(sm.date) as last_date, COUNT(*) as n')->get()->keyBy('reference_by');

        $inactive = $doctors->filter(fn ($d) => isset($last[$d->id]) && $this->days($last[$d->id]->last_date) > 90)
            ->map(fn ($d) => [
                'id'        => $d->id,
                'name'      => $d->name,
                'phone'     => $d->contact_number,
                'referrals' => (int) $last[$d->id]->n,
                'last'      => $this->fmtDate($last[$d->id]->last_date),
                'days'      => $this->days($last[$d->id]->last_date),
            ])->sortByDesc('referrals')->values();

        $active = $doctors->filter(fn ($d) => isset($last[$d->id]) && $this->days($last[$d->id]->last_date) <= 90)->count();

        return [
            'total'    => $doctors->count(),
            'active'   => $active,
            'inactive' => $inactive->count(),
            'never'    => $doctors->filter(fn ($d) => !isset($last[$d->id]))->count(),
            'list'     => $inactive->take(10)->all(),
        ];
    }

    protected function referralTrend(): array
    {
        $axis = $this->monthAxis(6);
        $rows = $this->sales()->join('doctors as d', 'd.id', '=', 'sm.reference_by')
            ->where('sm.date', '>=', now()->startOfMonth()->subMonths(5)->toDateString())
            ->groupBy('d.id', 'd.name')->groupByRaw("DATE_FORMAT(sm.date, '%Y-%m')")
            ->selectRaw("d.id, d.name, DATE_FORMAT(sm.date, '%Y-%m') as ym, COUNT(*) as n")
            ->get();

        $doctors = $rows->groupBy('id')->map(function ($list) use ($axis) {
            $byMonth = $list->pluck('n', 'ym');
            $counts = $axis->map(fn ($m) => (int) ($byMonth[$m] ?? 0))->values()->all();

            return ['name' => $list->first()->name, 'counts' => $counts, 'total' => array_sum($counts)];
        })->sortByDesc('total')->take(6)->values();

        return [
            'months'  => $axis->map(fn ($m) => Carbon::createFromFormat('Y-m-d', $m . '-01')->format('M'))->all(),
            'doctors' => $doctors->all(),
            'max'     => max((int) $doctors->flatMap(fn ($d) => $d['counts'])->max(), 1),
        ];
    }

    // ==================================================================
    //  6. Operations & inventory
    // ==================================================================

    protected function deadStock(): array
    {
        $stock = $this->branch(DB::table('stocks')->whereNull('deleted_at'), 'vendor_id')
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(in_qty - out_qty) as qty, MIN(CASE WHEN in_qty > 0 THEN date END) as first_in')
            ->havingRaw('SUM(in_qty - out_qty) > 0')->get()->keyBy('product_id');

        if ($stock->isEmpty()) {
            return ['count' => 0, 'units' => 0, 'value' => 0, 'list' => []];
        }

        $lastSale = $this->branch(DB::table('sale_details as sd')->join('sale_masters as sm', 'sm.id', '=', 'sd.sale_master_id')
                ->whereNull('sd.deleted_at')->whereNull('sm.deleted_at'), 'sm.vendor_id')
            ->whereIn('sd.product_id', $stock->keys())
            ->groupBy('sd.product_id')->selectRaw('sd.product_id, MAX(sm.date) as last_date')->pluck('last_date', 'product_id');

        $cost = $this->avgCostSub()->whereIn('product_id', $stock->keys())->pluck('avg_cost', 'product_id');
        $products = DB::table('products as p')->leftJoin('categories as cat', 'cat.id', '=', 'p.category_id')
            ->whereIn('p.id', $stock->keys())->get(['p.id', 'p.model', 'cat.category_name'])->keyBy('id');

        $dead = $stock->filter(function ($s) use ($lastSale) {
            $ref = $lastSale[$s->product_id] ?? $s->first_in;
            return $ref && $this->days($ref) > 90;
        })->map(fn ($s) => [
            'id'       => $s->product_id,
            'name'     => $products[$s->product_id]->model ?? 'N/A',
            'category' => $products[$s->product_id]->category_name ?? '—',
            'qty'      => (int) $s->qty,
            'value'    => round((float) $s->qty * (float) ($cost[$s->product_id] ?? 0), 2),
            'last'     => isset($lastSale[$s->product_id]) ? $this->fmtDate($lastSale[$s->product_id]) : 'Never sold',
            'days'     => $this->days($lastSale[$s->product_id] ?? $s->first_in),
        ])->sortByDesc('value')->values();

        return [
            'count' => $dead->count(),
            'units' => (int) $dead->sum('qty'),
            'value' => (float) $dead->sum('value'),
            'list'  => $dead->take(10)->all(),
        ];
    }

    /** Inventory value per branch — company admins only. */
    protected function branchStock(): array
    {
        if (!$this->isCompanyAdmin()) {
            return ['restricted' => true];
        }

        $per = DB::table('stocks')->whereNull('deleted_at')->groupBy('vendor_id', 'product_id')
            ->selectRaw('vendor_id, product_id, SUM(in_qty - out_qty) as qty');

        $rows = DB::query()->fromSub($per, 's')->leftJoinSub($this->avgCostSub(), 'pc', 'pc.product_id', '=', 's.product_id')
            ->where('s.qty', '>', 0)->groupBy('s.vendor_id')
            ->selectRaw('s.vendor_id, COUNT(*) as products, SUM(s.qty) as units, SUM(s.qty * COALESCE(pc.avg_cost, 0)) as value')
            ->get()->keyBy(fn ($r) => (string) ($r->vendor_id ?? 'hq'));

        $branches = collect(['hq' => 'Head Office'])->union($this->vendorUsers()->mapWithKeys(fn ($n, $id) => [(string) $id => $n]));
        $list = $branches->map(fn ($name, $key) => [
            'name'     => $name,
            'products' => (int) ($rows[$key]->products ?? 0),
            'units'    => (int) ($rows[$key]->units ?? 0),
            'value'    => (float) ($rows[$key]->value ?? 0),
        ])->sortByDesc('value')->values();

        return ['rows' => $list->all(), 'total' => (float) $list->sum('value'), 'max' => max((float) $list->max('value'), 1)];
    }

    protected function supplierAging(): array
    {
        $supIds = Supplier::query()->pluck('id')->all();   // global scope = current branch
        if (!$supIds) {
            return ['buckets' => ['0_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0], 'total' => 0, 'count' => 0, 'top' => []];
        }

        $rows = DB::table('supplier_ledgers')->whereNull('deleted_at')->whereIn('supplier_id', $supIds)
            ->orderBy('date')->orderBy('id')->get(['supplier_id', 'date', 'debit', 'credit']);

        $res = $this->fifoAging($rows, 'supplier_id', 'credit', 'debit');
        $names = DB::table('suppliers')->whereIn('id', array_column($res['parties'], 'id'))->get(['id', 'supplier_name', 'phone', 'mobile_no'])->keyBy('id');

        $res['top'] = array_map(fn ($p) => $p + [
            'name'  => $names[$p['id']]->supplier_name ?? 'Unknown',
            'phone' => $names[$p['id']]->mobile_no ?? ($names[$p['id']]->phone ?? ''),
        ], array_slice($res['parties'], 0, 8));
        unset($res['parties']);

        return $res;
    }

    protected function appointmentUtilisation(): array
    {
        $base = function () {
            $q = DataScope::appointments(Appointment::query())->toBase();
            if ($this->period !== 'all') {
                $q->whereBetween('appointments.appointment_date', [$this->from, $this->to]);
            }
            return $q;
        };

        $byStatus = $base()->groupBy('appointments.status')->selectRaw('appointments.status as st, COUNT(*) as n')->pluck('n', 'st');
        $total = (int) $byStatus->sum();
        $completed = (int) ($byStatus['completed'] ?? 0);
        $cancelled = (int) ($byStatus['cancelled'] ?? 0);
        $missed = $base()->where('appointments.status', 'scheduled')->where('appointments.appointment_date', '<', today())->count();
        $upcoming = DataScope::appointments(Appointment::query())->toBase()->where('appointments.status', 'scheduled')
            ->whereBetween('appointments.appointment_date', [now(), now()->addDays(7)->endOfDay()])->count();
        $closed = $completed + $cancelled + $missed;

        return [
            'total'      => $total,
            'completed'  => $completed,
            'cancelled'  => $cancelled,
            'missed'     => $missed,
            'scheduled'  => max($total - $closed, 0),
            'upcoming_7' => $upcoming,
            'show_rate'  => $this->pct($completed, $closed),
        ];
    }

    protected function ticketSla(): array
    {
        $active = [TicketStatus::OPEN->value, TicketStatus::IN_PROGRESS->value, TicketStatus::PENDING->value, TicketStatus::ON_HOLD->value];
        $base = fn () => DataScope::assignable(Ticket::query())->toBase();
        $slaHours = 72;

        $open = $base()->whereIn('tickets.status', $active);
        $openCount = (clone $open)->count();
        $overdue = (clone $open)->where('tickets.created_at', '<', now()->subHours($slaHours))->count();
        $avg = $base()->whereNotNull('tickets.resolved_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, tickets.created_at, tickets.resolved_at)) as h')->value('h');
        $resolvedMonth = $base()->whereNotNull('tickets.resolved_at')->where('tickets.resolved_at', '>=', now()->startOfMonth())->count();
        $byPriority = (clone $open)->groupBy('tickets.priority')->selectRaw('tickets.priority as p, COUNT(*) as n')->pluck('n', 'p');

        $oldest = (clone $open)->orderBy('tickets.created_at')->limit(5)->get(['tickets.id', 'tickets.subject', 'tickets.priority', 'tickets.status', 'tickets.created_at'])
            ->map(fn ($t) => [
                'id'       => $t->id,
                'subject'  => $t->subject,
                'priority' => $t->priority,
                'status'   => $t->status,
                'age'      => Carbon::parse($t->created_at)->diffForHumans(null, true),
                'overdue'  => Carbon::parse($t->created_at)->lt(now()->subHours($slaHours)),
            ])->all();

        return [
            'open'           => $openCount,
            'overdue'        => $overdue,
            'sla_hours'      => $slaHours,
            'avg_hours'      => $avg !== null ? round((float) $avg, 1) : null,
            'resolved_month' => $resolvedMonth,
            'by_priority'    => $byPriority->all(),
            'oldest'         => $oldest,
        ];
    }

    protected function ledgers(): Builder
    {
        $q = $this->branch(DB::table('ledgers')->whereNull('deleted_at'), 'vendor_id');
        if (!DataScope::seesAll()) {
            $q->where('created_by', DataScope::uid());
        }

        return $q;
    }

    protected function expenseRevenue(): array
    {
        $axis = $this->monthAxis(12);
        $from = now()->startOfMonth()->subMonths(11)->toDateString();
        $ym = "DATE_FORMAT(%s, '%%Y-%%m')";

        $rev = $this->sales()->where('sm.date', '>=', $from)->groupByRaw(sprintf($ym, 'sm.date'))
            ->selectRaw(sprintf($ym, 'sm.date') . ' as ym, SUM(sm.total_amount) as v')->pluck('v', 'ym');
        $pur = $this->purchases()->where('pm.date', '>=', $from)->groupByRaw(sprintf($ym, 'pm.date'))
            ->selectRaw(sprintf($ym, 'pm.date') . ' as ym, SUM(pm.total_amount) as v')->pluck('v', 'ym');
        $exp = $this->ledgers()->where('amount_type', Helper::$expense)->where('type', '!=', Helper::$purchase)
            ->where('date', '>=', $from)->groupByRaw(sprintf($ym, 'date'))
            ->selectRaw(sprintf($ym, 'date') . ' as ym, SUM(expense_amount) as v')->pluck('v', 'ym');

        $series = $axis->map(function ($m) use ($rev, $pur, $exp) {
            $r = (float) ($rev[$m] ?? 0);
            $p = (float) ($pur[$m] ?? 0);
            $e = (float) ($exp[$m] ?? 0);
            return ['month' => $this->monthLabel($m), 'revenue' => $r, 'purchases' => $p, 'expenses' => $e, 'net' => $r - $p - $e];
        })->values();

        $c = collect($series);

        return [
            'series'    => $series->all(),
            'revenue'   => (float) $c->sum('revenue'),
            'purchases' => (float) $c->sum('purchases'),
            'expenses'  => (float) $c->sum('expenses'),
            'net'       => (float) $c->sum('net'),
        ];
    }

    protected function cashToday(): array
    {
        $calc = function (string $from, string $to) {
            $in = (float) $this->ledgerOwner(DB::table('customer_ledgers')->whereNull('deleted_at'), 'created_by')
                ->where('type', '!=', Helper::$returnToCustomer)->whereBetween('date', [$from, $to])->sum('credit');
            $otherIn = (float) $this->ledgers()->where('amount_type', Helper::$income)->where('type', '!=', Helper::$sales)
                ->whereBetween('date', [$from, $to])->sum('income_amount');

            $supIds = Supplier::query()->pluck('id')->all();
            $supplier = $supIds ? (float) DB::table('supplier_ledgers')->whereNull('deleted_at')->whereIn('supplier_id', $supIds)
                ->whereBetween('date', [$from, $to])->sum('debit') : 0.0;
            $doctor = (float) $this->ledgerOwner(DB::table('doctor_ledgers')->whereNull('deleted_at'), 'created_by')
                ->where('type', 'Payment')->whereBetween('date', [$from, $to])->sum('debit');
            $expense = (float) $this->ledgers()->where('amount_type', Helper::$expense)->where('type', '!=', Helper::$purchase)
                ->whereBetween('date', [$from, $to])->sum('expense_amount');

            return [
                'in'        => $in + $otherIn,
                'customers' => $in,
                'other_in'  => $otherIn,
                'out'       => $supplier + $doctor + $expense,
                'suppliers' => $supplier,
                'doctors'   => $doctor,
                'expenses'  => $expense,
                'net'       => $in + $otherIn - $supplier - $doctor - $expense,
            ];
        };

        $today = today()->toDateString();
        $week = collect(range(6, 0))->map(function ($i) use ($calc) {
            $d = today()->subDays($i);
            $r = $calc($d->toDateString(), $d->toDateString());
            return ['day' => $d->format('D'), 'in' => $r['in'], 'out' => $r['out']];
        })->values();

        return [
            'today' => $calc($today, $today),
            'month' => $calc(now()->startOfMonth()->toDateString(), $today),
            'week'  => $week->all(),
            'max'   => max((float) $week->max('in'), (float) $week->max('out'), 1),
        ];
    }

    // ==================================================================
    //  Phase 2 widgets
    // ==================================================================

    /** People (leads/customers in scope) with a birthday in the next 30 days. */
    protected function birthdayPeople(): Collection
    {
        return $this->memo['birthdays'] ??= (function () {
            $today = today();

            return DataScope::leads(Lead::query())->toBase()
                ->whereNotNull('leads.date_of_birth')
                ->leftJoin('customers as c_bd', fn ($j) => $j->on('c_bd.lead_id', '=', 'leads.id')->whereNull('c_bd.deleted_at'))
                ->get(['leads.id', 'leads.full_name', 'leads.phone_number', 'leads.date_of_birth', 'c_bd.id as customer_id'])
                ->unique('id')
                ->map(function ($r) use ($today) {
                    $dob = Carbon::parse($r->date_of_birth)->startOfDay();
                    $next = Carbon::create($today->year, $dob->month, min($dob->day, Carbon::create($today->year, $dob->month, 1)->daysInMonth))->startOfDay();
                    if ($next->lt($today)) {
                        $next = Carbon::create($today->year + 1, $dob->month, min($dob->day, Carbon::create($today->year + 1, $dob->month, 1)->daysInMonth))->startOfDay();
                    }
                    $in = (int) $today->diffInDays($next);

                    return [
                        'lead_id'     => $r->id,
                        'customer_id' => $r->customer_id,
                        'name'        => $r->full_name ?: 'Unknown',
                        'phone'       => $r->phone_number ?: '',
                        'date_label'  => $next->format('D, d M'),
                        'turning'     => $next->year - $dob->year,
                        'in_days'     => $in,
                        'url'         => $r->customer_id ? url('admin/customers/' . $r->customer_id) : url('admin/leads/' . $r->id),
                    ];
                })
                ->filter(fn ($p) => $p['in_days'] <= 30)
                ->sortBy('in_days')->values();
        })();
    }

    /** 1.7 Birthdays this week (with SMS / call actions). */
    protected function birthdays(): array
    {
        $all = $this->birthdayPeople();
        $week = $all->where('in_days', '<=', 6)->values();
        $withDob = DataScope::leads(Lead::query())->toBase()->whereNotNull('leads.date_of_birth')->count();
        $totalLeads = DataScope::leads(Lead::query())->toBase()->count();

        return [
            'today'      => $all->where('in_days', 0)->count(),
            'week_count' => $week->count(),
            'month'      => $all->count(),
            'list'       => $week->take(15)->all(),
            'phones'     => $week->pluck('phone')->filter()->unique()->values()->all(),
            'coverage'   => $this->pct($withDob, $totalLeads),
            'with_dob'   => $withDob,
        ];
    }

    /** 1.8 Customers, leads and revenue by district. */
    protected function customerGeography(): array
    {
        $ids = $this->scopedCustomerIds();
        $key = fn ($id) => (string) ($id ?? 'none');

        $cust = DB::table('customers as c')->leftJoin('leads as l', 'l.id', '=', 'c.lead_id')
            ->whereNull('c.deleted_at')->whereIn('c.id', $ids)
            ->groupBy('l.district_id')->selectRaw('l.district_id as did, COUNT(*) as n')->get()
            ->mapWithKeys(fn ($r) => [$key($r->did) => (int) $r->n]);

        $rev = $this->inPeriod($this->withCustomer($this->sales()), 'sm.date')
            ->groupBy('l.district_id')->selectRaw('l.district_id as did, SUM(sm.total_amount) as rev')->get()
            ->mapWithKeys(fn ($r) => [$key($r->did) => (float) $r->rev]);

        $leads = DataScope::leads(Lead::query())->toBase()
            ->groupBy('leads.district_id')->selectRaw('leads.district_id as did, COUNT(*) as n')->get()
            ->mapWithKeys(fn ($r) => [$key($r->did) => (int) $r->n]);

        $keys = $cust->keys()->merge($rev->keys())->merge($leads->keys())->unique();
        $names = DB::table('districts')->whereIn('id', $keys->reject(fn ($k) => $k === 'none')->all())->pluck('name', 'id');

        $rows = $keys->map(fn ($k) => [
            'name'      => $k === 'none' ? 'Not set' : ($names[(int) $k] ?? 'Unknown'),
            'customers' => $cust[$k] ?? 0,
            'leads'     => $leads[$k] ?? 0,
            'revenue'   => $rev[$k] ?? 0.0,
            'unset'     => $k === 'none',
        ])->sortByDesc(fn ($r) => [$r['unset'] ? 0 : 1, $r['revenue'], $r['customers'], $r['leads']])->values();

        $named = $rows->where('unset', false);

        return [
            'rows'      => $rows->take(10)->all(),
            'districts' => $named->count(),
            'top'       => $named->first()['name'] ?? null,
            'unset'     => (int) ($rows->firstWhere('unset', true)['customers'] ?? 0),
            'max'       => max((float) $rows->max('revenue'), 1),
            'max_c'     => max((int) $rows->max('customers'), 1),
        ];
    }

    /** Latest serviceable device per customer with its next service date. */
    protected function serviceCandidates(): Collection
    {
        return $this->memo['service'] ??= (function () {
            $deviceIds = $this->deviceProductIds();

            $lines = $this->withCustomer($this->sales())
                ->join('sale_details as sd', 'sd.sale_master_id', '=', 'sm.id')->whereNull('sd.deleted_at')
                ->join('products as p', 'p.id', '=', 'sd.product_id')
                ->where(fn ($w) => $w->whereNotNull('p.service_interval_months')->orWhereIn('p.id', $deviceIds ?: [0]))
                ->orderBy('sm.date')->orderBy('sm.id')
                ->get(['sm.id as sale_id', 'sm.customer_id', 'c.lead_id', 'sm.date', 'p.model', 'p.service_interval_months as iv', 'l.full_name as name', 'l.phone_number as phone']);

            if ($lines->isEmpty()) {
                return collect();
            }

            $custIds = $lines->pluck('customer_id')->unique()->values()->all();

            // Last service touch: a sale with service lines, or a completed appointment
            $svcSale = $this->sales()->join('service_details as sv', 'sv.sale_master_id', '=', 'sm.id')->whereNull('sv.deleted_at')
                ->whereIn('sm.customer_id', $custIds)->groupBy('sm.customer_id')
                ->selectRaw('sm.customer_id, MAX(sm.date) as d')->pluck('d', 'customer_id');
            $svcAppt = DB::table('appointments as a')->leftJoin('customers as c', 'c.lead_id', '=', 'a.lead_id')
                ->whereNull('a.deleted_at')->where('a.status', 'completed')
                ->where(fn ($w) => $w->whereIn('a.customer_id', $custIds)->orWhereIn('c.id', $custIds))
                ->groupByRaw('COALESCE(a.customer_id, c.id)')
                ->selectRaw('COALESCE(a.customer_id, c.id) as cid, MAX(a.appointment_date) as d')->pluck('d', 'cid');

            $svcFollowUp = DB::table('lead_follow_ups as fu')
                ->join('customers as c', 'c.lead_id', '=', 'fu.lead_id')
                ->whereNull('fu.deleted_at')->where('fu.status', 'completed')
                ->where(function ($q) {
                    $q->where('fu.follow_up_notes', 'LIKE', '%[6-Month Service%')
                      ->orWhere('fu.outcome_notes', 'LIKE', '%[6-Month Service%')
                      ->orWhere('fu.follow_up_notes', 'LIKE', '%[Service%');
                })
                ->whereIn('c.id', $custIds)->groupBy('c.id')
                ->selectRaw('c.id as cid, MAX(fu.completed_at) as d')->pluck('d', 'cid');

            $today = today();

            return $lines->groupBy('customer_id')->map(function ($rows) use ($svcSale, $svcAppt, $svcFollowUp, $today) {
                $r = $rows->last();
                $iv = max((int) ($r->iv ?: 6), 1);
                $sold = Carbon::parse($r->date)->startOfDay();
                $elapsed = $this->months($r->date);
                if ($elapsed > 60) {
                    return null;   // older than 5 years — handled by the upgrade widget instead
                }
                $k = intdiv($elapsed, $iv);
                $lastPoint = $k >= 1 ? $sold->copy()->addMonthsNoOverflow($k * $iv) : null;
                $nextPoint = $sold->copy()->addMonthsNoOverflow(($k + 1) * $iv);

                $lastSvc = collect([$svcSale[$r->customer_id] ?? null, $svcAppt[$r->customer_id] ?? null, $svcFollowUp[$r->customer_id] ?? null])->filter()->max();
                $servicedSinceLast = $lastPoint && $lastSvc && Carbon::parse($lastSvc)->gte($lastPoint->copy()->subDays(15));

                $stage = null;
                $dueDate = $nextPoint;
                if ($lastPoint && !$servicedSinceLast && $lastPoint->gte($today->copy()->subDays(60))) {
                    $stage = 'overdue';
                    $dueDate = $lastPoint;
                } elseif ($nextPoint->lte($today->copy()->addDays(30))) {
                    $stage = 'due';
                }
                if (!$stage) {
                    return null;
                }

                return [
                    'customer_id' => $r->customer_id,
                    'name'        => $r->name ?: 'Unknown',
                    'phone'       => $r->phone ?: '',
                    'model'       => $r->model,
                    'sale_id'     => $r->sale_id,
                    'interval'    => $iv,
                    'due_date'    => $dueDate->toDateString(),
                    'due_label'   => $dueDate->format('d M Y'),
                    'days'        => (int) $today->diffInDays($dueDate, false),
                    'stage'       => $stage,
                    'last_service'=> $lastSvc ? $this->fmtDate($lastSvc) : null,
                ];
            })->filter()->sortBy('days')->values();
        })();
    }

    /** 2.3 Service / cleaning due. */
    protected function serviceDue(): array
    {
        $all = $this->serviceCandidates();

        return [
            'overdue' => $all->where('stage', 'overdue')->count(),
            'due'     => $all->where('stage', 'due')->count(),
            'list'    => $all->take(12)->all(),
            'phones'  => $all->pluck('phone')->filter()->unique()->values()->all(),
        ];
    }

    /** 2.4 Hearing test → fitting → device sale funnel. */
    protected function testToSaleFunnel(): array
    {
        $q = DataScope::appointments(Appointment::query())->toBase()
            ->join('appointment_services as svc', 'svc.id', '=', 'appointments.service_id')
            ->leftJoin('customers as c_fn', fn ($j) => $j->on('c_fn.lead_id', '=', 'appointments.lead_id')->whereNull('c_fn.deleted_at'));
        if ($this->period !== 'all') {
            $q->whereBetween('appointments.appointment_date', [$this->from, $this->to]);
        }
        $appts = $q->get(['appointments.id', 'appointments.lead_id', 'appointments.customer_id', 'c_fn.id as lead_cust',
            'appointments.status', 'appointments.appointment_date', 'svc.name', 'svc.category'])->unique('id');

        $isTest = fn ($a) => in_array($a->category, ['diagnostic', 'consultation'], true) || preg_match('/assess|test|audiom|counsel|screen/i', (string) $a->name);
        $isFit  = fn ($a) => preg_match('/fit|trial/i', (string) $a->name) === 1;
        $person = fn ($a) => ($cid = $a->customer_id ?: $a->lead_cust) ? 'c' . $cid : ($a->lead_id ? 'l' . $a->lead_id : null);

        $tests = $appts->filter(fn ($a) => $isTest($a) && !$isFit($a) && $a->status !== 'cancelled' && $person($a));
        $byPerson = $tests->groupBy($person);
        $attended = $tests->where('status', 'completed')->groupBy($person)->keys();
        $fitted = $appts->filter(fn ($a) => $isFit($a) && $a->status !== 'cancelled' && $person($a))->map($person)->unique()
            ->intersect($byPerson->keys());

        // Bought a device (or anything, if no device categories exist) on/after the first test
        $buySrc = $this->deviceProductIds() ? $this->deviceSales() : $this->sales()->get(['sm.customer_id', 'sm.date']);
        $firstBuy = $buySrc->groupBy('customer_id')->map(fn ($rows) => $rows->pluck('date')->all());

        $bought = collect();
        $daysToBuy = [];
        foreach ($byPerson as $pk => $rows) {
            if (!str_starts_with($pk, 'c')) {
                continue;
            }
            $cid = (int) substr($pk, 1);
            $firstTest = Carbon::parse($rows->min('appointment_date'))->startOfDay();
            $after = collect($firstBuy[$cid] ?? [])->filter(fn ($d) => Carbon::parse($d)->gte($firstTest))->sort()->first();
            if ($after) {
                $bought->push($pk);
                $daysToBuy[] = (int) $firstTest->diffInDays(Carbon::parse($after));
            }
        }

        $booked = $byPerson->count();
        $stages = [
            ['label' => 'Test booked',   'count' => $booked,          'color' => '#6366f1', 'icon' => 'far fa-calendar-plus'],
            ['label' => 'Test attended', 'count' => $attended->count(), 'color' => '#0284c7', 'icon' => 'fas fa-ear-listen'],
            ['label' => 'Fitting / trial', 'count' => $fitted->count(), 'color' => '#0d9488', 'icon' => 'fas fa-assistive-listening-systems'],
            ['label' => 'Bought device', 'count' => $bought->count(),  'color' => '#059669', 'icon' => 'fas fa-shopping-bag'],
        ];
        foreach ($stages as &$s) {
            $s['pct'] = $this->pct($s['count'], $booked, 0);
        }
        unset($s);

        return [
            'stages'     => $stages,
            'conversion' => $this->pct($bought->count(), $booked),
            'avg_days'   => $daysToBuy ? (int) round(array_sum($daysToBuy) / count($daysToBuy)) : null,
            'by_service' => $tests->groupBy('name')->map(fn ($g, $n) => ['name' => $n, 'count' => $g->count()])->sortByDesc('count')->values()->take(4)->all(),
        ];
    }

    /** 4.1 Lead response time: lead created → first contact (completed follow-up or first status change). */
    protected function leadResponseTime(): array
    {
        $q = DataScope::leads(Lead::query())->toBase();
        if ($this->period !== 'all') {
            $q->whereBetween('leads.created_at', [$this->from, $this->to]);
        }
        $leads = $q->get(['leads.id', 'leads.created_at', 'leads.created_by', 'leads.lead_status'])->unique('id');
        $ids = $leads->pluck('id')->all();

        $fu = $ids ? DB::table('lead_follow_ups')->whereNull('deleted_at')->whereIn('lead_id', $ids)->whereNotNull('completed_at')
            ->groupBy('lead_id')->selectRaw('lead_id, MIN(completed_at) as t')->pluck('t', 'lead_id') : collect();
        $st = $ids ? DB::table('lead_status_histories')->whereIn('lead_id', $ids)->whereNotNull('from_status')
            ->groupBy('lead_id')->selectRaw('lead_id, MIN(created_at) as t')->pluck('t', 'lead_id') : collect();

        $buckets = ['lt1h' => 0, 'lt24h' => 0, 'lt3d' => 0, 'gt3d' => 0, 'none' => 0];
        $hours = [];
        $perStaff = [];

        foreach ($leads as $l) {
            $first = collect([$fu[$l->id] ?? null, $st[$l->id] ?? null])->filter()->min();
            $sid = (string) ($l->created_by ?? '0');
            $perStaff[$sid] ??= ['leads' => 0, 'hours' => []];
            $perStaff[$sid]['leads']++;

            if (!$first) {
                $buckets['none']++;
                continue;
            }
            $h = max(Carbon::parse($l->created_at)->diffInMinutes(Carbon::parse($first), false), 0) / 60;
            $hours[] = $h;
            $perStaff[$sid]['hours'][] = $h;
            $buckets[$h < 1 ? 'lt1h' : ($h < 24 ? 'lt24h' : ($h < 72 ? 'lt3d' : 'gt3d'))]++;
        }

        $names = User::whereIn('id', array_filter(array_map('intval', array_keys($perStaff))))->pluck('name', 'id');
        $staff = collect($perStaff)->map(fn ($s, $id) => [
            'name'      => $names[(int) $id] ?? 'Unknown',
            'leads'     => $s['leads'],
            'contacted' => count($s['hours']),
            'avg_hours' => $s['hours'] ? round(array_sum($s['hours']) / count($s['hours']), 1) : null,
        ])->sortBy(fn ($s) => $s['avg_hours'] ?? PHP_INT_MAX)->values()->take(6)->all();

        sort($hours);
        $n = count($hours);

        return [
            'leads'      => $leads->count(),
            'contacted'  => $n,
            'avg_hours'  => $n ? round(array_sum($hours) / $n, 1) : null,
            'median'     => $n ? round($n % 2 ? $hours[intdiv($n, 2)] : ($hours[$n / 2 - 1] + $hours[$n / 2]) / 2, 1) : null,
            'within_24h' => $this->pct($buckets['lt1h'] + $buckets['lt24h'], max($leads->count(), 1)),
            'buckets'    => $buckets,
            'staff'      => $staff,
        ];
    }

    /** 4.2 Lost lead reasons (grouped from the free-text reason). */
    protected function lostReasons(): array
    {
        $q = DataScope::leads(Lead::query())->toBase()->where('leads.lead_status', LeadStatus::LOST->value);
        if ($this->period !== 'all') {
            $q->whereBetween('leads.updated_at', [$this->from, $this->to]);
        }
        $rows = $q->orderByDesc('leads.updated_at')->get(['leads.id', 'leads.full_name', 'leads.loss_reason', 'leads.loss_to_whom', 'leads.updated_at'])->unique('id');

        $classify = function ($r) {
            $txt = trim((string) $r->loss_reason);
            foreach (self::LOST_REASONS as $k => $meta) {
                if ($txt !== '' && (strcasecmp($txt, $meta['label']) === 0 || preg_match($meta['match'], $txt))) {
                    return $k;
                }
            }
            if (trim((string) $r->loss_to_whom) !== '') {
                return 'competitor';
            }

            return $txt === '' ? 'unknown' : 'other';
        };

        $grouped = $rows->groupBy($classify);
        $total = max($rows->count(), 1);
        $meta = self::LOST_REASONS + [
            'other'   => ['label' => 'Other',          'icon' => 'fas fa-ellipsis-h',     'color' => '#94a3b8'],
            'unknown' => ['label' => 'No reason given', 'icon' => 'fas fa-question-circle', 'color' => '#cbd5e1'],
        ];

        $reasons = collect($meta)->map(fn ($m, $k) => [
            'key'   => $k,
            'label' => $m['label'],
            'icon'  => $m['icon'],
            'color' => $m['color'],
            'count' => $grouped->get($k, collect())->count(),
            'share' => $this->pct($grouped->get($k, collect())->count(), $total),
        ])->filter(fn ($r) => $r['count'] > 0)->sortByDesc('count')->values();

        $competitors = $rows->filter(fn ($r) => trim((string) $r->loss_to_whom) !== '')
            ->groupBy(fn ($r) => ucwords(strtolower(trim($r->loss_to_whom))))
            ->map(fn ($g, $n) => ['name' => $n, 'count' => $g->count()])->sortByDesc('count')->values()->take(5);

        $allQ = DataScope::leads(Lead::query())->toBase();
        if ($this->period !== 'all') {
            $allQ->whereBetween('leads.created_at', [$this->from, $this->to]);
        }

        return [
            'total'       => $rows->count(),
            'lost_rate'   => $this->pct($rows->count(), max($allQ->count(), 1)),
            'reasons'     => $reasons->all(),
            'max'         => max((int) $reasons->max('count'), 1),
            'competitors' => $competitors->all(),
            'recent'      => $rows->take(5)->map(fn ($r) => [
                'id'     => $r->id,
                'name'   => $r->full_name,
                'reason' => $r->loss_reason ?: '—',
                'when'   => $this->fmtDate($r->updated_at),
            ])->values()->all(),
        ];
    }

    /** 5.4 Referral commission due per doctor (doctor ledger: credit = earned, debit = paid). */
    protected function commissionDue(): array
    {
        $docs = $this->doctors()->get(['id', 'name', 'contact_number', 'product_commission_percent', 'product_commission_amount',
            'service_commission_percent', 'service_commission_amount'])->keyBy('id');
        if ($docs->isEmpty()) {
            return ['earned' => 0, 'paid' => 0, 'due' => 0, 'earned_month' => 0, 'paid_month' => 0, 'doctors_due' => 0, 'list' => []];
        }
        $m0 = now()->startOfMonth()->toDateString();

        $led = DB::table('doctor_ledgers')->whereNull('deleted_at')->whereIn('doctor_id', $docs->keys())
            ->groupBy('doctor_id')
            ->selectRaw('doctor_id, SUM(credit) as earned, SUM(debit) as paid,
                SUM(CASE WHEN date >= ? THEN credit ELSE 0 END) as earned_month,
                SUM(CASE WHEN date >= ? THEN debit ELSE 0 END) as paid_month, MAX(CASE WHEN debit > 0 THEN date END) as last_paid', [$m0, $m0])
            ->get()->keyBy('doctor_id');

        $rule = function ($d) {
            $p = (float) $d->product_commission_percent > 0 ? rtrim(rtrim(number_format((float) $d->product_commission_percent, 2), '0'), '.') . '%'
                : ((float) $d->product_commission_amount > 0 ? '৳' . number_format((float) $d->product_commission_amount) : null);
            $s = (float) $d->service_commission_percent > 0 ? rtrim(rtrim(number_format((float) $d->service_commission_percent, 2), '0'), '.') . '%'
                : ((float) $d->service_commission_amount > 0 ? '৳' . number_format((float) $d->service_commission_amount) : null);

            return trim(($p ? "Product $p" : '') . ($p && $s ? ' · ' : '') . ($s ? "Service $s" : '')) ?: 'No rule set';
        };

        $list = $led->map(fn ($r) => [
            'id'           => $r->doctor_id,
            'name'         => $docs[$r->doctor_id]->name ?? 'Unknown',
            'phone'        => $docs[$r->doctor_id]->contact_number ?? '',
            'rule'         => isset($docs[$r->doctor_id]) ? $rule($docs[$r->doctor_id]) : '',
            'earned'       => (float) $r->earned,
            'paid'         => (float) $r->paid,
            'due'          => round((float) $r->earned - (float) $r->paid, 2),
            'earned_month' => (float) $r->earned_month,
            'last_paid'    => $r->last_paid ? $this->fmtDate($r->last_paid) : 'Never',
        ])->filter(fn ($r) => $r['due'] > 0.009)->sortByDesc('due')->values();

        return [
            'earned'       => (float) $led->sum('earned'),
            'paid'         => (float) $led->sum('paid'),
            'due'          => (float) $list->sum('due'),
            'earned_month' => (float) $led->sum('earned_month'),
            'paid_month'   => (float) $led->sum('paid_month'),
            'doctors_due'  => $list->count(),
            'list'         => $list->take(8)->all(),
        ];
    }

    // ==================================================================
    //  7. Smart alerts
    // ==================================================================

    protected function smartAlerts(): array
    {
        $can = fn (string $k) => DataScope::can('dashboard.' . $k);
        $alerts = [];

        $hot = DataScope::leads(Lead::query())->toBase()
            ->where('leads.lead_temperature', 'hot')
            ->whereNotIn('leads.lead_status', [LeadStatus::WON->value, LeadStatus::LOST->value])
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('lead_follow_ups as f')->whereColumn('f.lead_id', 'leads.id')
                ->whereNull('f.deleted_at')->where('f.completed_at', '>=', today()))
            ->count();
        if ($hot > 0) {
            $alerts[] = ['tone' => 'danger', 'icon' => 'fas fa-fire', 'text' => "$hot hot lead" . ($hot > 1 ? 's' : '') . ' not contacted today', 'url' => url('admin/leads?lead_temperature=hot')];
        }

        $missed = DataScope::appointments(Appointment::query())->toBase()->where('appointments.status', 'scheduled')
            ->whereDate('appointments.appointment_date', today()->subDay())->count();
        if ($missed > 0) {
            $alerts[] = ['tone' => 'warning', 'icon' => 'far fa-calendar-times', 'text' => "$missed appointment" . ($missed > 1 ? 's' : '') . ' from yesterday not updated (no-show?)', 'url' => url('admin/appointments/calendar')];
        }

        $upgrade = $this->upgradeCandidates()->where('stage', 'due')->count();
        if ($upgrade > 0) {
            $alerts[] = ['tone' => 'accent', 'icon' => 'fas fa-sync-alt', 'text' => "$upgrade customer" . ($upgrade > 1 ? 's' : '') . ' due for a device upgrade', 'url' => $can('upgrade_due') ? '#w-upgrade_due' : url('admin/customers')];
        }

        $single = $this->binauralCandidates()->count();
        if ($single > 0 && $can('binaural_opportunity')) {
            $alerts[] = ['tone' => 'info', 'icon' => 'fas fa-assistive-listening-systems', 'text' => "$single single-device owner" . ($single > 1 ? 's' : '') . ' — second-ear opportunity', 'url' => '#w-binaural_opportunity'];
        }

        if ($can('receivables_aging') || $can('net_balance')) {
            $b = $this->receivablesRaw()['buckets'];
            $old = ($b['61_90'] ?? 0) + ($b['90_plus'] ?? 0);
            if ($old > 0) {
                $alerts[] = ['tone' => 'danger', 'icon' => 'fas fa-hand-holding-usd', 'text' => '৳' . number_format($old) . ' customer dues older than 60 days', 'url' => $can('receivables_aging') ? '#w-receivables_aging' : url('admin/customers')];
            }
        }

        $ids = $this->scopedCustomerIds();
        $expiring = (float) DB::table('reward_ledgers')->whereNull('deleted_at')->whereIn('customer_id', $ids)->where('points', '>', 0)
            ->whereBetween('expiry_date', [today()->toDateString(), today()->addDays(30)->toDateString()])->sum('points');
        if ($expiring > 0) {
            $alerts[] = ['tone' => 'warning', 'icon' => 'fas fa-coins', 'text' => number_format($expiring) . ' loyalty points expire within 30 days', 'url' => $can('points_liability') ? '#w-points_liability' : url('admin/customers')];
        }

        $inactiveDocs = $this->sales()->whereNotNull('sm.reference_by')->groupBy('sm.reference_by')
            ->havingRaw('MAX(sm.date) < ?', [today()->subDays(90)->toDateString()])->select('sm.reference_by')->get()->count();
        if ($inactiveDocs > 0) {
            $alerts[] = ['tone' => 'info', 'icon' => 'fas fa-user-md', 'text' => "$inactiveDocs referring doctor" . ($inactiveDocs > 1 ? 's' : '') . ' silent for 90+ days', 'url' => $can('inactive_doctors') ? '#w-inactive_doctors' : url('admin/doctor')];
        }

        // Phase 2 alerts
        if ($can('birthdays')) {
            $bd = $this->birthdayPeople()->where('in_days', 0)->count();
            if ($bd > 0) {
                $alerts[] = ['tone' => 'accent', 'icon' => 'fas fa-birthday-cake', 'text' => "$bd birthday" . ($bd > 1 ? 's' : '') . ' today — send a wish', 'url' => '#w-birthdays'];
            }
        }
        if ($can('service_due')) {
            $od = $this->serviceCandidates()->where('stage', 'overdue')->count();
            if ($od > 0) {
                $alerts[] = ['tone' => 'warning', 'icon' => 'fas fa-tools', 'text' => "$od device" . ($od > 1 ? 's' : '') . ' overdue for service / cleaning', 'url' => '#w-service_due'];
            }
        }

        return ['alerts' => $alerts];
    }

    // ==================================================================
    //  Upgrades to existing widgets
    // ==================================================================

    /** A1 — 12-month revenue/purchases + same months last year (same scope as the existing chart). */
    protected function revenueExtended(): array
    {
        $axis = $this->monthAxis(12);
        $from = now()->startOfMonth()->subMonths(23);
        $series = fn ($q) => $q->selectRaw("DATE_FORMAT(date, '%Y-%m') as ym, SUM(total_amount) as total")
            ->where('date', '>=', $from)->groupBy('ym')->pluck('total', 'ym');

        $sales = $series(DataScope::ownedBy(SaleMaster::query()));
        $purch = $series(DataScope::ownedBy(PurchaseMaster::query()));

        return [
            'labels'    => $axis->map(fn ($m) => $this->monthLabel($m))->values()->all(),
            'sales'     => $axis->map(fn ($m) => (float) ($sales[$m] ?? 0))->values()->all(),
            'purchases' => $axis->map(fn ($m) => (float) ($purch[$m] ?? 0))->values()->all(),
            'sales_ly'  => $axis->map(fn ($m) => (float) ($sales[Carbon::createFromFormat('Y-m-d', $m . '-01')->subYear()->format('Y-m')] ?? 0))->values()->all(),
        ];
    }

    /** A2 — revenue & conversion per lead source. */
    protected function sourceRoi(): array
    {
        $leads = DataScope::leads(Lead::query())->toBase()
            ->groupBy('leads.source_of_entry')
            ->selectRaw('leads.source_of_entry as src, COUNT(*) as total, SUM(CASE WHEN leads.lead_status = ? THEN 1 ELSE 0 END) as won', [LeadStatus::WON->value])
            ->get()->keyBy(fn ($r) => (string) ($r->src ?: 'none'));

        $rev = $this->withCustomer($this->sales())->groupBy('l.source_of_entry')
            ->selectRaw('l.source_of_entry as src, SUM(sm.total_amount) as revenue, COUNT(DISTINCT sm.customer_id) as buyers')
            ->get()->keyBy(fn ($r) => (string) ($r->src ?: 'none'));

        $rows = $leads->keys()->merge($rev->keys())->unique()->map(function ($k) use ($leads, $rev) {
            $l = $leads[$k] ?? null;
            $r = $rev[$k] ?? null;
            return [
                'source'     => $k === 'none' ? 'Not set' : ucwords(str_replace('_', ' ', $k)),
                'key'        => $k,
                'leads'      => (int) ($l->total ?? 0),
                'won'        => (int) ($l->won ?? 0),
                'conversion' => $this->pct((float) ($l->won ?? 0), (float) ($l->total ?? 0)),
                'buyers'     => (int) ($r->buyers ?? 0),
                'revenue'    => (float) ($r->revenue ?? 0),
            ];
        })->sortByDesc(fn ($r) => [$r['revenue'], $r['leads']])->values();

        return ['rows' => $rows->all(), 'max' => max((float) $rows->max('revenue'), 1)];
    }

    /** A3 — open leads by temperature. */
    protected function leadTemperature(): array
    {
        $counts = DataScope::leads(Lead::query())->toBase()
            ->whereNotIn('leads.lead_status', [LeadStatus::WON->value, LeadStatus::LOST->value])
            ->groupBy('leads.lead_temperature')->selectRaw('leads.lead_temperature as t, COUNT(*) as n')->pluck('n', 't');

        return [
            'hot'   => (int) ($counts['hot'] ?? 0),
            'warm'  => (int) ($counts['warm'] ?? 0),
            'cold'  => (int) ($counts['cold'] ?? 0),
            'unset' => (int) ($counts[''] ?? 0),
        ];
    }

    /** A5 — extra per-staff metrics keyed by user id. */
    protected function staffExtra(): array
    {
        $salesQ = $this->branch(DB::table('sale_masters')->whereNull('deleted_at'), 'vendor_id');
        $sales = $salesQ->groupBy('created_by')
            ->selectRaw('created_by, COUNT(*) as n, SUM(total_amount) as total, SUM(total_discount) as disc')
            ->get()->keyBy('created_by');

        $appts = DB::table('appointments')->whereNull('deleted_at')->where('status', 'completed')
            ->groupBy('user_id')->selectRaw('user_id, COUNT(*) as n')->pluck('n', 'user_id');

        $ids = $sales->keys()->merge($appts->keys())->unique();

        return $ids->mapWithKeys(fn ($id) => [(string) $id => [
            'appointments' => (int) ($appts[$id] ?? 0),
            'aov'          => isset($sales[$id]) && $sales[$id]->n > 0 ? round($sales[$id]->total / $sales[$id]->n) : 0,
            'discount'     => (float) ($sales[$id]->disc ?? 0),
        ]])->all();
    }
}
