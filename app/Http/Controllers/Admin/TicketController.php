<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Enums\SmsAutomationTrigger;
use App\Services\SmsAutomationService;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

class TicketController extends Controller
{
    /** The only fields a ticket form may set (branch and creator are never taken from the request). */
    private const FORM_FIELDS = ['subject', 'description', 'customer_id', 'assigned_to', 'status', 'priority'];

    /**
     * Configure middleware to handle authorization for different ticket operations
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:ticket_list.index')->only(['index', 'show']);
        $this->middleware('permission:add_ticket.create')->only(['create', 'store']);
        $this->middleware('permission:ticket_list.edit')->only(['edit', 'update', 'updateStatus', 'assign', 'addNote']);
        $this->middleware('permission:ticket_list.delete')->only(['destroy']);
    }

    /**
     * People a ticket can be given to: active staff, never a shared branch login.
     */
    private function assignableUsers()
    {
        return User::select('id', 'name')
            ->where('status', 1)
            ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'vendor'))
            ->orderBy('name')
            ->get();
    }

    /**
     * A ticket may not be given to a branch login. A legacy ticket that already has one keeps it until someone changes it.
     */
    private function assertAssignable($userId, $currentId = null): void
    {
        if (!$userId || (int) $userId === (int) $currentId) {
            return;
        }
        $user = User::find($userId);
        if (!$user || $user->type === 'vendor' || !$user->status) {
            throw ValidationException::withMessages(['assigned_to' => 'Give the ticket to a person, not to a branch login.']);
        }
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

        // Filter by date range (the whole of the end day is included)
        $data['start_date'] = $request->start_date ?? null;
        $data['end_date'] = $request->end_date ?? null;

        if ($data['start_date'] && $data['end_date']) {
            $query->whereBetween('created_at', [
                date('Y-m-d 00:00:00', strtotime($data['start_date'])),
                date('Y-m-d 23:59:59', strtotime($data['end_date'])),
            ]);
        }

        if ($request->boolean('open_only')) {
            $query->whereNotIn('status', ['Solved', 'Closed']);
        }
        if ($request->boolean('done_only')) {
            $query->whereIn('status', ['Solved', 'Closed']);
        }

        $data['tickets'] = Helper::readAllBranches(fn () => $query->latest()->paginate(20));
        // Customers of every branch (read-only dropdown) for HQ support staff who can view all branches
        $data['customers'] = Helper::readAllBranches(fn () => Customer::with('lead')->orderBy('id', 'desc')->get());
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
     * The ticket page: details, quick actions and the timeline of notes and changes.
     */
    public function show(int $id): View
    {
        // Same reach as the list: head office users who may view all branches can open any branch's ticket
        $ticket = Helper::readAllBranches(fn () => Ticket::with(['customer.lead', 'assignedTo', 'notes.user'])->findOrFail($id));

        return view('admin.ticket.show', [
            'ticket'     => $ticket,
            'users'      => $this->assignableUsers(),
            'statuses'   => TicketStatus::cases(),
            'priorities' => TicketPriority::cases(),
        ]);
    }

    /**
     * Show the form for creating a new ticket.
     *
     * @return View
     */
    public function create(): View
    {
        // Customers of every branch (read-only dropdown) for HQ support staff who can view all branches
        $data['customers'] = Helper::readAllBranches(fn () => Customer::with('lead')->orderBy('id', 'desc')->get());
        $data['users'] = $this->assignableUsers();
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
            'description' => 'required|string|max:10000',
            'customer_id' => 'required|exists:customers,id',
            'assigned_to' => 'nullable|exists:users,id',
            'status' => 'required|in:' . $statusValues,
            'priority' => 'required|in:' . $priorityValues,
        ]);
        $this->assertAssignable($request->assigned_to);

        $ticket = new Ticket($request->only(self::FORM_FIELDS));

        if ($ticket->save()) {
            // "Service Ticket Created" SMS (text managed in SMS Center → Automation)
            $customer = Helper::readAllBranches(fn () => Customer::with('lead')->find($request->customer_id));

            if ($customer && $customer->lead && $customer->lead->phone_number) {
                app(SmsAutomationService::class)->fire(SmsAutomationTrigger::TICKET_CREATED, $customer->lead->phone_number, 'customer', (int) $customer->id, [
                    'name' => $customer->lead->full_name, 'customer_name' => $customer->lead->full_name,
                    'ticket_id' => str_pad($ticket->id, 4, '0', STR_PAD_LEFT),
                    'subject' => $ticket->subject,
                    'priority' => ucfirst($ticket->priority->value),
                    'status' => ucfirst(str_replace('_', ' ', $ticket->status->value)),
                ], 'ticket-created:' . $ticket->id);
            }

            return redirect('admin/ticket/' . $ticket->id)->with('success', 'Ticket ' . $ticket->number . ' created');
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
        $data['customers'] = Helper::readAllBranches(fn () => Customer::with('lead')->orderBy('id', 'desc')->get());
        $data['users'] = $this->assignableUsers();
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
            'description' => 'required|string|max:10000',
            'customer_id' => 'required|exists:customers,id',
            'assigned_to' => 'nullable|exists:users,id',
            'status' => 'required|in:' . $statusValues,
            'priority' => 'required|in:' . $priorityValues,
        ]);

        $ticket = Ticket::findOrFail($id);
        $this->assertAssignable($request->assigned_to, $ticket->assigned_to);
        $ticket->fill($request->only(self::FORM_FIELDS));

        // The resolved time follows the status (see Ticket::booted); every change is added to the timeline
        if ($ticket->save()) {
            return redirect('admin/ticket/' . $ticket->id)->with('success', 'Ticket updated');
        }

        return redirect()->back()->with('warning', 'Ticket not updated');
    }

    /**
     * Quick status update (ticket page buttons and the dashboard Daily Lookout).
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse|RedirectResponse
     */
    public function updateStatus(Request $request, int $id)
    {
        $request->validate([
            'status' => 'required|in:' . implode(',', array_column(TicketStatus::cases(), 'value')),
        ]);

        $ticket = Ticket::findOrFail($id);
        $ticket->status = $request->status;
        $ticket->save();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Ticket status updated']);
        }

        return redirect('admin/ticket/' . $ticket->id)->with('success', 'Status changed to ' . $request->status);
    }

    /**
     * Give the ticket to someone else (or to nobody).
     */
    public function assign(Request $request, int $id): RedirectResponse
    {
        $request->validate(['assigned_to' => 'nullable|exists:users,id']);

        $ticket = Ticket::findOrFail($id);
        $this->assertAssignable($request->assigned_to, $ticket->assigned_to);
        $ticket->assigned_to = $request->assigned_to ?: null;
        $ticket->save();

        return redirect('admin/ticket/' . $ticket->id)->with('success', 'Owner updated');
    }

    /**
     * Add a note to the timeline.
     */
    public function addNote(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['body' => 'required|string|max:2000']);

        $ticket = Ticket::findOrFail($id);
        $ticket->addNote('note', trim($data['body']));

        return redirect('admin/ticket/' . $ticket->id . '#timeline')->with('success', 'Note added');
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
