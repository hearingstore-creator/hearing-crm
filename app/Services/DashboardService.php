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
use App\Enums\RewardLedgerType;
use App\Models\SaleMaster;
use App\Models\SaleDetail;
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

class DashboardService
{
    /**
     * Get all dashboard statistics
     *
     * @return array
     */
    public function getDashboardStatistics(): array
    {
        return [
            'overview' => $this->getOverviewStats(),
            'sales' => $this->getSalesStats(),
            'purchases' => $this->getPurchaseStats(),
            'products' => $this->getProductStats(),
            'leads' => $this->getLeadStats(),
            'customers' => $this->getCustomerStats(),
            'suppliers' => $this->getSupplierStats(),
            'financials' => $this->getFinancialStats(),
            'tickets' => $this->getTicketStats(),
            'tasks' => $this->getTaskStats(),
            'appointments' => $this->getAppointmentStats(),
            'membership' => $this->getMembershipStats(),
            'inventory' => $this->getInventoryStats(),
            'performance' => $this->getPerformanceStats(),
            'charts' => $this->getChartData(),
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
            'today_sales' => SaleMaster::whereDate('date', $today)->sum('total_amount'),
            'today_sales_count' => SaleMaster::whereDate('date', $today)->count(),
            'total_sales' => SaleMaster::sum('total_amount'),
            'year_sales' => SaleMaster::whereBetween('date', [$thisYear, now()])->sum('total_amount'),
            'total_customers' => Customer::count(),
            'total_leads' => Lead::count(),
            'total_products' => Product::count(),
            'total_suppliers' => Supplier::count(),
            'total_doctors' => Doctor::count(),
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

        $salesToday = SaleMaster::whereDate('date', $today)->sum('total_amount');
        $salesTotal = SaleMaster::sum('total_amount');

        $paidToday = SaleMaster::whereDate('date', $today)->sum('paid');
        $paidTotal = SaleMaster::sum('paid');

        $dueToday = $salesToday - $paidToday;
        $dueTotal = $salesTotal - $paidTotal;

        $salesCountToday = SaleMaster::whereDate('date', $today)->count();
        $salesCountThisMonth = SaleMaster::count();

        // Top selling products using Eloquent relationships
        $topProducts = SaleDetail::with('product')
            ->select('product_id')
            ->selectRaw('SUM(qty) as total_qty')
            ->whereHas('salesMaster', function($query) {
                $query->whereBetween('date', [now()->startOfMonth(), now()]);
            })
            ->groupBy('product_id')
            ->orderBy('total_qty', 'desc')
            ->limit(5)
            ->get()
            ->map(function($item) {
                return [
                    'product_name' => $item->product->category->category_name ?? 'Unknown' . ' - ' . $item->product->model,
                    'total_qty' => $item->total_qty,
                ];
            });

        return [
            'today' => [
                'amount' => $salesToday,
                'paid' => $paidToday,
                'due' => $dueToday,
                'count' => $salesCountToday,
            ],
            'total' => [
                'amount' => $salesTotal,
                'paid' => $paidTotal,
                'due' => $dueTotal,
                'count' => $salesCountThisMonth,
            ],
            'top_products' => $topProducts,
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

        $purchaseToday = PurchaseMaster::whereDate('date', $today)->sum('total_amount');
        $purchaseTotal = PurchaseMaster::sum('total_amount');

        $paidToday = PurchaseMaster::whereDate('date', $today)->sum('paid');
        $paidTotal = PurchaseMaster::sum('paid');

        $dueToday = $purchaseToday - $paidToday;
        $due = $purchaseTotal - $paidTotal;

        return [
            'today' => [
                'amount' => $purchaseToday,
                'paid' => $paidToday,
                'due' => $dueToday,
            ],
            'total' => [
                'amount' => $purchaseTotal,
                'paid' => $paidTotal,
                'due' => $due,
            ],
        ];
    }

    /**
     * Get product statistics
     *
     * @return array
     */
    protected function getProductStats(): array
    {
        $totalProducts = Product::count();

        // Get stock data using Eloquent
        $stockData = Stock::select('product_id')
            ->selectRaw('SUM(in_qty - out_qty) as stock_qty')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $outOfStockCount = $stockData->filter(function($item) {
            return $item->stock_qty <= 0;
        })->count();

        $lowStockCount = $stockData->filter(function($item) {
            return $item->stock_qty > 0 && $item->stock_qty < 10;
        })->count();

        $inStockCount = $stockData->filter(function($item) {
            return $item->stock_qty >= 1;
        })->count();

        return [
            'total_products' => $totalProducts,
            'in_stock' => $inStockCount,
            'low_stock' => $lowStockCount,
            'out_of_stock' => $outOfStockCount,
        ];
    }

    /**
     * Get lead statistics
     *
     * @return array
     */
    protected function getLeadStats(): array
    {
        $totalLeads = Lead::count();
        $newLeads = Lead::where('lead_status', LeadStatus::NEW->value)->count();
        $convertedLeads = Lead::where('lead_status', LeadStatus::WON->value)->count();
        $lostLeads = Lead::where('lead_status', LeadStatus::LOST->value)->count();

        // Leads created this month
        $leadsThisMonth = Lead::whereBetween('created_at', [now()->startOfMonth(), now()])->count();

        // Leads by source using Eloquent
        $leadsBySource = Lead::select('source_of_entry')
            ->selectRaw('count(*) as count')
            ->groupBy('source_of_entry')
            ->get();

        return [
            'total' => $totalLeads,
            'new' => $newLeads,
            'converted' => $convertedLeads,
            'lost' => $lostLeads,
            'this_month' => $leadsThisMonth,
            'by_source' => $leadsBySource,
        ];
    }

    /**
     * Get customer statistics
     *
     * @return array
     */
    protected function getCustomerStats(): array
    {
        $totalCustomers = Customer::count();

        // Customers added this month
        $customersThisMonth = Customer::whereBetween('created_at', [now()->startOfMonth(), now()])->count();

        // Customer ledger summary using Eloquent
        $customerLedger = CustomerLedger::selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $totalDebit = $customerLedger->total_debit ?? 0;
        $totalCredit = $customerLedger->total_credit ?? 0;

        return [
            'total' => $totalCustomers,
            'this_month' => $customersThisMonth,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balance' => $totalDebit - $totalCredit,
        ];
    }

    /**
     * Get supplier statistics
     *
     * @return array
     */
    protected function getSupplierStats(): array
    {
        $today = now()->today();
        $thisMonth = now()->startOfMonth();

        $totalSuppliers = Supplier::count();
        $suppliersToday = Supplier::whereDate('created_at', $today)->count();
        $suppliersThisMonth = Supplier::whereBetween('created_at', [$thisMonth, now()])->count();

        // Pending suppliers
        $pendingSuppliers = Supplier::count();

        return [
            'total' => $totalSuppliers,
            'today' => $suppliersToday,
            'this_month' => $suppliersThisMonth,
            'pending' => $pendingSuppliers,
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

        // Income using Eloquent
        $incomeThisMonth = Ledger::where('amount_type', Helper::$income)->whereBetween('date', [$thisMonth, now()])->sum('income_amount');

        // Expense using Eloquent
        $expenseThisMonth = Ledger::where('amount_type', Helper::$expense)->whereBetween('date', [$thisMonth, now()])->sum('expense_amount');

        return [
            'income_this_month' => $incomeThisMonth,
            'expense_this_month' => $expenseThisMonth,
            'net_profit' => $incomeThisMonth - $expenseThisMonth,
        ];
    }

    /**
     * Get chart data
     *
     * @return array
     */
    protected function getMembershipStats(): array
    {
        // Active members by tier
        $membersByTier = MembershipTier::select('membership_tiers.id', 'membership_tiers.name', 'membership_tiers.min_purchase')
            ->selectRaw('COUNT(DISTINCT customers.id) as members_count')
            ->leftJoin('customers', 'membership_tiers.id', '=', 'customers.membership_tier_id')
            ->groupBy('membership_tiers.id', 'membership_tiers.name', 'membership_tiers.min_purchase')
            ->orderBy('membership_tiers.min_purchase')
            ->get();

        // Points issued vs redeemed
        $pointsIssued = RewardLedger::where('points', '>', 0)->sum('points');
        $pointsRedeemed = abs(RewardLedger::where('points', '<', 0)->sum('points'));
        $pointsBalance = $pointsIssued - $pointsRedeemed;

        // Referral count
        $totalReferrals = Referral::count();
        $successfulReferrals = Referral::where('status', 'successful')->count();
        $pendingReferrals = Referral::where('status', 'pending')->count();

        return [
            'members_by_tier' => $membersByTier,
            'points' => [
                'issued' => $pointsIssued,
                'redeemed' => $pointsRedeemed,
                'balance' => $pointsBalance,
            ],
            'referrals' => [
                'total' => $totalReferrals,
                'successful' => $successfulReferrals,
                'pending' => $pendingReferrals,
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

        // Low stock products (stock between 1 and 5) - max 20
        $lowStockProducts = Stock::select('product_id')
            ->selectRaw('SUM(in_qty - out_qty) as stock_qty')
            ->groupBy('product_id')
            ->havingRaw('SUM(in_qty - out_qty) > 0 AND SUM(in_qty - out_qty) <= ?', [$lowStockThreshold])
            ->with('product.category')
            ->limit(20)
            ->get()
            ->map(function($item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->model ?? 'N/A',
                    'category' => $item->product->category->category_name ?? 'N/A',
                    'stock' => (int) $item->stock_qty,
                ];
            });

        // Fast moving products (highest sales quantity in last 30 days) - max 20
        $fastMovingProducts = SaleDetail::select('product_id')
            ->selectRaw('SUM(qty) as total_qty')
            ->whereHas('salesMaster', function($query) {
                $query->whereBetween('date', [now()->subDays(30)->startOfDay(), now()->endOfDay()]);
            })
            ->groupBy('product_id')
            ->orderBy('total_qty', 'desc')
            ->limit(20)
            ->with('product.category')
            ->get()
            ->map(function($item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->model ?? 'N/A',
                    'category' => $item->product->category->category_name ?? 'N/A',
                    'qty_sold' => (int) $item->total_qty,
                ];
            });

        // Purchase needed products (out of stock OR below 5 units) - max 20
        $purchaseNeededProducts = Stock::select('product_id')
            ->selectRaw('SUM(in_qty - out_qty) as stock_qty')
            ->groupBy('product_id')
            ->havingRaw('SUM(in_qty - out_qty) <= 0 OR SUM(in_qty - out_qty) < ?', [$lowStockThreshold])
            ->with('product.category')
            ->limit(20)
            ->get()
            ->map(function($item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->model ?? 'N/A',
                    'category' => $item->product->category->category_name ?? 'N/A',
                    'stock' => (int) max(0, $item->stock_qty),
                ];
            });

        return [
            'low_stock' => [
                'count' => $lowStockProducts->count(),
                'products' => $lowStockProducts,
            ],
            'fast_moving' => [
                'count' => $fastMovingProducts->count(),
                'products' => $fastMovingProducts,
            ],
            'purchase_needed' => [
                'count' => $purchaseNeededProducts->count(),
                'products' => $purchaseNeededProducts,
            ],
        ];
    }

    /**
     * Get chart data
     *
     * @return array
     */
    protected function getChartData(): array
    {
        // Monthly sales data for the last 6 months
        $monthlySales = SaleMaster::selectRaw('DATE_FORMAT(date, "%Y-%m") as month, SUM(total_amount) as total')
            ->whereBetween('date', [now()->subMonths(5)->startOfMonth(), now()])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Monthly purchase data for the last 6 months
        $monthlyPurchases = PurchaseMaster::selectRaw('DATE_FORMAT(date, "%Y-%m") as month, SUM(total_amount) as total')
            ->whereBetween('date', [now()->subMonths(5)->startOfMonth(), now()])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Lead status distribution using Eloquent
        $leadStatusDistribution = Lead::select('lead_status')
            ->selectRaw('count(*) as count')
            ->groupBy('lead_status')
            ->get();

        // Daily sales for current week
        $weeklySales = SaleMaster::selectRaw('DATE(date) as day, SUM(total_amount) as total')
            ->whereBetween('date', [now()->startOfWeek(), now()])
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return [
            'monthly_sales' => $monthlySales,
            'monthly_purchases' => $monthlyPurchases,
            'lead_status_distribution' => $leadStatusDistribution,
            'weekly_sales' => $weeklySales,
        ];
    }

    /**
     * Get ticket statistics
     *
     * @return array
     */
    protected function getTicketStats(): array
    {
        $totalTickets = Ticket::count();

        // Tickets created today
        $ticketsToday = Ticket::whereDate('created_at', now()->today())->count();

        // Active tickets (not solved or closed)
        $activeTickets = Ticket::whereIn('status', [
            TicketStatus::OPEN->value,
            TicketStatus::IN_PROGRESS->value,
            TicketStatus::PENDING->value,
            TicketStatus::ON_HOLD->value
        ])->count();

        return [
            'total' => $totalTickets,
            'active' => $activeTickets,
            'today' => $ticketsToday,
        ];
    }

    /**
     * Get task statistics
     *
     * @return array
     */
    protected function getTaskStats(): array
    {
        $totalTasks = Task::count();

        // Tasks created today
        $tasksToday = Task::whereDate('created_at', now()->today())->count();

        // Overdue tasks
        $overdueTasks = Task::where('due_date', '<', now())
            ->whereNotIn('status', [TaskStatus::COMPLETED->value, TaskStatus::CANCELLED->value])
            ->count();

        return [
            'total' => $totalTasks,
            'overdue' => $overdueTasks,
            'today' => $tasksToday,
        ];
    }

    /**
     * Get appointment statistics
     *
     * @return array
     */
    protected function getAppointmentStats(): array
    {
        $totalAppointments = Appointment::count();

        // Appointments today
        $appointmentsToday = Appointment::whereDate('appointment_date', now()->today())->count();

        // Appointments this week
        $appointmentsThisWeek = Appointment::whereBetween('appointment_date', [now()->startOfWeek(), now()->endOfWeek()])->count();

        // Appointments this month
        $appointmentsThisMonth = Appointment::whereBetween('appointment_date', [now()->startOfMonth(), now()->endOfMonth()])->count();

        // Upcoming appointments (future scheduled)
        $upcomingAppointments = Appointment::where('status', AppointmentStatus::SCHEDULED)
            ->whereDate('appointment_date', '>=', now()->today())
            ->count();

        // Past appointments
        $pastAppointments = Appointment::whereDate('appointment_date', '<', now()->today())->count();

        // Appointments by status
        $appointmentsByStatus = Appointment::select('status')
            ->selectRaw('count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'total'       => $totalAppointments,
            'today'       => $appointmentsToday,
            'this_week'   => $appointmentsThisWeek,
            'this_month'  => $appointmentsThisMonth,
            'upcoming'    => $upcomingAppointments,
            'past'        => $pastAppointments,
            'scheduled'   => $appointmentsByStatus[AppointmentStatus::SCHEDULED->value] ?? 0,
            'completed'   => $appointmentsByStatus[AppointmentStatus::COMPLETED->value] ?? 0,
            'cancelled'   => $appointmentsByStatus[AppointmentStatus::CANCELLED->value] ?? 0,
            'by_status'   => $appointmentsByStatus,
        ];
    }

    /**
     * Get performance statistics for staff
     *
     * @return array
     */
    protected function getPerformanceStats(): array
    {
        // Get staff performance data with optimized queries using subqueries to avoid duplication
        $staffPerformance = User::select('users.id', 'users.name')
            ->selectRaw('
                (SELECT COUNT(*) FROM leads WHERE leads.created_by = users.id) as total_leads,
                (SELECT COUNT(*) FROM leads WHERE leads.created_by = users.id AND leads.lead_status = ?) as won_leads,
                (SELECT COALESCE(SUM(total_amount), 0) FROM sale_masters WHERE sale_masters.created_by = users.id) as total_revenue,
                (SELECT COUNT(*) FROM lead_follow_ups WHERE lead_follow_ups.assigned_user = users.id AND lead_follow_ups.follow_up_date <= CURDATE()) as follow_ups_completed,
                (SELECT COUNT(*) FROM lead_follow_ups WHERE lead_follow_ups.assigned_user = users.id AND lead_follow_ups.follow_up_date <= CURDATE()) as expected_follow_ups
            ', [LeadStatus::WON->value])
            ->whereNotNull('users.name')
            ->where('users.status', 1)
            ->havingRaw('(total_leads > 0 OR total_revenue > 0)')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get()
            ->map(function($staff) {
                $conversionRate = $staff->total_leads > 0
                    ? ($staff->won_leads / $staff->total_leads) * 100
                    : 0;

                // Since we count all past follow-ups as both completed and expected,
                // compliance is 100% if they have any follow-ups
                $followUpCompliance = $staff->expected_follow_ups > 0 ? 100.0 : 0.0;

                return [
                    'staff_id' => $staff->id,
                    'staff_name' => $staff->name,
                    'total_leads' => (int) $staff->total_leads,
                    'won_leads' => (int) $staff->won_leads,
                    'conversion_rate' => round($conversionRate, 2),
                    'total_revenue' => (float) $staff->total_revenue,
                    'follow_ups_completed' => (int) $staff->follow_ups_completed,
                    'follow_up_compliance' => round($followUpCompliance, 2),
                ];
            });

        // Calculate overall averages
        $avgConversion = $staffPerformance->avg('conversion_rate') ?? 0;
        $avgCompliance = $staffPerformance->avg('follow_up_compliance') ?? 0;
        $totalRevenue = $staffPerformance->sum('total_revenue') ?? 0;

        return [
            'staff_performance' => $staffPerformance,
            'top_performer' => $staffPerformance->first(),
            'averages' => [
                'conversion_rate' => round($avgConversion, 2),
                'follow_up_compliance' => round($avgCompliance, 2),
                'total_revenue' => $totalRevenue,
            ],
        ];
    }
}
