<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MarketingRoi;
use App\Support\CsvDownload;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Marketing return: what was spent, what it brought in, and what each lead and each sale cost. */
class MarketingRoiController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:marketing_roi.index')->only(['index']);
        $this->middleware('permission:marketing_roi.export')->only(['export']);
    }

    public function index(Request $request): View
    {
        [$roi, $filters] = $this->report($request);

        return view('admin.marketing.roi', $roi->build() + $filters + [
            'branches' => \App\Classes\Helper::branchOptions(),
            'isHq'     => !auth()->user()->branchId(),
        ]);
    }

    public function export(Request $request)
    {
        [$roi, $f] = $this->report($request);
        $data = $roi->build();

        $head = ['Group', 'Spend', 'Commission', 'Acquisition cost', 'Enquiries', 'Appointments', 'Trials', 'Sales', 'Revenue', 'Gross profit',
            'Cost per enquiry', 'Cost per appointment', 'Cost per sale', 'ROAS', 'Profit per taka spent', 'Enquiry to sale %'];
        $line = fn ($r) => [$r['label'], $r['spend'], $r['commission'], $r['acquisition_cost'], $r['enquiries'], $r['appointments'], $r['trials'], $r['sales'],
            $r['revenue'], $r['gross_profit'], $r['cost_per_enquiry'], $r['cost_per_appointment'], $r['cost_per_sale'], $r['roas'], $r['poas'], $r['enquiry_to_sale']];

        $rows = array_map($line, $data['rows']);
        $rows[] = $line($data['total']);

        return CsvDownload::stream('marketing-roi-' . $f['from']->format('Ymd') . '-' . $f['to']->format('Ymd') . '.csv', $head, $rows);
    }

    /** @return array{0: MarketingRoi, 1: array} */
    private function report(Request $request): array
    {
        $from = $this->date($request->from, now()->startOfMonth());
        $to = $this->date($request->to, now());
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        $group = in_array($request->group, ['channel', 'campaign', 'branch', 'month'], true) ? $request->group : 'channel';
        $basis = $request->basis === 'cohort' ? 'cohort' : 'period';
        $touch = $request->touch === 'first' ? 'first' : 'last';

        // branch staff see their own branch only
        $branch = auth()->user()->branchId() ?: ($request->filled('branch') ? (int) $request->branch : null);

        return [new MarketingRoi($from, $to, $group, $basis, $touch, $branch), compact('from', 'to', 'group', 'basis', 'touch', 'branch')];
    }

    private function date(?string $value, Carbon $default): Carbon
    {
        try {
            return $value ? Carbon::parse($value) : $default;
        } catch (\Throwable) {
            return $default;
        }
    }
}
