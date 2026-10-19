<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    /** Most rows one CSV export will hold, so a huge log cannot hang the server. */
    public const EXPORT_LIMIT = 5000;

    /**
     * Configure middleware to handle authorization for activity log operations
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:activity_log.index')->only(['index', 'export']);
    }

    /**
     * Display a listing of activity logs: data changes by default, sign-ins on ?tab=signins.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        if ($request->query('tab') === 'signins') {
            return view('admin.activity-log.signins', [
                'events' => $this->signins($request)->paginate(25)->withQueryString(),
                'users'  => User::orderBy('name')->get(['id', 'name']),
            ]);
        }

        $query = $this->changes($request)->with(['user', 'subject']);

        $data['activityLogs'] = $query->paginate(15);
        $data['users'] = User::all();
        $data['subjectTypes'] = ActivityLog::select('subject_type')
            ->distinct()
            ->whereNotNull('subject_type')
            ->pluck('subject_type');

        // Preserve filter parameters
        $data['filters'] = [
            'user_id' => $request->user_id,
            'subject_type' => $request->subject_type,
            'action' => $request->action,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ];

        return view('admin.activity-log.index', $data);
    }

    /** Same filters as the screen, as a CSV a spreadsheet can open. */
    public function export(Request $request)
    {
        $signins = $request->query('tab') === 'signins';
        $name = ($signins ? 'sign-ins-' : 'activity-log-') . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($request, $signins) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads names correctly

            if ($signins) {
                fputcsv($out, ['When', 'Person', 'Email', 'What happened', 'IP address']);
                $this->signins($request)->limit(self::EXPORT_LIMIT)->get()->each(fn ($e) => fputcsv($out, [
                    $e->created_at->format('Y-m-d H:i:s'), $e->user->name ?? '', $e->email, LoginEvent::LABELS[$e->event] ?? $e->event, $e->ip,
                ]));
            } else {
                fputcsv($out, ['When', 'Person', 'What', 'Record', 'Action', 'IP address']);
                $this->changes($request)->with('user')->limit(self::EXPORT_LIMIT)->get()->each(fn ($l) => fputcsv($out, [
                    $l->created_at?->format('Y-m-d H:i:s'), $l->user->name ?? '', class_basename((string) $l->subject_type), $l->subject_id, $l->action, $l->ip_address,
                ]));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function changes(Request $request)
    {
        $query = ActivityLog::query()->latest();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->subject_type);
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->start_date . ' 00:00:00', $request->end_date . ' 23:59:59']);
        }

        return $query;
    }

    private function signins(Request $request)
    {
        $query = LoginEvent::with('user:id,name')->latest('created_at')->latest('id');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('event') && isset(LoginEvent::LABELS[$request->event])) {
            $query->where('event', $request->event);
        }
        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->start_date . ' 00:00:00');
        }
        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
        }

        return $query;
    }
}
