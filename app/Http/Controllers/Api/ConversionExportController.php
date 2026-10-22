<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ConversionFeedback;
use App\Support\CsvDownload;
use Illuminate\Http\Request;

/**
 * Google Ads offline conversions as CSV. Google Ads → Goals → Conversions → Uploads → Schedule, source "HTTPS",
 * URL = /exports/google-ads-conversions.csv?key=<GOOGLE_CONVERSIONS_KEY>. Off until the key is set.
 * Signed-in head-office users with the Marketing Return export permission can download it too.
 */
class ConversionExportController extends Controller
{
    public function google(Request $request, ConversionFeedback $feedback)
    {
        $key = (string) config('connectors.google.export_key');
        $user = auth()->user();
        $allowed = ($key !== '' && hash_equals($key, (string) $request->query('key')))
            || ($user && !$user->branchId() && $user->hasAccess('marketing_roi.export'));
        abort_unless($allowed, $key === '' && !$user ? 404 : 403);

        return CsvDownload::stream('google-ads-conversions.csv',
            ['Google Click ID', 'Conversion Name', 'Conversion Time', 'Conversion Value', 'Conversion Currency'],
            $feedback->googleRows());
    }
}
