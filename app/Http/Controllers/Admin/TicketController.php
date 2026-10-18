<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SmsAutomationTrigger;
use App\Services\SmsAutomationService;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

class TicketController extends Controller
{
    /**
     * Configure middleware to handle authorization for different ticket operations
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:ticket_list.index')->only(['index']);
        $this->middleware('permission:add_ticket.create')->only(['create', 'store']);
        $this->middleware('permission:ticket_list.edit')->only(['edit', 'update', 'updateStatus']);
        $this->middleware('permission:ticket_list.delete')->only(['destroy']);
    }

    /**
     * Display a listing of tickets with filters.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = Ticket::with(['customer', 'assignedTo']);

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

        if ($request->boolean('open_only')) {
            $query->whereNotIn('status', ['Solved', 'Closed']);
        }
        if ($request->boolean('done_only')) {
            $query->whereIn('status', ['Solved', 'Closed']);
        }

        $data['tickets'] = \App\Classes\Helper::readAllBranches(fn () => $query->latest()->paginate(20));
        // Customers of every branch (read-only dropdown) for HQ support staff who can view all branches
        $data['customers'] = \App\Classes\Helper::readAllBranches(fn () => Customer::with('lead')->orderBy('id', 'desc')->get());
        $data['users'] = User::select('id', 'name')->orderBy('id', 'desc')->get();
        $data['statuses'] = TicketStatus::cases();
        $data['priorities'] = TicketPriority::cases();

        // Summary cards
        $byStatus = Ticket::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $completed = (int) (($byStatus['Solved'] ?? 0) + ($byStatus['Closed'] ?? 0));
        $total = (int) $byStatus->sum();
        $data['ticketStats'] = [
            'total' => $total,
            'completed' => $completed,
            'due' => $total - $completed,
            'in_progress' => (int) ($byStatus['In Progress'] ?? 0),
            'open' => (int) ($byStatus['Open'] ?? 0),
            'today' => Ticket::whereDate('created_at', today())->count(),
            'mine_open' => Ticket::where('assigned_to', auth()->id())->whereNotIn('status', ['Solved', 'Closed'])->count(),
        ];

        return view('admin.ticket.index', $data);
    }

    /**
     * Show the form for creating a new ticket.
     *
     * @return View
     */
    public function create(): View
    {
        // Customers of every branch (read-only dropdown) for HQ support staff who can view all branches
        $data['customers'] = \App\Classes\Helper::readAllBranches(fn () => Customer::with('lead')->orderBy('id', 'desc')->get());
        $data['users'] = User::orderBy('id', 'desc')->get();
        $data['statuses'] = TicketStatus::cases();
        $data['priorities'] = TicketPriority::cases();
        return view('admin.ticket.create', $data);
    }

    /**
     * Store a newly created ticket in storage.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $statusValues = implode(',', array_column(TicketStatus::cases(), 'value'));
        $priorityValues = implode(',', array_column(TicketPriority::cases(), 'value'));

        $this->validate($request, [
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'customer_id' => 'required|exists:customers,id',
            'assigned_to' => 'nullable|exists:users,id',
            'status' => 'required|in:' . $statusValues,
            'priority' => 'required|in:' . $priorityValues,
        ]);

        $ticket = new Ticket($request->all());

        if ($ticket->save()) {
            // "Service Ticket Created" SMS (text managed in SMS Center → Automation)
            $customer = \App\Classes\Helper::readAllBranches(fn () => Customer::with('lead')->find($request->customer_id));

            if ($customer && $customer->lead && $customer->lead->phone_number) {
                app(SmsAutomationService::class)->fire(SmsAutomationTrigger::TICKET_CREATED, $customer->lead->phone_number, 'customer', (int) $customer->id, [
                    'name' => $customer->lead->full_name, 'customer_name' => $customer->lead->full_name,
                    'ticket_id' => str_pad($ticket->id, 4, '0', STR_PAD_LEFT),
                    'subject' => $ticket->subject,
                    'priority' => ucfirst($ticket->priority->value),
                    'status' => ucfirst(str_replace('_', ' ', $ticket->status->value)),
                ], 'ticket-created:' . $ticket->id);
            }

            return redirect('admin/ticket')->with('success', 'Ticket created successfully');
        }

        return redirect()->back()->with('warning', 'Ticket not created');
    }

    /**
     * Show the form for editing the specified ticket.
     *
     * @param int $id
     * @return View
     */
    public function edit(int $id): View
    {
        $data['ticket'] = Ticket::findOrFail($id);
        // Customers of every branch (read-only dropdown) for HQ support staff who can view all branches
        $data['customers'] = \App\Classes\Helper::readAllBranches(fn () => Customer::with('lead')->orderBy('id', 'desc')->get());
        $data['users'] = User::orderBy('id', 'desc')->get();
        $data['statuses'] = TicketStatus::cases();
        $data['priorities'] = TicketPriority::cases();
        return view('admin.ticket.edit', $data);
    }

    /**
     * Update the specified ticket in storage.
     *
     * @param Request $request
     * @param int $id
     * @return RedirectResponse
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $statusValues = implode(',', array_column(TicketStatus::cases(), 'value'));
        $priorityValues = implode(',', array_column(TicketPriority::cases(), 'value'));

        $this->validate($request, [
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'customer_id' => 'required|exists:customers,id',
            'assigned_to' => 'nullable|exists:users,id',
            'status' => 'required|in:' . $statusValues,
            'priority' => 'required|in:' . $priorityValues,
        ]);

        $ticket = Ticket::findOrFail($id);
        $ticket->fill($request->all());

        // Set resolved_at timestamp if status is changed to Solved
        if ($ticket->isDirty('status') && $request->status === TicketStatus::SOLVED->value) {
            $ticket->resolved_at = now();
        }

        if ($ticket->save()) {
            return redirect('admin/ticket')->with('success', 'Ticket updated successfully');
        }

        return redirect()->back()->with('warning', 'Ticket not updated');
    }

    /**
     * Quick status update (used by dashboard Daily Lookout).
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:' . implode(',', array_column(TicketStatus::cases(), 'value')),
        ]);

        $ticket = Ticket::findOrFail($id);
        $ticket->status = $request->status;

        // Same rule as update(): stamp resolved_at when moving to Solved
        if ($ticket->isDirty('status') && $request->status === TicketStatus::SOLVED->value) {
            $ticket->resolved_at = now();
        }

        $ticket->save();

        return response()->json(['success' => true, 'message' => 'Ticket status updated']);
    }

    /**
     * Remove the specified ticket from storage.
     *
     * @param int $id
     * @return RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        $ticket = Ticket::findOrFail($id);
        $ticket->delete();

        return redirect('admin/ticket')->with('success', 'Ticket deleted successfully');
    }
}
