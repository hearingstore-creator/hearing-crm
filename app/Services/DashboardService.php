<?php

namespace App\Services;

use App\Classes\Helper;
use App\Enums\LeadStatus;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Lead;
use App\Models\Ledger;
use App\Models\MembershipTier;
use App\Models\Product;
use App\Models\Referral;
use App\Models\RewardLedger;
use App\Models\SaleMaster;
use App\Models\SaleDetail;
use App\Models\ServiceDetail;
use App\Models\PurchaseMaster;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\Doctor;
use App\Models\Ticket;
use App\Models\Task;
use App\Models\Appointment;
use App\Enums\AppointmentStatus;
use App\Enums\TicketStatus;
use App\Enums\TaskStatus;
use App\Models\User;
use App\Models\LeadFollowUp;
use App\Support\DataScope;
use Illuminate\Support\Facades\DB;

/**
 * Builds all dashboard data.
 *
 * Every query that touches user-owned records goes through App\Support\DataScope so a user
 * only sees what they are allowed to (all data / vendor data / own data). Heavy sections are
 * only computed when the user has the matching `dashboard.*` widget permission.
 */
class DashboardService
{
    /** Small per-request memo to avoid running the same query twice. */
    protected array $memo = [];

    // ------------------------------------------------------------------
    //  Scoped query starters
    // ------------------------------------------------------------------
    protected function sales()       { return DataScope::ownedBy(SaleMaster::query()); }
    protected function purchases()   { return DataScope::ownedBy(PurchaseMaster::query()); }
    protected function leads()       { return DataScope::leads(Lead::query()); }
    protected function customers()   { return DataScope::customers(Customer::query()); }
    protected function tasks()       { return DataScope::assignable(Task::query()); }
    protected function tickets()     { return DataScope::assignable(Ticket::query()); }
    protected function appts()       { return DataScope::appointments(Appointment::query()); }
    protected function followUps()   { return DataScope::followUps(LeadFollowUp::query()); }

    protected function activeTicketStatuses(): array
    {
        return [
            TicketStatus::OPEN->value,
            TicketStatus::IN_PROGRESS->value,
            TicketStatus::PENDING->value,
            TicketStatus::ON_HOLD->value,
        ];
    }

    /**
     * Get all dashboard statistics
     *
     * @return array
     */
    public function getDashboardStatistics(): array
    {
        $can = fn (string $p) => DataScope::can('dashboard.' . $p);

        $needsLookout = $can('daily_lookout');
        $needsCharts  = $can('revenue_chart') || $can('lead_pipeline') || $can('lead_charts');

        return [
            'scope_label' => DataScope::label(),
            'overview'    => $this->getOverviewStats(),
            'sales'       => $this->getSalesStats(),
            'purchases'   => $can('total_purchases') || $can('net_balance') ? $this->getPurchaseStats() : [],
            'products'    => $can('total_products') || $can('inventory') ? $this->getProductStats() : [],
            'leads'       => $this->getLeadStats(),
            'customers'   => $this->getCustomerStats(),
            'suppliers'   => $can('suppliers') ? $this->getSupplierStats() : [],
            'financials'  => $can('net_balance') ? $this->getFinancialStats() : [],
            'tickets'     => $this->getTicketStats(),
            'tasks'       => $this->getTaskStats(),
            'follow_ups'  => $this->getFollowUpStats(),
            'appointments'=> $this->getAppointmentStats(),
            'membership'  => $can('membership') ? $this->getMembershipStats() : [],
            'inventory'   => $can('inventory') || $can('total_products') ? $this->getInventoryStats() : [],
            'performance' => $can('performance') || $can('marketing_insights') ? $this->getPerformanceStats() : [],
            'charts'      => $needsCharts ? $this->getChartData() : [],
            'insights'    => $can('marketing_insights') ? $this->getMarketingInsights() : [],
            'daily_lookout' => $needsLookout ? $this->getDailyLookout() : [],
        ];
    }

    /**
     * Get overview statistics
     *
     * @return array
     */
    protected function getOverviewStats(): array
    {
        $today = now()->today();
        $thisYear = now()->startOfYear();

        return [
            'today_sales'       => $this->sales()->whereDate('date', $today)->sum('total_amount'),
            'today_sales_count' => $this->sales()->whereDate('date', $today)->count(),
            'total_sales'       => $this->sales()->sum('total_amount'),
            'year_sales'        => $this->sales()->whereBetween('date', [$thisYear, now()])->sum('total_amount'),
            'total_customers'   => $this->customers()->count(),
            'total_leads'       => $this->leads()->count(),
            'today_leads'       => $this->leads()->whereDate('leads.created_at', $today)->count(),
            'total_products'    => Product::count(),
            'total_suppliers'   => Supplier::count(),
            'total_doctors'     => Doctor::count(),
            'pending_services_count' => ServiceDetail::where('fulfillment_status', ServiceDetail::STATUS_NOT_BOOKED)->count(),
            'booked_services_count'  => ServiceDetail::where('fulfillment_status', ServiceDetail::STATUS_BOOKED)->count(),
        ];
    }

    /**
     * Get sales statistics
     *
     * @return array
     */
    protected function getSalesStats(): array
    {
        $today = now()->today();

        $salesToday = $this->sales()->whereDate('date', $today)->sum('total_amount');
        $salesTotal = $this->sales()->sum('total_amount');
        $paidToday  = $this->sales()->whereDate('date', $today)->sum('paid');
        $paidTotal  = $this->sales()->sum('paid');

        $salesMonth = $this->sales()->whereBetween('date', [now()->startOfMonth(), now()])->sum('total_amount');

        return [
            'today' => [
                'amount' => $salesToday,
                'paid'   => $paidToday,
                'due'    => $salesToday - $paidToday,
                'count'  => $this->sales()->whereDate('date', $today)->count(),
            ],
            'month' => [
                'amount' => $salesMonth,
                'count'  => $this->sales()->whereBetween('date', [now()->startOfMonth(), now()])->count(),
            ],
            'total' => [
                'amount' => $salesTotal,
                'paid'   => $paidTotal,
                'due'    => $salesTotal - $paidTotal,
                'count'  => $this->sales()->count(),
            ],
        ];
    }

    /**
     * Get purchase statistics
     *
     * @return array
     */
    protected function getPurchaseStats(): array
    {
        $today = now()->today();

        $purchaseToday = $this->purchases()->whereDate('date', $today)->sum('total_amount');
        $purchaseTotal = $this->purchases()->sum('total_amount');
        $paidToday     = $this->purchases()->whereDate('date', $today)->sum('paid');
        $paidTotal     = $this->purchases()->sum('paid');

        return [
            'today' => ['amount' => $purchaseToday, 'paid' => $paidToday, 'due' => $purchaseToday - $paidToday],
            'total' => ['amount' => $purchaseTotal, 'paid' => $paidTotal, 'due' => $purchaseTotal - $paidTotal],
        ];
    }

    /**
     * Get product statistics (company-level inventory, gated by permission)
     *
     * @return array
     */
    protected function getProductStats(): array
    {
        if (isset($this->memo['products'])) {
            return $this->memo['products'];
        }

        $stockData = Stock::select('product_id')
            ->selectRaw('SUM(in_qty - out_qty) as stock_qty')
            ->groupBy('product_id')
            ->get();

        return $this->memo['products'] = [
            'total_products' => Product::count(),
            'in_stock'       => $stockData->filter(fn ($i) => $i->stock_qty >= 1)->count(),
            'low_stock'      => $stockData->filter(fn ($i) => $i->stock_qty > 0 && $i->stock_qty < 10)->count(),
            'out_of_stock'   => $stockData->filter(fn ($i) => $i->stock_qty <= 0)->count(),
        ];
    }

    /**
     * Get lead statistics
     *
     * @return array
     */
    protected function getLeadStats(): array
    {
        return [
            'total'      => $this->leads()->count(),
            'today'      => $this->leads()->whereDate('leads.created_at', now()->today())->count(),
            'new'        => $this->leads()->where('lead_status', LeadStatus::NEW->value)->count(),
            'converted'  => $this->leads()->where('lead_status', LeadStatus::WON->value)->count(),
            'lost'       => $this->leads()->where('lead_status', LeadStatus::LOST->value)->count(),
            'this_month' => $this->leads()->whereBetween('leads.created_at', [now()->startOfMonth(), now()])->count(),
        ];
    }

    /**
     * Get customer statistics
     *
     * @return array
     */
    protected function getCustomerStats(): array
    {
        $customerLedger = CustomerLedger::selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')->first();
        $totalDebit = $customerLedger->total_debit ?? 0;
        $totalCredit = $customerLedger->total_credit ?? 0;

        return [
            'total'        => $this->customers()->count(),
            'today'        => $this->customers()->whereDate('customers.created_at', now()->today())->count(),
            'this_month'   => $this->customers()->whereBetween('customers.created_at', [now()->startOfMonth(), now()])->count(),
            'total_debit'  => $totalDebit,
            'total_credit' => $totalCredit,
            'balance'      => $totalDebit - $totalCredit,
        ];
    }

    /**
     * Get supplier statistics
     *
     * @return array
     */
    protected function getSupplierStats(): array
    {
        return [
            'total'      => Supplier::count(),
            'today'      => Supplier::whereDate('created_at', now()->today())->count(),
            'this_month' => Supplier::whereBetween('created_at', [now()->startOfMonth(), now()])->count(),
        ];
    }

    /**
     * Get financial statistics
     *
     * @return array
     */
    protected function getFinancialStats(): array
    {
        $thisMonth = now()->startOfMonth();

        $income  = Ledger::where('amount_type', Helper::$income)->whereBetween('date', [$thisMonth, now()])->sum('income_amount');
        $expense = Ledger::where('amount_type', Helper::$expense)->whereBetween('date', [$thisMonth, now()])->sum('expense_amount');

        return [
            'income_this_month'  => $income,
            'expense_this_month' => $expense,
            'net_profit'         => $income - $expense,
        ];
    }

    /**
     * Get membership statistics
     *
     * @return array
     */
    protected function getMembershipStats(): array
    {
        $membersByTier = MembershipTier::select('membership_tiers.id', 'membership_tiers.name', 'membership_tiers.min_purchase')
            ->selectRaw('COUNT(DISTINCT customers.id) as members_count')
            ->leftJoin('customers', 'membership_tiers.id', '=', 'customers.membership_tier_id')
            ->groupBy('membership_tiers.id', 'membership_tiers.name', 'membership_tiers.min_purchase')
            ->orderBy('membership_tiers.min_purchase')
            ->get();

        $issued = RewardLedger::where('points', '>', 0)->sum('points');
        $redeemed = abs(RewardLedger::where('points', '<', 0)->sum('points'));

        return [
            'members_by_tier' => $membersByTier,
            'points' => ['issued' => $issued, 'redeemed' => $redeemed, 'balance' => $issued - $redeemed],
            'referrals' => [
                'total'      => Referral::count(),
                'successful' => Referral::where('status', 'successful')->count(),
                'pending'    => Referral::where('status', 'pending')->count(),
            ],
        ];
    }

    /**
     * Get inventory alert statistics
     *
     * @return array
     */
    protected function getInventoryStats(): array
    {
        $lowStockThreshold = 5;
        // Phase 2 (A4): per-product reorder level, falling back to the old fixed threshold
        $levelSql = 'COALESCE((SELECT p_rl.reorder_level FROM products p_rl WHERE p_rl.id = stocks.product_id), ?)';

        $mapStock = fn ($item) => [
            'product_id'    => $item->product_id,
            'product_name'  => $item->product->model ?? 'N/A',
            'category'      => $item->product->category->category_name ?? 'N/A',
            'stock'         => (int) max(0, $item->stock_qty),
            'reorder_level' => (int) ($item->product->reorder_level ?? $lowStockThreshold),
        ];

        $lowStockProducts = Stock::select('product_id')
            ->selectRaw('SUM(in_qty - out_qty) as stock_qty')
            ->groupBy('product_id')
            ->havingRaw("SUM(in_qty - out_qty) > 0 AND SUM(in_qty - out_qty) <= $levelSql", [$lowStockThreshold])
            ->with('product.category')
            ->limit(20)
            ->get()
            ->map($mapStock);

        $fastMovingProducts = SaleDetail::select('product_id')
            ->selectRaw('SUM(qty) as total_qty')
            ->whereHas('salesMaster', function ($query) {
                $query->whereBetween('date', [now()->subDays(30)->startOfDay(), now()->endOfDay()]);
            })
            ->groupBy('product_id')
            ->orderBy('total_qty', 'desc')
            ->limit(20)
            ->with('product.category')
            ->get()
            ->map(fn ($item) => [
                'product_id'   => $item->product_id,
                'product_name' => $item->product->model ?? 'N/A',
                'category'     => $item->product->category->category_name ?? 'N/A',
                'qty_sold'     => (int) $item->total_qty,
            ]);

        $purchaseNeededProducts = Stock::select('product_id')
            ->selectRaw('SUM(in_qty - out_qty) as stock_qty')
            ->groupBy('product_id')
            ->havingRaw("SUM(in_qty - out_qty) <= 0 OR SUM(in_qty - out_qty) < $levelSql", [$lowStockThreshold])
            ->with('product.category')
            ->limit(20)
            ->get()
            ->map($mapStock);

        $stockValue = DB::select("SELECT COALESCE(SUM(s.sq * COALESCE(p.avg_cost, 0)), 0) as val FROM (SELECT product_id, SUM(in_qty - out_qty) as sq FROM stocks WHERE deleted_at IS NULL GROUP BY product_id HAVING sq > 0) s LEFT JOIN (SELECT product_id, AVG(purchase_price) as avg_cost FROM purchase_details GROUP BY product_id) p ON s.product_id = p.product_id");

        return [
            'low_stock'       => ['count' => $lowStockProducts->count(), 'products' => $lowStockProducts],
            'fast_moving'     => ['count' => $fastMovingProducts->count(), 'products' => $fastMovingProducts],
            'purchase_needed' => ['count' => $purchaseNeededProducts->count(), 'products' => $purchaseNeededProducts],
            'stock_value'     => $stockValue[0]->val ?? 0,
            'stock_units'     => (int) (Stock::selectRaw('SUM(in_qty - out_qty) as total')->value('total') ?? 0),
        ];
    }

    /**
     * Get chart data
     *
     * @return array
     */
    protected function getChartData(): array
    {
        $from = now()->subMonths(5)->startOfMonth();

        // Build a continuous 6-month axis so empty months show as 0 instead of disappearing
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));

        $salesByMonth = $this->sales()
            ->selectRaw('DATE_FORMAT(date, "%Y-%m") as month, SUM(total_amount) as total')
            ->whereBetween('date', [$from, now()])
            ->groupBy('month')
            ->pluck('total', 'month');

        $purchasesByMonth = $this->purchases()
            ->selectRaw('DATE_FORMAT(date, "%Y-%m") as month, SUM(total_amount) as total')
            ->whereBetween('date', [$from, now()])
            ->groupBy('month')
            ->pluck('total', 'month');

        $toSeries = fn ($data) => $months->map(fn ($m) => [
            'month' => \Carbon\Carbon::createFromFormat('Y-m', $m)->format('M y'),
            'total' => (float) ($data[$m] ?? 0),
        ])->values();

        $group = fn (string $col) => $this->leads()
            ->select($col)
            ->selectRaw('count(*) as count')
            ->whereNotNull($col)
            ->where($col, '!=', '')
            ->groupBy($col)
            ->get();

        return [
            'monthly_sales'             => $toSeries($salesByMonth),
            'monthly_purchases'         => $toSeries($purchasesByMonth),
            'lead_status_distribution'  => $this->leads()->select('lead_status')->selectRaw('count(*) as count')->groupBy('lead_status')->get(),
            'lead_type_distribution'    => $group('lead_type'),
            'lead_source_distribution'  => $group('source_of_entry'),
            'lead_inquiry_distribution' => $group('inquiry_for'),
        ];
    }

    /**
     * Get ticket statistics
     *
     * @return array
     */
    protected function getTicketStats(): array
    {
        return [
            'total'  => $this->tickets()->count(),
            'active' => $this->tickets()->whereIn('status', $this->activeTicketStatuses())->count(),
            'today'  => $this->tickets()->whereDate('tickets.created_at', now()->today())->count(),
        ];
    }

    /**
     * Get task statistics
     *
     * @return array
     */
    protected function getTaskStats(): array
    {
        $open = [TaskStatus::COMPLETED->value, TaskStatus::CANCELLED->value];

        return [
            'total'   => $this->tasks()->count(),
            'pending' => $this->tasks()->whereNotIn('status', $open)->count(),
            'overdue' => $this->tasks()->where('due_date', '<', now())->whereNotIn('status', $open)->count(),
            'today'   => $this->tasks()->whereDate('tasks.created_at', now()->today())->count(),
        ];
    }

    /**
     * Pending follow-up counts
     */
    protected function getFollowUpStats(): array
    {
        $pending = fn () => $this->followUps()->where('status', '!=', 'completed');

        return [
            'pending' => $pending()->count(),
            'overdue' => $pending()->whereDate('follow_up_date', '<', now()->today())->count(),
            'today'   => $pending()->whereDate('follow_up_date', now()->today())->count(),
        ];
    }

    /**
     * Get appointment statistics
     *
     * @return array
     */
    protected function getAppointmentStats(): array
    {
        $byStatus = $this->appts()
            ->select('status')
            ->selectRaw('count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'total'      => array_sum($byStatus),
            'today'      => $this->appts()->whereDate('appointment_date', now()->today())->count(),
            'this_week'  => $this->appts()->whereBetween('appointment_date', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'this_month' => $this->appts()->whereBetween('appointment_date', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'upcoming'   => $this->appts()->where('status', AppointmentStatus::SCHEDULED)->whereDate('appointment_date', '>=', now()->today())->count(),
            'scheduled'  => $byStatus[AppointmentStatus::SCHEDULED->value] ?? 0,
            'completed'  => $byStatus[AppointmentStatus::COMPLETED->value] ?? 0,
            'cancelled'  => $byStatus[AppointmentStatus::CANCELLED->value] ?? 0,
            'by_status'  => $byStatus,
        ];
    }

    /**
     * Get performance statistics for staff
     *
     * @return array
     */
    protected function getPerformanceStats(): array
    {
        if (isset($this->memo['performance'])) {
            return $this->memo['performance'];
        }

        $query = User::select('users.id', 'users.name')
            ->selectRaw('
                (SELECT COUNT(*) FROM leads WHERE leads.created_by = users.id AND leads.deleted_at IS NULL) as total_leads,
                (SELECT COUNT(*) FROM leads WHERE leads.created_by = users.id AND leads.deleted_at IS NULL AND leads.lead_status = ?) as won_leads,
                (SELECT COALESCE(SUM(total_amount), 0) FROM sale_masters WHERE sale_masters.created_by = users.id AND sale_masters.deleted_at IS NULL) as total_revenue,
                (SELECT COUNT(*) FROM lead_follow_ups WHERE lead_follow_ups.assigned_user = users.id AND lead_follow_ups.status = "completed" AND lead_follow_ups.deleted_at IS NULL) as follow_ups_completed,
                (SELECT COUNT(*) FROM lead_follow_ups WHERE lead_follow_ups.assigned_user = users.id AND lead_follow_ups.follow_up_date <= NOW() AND lead_follow_ups.deleted_at IS NULL) as expected_follow_ups
            ', [LeadStatus::WON->value])
            ->whereNotNull('users.name')
            ->where('users.status', 1);

        // Staff without "view all" only see their own row
        if (!DataScope::seesAll()) {
            $query->where('users.id', DataScope::uid());
        } elseif (DataScope::user()?->isVendor()) {
            $query->where('users.id', DataScope::uid());
        }

        $staffPerformance = $query
            ->havingRaw('(total_leads > 0 OR total_revenue > 0)')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get()
            ->map(function ($staff) {
                $conversionRate = $staff->total_leads > 0 ? ($staff->won_leads / $staff->total_leads) * 100 : 0;
                $compliance = $staff->expected_follow_ups > 0
                    ? min(100, ($staff->follow_ups_completed / $staff->expected_follow_ups) * 100)
                    : 0;

                return [
                    'staff_id'             => $staff->id,
                    'staff_name'           => $staff->name,
                    'total_leads'          => (int) $staff->total_leads,
                    'won_leads'            => (int) $staff->won_leads,
                    'conversion_rate'      => round($conversionRate, 2),
                    'total_revenue'        => (float) $staff->total_revenue,
                    'follow_ups_completed' => (int) $staff->follow_ups_completed,
                    'follow_up_compliance' => round($compliance, 2),
                ];
            });

        return $this->memo['performance'] = [
            'staff_performance' => $staffPerformance,
            'top_performer'     => $staffPerformance->first(),
            'averages' => [
                'conversion_rate'      => round($staffPerformance->avg('conversion_rate') ?? 0, 2),
                'follow_up_compliance' => round($staffPerformance->avg('follow_up_compliance') ?? 0, 2),
                'total_revenue'        => $staffPerformance->sum('total_revenue') ?? 0,
            ],
        ];
    }

    /**
     * Get marketing insights for the dashboard
     *
     * @return array
     */
    protected function getMarketingInsights(): array
    {
        $totalCustomers = max($this->customers()->count(), 1);
        $totalSales = $this->sales()->sum('total_amount');
        $avgRevenuePerCustomer = round($totalSales / $totalCustomers, 2);

        $leadsThisMonth = $this->leads()->whereBetween('leads.created_at', [now()->startOfMonth(), now()])->count();
        $leadsLastMonth = $this->leads()->whereBetween('leads.created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])->count();
        $leadVelocity = $leadsLastMonth > 0 ? round((($leadsThisMonth - $leadsLastMonth) / $leadsLastMonth) * 100, 1) : 0;

        $revenueThisMonth = $this->sales()->whereBetween('date', [now()->startOfMonth(), now()])->sum('total_amount');
        $revenueLastMonth = $this->sales()->whereBetween('date', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])->sum('total_amount');
        $revenueGrowth = $revenueLastMonth > 0 ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1) : 0;

        $bestSource = $this->leads()
            ->select('source_of_entry')
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN lead_status = ? THEN 1 ELSE 0 END) as won', [LeadStatus::WON->value])
            ->whereNotNull('source_of_entry')
            ->where('source_of_entry', '!=', '')
            ->groupBy('source_of_entry')
            ->orderByDesc('won')
            ->first();

        $messages = [];

        if ($leadVelocity > 0) {
            $messages[] = ['type' => 'up', 'icon' => 'fas fa-arrow-up', 'text' => 'Lead volume is up ' . abs($leadVelocity) . '% vs last month — your marketing is working.'];
        } elseif ($leadVelocity < 0) {
            $messages[] = ['type' => 'down', 'icon' => 'fas fa-arrow-down', 'text' => 'Lead volume dropped ' . abs($leadVelocity) . '% vs last month — consider boosting campaigns.'];
        }

        if ($revenueGrowth > 0) {
            $messages[] = ['type' => 'up', 'icon' => 'fas fa-chart-line', 'text' => 'Revenue grew ' . abs($revenueGrowth) . '% month-over-month — great momentum.'];
        } elseif ($revenueGrowth < 0) {
            $messages[] = ['type' => 'down', 'icon' => 'fas fa-chart-line', 'text' => 'Revenue declined ' . abs($revenueGrowth) . '% vs last month — review pricing & offers.'];
        }

        $convRate = $this->getPerformanceStats()['averages']['conversion_rate'] ?? 0;
        if ($convRate < 20) {
            $messages[] = ['type' => 'warn', 'icon' => 'fas fa-exclamation-triangle', 'text' => 'Conversion rate is ' . $convRate . '% — focus on lead nurturing and follow-ups.'];
        } elseif ($convRate >= 40) {
            $messages[] = ['type' => 'up', 'icon' => 'fas fa-trophy', 'text' => 'Conversion rate at ' . $convRate . '% — excellent sales performance.'];
        }

        $overdueFollowUps = $this->getFollowUpStats()['overdue'] ?? 0;
        if ($overdueFollowUps > 0) {
            $messages[] = ['type' => 'warn', 'icon' => 'fas fa-phone-slash', 'text' => $overdueFollowUps . ' follow-up(s) are overdue — clear them from the Daily Lookout.'];
        }

        if (DataScope::can('dashboard.inventory') && ($this->getProductStats()['low_stock'] ?? 0) > 3) {
            $messages[] = ['type' => 'warn', 'icon' => 'fas fa-box-open', 'text' => $this->getProductStats()['low_stock'] . ' products running low on stock — review purchase orders.'];
        }

        if ($bestSource && $bestSource->total > 0) {
            $srcName = ucfirst(str_replace('_', ' ', $bestSource->source_of_entry));
            $messages[] = ['type' => 'info', 'icon' => 'fas fa-bullhorn', 'text' => '"' . $srcName . '" is your top lead source with ' . $bestSource->total . ' leads — invest more here.'];
        }

        return [
            'avg_revenue_per_customer' => $avgRevenuePerCustomer,
            'lead_velocity'            => $leadVelocity,
            'leads_this_month'         => $leadsThisMonth,
            'leads_last_month'         => $leadsLastMonth,
            'revenue_growth'           => $revenueGrowth,
            'revenue_this_month'       => $revenueThisMonth,
            'revenue_last_month'       => $revenueLastMonth,
            'best_source'              => $bestSource,
            'messages'                 => $messages,
        ];
    }

    /**
     * Daily lookout items — the first thing a user works on each day.
     * Each item carries the ids needed for click-through links and quick actions.
     */
    protected function getDailyLookout(): array
    {
        $can = fn (string $p) => DataScope::can('dashboard.' . $p);
        $out = [
            'warranty_expiry' => collect(), 'battery_expiry' => collect(), 'appointments' => collect(),
            'tasks' => collect(), 'tickets' => collect(), 'follow_ups' => collect(),
        ];

        // Restrict warranty/battery rows to sales the user can see
        $salesScope = function ($q, string $column, int $daysAhead = 30, int $daysBehind = 0) {
            DataScope::ownedBy($q);
            if ($daysBehind > 0) {
                $q->whereRaw("DATE_ADD(date, INTERVAL (SELECT $column FROM products WHERE products.id = sale_details.product_id) MONTH) BETWEEN DATE_SUB(CURDATE(), INTERVAL {$daysBehind} DAY) AND DATE_ADD(CURDATE(), INTERVAL {$daysAhead} DAY)");
            } else {
                $q->whereRaw("DATE_ADD(date, INTERVAL (SELECT $column FROM products WHERE products.id = sale_details.product_id) MONTH) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL {$daysAhead} DAY)");
            }
        };

        if ($can('warranty_alerts')) {
            // 1. Warranty expiry within 30 days
            $out['warranty_expiry'] = SaleDetail::with(['product:id,model,warranty_sales_months', 'salesMaster.customer.lead:id,full_name,phone_number'])
                ->whereHas('product', fn ($q) => $q->whereNotNull('warranty_sales_months')->where('warranty_sales_months', '>', 0))
                ->whereHas('salesMaster', fn ($q) => $salesScope($q, 'warranty_sales_months', 30, 5))
                ->limit(20)
                ->get()
                ->map(function ($item) {
                    $saleDate = $item->salesMaster->date ?? null;
                    $expiry = $saleDate ? \Carbon\Carbon::parse($saleDate)->addMonths($item->product->warranty_sales_months ?? 0) : null;
                    return [
                        'customer_id' => $item->salesMaster->customer_id ?? null,
                        'sale_id'     => $item->sale_master_id,
                        'customer'    => $item->salesMaster->customer->lead->full_name ?? 'N/A',
                        'phone'       => $item->salesMaster->customer->lead->phone_number ?? '',
                        'product'     => $item->product->model ?? 'N/A',
                        'expiry_date' => $expiry ? $expiry->format('d M Y') : 'N/A',
                        'days_left'   => $expiry ? (int) now()->startOfDay()->diffInDays($expiry, false) : 0,
                    ];
                })
                ->sortBy('days_left')->values();

            // 2. Battery usage expiry within 15 days
            $out['battery_expiry'] = SaleDetail::with(['product:id,model,battery_size,usage_months', 'salesMaster.customer.lead:id,full_name,phone_number'])
                ->whereHas('product', fn ($q) => $q->whereNotNull('usage_months')->where('usage_months', '>', 0))
                ->whereHas('salesMaster', fn ($q) => $salesScope($q, 'usage_months', 15, 5))
                ->limit(20)
                ->get()
                ->map(function ($item) {
                    $saleDate = $item->salesMaster->date ?? null;
                    $expiry = $saleDate ? \Carbon\Carbon::parse($saleDate)->addMonths($item->product->usage_months ?? 0) : null;
                    return [
                        'customer_id'  => $item->salesMaster->customer_id ?? null,
                        'sale_id'      => $item->sale_master_id,
                        'customer'     => $item->salesMaster->customer->lead->full_name ?? 'N/A',
                        'phone'        => $item->salesMaster->customer->lead->phone_number ?? '',
                        'product'      => $item->product->model ?? 'N/A',
                        'battery_size' => $item->product->battery_size ?? 'N/A',
                        'expiry_date'  => $expiry ? $expiry->format('d M Y') : 'N/A',
                        'days_left'    => $expiry ? (int) now()->startOfDay()->diffInDays($expiry, false) : 0,
                    ];
                })
                ->sortBy('days_left')->values();
        }

        if ($can('appointments')) {
            // 3. Today's appointments
            $out['appointments'] = $this->appts()
                ->with(['lead:id,full_name,phone_number', 'customer.lead:id,full_name,phone_number'])
                ->whereDate('appointment_date', today())
                ->orderBy('appointment_date')
                ->limit(15)
                ->get()
                ->map(function ($a) {
                    $status = $a->status instanceof \BackedEnum ? $a->status->value : $a->status;
                    return [
                        'id'       => $a->id,
                        'customer' => $a->customer->lead->full_name ?? ($a->lead->full_name ?? 'N/A'),
                        'phone'    => $a->customer->lead->phone_number ?? ($a->lead->phone_number ?? ''),
                        'date'     => \Carbon\Carbon::parse($a->appointment_date)->format('h:i A'),
                        'status'   => $status,
                    ];
                });
        }

        if ($can('total_tasks')) {
            // 4. Pending / overdue tasks
            $out['tasks'] = $this->tasks()
                ->with('assignedTo:id,name')
                ->whereNotIn('status', [TaskStatus::COMPLETED->value, TaskStatus::CANCELLED->value])
                ->orderByRaw('due_date IS NULL, due_date ASC')
                ->limit(15)
                ->get()
                ->map(function ($t) {
                    $status = $t->status instanceof \BackedEnum ? $t->status->value : $t->status;
                    $priority = $t->priority instanceof \BackedEnum ? $t->priority->value : ($t->priority ?? 'normal');
                    return [
                        'id'          => $t->id,
                        'subject'     => $t->subject,
                        'assigned_to' => $t->assignedTo->name ?? 'Unassigned',
                        'due_date'    => $t->due_date ? \Carbon\Carbon::parse($t->due_date)->format('d M Y') : 'No due date',
                        'is_overdue'  => $t->due_date && \Carbon\Carbon::parse($t->due_date)->lt(now()),
                        'priority'    => $priority,
                        'status'      => $status,
                    ];
                });
        }

        if ($can('total_tickets')) {
            // 5. Active tickets
            $out['tickets'] = $this->tickets()
                ->with(['assignedTo:id,name', 'customer.lead:id,full_name'])
                ->whereIn('status', $this->activeTicketStatuses())
                ->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
                ->limit(15)
                ->get()
                ->map(function ($t) {
                    $status = $t->status instanceof \BackedEnum ? $t->status->value : $t->status;
                    $priority = $t->priority instanceof \BackedEnum ? $t->priority->value : ($t->priority ?? 'normal');
                    return [
                        'id'          => $t->id,
                        'subject'     => $t->subject,
                        'customer'    => $t->customer->lead->full_name ?? null,
                        'assigned_to' => $t->assignedTo->name ?? 'Unassigned',
                        'priority'    => $priority,
                        'status'      => $status,
                        'created'     => $t->created_at?->diffForHumans(),
                    ];
                });
        }

        if ($can('follow_ups')) {
            // 6. Pending follow-ups: overdue + today + next 3 days
            $out['follow_ups'] = $this->followUps()
                ->with(['lead:id,full_name,phone_number', 'assignedUser:id,name'])
                ->where('status', '!=', 'completed')
                ->whereHas('lead')
                ->where('follow_up_date', '<=', now()->addDays(3)->endOfDay())
                ->orderBy('follow_up_date')
                ->limit(20)
                ->get()
                ->map(function ($f) {
                    $date = \Carbon\Carbon::parse($f->follow_up_date);
                    return [
                        'id'          => $f->id,
                        'lead_id'     => $f->lead_id,
                        'lead'        => $f->lead->full_name ?? 'N/A',
                        'phone'       => $f->lead->phone_number ?? '',
                        'assigned_to' => $f->assignedUser->name ?? 'Unassigned',
                        'date'        => $date->format('d M Y'),
                        'time'        => $date->format('h:i A'),
                        'mode'        => $f->follow_up_mode_label ?: 'N/A',
                        'is_overdue'  => $date->lt(today()),
                        'is_today'    => $date->isToday(),
                        'notes'       => $f->follow_up_notes,
                    ];
                });
        }

        return $out;
    }
}
