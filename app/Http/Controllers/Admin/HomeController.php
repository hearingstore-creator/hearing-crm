<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Medicine;
use App\Models\Stock;
use App\Services\DashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    protected $dashboardService;

    /**
     * Create a new controller instance.
     *
     * @param  \App\Services\DashboardService  $dashboardService
     * @return void
     */
    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
        $this->middleware('permission:dashboard.index')->only(['dashboard', 'legacyDashboard']);
        // Legacy medicine / employee screens have no menu; their permissions are Super Admin only
        $this->middleware('permission:medicine.index')->only(['medicineSearch']);
        $this->middleware('permission:employee.index')->only(['employeeSearch']);
    }

    /**
     * Show the application dashboard.
     *
     * This method gathers various data points to display on the admin dashboard,
     * including total customers, today's sales, total medicines, and stock levels.
     * It also retrieves data for a chart displaying the best-selling medicines of the month.
     * The assembled data is then passed to the 'admin.dashboard.index' view.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function dashboard()
    {
        // config crm.dashboard (CRM_DASHBOARD) picks which one opens here; both also keep their own URL
        if (config('crm.dashboard') === 'new') {
            return app(DashboardTodayController::class)->index(request());
        }

        return $this->legacyDashboard();
    }

    /** The old dashboard, kept at /admin/dashboard/legacy until the new one is accepted. */
    public function legacyDashboard(): View
    {
        $statistics = $this->dashboardService->getDashboardStatistics();
        $analytics  = app(\App\Services\DashboardAnalyticsService::class)->build(request('period'));
        $myDay      = $this->myDay($statistics, $analytics);

        return view('admin.dashboard-legacy.index', compact('statistics', 'analytics', 'myDay'));
    }

    /** "My Day": my open deal activities, appointments, and actionable after-sales care tasks (service, warranty, birthdays). */
    public function myDay(array $statistics = [], array $analytics = []): array
    {
        $user = auth()->user();
        $today = today();

        // 1. My Deal activities (calls, WhatsApp, visits overdue or due today)
        $activities = \App\Models\LeadFollowUp::with([
                'lead:id,full_name,phone_number',
                'deal' => fn ($q) => $q->withoutGlobalScopes()->with('stage:id,name,color')
            ])
            ->where('assigned_user', $user->id)
            ->where('status', 'pending')
            ->where('follow_up_date', '<=', now()->endOfDay())
            ->orderBy('follow_up_date')
            ->limit(40)
            ->get();

        // 2. Today's appointments for this professional/user
        $appointments = $user->type === 'professional'
            ? \App\Models\Appointment::with(['lead:id,full_name,phone_number', 'service:id,name', 'professionalTimeSlot.timeSlot'])
                ->where('user_id', $user->id)->whereDate('appointment_date', $today)
                ->where('status', \App\Enums\AppointmentStatus::SCHEDULED)->get()
            : collect();

        // 3. Phase 3 After-Sales: 6-Month Service Due
        $rawService = $analytics['service_due']['list'] ?? [];
        $serviceDueItems = collect($rawService)->take(15)->map(function ($s) {
            $isOverdue = ($s['days'] ?? 0) < 0 || ($s['stage'] ?? '') === 'overdue';
            $custName = $s['name'] ?? 'Customer';
            $model = $s['model'] ?? 'Hearing Aid';
            $phone = $s['phone'] ?? '';
            $waMsg = "Assalamu Alaikum {$custName}. This is Zenhear Hearing Care Centre. Your hearing aid ({$model}) is due for a 6-month checkup and complimentary cleaning service. Please let us know your preferred time to visit our branch.";

            return [
                'type'           => 'service_due',
                'category'       => 'service',
                'badge'          => $isOverdue ? 'Overdue' : 'Due Service',
                'badge_tone'     => $isOverdue ? 'danger' : 'warning',
                'time'           => $isOverdue ? abs($s['days']) . 'd late' : (($s['days'] ?? 0) === 0 ? 'Today' : 'in ' . $s['days'] . 'd'),
                'is_overdue'     => $isOverdue,
                'title'          => "{$custName} — {$model}",
                'subtitle'       => '6-month service & cleaning checkup · ' . ($s['due_label'] ?? ''),
                'customer_id'    => $s['customer_id'] ?? null,
                'url'            => !empty($s['customer_id']) ? url('admin/customers/' . $s['customer_id']) : '#',
                'phone'          => $phone,
                'whatsapp_url'   => $phone ? 'https://wa.me/' . \App\Classes\Helper::normalizePhone($phone) . '?text=' . rawurlencode($waMsg) : null,
                'can_done'       => true,
                'after_sales'    => 'service_check',
            ];
        });

        // 4. Phase 3 After-Sales: Warranty Expiry within 30 days
        $rawWarranty = $statistics['daily_lookout']['warranty_expiry'] ?? collect();
        $contactedWarrantyCusts = \App\Models\LeadFollowUp::where('status', 'completed')
            ->where('follow_up_notes', 'LIKE', '%[Warranty Expiry%')
            ->where('completed_at', '>=', now()->subDays(30))
            ->pluck('lead_id')->all();

        $warrantyItems = collect($rawWarranty)->filter(function ($w) use ($contactedWarrantyCusts) {
            return !in_array($w['customer_id'] ?? 0, $contactedWarrantyCusts);
        })->take(15)->map(function ($w) {
            $isUrgent = ($w['days_left'] ?? 30) <= 7;
            $custName = $w['customer'] ?? 'Customer';
            $prod = $w['product'] ?? 'Hearing Aid';
            $phone = $w['phone'] ?? '';
            $expDate = $w['expiry_date'] ?? '';
            $waMsg = "Assalamu Alaikum {$custName}. This is Zenhear Hearing Care Centre. The warranty for your {$prod} will expire on {$expDate}. We invite you for a complimentary end-of-warranty inspection before it expires. Contact us to schedule.";

            return [
                'type'           => 'warranty_expiry',
                'category'       => 'warranty',
                'badge'          => ($w['days_left'] ?? 0) <= 0 ? 'Expired' : ($w['days_left'] . 'd left'),
                'badge_tone'     => $isUrgent ? 'danger' : 'warning',
                'time'           => ($w['days_left'] ?? 0) <= 0 ? 'Expired' : ($w['days_left'] . 'd left'),
                'is_overdue'     => ($w['days_left'] ?? 0) <= 0,
                'title'          => "{$custName} — {$prod}",
                'subtitle'       => "Warranty expires {$expDate} · Invoice #{$w['sale_id']}",
                'customer_id'    => $w['customer_id'] ?? null,
                'url'            => !empty($w['customer_id']) ? url('admin/customers/' . $w['customer_id']) : '#',
                'phone'          => $phone,
                'whatsapp_url'   => $phone ? 'https://wa.me/' . \App\Classes\Helper::normalizePhone($phone) . '?text=' . rawurlencode($waMsg) : null,
                'can_done'       => true,
                'after_sales'    => 'warranty_check',
            ];
        });

        // 5. Phase 3 After-Sales: Birthdays Today
        $rawBirthdays = $analytics['birthdays']['list'] ?? [];
        $birthdayItems = collect($rawBirthdays)->where('in_days', 0)->take(10)->map(function ($b) {
            $name = $b['name'] ?? 'Customer';
            $phone = $b['phone'] ?? '';
            $waMsg = "Happy Birthday {$name}! Wishing you good health, joy, and wonderful hearing moments today and throughout the year. Warm wishes from Zenhear Hearing Care Centre.";

            return [
                'type'           => 'birthday',
                'category'       => 'birthday',
                'badge'          => 'Today',
                'badge_tone'     => 'purple',
                'time'           => 'Today',
                'is_overdue'     => false,
                'title'          => "{$name} (turns " . ($b['turning'] ?? '') . ")",
                'subtitle'       => 'Customer birthday celebration today',
                'customer_id'    => $b['customer_id'] ?? null,
                'lead_id'        => $b['lead_id'] ?? null,
                'url'            => $b['url'] ?? '#',
                'phone'          => $phone,
                'whatsapp_url'   => $phone ? 'https://wa.me/' . \App\Classes\Helper::normalizePhone($phone) . '?text=' . rawurlencode($waMsg) : null,
                'can_done'       => true,
                'after_sales'    => 'birthday_wish',
            ];
        });

        // 6. Phase 3 After-Sales: Battery Expiry within 15 days
        $rawBattery = $statistics['daily_lookout']['battery_expiry'] ?? collect();
        $contactedBatteryCusts = \App\Models\LeadFollowUp::where('status', 'completed')
            ->where('follow_up_notes', 'LIKE', '%[Battery Refill%')
            ->where('completed_at', '>=', now()->subDays(15))
            ->pluck('lead_id')->all();

        $batteryItems = collect($rawBattery)->filter(function ($b) use ($contactedBatteryCusts) {
            return !in_array($b['customer_id'] ?? 0, $contactedBatteryCusts);
        })->take(15)->map(function ($b) {
            $custName = $b['customer'] ?? 'Customer';
            $prod = $b['product'] ?? 'Hearing Aid';
            $batSize = !empty($b['battery_size']) && $b['battery_size'] !== 'N/A' ? " (Size {$b['battery_size']})" : '';
            $phone = $b['phone'] ?? '';
            $expDate = $b['expiry_date'] ?? '';
            $daysLeft = $b['days_left'] ?? 0;
            $isUrgent = $daysLeft <= 3;
            $waMsg = "Assalamu Alaikum {$custName}. This is Zenhear Hearing Care Centre. Your {$prod} battery supply{$batSize} is estimated to run out around {$expDate} ({$daysLeft} days left). We recommend stocking up fresh batteries now to prevent any hearing interruption. Contact us to collect or order refills.";

            return [
                'type'           => 'battery_expiry',
                'category'       => 'battery',
                'badge'          => $daysLeft <= 0 ? 'Expired' : ($daysLeft . 'd left'),
                'badge_tone'     => $isUrgent ? 'danger' : 'amber',
                'time'           => $daysLeft <= 0 ? 'Expired' : ($daysLeft . 'd left'),
                'is_overdue'     => $daysLeft <= 0,
                'title'          => "{$custName} — {$prod}" . ($batSize ? " [Bat: {$b['battery_size']}]" : ''),
                'subtitle'       => "Battery runs out {$expDate} ({$daysLeft}d left) · Invoice #{$b['sale_id']}",
                'customer_id'    => $b['customer_id'] ?? null,
                'url'            => !empty($b['customer_id']) ? url('admin/customers/' . $b['customer_id']) : '#',
                'phone'          => $phone,
                'whatsapp_url'   => $phone ? 'https://wa.me/' . \App\Classes\Helper::normalizePhone($phone) . '?text=' . rawurlencode($waMsg) : null,
                'can_done'       => true,
                'after_sales'    => 'battery_reminder',
            ];
        });

        $actOverdue = $activities->filter(fn ($a) => \Illuminate\Support\Carbon::parse($a->follow_up_date)->lt(today()))->count();
        $totalOverdue = $actOverdue + $serviceDueItems->where('is_overdue', true)->count() + $batteryItems->where('is_overdue', true)->count();
        $totalPersonal = $activities->count() + $appointments->count() + $serviceDueItems->count() + $warrantyItems->count() + $birthdayItems->count() + $batteryItems->count();

        return [
            'activities'      => $activities,
            'appointments'    => $appointments,
            'service_due'     => $serviceDueItems,
            'warranty'        => $warrantyItems,
            'birthdays'       => $birthdayItems,
            'battery'         => $batteryItems,
            'totalPersonal'   => $totalPersonal,
            'overdue'         => $totalOverdue,
            'tomorrow'        => \App\Models\LeadFollowUp::where('assigned_user', $user->id)->where('status', 'pending')
                ->whereDate('follow_up_date', today()->addDay())->count(),
            'canDone'         => $user->hasAccess('deal_board.move'),
            'canBoard'        => $user->hasAccess('deal_board.index'),
        ];
    }

    /**
     * Search for medicines based on request parameters.
     *
     * This method provides a dynamic search functionality for medicines, typically used for AJAX requests.
     * It filters by manufacturer and category, and performs a text-based search on the medicine name.
     * The response is a JSON array formatted for use with select2 dropdowns.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function medicineSearch(Request $request): JsonResponse
    {
        $query = Medicine::query();

        $query->when($request->manufacturer_id, function ($q) use ($request) {
            return $q->where('manufacturer_id', $request->input('manufacturer_id'));
        });

        $query->when($request->category_id, function ($q) use ($request) {
            return $q->where('category_id', $request->input('category_id'));
        });

        $data = $query->where('medicine_name', 'LIKE', '%' . $request->search . '%')->where('status', 1)->get();
        $response = [];

        foreach ($data as $val) {
            $stock = $this->medicineStock($val['id']);

            if ($request->type === 'purchase' || $stock > 0) {
                $response[] = [
                    "id" => $val['id'],
                    "text" => $val['strength'] ? $val['medicine_name'] . ' (' . $val['strength'] . ')' : $val['medicine_name'],
                ];
            }
        }

        return response()->json($response);
    }

    /**
     * Calculate the stock for a given medicine.
     *
     * This helper method computes the current stock level for a specific medicine ID
     * by summing the 'in_qty' and subtracting the 'out_qty' from the stocks table.
     *
     * @param  int  $id
     * @return int
     */
    public function medicineStock(int $id): int
    {
        return Stock::where('medicine_id', $id)->value(DB::raw("SUM(in_qty - out_qty)")) ?? 0;
    }

    /**
     * Search for employees based on a search term.
     *
     * Similar to customerSearch, this method finds employees by name using a LIKE query.
     * It is intended for use with AJAX to populate UI elements like select2 dropdowns.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function employeeSearch(Request $request): JsonResponse
    {
        $data = Employee::where('status', 1)->where('name', 'LIKE', '%' . $request->search . '%')->get();
        $response = [];

        foreach ($data as $val) {
            $response[] = [
                "id" => $val['id'],
                "text" => $val['name'],
            ];
        }

        return response()->json($response);
    }
}
