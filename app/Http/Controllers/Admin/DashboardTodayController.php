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

        return view('admin.dashboard.partials.my-day', ['myDay' => $myDay]);
    }

    protected function scoped(Request $request): DashboardTodayService
    {
        return $this->today->forBranch($request->query('branch'))->forPeriod($request->query('period'));
    }
}
