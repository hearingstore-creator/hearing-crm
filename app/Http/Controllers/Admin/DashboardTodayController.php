<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardAnalyticsService;
use App\Services\DashboardService;
use App\Services\DashboardTodayService;
use App\Support\DataScope;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * New Today dashboard (dashboard plan phase 2). The page itself is a light shell; tiles, My Day,
 * alerts and charts load afterwards from the JSON / HTML endpoints below, so one slow block never
 * holds up the page. The old dashboard stays at /admin/dashboard/legacy (HomeController).
 */
class DashboardTodayController extends Controller
{
    public const BLOCKS = ['my-day', 'alerts'];

    public function __construct(protected DashboardTodayService $today)
    {
        $this->middleware('permission:dashboard.index');
    }

    public function index(Request $request): View
    {
        $this->scoped($request);

        return view('admin.dashboard.today', [
            'branches'   => $this->today->branchChoices(),
            'branch'     => $this->today->branchKey(),
            'period'     => $this->today->periodKey(),
            'periods'    => DashboardAnalyticsService::PERIODS,
            'tabs'       => $this->today->visibleTabs(),
            'startTab'   => $this->today->defaultTab($request->user()),
            'myDayFirst' => $this->today->myDayFirst(),
            'scopeLabel' => DataScope::label(),
            'can'        => fn (string $p) => DataScope::can('dashboard.' . $p),
        ]);
    }

    public function tiles(Request $request): JsonResponse
    {
        return response()->json(['tiles' => $this->scoped($request)->tiles()]);
    }

    public function chart(Request $request, string $chart): JsonResponse
    {
        $data = $this->scoped($request)->chart($chart);
        abort_if($data === null, 404);

        return response()->json($data);
    }

    /** Remember the tab this user wants to open on. */
    public function pin(Request $request): JsonResponse
    {
        $data = $request->validate(['tab' => 'required|string|max:20']);
        abort_unless($this->today->pin($request->user(), $data['tab']), 422, 'That tab is not available to you.');

        return response()->json(['tab' => $data['tab']]);
    }

    /** One of the other tabs (Sales & Money, Patients & Care, Pipeline, Branches), loaded when first opened. */
    public function tab(Request $request, string $tab)
    {
        $data = $this->scoped($request)->tab($tab);
        abort_if($data === null, 404);

        return view('admin.dashboard.tabs.' . $tab, [
            'w'      => $data,
            'period' => $this->today->periodKey(),
            'label'  => DashboardAnalyticsService::PERIODS[$this->today->periodKey()],
            'branch' => $this->today->branchKey(),
            'legacy' => route('dashboard.legacy'),
            'report' => fn (string $path) => $this->today->reportUrl($path),
        ]);
    }

    /** HTML blocks: My Day (always the signed-in user's own list) and the alerts strip. */
    public function block(Request $request, string $block)
    {
        abort_unless(in_array($block, self::BLOCKS, true), 404);

        if ($block === 'alerts') {
            $alerts = $this->scoped($request)->alerts();
            abort_if($alerts === null, 404);

            return view('admin.dashboard.partials.alerts', ['alerts' => $alerts]);
        }

        // Not cached: items are ticked off from here and must disappear at once
        $statistics = ['daily_lookout' => app(DashboardService::class)->dailyLookout()];
        $analytics = app(DashboardAnalyticsService::class)->pick(['service_due', 'birthdays']);
        $myDay = app(HomeController::class)->myDay($statistics, $analytics);
        $myDay = $this->withSupportWork($myDay, $request->user());

        return view('admin.dashboard.partials.my-day', ['myDay' => $myDay]);
    }

    /**
     * My work also holds the support tickets and tasks that are mine, so one list says what needs me today:
     * open tickets (most urgent first) and tasks due tomorrow or earlier. Each shows only with its menu permission.
     */
    protected function withSupportWork(array $myDay, $user): array
    {
        $tickets = collect();
        $tasks = collect();

        if ($user->hasAccess('ticket_list.index')) {
            $tickets = \App\Models\Ticket::with('customer.lead')->where('assigned_to', $user->id)->whereNotIn('status', ['Solved', 'Closed'])
                ->orderByRaw("CASE WHEN status IN ('Pending','On Hold') THEN 1 ELSE 0 END")
                ->orderByRaw(\App\Models\Ticket::dueSql())
                ->limit(15)->get();
        }
        if ($user->hasAccess('task_list.index')) {
            $tasks = \App\Models\Task::with('customer.lead')->where('assigned_to', $user->id)->whereNotIn('status', ['completed', 'cancelled'])
                ->whereNotNull('due_date')->whereDate('due_date', '<=', today()->addDay())
                ->orderBy('due_date')->limit(10)->get();
        }

        $myDay['tickets'] = $tickets;
        $myDay['tasks'] = $tasks;
        $myDay['totalPersonal'] = ($myDay['totalPersonal'] ?? 0) + $tickets->count() + $tasks->count();
        $myDay['overdue'] = ($myDay['overdue'] ?? 0)
            + $tickets->filter(fn ($t) => $t->dueState()['state'] === 'overdue')->count()
            + $tasks->filter(fn ($t) => $t->isOverdue())->count();

        return $myDay;
    }

    protected function scoped(Request $request): DashboardTodayService
    {
        return $this->today->forBranch($request->query('branch'))->forPeriod($request->query('period'));
    }
}
