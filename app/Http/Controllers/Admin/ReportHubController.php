<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportHub;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** One page listing every report the person may open, with up to six starred to the top. */
class ReportHubController extends Controller
{
    public function __construct(protected ReportHub $hub)
    {
        $this->middleware('permission:reports_hub.index');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $groups = $this->hub->forUser($user);
        $starred = $this->hub->starred($user, $groups);

        return view('admin.reports.hub', [
            'groups'  => $groups,
            'starred' => $starred,
            'stars'   => array_column($starred, 'url'),
            'max'     => ReportHub::MAX_STARS,
        ]);
    }

    public function star(Request $request): JsonResponse
    {
        $data = $request->validate(['url' => 'required|string|max:200']);
        $saved = $this->hub->toggle($request->user(), $data['url']);

        if ($saved === null) {
            return response()->json(['message' => 'That report cannot be starred. You can star up to ' . ReportHub::MAX_STARS . ' reports you may open.'], 422);
        }

        return response()->json(['stars' => $saved]);
    }
}
