<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\Customer;
use App\Models\User;
use App\Enums\TaskStatus;
use App\Enums\TaskPriority;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class TaskController extends Controller
{
    /**
     * Configure middleware to handle authorization for different task operations
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:task_list.index')->only(['index']);
        $this->middleware('permission:add_task.create')->only(['create', 'store']);
        $this->middleware('permission:task_list.edit')->only(['edit', 'update']);
        $this->middleware('permission:task_list.delete')->only(['destroy']);
    }

    /**
     * Display a listing of tasks with filters.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = Task::with(['customer.lead', 'assignedTo', 'creator']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by priority
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        // Filter by customer
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by assigned user
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        // Filter by date range
        $data['start_date'] = $request->start_date ?? null;
        $data['end_date'] = $request->end_date ?? null;

        if ($data['start_date'] && $data['end_date']) {
            $start = date('Y-m-d', strtotime($data['start_date']));
            $end = date('Y-m-d', strtotime($data['end_date']));
            $query->whereBetween('created_at', [$start, $end]);
        }

        // Search by subject or description
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $data['tasks'] = $query->latest()->paginate(20);
        $data['customers'] = Customer::with('lead')->orderBy('id', 'desc')->get();
        $data['users'] = User::select('id', 'name')->orderBy('id', 'desc')->get();
        $data['statuses'] = TaskStatus::cases();
        $data['priorities'] = TaskPriority::cases();

        // Preserve filter parameters in pagination
        $data['tasks']->appends($request->all());

        return view('admin.task.index', $data);
    }

    /**
     * Show the form for creating a new task.
     *
     * @return View
     */
    public function create(): View
    {
        $data['customers'] = Customer::with('lead')->orderBy('id', 'desc')->get();
        $data['users'] = User::select('id', 'name')->orderBy('id', 'desc')->get();
        $data['statuses'] = TaskStatus::cases();
        $data['priorities'] = TaskPriority::cases();

        return view('admin.task.create', $data);
    }

    /**
     * Store a newly created task in storage.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string',
            'customer_id' => 'nullable|exists:customers,id',
            'assigned_to' => 'nullable|exists:users,id',
            'status' => 'required|in:' . implode(',', array_column(TaskStatus::cases(), 'value')),
            'priority' => 'required|in:' . implode(',', array_column(TaskPriority::cases(), 'value')),
            'due_date' => 'nullable|date|after:today',
        ], [
            'customer_id.required' => 'The customer field is required.',
            'assigned_to.exists' => 'The selected user is invalid.',
            'due_date.after' => 'The due date must be after today.',
        ]);

        $task = new Task($request->all());

        if ($task->save()) {
            return redirect('admin/task')->with('success', 'Task created successfully');
        }

        return redirect()->back()->with('warning', 'Task not saved')->withInput();
    }

    /**
     * Show the form for editing the specified task.
     *
     * @param int $id
     * @return View
     */
    public function edit(int $id): View
    {
        $data['task'] = Task::with('customer.lead')->findOrFail($id);
        $data['customers'] = Customer::with('lead')->orderBy('id', 'desc')->get();
        $data['users'] = User::select('id', 'name')->orderBy('id', 'desc')->get();
        $data['statuses'] = TaskStatus::cases();
        $data['priorities'] = TaskPriority::cases();

        return view('admin.task.edit', $data);
    }

    /**
     * Update the specified task in storage.
     *
     * @param Request $request
     * @param int $id
     * @return RedirectResponse
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->validate($request, [
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string',
            'customer_id' => 'nullable|exists:customers,id',
            'assigned_to' => 'nullable|exists:users,id',
            'status' => 'required|in:' . implode(',', array_column(TaskStatus::cases(), 'value')),
            'priority' => 'required|in:' . implode(',', array_column(TaskPriority::cases(), 'value')),
            'due_date' => 'nullable|date',
        ], [
            'customer_id.required' => 'The customer field is required.',
            'assigned_to.exists' => 'The selected user is invalid.',
        ]);

        $task = Task::findOrFail($id);
        $task->fill($request->all());

        if ($task->save()) {
            return redirect('admin/task')->with('success', 'Task updated successfully');
        }

        return redirect()->back()->with('warning', 'Task not updated')->withInput();
    }

    /**
     * Remove the specified task from storage.
     *
     * @param int $id
     * @return RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        $task = Task::findOrFail($id);
        $task->delete();

        return redirect('admin/task')->with('success', 'Task deleted successfully');
    }
}
