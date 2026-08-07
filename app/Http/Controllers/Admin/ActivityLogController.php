<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    /**
     * Configure middleware to handle authorization for activity log operations
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:activity_log.index')->only(['index']);
    }

    /**
     * Display a listing of activity logs.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = ActivityLog::with(['user', 'subject'])->latest();

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by subject type
        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->subject_type);
        }

        // Filter by action
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // Filter by date range
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59'
            ]);
        }

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
}
