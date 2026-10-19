<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Exports\ArrayReportExport;
use App\Http\Controllers\Controller;
use App\Models\ProfitBudget;
use App\Models\User;
use App\Services\ProfitStatement;
use App\Support\ReportPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Branch profit statement: income by category, cost of goods, referral, expenses by category, other income, net profit.
 * Also: a 12-month trend, the records behind any line, and monthly budgets compared with actuals.
 */
class ProfitStatementController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:profit_statement.index');
        $this->middleware('permission:profit_statement.budget')->only(['budget', 'saveBudget']);
        // Branch-bound people always get their own branch
        $this->middleware(fn ($request, $next) => Helper::forceReportBranch($request, $next));
    }

    public function index(Request $request)
    {
        [$start, $end, $period] = ReportPeriod::resolve($request);
        $branch = (string) $request->input('vendor_id', 'all');
        $view = $request->input('view') === 'trend' ? 'trend' : 'statement';

        $ps = new ProfitStatement($start, $end, $branch);
        $statement = $ps->build();

        if (in_array($request->input('export'), ['csv', 'xlsx'], true)) {
            return $this->export($statement, $request->input('export'), $start, $end);
        }

        // Same-length period right before, for the comparison line
        $days = $start->diffInDays($end) + 1;
        $prevEnd = $start->copy()->subDay()->endOfDay();
        $prevStart = $prevEnd->copy()->subDays($days - 1)->startOfDay();
        $previous = (new ProfitStatement($prevStart, $prevEnd, $branch))->build()['summary'];

        return view('admin.report.profit-statement', [
            'statement' => $statement,
            'previous'  => $previous,
            'start'     => $start,
            'end'       => $end,
            'period'    => $period,
            'periods'   => ReportPeriod::PERIODS,
            'branch'    => $branch,
            'branches'  => $this->branchChoices(),
            'prevStart' => $prevStart,
            'prevEnd'   => $prevEnd,
            'view'      => $view,
            'trend'     => $view === 'trend' ? ProfitStatement::trend($end, $branch) : [],
            'budget'    => $this->budgetVsActual($start, $end, $ps->scopeKey(), $statement['summary']),
            'canBudget' => auth()->user()->hasAccess('profit_statement.budget'),
            'scopeKey'  => $ps->scopeKey(),
        ]);
    }

    /** The records behind one line of the statement. */
    public function detail(Request $request)
    {
        [$start, $end, $period] = ReportPeriod::resolve($request);
        $branch = (string) $request->input('vendor_id', 'all');
        $kind = (string) $request->input('kind');
        $label = (string) $request->input('label');

        $detail = (new ProfitStatement($start, $end, $branch))->detail($kind, $label);
        abort_if($detail === null || $label === '', 404);

        return view('admin.report.profit-statement-detail', [
            'detail' => $detail, 'kind' => $kind, 'label' => $label,
            'start' => $start, 'end' => $end, 'branch' => $branch, 'period' => $period,
            'back' => url('admin/report/profit-statement') . '?' . http_build_query($request->only('period', 'start_date', 'end_date', 'vendor_id')),
        ]);
    }

    public function budget(Request $request)
    {
        [$month, $key] = $this->budgetTarget($request);
        $values = ProfitBudget::where('month', $month)->where('branch_key', $key)->pluck('amount', 'metric')->all();

        return view('admin.report.profit-budget', [
            'month' => $month, 'key' => $key, 'values' => $values, 'metrics' => ProfitBudget::METRICS,
            'branches' => $this->branchChoices(),
        ]);
    }

    public function saveBudget(Request $request)
    {
        [$month, $key] = $this->budgetTarget($request);
        $request->validate(['amounts' => 'nullable|array', 'amounts.*' => 'nullable|numeric|min:-999999999|max:999999999']);

        foreach (array_keys(ProfitBudget::METRICS) as $metric) {
            $amount = $request->input("amounts.$metric");
            if ($amount === null || $amount === '') {
                ProfitBudget::where(['month' => $month, 'branch_key' => $key, 'metric' => $metric])->delete();
                continue;
            }
            ProfitBudget::updateOrCreate(
                ['month' => $month, 'branch_key' => $key, 'metric' => $metric],
                ['amount' => round((float) $amount, 2), 'updated_by' => auth()->id()]
            );
        }

        return redirect()->route('report.profit-budget', ['month' => $month, 'vendor_id' => $key])->with('success', 'Budget saved.');
    }

    // ------------------------------------------------------------------

    /** @return array{0: string, 1: string} month (Y-m) and branch key */
    private function budgetTarget(Request $request): array
    {
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->input('month')) ? $request->input('month') : now()->format('Y-m');
        $forced = Helper::reportBranchId();
        $key = $forced ? (string) $forced : (string) $request->input('vendor_id', 'all');
        if (! $forced && $key !== 'all' && $key !== 'hq' && ! User::where('type', 'vendor')->whereKey((int) $key)->exists()) {
            $key = 'all';
        }

        return [$month, $key];
    }

    /** Budget lines for a period that is exactly one calendar month; null otherwise or when no budget is set. */
    private function budgetVsActual(Carbon $start, Carbon $end, string $key, array $summary): ?array
    {
        if (! $start->isSameDay($start->copy()->startOfMonth()) || ! $end->isSameDay($start->copy()->endOfMonth())) {
            return null;
        }
        $set = ProfitBudget::where('month', $start->format('Y-m'))->where('branch_key', $key)->pluck('amount', 'metric');
        if ($set->isEmpty()) {
            return null;
        }

        $actual = [
            'net_sales' => $summary['net_sales'], 'gross_profit' => $summary['gross_profit'],
            'operating_expenses' => $summary['operating_expenses'], 'net_profit' => $summary['net_profit'],
        ];
        $rows = [];
        foreach (ProfitBudget::METRICS as $metric => $label) {
            if (! $set->has($metric)) {
                continue;
            }
            $budget = (float) $set[$metric];
            $rows[] = [
                'metric' => $metric, 'label' => $label, 'budget' => $budget, 'actual' => $actual[$metric],
                'diff' => round($actual[$metric] - $budget, 2),
                'pct' => $budget != 0.0 ? round($actual[$metric] / $budget * 100) : null,
                // spending more than planned is the bad direction for expenses
                'good' => $metric === 'operating_expenses' ? $actual[$metric] <= $budget : $actual[$metric] >= $budget,
            ];
        }

        return ['month' => $start->format('F Y'), 'rows' => $rows];
    }

    private function branchChoices(): array
    {
        return Helper::reportBranchId() ? [] : User::where('type', 'vendor')->orderBy('name')->pluck('name', 'id')->all();
    }

    private function export(array $statement, string $format, $start, $end)
    {
        $cols = $statement['columns'];
        $header = array_merge(['Line'], array_values($cols), count($cols) > 1 ? ['Total'] : []);
        $lines = [];
        foreach ($statement['rows'] as $r) {
            if ($r['kind'] === 'head') {
                $lines[] = [strtoupper($r['label'])];
                continue;
            }
            $lines[] = array_merge([str_repeat('  ', $r['indent']) . $r['label']], array_values($r['vals']), count($cols) > 1 ? [$r['total']] : []);
        }
        $name = 'profit-statement-' . $start->format('Y-m-d') . '-to-' . $end->format('Y-m-d');

        if ($format === 'xlsx') {
            return Excel::download(new ArrayReportExport('Profit statement', $header, $lines), $name . '.xlsx');
        }

        return response()->streamDownload(function () use ($header, $lines) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($lines as $l) {
                fputcsv($out, $l);
            }
            fclose($out);
        }, $name . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
