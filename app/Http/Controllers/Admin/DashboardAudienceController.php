<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardAnalyticsService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dashboard → Audience Builder: exports a customer segment as CSV (read-only).
 */
class DashboardAudienceController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:dashboard.audience_builder');
    }

    public function export(string $segment, DashboardAnalyticsService $service): StreamedResponse
    {
        abort_unless(array_key_exists($segment, DashboardAnalyticsService::AUDIENCES), 404);

        $members = $service->segmentMembers($segment);
        $label = DashboardAnalyticsService::AUDIENCES[$segment][0];
        $file = 'audience-' . $segment . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($members, $label) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Bangla/Taka correctly
            fputcsv($out, ['Customer ID', 'Name', 'Phone', 'Segment', 'Last Purchase', 'Total Spend', 'Orders', 'Note']);
            foreach ($members as $m) {
                fputcsv($out, [
                    $m['customer_id'], $m['name'], $m['phone'], $label,
                    $m['last_purchase'], number_format($m['spend'], 2, '.', ''), $m['orders'], $m['note'],
                ]);
            }
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
