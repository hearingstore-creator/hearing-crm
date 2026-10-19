<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Exports\ArrayReportExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ProfitStatement;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/** Branch profit statement: income by category, cost of goods, referral, expenses by category, other income, net profit. */
class ProfitStatementController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:profit_statement.index');
        // Branch-bound people always get their own branch
        $this->middleware(fn ($request, $next) => Helper::forceReportBranch($request, $next));
    }

    public function index(Request $request)
    {
        [$start, $end, $period] = ReportPeriod::resolve($request);
        $branch = (string) $request->input('vendor_id', 'all');

        $statement = (new ProfitStatement($start, $end, $branch))->build();

        // Same-length period right before, for the comparison line
        $days = $start->diffInDays($end) + 1;
        $prevEnd = $start->copy()->subDay()->endOfDay();
        $prevStart = $prevEnd->copy()->subDays($days - 1)->startOfDay();
        $previous = (new ProfitStatement($prevStart, $prevEnd, $branch))->build()['summary'];

        if (in_array($request->input('export'), ['csv', 'xlsx'], true)) {
            return $this->export($statement, $request->input('export'), $start, $end);
        }

        return view('admin.report.profit-statement', [
            'statement' => $statement,
            'previous'  => $previous,
            'start'     => $start,
            'end'       => $end,
            'period'    => $period,
            'periods'   => ReportPeriod::PERIODS,
            'branch'    => $branch,
            'branches'  => Helper::reportBranchId() ? [] : User::where('type', 'vendor')->orderBy('name')->pluck('name', 'id')->all(),
            'prevStart' => $prevStart,
            'prevEnd'   => $prevEnd,
        ]);
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
