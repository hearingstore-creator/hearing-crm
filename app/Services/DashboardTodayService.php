<?php

namespace App\Services;

use App\Classes\Helper;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\User;
use App\Models\UserPreference;
use App\Support\DataScope;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * New "Today" dashboard (dashboard plan phase 2): KPI tiles, alerts and two charts.
 *
 * Reuses the analytics helpers (sales(), ledgers(), receivablesRaw()…) and adds a branch pick for
 * users who may read every branch: "hq" (Head Office, the default, same rows as before),
 * "all" or a branch id. Everyone else always gets their own scope. Results are cached briefly
 * per user + branch: tiles 2 minutes, charts 10 minutes.
 */
class DashboardTodayService extends DashboardAnalyticsService
{
    public const TILE_TTL = 120;
    public const CHART_TTL = 600;
    public const DEFAULT_PERIOD = 'month';
    protected const SPARK_DAYS = 14;

    /** null = the user's own scope (branch-bound users); otherwise 'hq', 'all' or a branch id. Super Admin opens on 'all', other company-wide users on 'hq'. */
    protected ?string $branchPick = null;

    public function __construct()
    {
        $this->setPeriod(self::DEFAULT_PERIOD);
    }

    /** Branch choices for the header picker; empty for users bound to one scope. */
    public function branchChoices(): array
    {
        if (!auth()->user()?->canViewAllBranches()) {
            return [];
        }

        return ['hq' => 'Head office', 'all' => 'All branches'] + Helper::branchOptions();
    }

    /** Apply the picker value; anything the user may not choose falls back to their own scope. */
    public function forBranch(?string $pick): static
    {
        $choices = $this->branchChoices();
        $default = auth()->user()?->isSuperAdmin() ? 'all' : 'hq';
        $this->branchPick = $choices ? (array_key_exists((string) $pick, $choices) ? (string) $pick : $default) : null;
        $this->memo = [];

        return $this;
    }

    public function branchKey(): string
    {
        return $this->branchPick ?? 'own';
    }

    public function forPeriod(?string $period): static
    {
        $this->setPeriod(array_key_exists((string) $period, self::PERIODS) ? $period : self::DEFAULT_PERIOD);
        $this->memo = [];

        return $this;
    }

    public function periodKey(): string
    {
        return $this->period;
    }

    // ==================================================================
    //  Branch pick (overrides the analytics scope helpers)
    // ==================================================================

    protected function branch(Builder $q, string $col): Builder
    {
        return $this->branchPick === null ? parent::branch($q, $col) : $this->picked($q, $col);
    }

    protected function picked($q, string $col)
    {
        return match ($this->branchPick) {
            null, 'all' => $q,
            'hq'        => $q->whereNull($col),
            default     => $q->where($col, (int) $this->branchPick),
        };
    }

    /** Ledgers without vendor_id are scoped by who wrote the row: a branch = its login + its staff. */
    protected function ledgerOwner(Builder $q, string $col): Builder
    {
        if ($this->branchPick === null || $this->branchPick === 'hq') {
            return parent::ledgerOwner($q, $col);
        }
        if ($this->branchPick === 'all') {
            return DataScope::seesAll() ? $q : $q->where($col, DataScope::uid());
        }
        $id = (int) $this->branchPick;

        return $q->whereIn($col, User::where('vendor_id', $id)->pluck('id')->push($id)->all());
    }

    // ==================================================================
    //  Cached entry points
    // ==================================================================

    protected function cacheKey(string $what): string
    {
        return 'dash-today:' . $what . ':' . auth()->id() . ':' . $this->branchKey() . ':' . $this->period . ':' . today()->toDateString();
    }

    public function tiles(): array
    {
        return Cache::remember($this->cacheKey('tiles'), self::TILE_TTL, fn () => $this->buildTiles());
    }

    public function chart(string $name): ?array
    {
        $method = ['revenue_month' => 'revenueMonth', 'sales_by_category' => 'salesByCategory'][$name] ?? null;
        $perm = ['revenue_month' => 'revenue_chart', 'sales_by_category' => 'top_products'][$name] ?? null;
        if (!$method || !DataScope::can('dashboard.' . $perm)) {
            return null;
        }

        return Cache::remember($this->cacheKey('chart-' . $name), self::CHART_TTL, fn () => $this->safe($name, fn () => $this->{$method}()));
    }

    /** Smart alerts of the user's own scope; links to old-dashboard widgets open the legacy page. */
    public function alerts(): ?array
    {
        if (!DataScope::can('dashboard.smart_alerts')) {
            return null;
        }

        return Cache::remember($this->cacheKey('alerts'), self::TILE_TTL, function () {
            $own = clone $this;
            $own->branchPick = $this->branchChoices() ? 'hq' : null;
            $r = $own->safe('smart_alerts', fn () => $own->smartAlerts());
            foreach ($r['alerts'] ?? [] as $i => $a) {
                if (str_starts_with($a['url'], '#')) {
                    $r['alerts'][$i]['url'] = route('dashboard.legacy') . $a['url'];
                }
            }

            return $r;
        });
    }

    // ==================================================================
    //  Report links that carry the dashboard's period and branch
    // ==================================================================

    /** Reports that take start_date / end_date. */
    protected const REPORT_DATES = ['admin/report/sales-performance', 'admin/report/lead-funnel', 'admin/report/ledger'];

    /** Reports that take a branch (vendor_id). */
    protected const REPORT_BRANCH = ['admin/report/sales-performance', 'admin/report/lead-funnel'];

    /** URL of a report with the dashboard's filters filled in, for the reports that understand them. */
    public function reportUrl(string $path): string
    {
        $q = [];
        if (in_array($path, self::REPORT_DATES, true) && $this->period !== 'all') {
            $q['start_date'] = $this->from->toDateString();
            $q['end_date'] = $this->to->toDateString();
        }
        if (in_array($path, self::REPORT_BRANCH, true) && ctype_digit((string) $this->branchPick)) {
            $q['vendor_id'] = $this->branchPick;
        }

        return url($path) . ($q ? '?' . http_build_query($q) : '');
    }

    // ==================================================================
    //  Tabs (loaded on demand)
    // ==================================================================

    /** tab => [label, widget keys, cache seconds]. Widget keys are the analytics WIDGETS plus EXTRA. */
    public const TABS = [
        'sales'    => ['Sales & Money', ['revenue_extended', 'gross_profit', 'aov_trend', 'payment_mix', 'discount_leakage', 'receivables_aging', 'supplier_aging', 'expense_revenue', 'cash_today'], 600],
        'care'     => ['Patients & Care', ['new_vs_returning', 'customer_rfm', 'dormant_customers', 'upgrade_due', 'binaural_opportunity', 'membership_breakdown', 'points_liability', 'service_due'], 1800],
        'pipeline' => ['Pipeline', ['test_to_sale_funnel', 'lead_response_time', 'lost_reasons', 'source_roi', 'top_doctors', 'inactive_doctors', 'referral_trend', 'commission_due'], 600],
        'branches' => ['Branches', ['branch_leaderboard', 'branch_stock', 'dead_stock', 'appointment_utilisation'], 600],
    ];

    /** Widgets that are not in WIDGETS: key => [permission suffix, method]. */
    protected const EXTRA = [
        'revenue_extended' => ['revenue_chart', 'revenueExtended'],
        'source_roi'       => ['lead_charts', 'sourceRoi'],
    ];

    /** Permission suffix of a widget key (the same rule build() applies). */
    protected function widgetPermission(string $key): string
    {
        return self::EXTRA[$key][0] ?? $key;
    }

    /** Tabs this user may open: at least one widget permission; Branches only for people who can read every branch. */
    public function visibleTabs(): array
    {
        $tabs = [];
        foreach (self::TABS as $name => [$label, $keys]) {
            if ($name === 'branches' && !$this->branchChoices()) {
                continue;
            }
            if (collect($keys)->contains(fn ($k) => DataScope::can('dashboard.' . $this->widgetPermission($k)))) {
                $tabs[$name] = $label;
            }
        }

        return $tabs;
    }

    /** Widget results for a tab (cached); a widget the user may not see is null, a failed one is ['error' => true]. */
    public function tab(string $name): ?array
    {
        if (!isset($this->visibleTabs()[$name])) {
            return null;
        }
        [, $keys, $ttl] = self::TABS[$name];

        return Cache::remember($this->cacheKey('tab-' . $name), $ttl, function () use ($keys) {
            $out = [];
            foreach ($keys as $key) {
                $out[$key] = $this->runWidget($key);
            }

            return $out;
        });
    }

    /** Widgets that are slow on real data: kept for a day (warmed by crm:dashboard-snapshots at 02:00). */
    public const HEAVY = ['customer_rfm', 'dormant_customers', 'upgrade_due', 'binaural_opportunity', 'dead_stock', 'branch_stock'];
    public const SNAPSHOT_TTL = 90000;
    protected const SLOW_MS = 1500;

    /** Milliseconds each widget took in this run (the crm:dashboard-snapshots command prints them). */
    public array $timings = [];
    protected bool $refreshSnapshots = false;

    public function refreshing(bool $on = true): static
    {
        $this->refreshSnapshots = $on;

        return $this;
    }

    /** One widget: null when the user may not see it, ['error' => true] when it failed, otherwise its data. */
    protected function runWidget(string $key): ?array
    {
        [$perm, $method] = self::EXTRA[$key] ?? [$key, self::WIDGETS[$key] ?? null];
        if (!$method || !DataScope::can('dashboard.' . $perm)) {
            return null;
        }

        $compute = function () use ($key, $method) {
            $t = microtime(true);
            $r = $this->safe($key, fn () => $this->{$method}());
            $this->timings[$key] = (int) round((microtime(true) - $t) * 1000);
            if ($this->timings[$key] > self::SLOW_MS) {
                Log::warning("Dashboard widget [$key] was slow", ['ms' => $this->timings[$key], 'period' => $this->period, 'branch' => $this->branchKey()]);
            }

            return $r;
        };

        if (!in_array($key, self::HEAVY, true)) {
            return $compute();
        }

        $cacheKey = $this->snapshotKey($key);
        if (!$this->refreshSnapshots && ($hit = Cache::get($cacheKey)) !== null) {
            return $hit;
        }
        $r = $compute();
        if (empty($r['error'])) {
            Cache::put($cacheKey, $r, self::SNAPSHOT_TTL);
        }

        return $r;
    }

    /** Recompute the heavy widgets now and store them as today's snapshot (the nightly job); returns [key => ms]. */
    public function warm(): array
    {
        $this->refreshing();
        $this->timings = [];
        foreach (self::HEAVY as $key) {
            $this->runWidget($key);
        }

        return $this->timings;
    }
    /** Whose data a user sees, so users with the same view share one snapshot. */
    public function scopeSignature(): string
    {
        $user = auth()->user();

        return DataScope::seesAll() ? 'all:' . ($user?->branchId() ?? 0) : 'user:' . $user?->id;
    }

    protected function snapshotKey(string $key): string
    {
        return 'dash-today:snap:' . $key . ':' . $this->scopeSignature() . ':' . $this->branchKey() . ':' . today()->toDateString();
    }

    // ==================================================================
    //  Start tab (pinned by the user, otherwise by role)
    // ==================================================================

    /** The tab to open on: the user's pinned tab if they can still open it, else a default by what their role has. */
    public function defaultTab(User $user): string
    {
        $tabs = $this->visibleTabs();
        $pinned = UserPreference::read($user->id, 'dashboard_tab');
        if ($pinned === 'today' || ($pinned && isset($tabs[$pinned]))) {
            return $pinned;
        }

        // Money without the daily work lists (an accounts role) opens on Sales & Money
        $worklists = DataScope::can('dashboard.follow_ups') || DataScope::can('dashboard.appointments') || DataScope::can('dashboard.daily_lookout');

        return isset($tabs['sales']) && !$worklists ? 'sales' : 'today';
    }

    /** Front desk style roles (no money widgets) see My Day before the tiles. */
    public function myDayFirst(): bool
    {
        return !DataScope::can('dashboard.total_sales') && !DataScope::can('dashboard.net_balance') && !DataScope::can('dashboard.gross_profit');
    }

    public function pin(User $user, string $tab): bool
    {
        if ($tab !== 'today' && !isset($this->visibleTabs()[$tab])) {
            return false;
        }
        UserPreference::write($user->id, 'dashboard_tab', $tab);

        return true;
    }

    // ==================================================================
    //  KPI tiles
    // ==================================================================

    protected function buildTiles(): array
    {
        $can = fn (string $k) => DataScope::can('dashboard.' . $k);
        $user = auth()->user();
        $tiles = [];
        $today = today()->toDateString();

        if ($can('todays_sales')) {
            $tiles[] = $this->safeTile('sales_today', fn () => $this->dailyTile(
                'sales_today', 'Sales today', 'fas fa-cash-register', 'money',
                $this->daily($this->sales(), 'sm.date', 'SUM(sm.total_amount)'),
                url('admin/sale?start_date=' . $today . '&end_date=' . $today),
                fn ($v) => $this->sales()->whereDate('sm.date', $today)->count() . ' invoice(s)'
            ));
        }

        if ($can('cash_today')) {
            $tiles[] = $this->safeTile('cash_in', fn () => $this->dailyTile(
                'cash_in', 'Cash in today', 'fas fa-wallet', 'money', $this->dailyCashIn(),
                url('admin/report/ledger?start_date=' . $today . '&end_date=' . $today),
                fn () => 'Patients + other income'
            ));
        }

        if ($can('appointments')) {
            $tiles[] = $this->safeTile('bookings', fn () => $this->dailyTile(
                'bookings', 'Bookings today', 'far fa-calendar-check', 'count', $this->dailyBookings(),
                url('admin/appointments/calendar'), fn () => 'Appointments on today\'s calendar'
            ));
        }

        if ($user?->hasAccess('repair_jobs_board.index')) {
            $tiles[] = $this->safeTile('open_repairs', fn () => $this->repairsTile());
        }

        if ($can('receivables_aging') || $can('net_balance')) {
            $tiles[] = $this->safeTile('patient_dues', fn () => $this->duesTile());
        }

        if ($can('total_leads')) {
            $tiles[] = $this->safeTile('new_leads', fn () => $this->dailyTile(
                'new_leads', 'New leads', 'fas fa-user-plus', 'count', $this->dailyLeads(),
                url('admin/leads'), fn () => 'Added today'
            ));
        }

        return $tiles;
    }

    protected function safeTile(string $key, callable $fn): array
    {
        $r = $this->safe($key, $fn);

        return empty($r['error']) ? $r : ['key' => $key, 'error' => true];
    }

    /** [Y-m-d => value] for the last SPARK_DAYS days, zero-filled. */
    protected function daysAxis(): array
    {
        return collect(range(self::SPARK_DAYS - 1, 0))->mapWithKeys(fn ($i) => [today()->subDays($i)->toDateString() => 0.0])->all();
    }

    protected function daily($q, string $dateCol, string $aggregate): array
    {
        $from = today()->subDays(self::SPARK_DAYS - 1)->toDateString();
        $rows = $q->whereBetween(DB::raw("DATE($dateCol)"), [$from, today()->toDateString()])
            ->groupBy(DB::raw("DATE($dateCol)"))
            ->selectRaw("DATE($dateCol) as d, $aggregate as v")->pluck('v', 'd');

        $axis = $this->daysAxis();
        foreach ($rows as $d => $v) {
            if (array_key_exists((string) $d, $axis)) {
                $axis[(string) $d] = (float) $v;
            }
        }

        return $axis;
    }

    protected function dailyCashIn(): array
    {
        $customers = $this->daily(
            $this->ledgerOwner(DB::table('customer_ledgers')->whereNull('deleted_at'), 'created_by')->where('type', '!=', Helper::$returnToCustomer),
            'date', 'SUM(credit)'
        );
        $other = $this->daily(
            $this->ledgers()->where('amount_type', Helper::$income)->where('type', '!=', Helper::$sales),
            'date', 'SUM(income_amount)'
        );

        foreach ($other as $d => $v) {
            $customers[$d] += $v;
        }

        return $customers;
    }

    protected function dailyBookings(): array
    {
        $q = DataScope::appointments(Appointment::query())->toBase()->whereNull('appointments.deleted_at')
            ->whereNotIn('appointments.status', ['cancelled', 'cancelled_refunded', 'cancelled_credit_kept', 'rescheduled']);
        $this->picked($q, 'appointments.branch_id');

        return $this->daily($q, 'appointments.appointment_date', 'COUNT(*)');
    }

    protected function dailyLeads(): array
    {
        $q = DataScope::leads(Lead::query())->toBase()->whereNull('leads.deleted_at');
        $this->picked($q, 'leads.vendor_id');

        return $this->daily($q, 'leads.created_at', 'COUNT(*)');
    }

    protected function dailyTile(string $key, string $label, string $icon, string $format, array $series, string $url, callable $sub): array
    {
        $values = array_values($series);
        $now = (float) end($values);
        $prev = (float) ($values[count($values) - 2] ?? 0);

        return [
            'key'     => $key,
            'label'   => $label,
            'icon'    => $icon,
            'format'  => $format,
            'value'   => $now,
            'change'  => $this->change($now, $prev),
            'vs'      => 'vs yesterday',
            'sub'     => $sub($now),
            'spark'   => $values,
            'url'     => $url,
        ];
    }

    /** Change against the previous value: absolute and percent (null percent when there is no base). */
    protected function change(float $now, float $prev): array
    {
        return [
            'diff' => round($now - $prev, 2),
            'pct'  => $prev > 0 ? round(($now - $prev) / $prev * 100) : null,
        ];
    }

    protected function repairsTile(): array
    {
        $base = fn () => $this->branch(DB::table('repair_jobs')->whereNull('deleted_at'), 'vendor_id');
        $open = (int) $base()->whereNotIn('current_status', ['delivered', 'cancelled', 'patient_declined'])->count();
        $ready = (int) $base()->where('current_status', 'ready_for_pickup')->count();
        $received = array_values($this->daily($base(), 'created_at', 'COUNT(*)'));

        return [
            'key'    => 'open_repairs',
            'label'  => 'Open repairs',
            'icon'   => 'fas fa-tools',
            'format' => 'count',
            'value'  => $open,
            'change' => $this->change((float) end($received), 0.0),
            'vs'     => 'received today',
            'sub'    => $ready . ' ready for pickup',
            'spark'  => $received,
            'url'    => url('admin/repairs'),
        ];
    }

    protected function duesTile(): array
    {
        $r = $this->receivablesRaw();
        $old = (float) (($r['buckets']['61_90'] ?? 0) + ($r['buckets']['90_plus'] ?? 0));

        return [
            'key'    => 'patient_dues',
            'label'  => 'Patient dues',
            'icon'   => 'fas fa-hand-holding-usd',
            'format' => 'money',
            'value'  => (float) ($r['total'] ?? 0),
            'change' => null,
            'vs'     => null,
            'sub'    => ($r['count'] ?? 0) . ' patient(s) · ৳' . number_format($old) . ' older than 60 days',
            'spark'  => null,
            'url'    => route('dashboard.legacy') . '#w-receivables_aging',
        ];
    }

    // ==================================================================
    //  Charts
    // ==================================================================

    /** Running total of sales, this month vs last month, by day of month. */
    protected function revenueMonth(): array
    {
        $thisStart = now()->startOfMonth();
        $lastStart = now()->startOfMonth()->subMonth();
        $byDay = fn (Carbon $start, Carbon $end) => $this->sales()->whereBetween('sm.date', [$start->toDateString(), $end->toDateString()])
            ->groupBy(DB::raw('DAY(sm.date)'))->selectRaw('DAY(sm.date) as d, SUM(sm.total_amount) as v')->pluck('v', 'd');

        $cur = $byDay($thisStart, today());
        $prev = $byDay($lastStart, $lastStart->copy()->endOfMonth());
        $days = max($thisStart->daysInMonth, $lastStart->daysInMonth);

        $run = function ($rows, int $upTo) use ($days) {
            $sum = 0.0;
            $out = [];
            for ($d = 1; $d <= $days; $d++) {
                $sum += (float) ($rows[$d] ?? 0);
                $out[] = $d <= $upTo ? round($sum, 2) : null;
            }

            return $out;
        };

        $current = $run($cur, (int) today()->day);
        $last = $run($prev, $lastStart->daysInMonth);
        $sameDayLast = $last[today()->day - 1] ?? end($last);

        return [
            'labels'     => range(1, $days),
            'this_month' => $current,
            'last_month' => $last,
            'this_label' => $thisStart->format('M Y'),
            'last_label' => $lastStart->format('M Y'),
            'total'      => (float) ($current[today()->day - 1] ?? 0),
            'same_day_last' => (float) $sameDayLast,
        ];
    }

    /** Sales by product category in the chosen period (top 6, the rest as "Other"). */
    protected function salesByCategory(): array
    {
        $rev = $this->lineRevenueSql();
        $rows = $this->lineBase()->groupBy('cat.category_name')
            ->selectRaw("COALESCE(cat.category_name, 'Uncategorised') as name, SUM($rev) as revenue")
            ->orderByDesc('revenue')->get();

        $top = $rows->take(6)->map(fn ($r) => ['name' => $r->name, 'revenue' => round((float) $r->revenue, 2)])->values();
        $other = (float) $rows->slice(6)->sum('revenue');
        if ($other > 0) {
            $top->push(['name' => 'Other', 'revenue' => round($other, 2)]);
        }

        return [
            'labels'  => $top->pluck('name')->all(),
            'values'  => $top->pluck('revenue')->all(),
            'total'   => round((float) $rows->sum('revenue'), 2),
            'period'  => self::PERIODS[$this->period],
        ];
    }
}
