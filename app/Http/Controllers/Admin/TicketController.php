<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Enums\SmsAutomationTrigger;
use App\Services\SmsAutomationService;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SaleDetail;
use App\Models\SaleMaster;
use App\Services\StockGuard;
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
        $this->middleware('permission:add_ticket.create|ticket_list.edit')->only(['patientSearch', 'devices']);
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

    /** The option shown for an already chosen patient in the search box: [id, text] or null. */
    private function patientOption($customerId): ?array
    {
        if (!$customerId) {
            return null;
        }
        $c = Helper::readAllBranches(fn () => Customer::with('lead:id,full_name,phone_number')->find($customerId));

        return $c ? ['id' => $c->id, 'text' => ($c->lead->full_name ?? 'Unknown') . ($c->lead?->phone_number ? ' (' . $c->lead->phone_number . ')' : '')] : null;
    }

    /** Value of the device picker: sale id and serial, e.g. "57|SN-4821". */
    private function deviceValue($saleId, ?string $serial): string
    {
        return $saleId . '|' . ($serial ?? '');
    }

    /**
     * The invoice lines a patient has bought, one entry per serial (or per line without serials), newest first.
     * Used by the device picker on the ticket forms. [['value' => '57|SN-1', 'text' => 'INV-… · Brand Model · SN-1 · warranty …']]
     */
    private function devicesFor(int $customerId): array
    {
        return Helper::readAllBranches(function () use ($customerId) {
            $sales = SaleMaster::where('customer_id', $customerId)->orderByDesc('date')->orderByDesc('id')->limit(30)->get()->keyBy('id');
            if ($sales->isEmpty()) {
                return [];
            }

            $out = [];
            foreach (SaleDetail::with('product.brand:id,brand_name')->whereIn('sale_master_id', $sales->keys())->orderBy('id')->get() as $line) {
                $sale = $sales[$line->sale_master_id];
                $product = $line->product;
                $name = trim(($product?->brand->brand_name ?? '') . ' ' . ($product->model ?? 'Item'));
                $sold = $sale->date ? $sale->date->copy()->startOfDay() : null;
                $until = ($sold && $product?->warranty_sales_months) ? $sold->copy()->addMonths($product->warranty_sales_months) : null;
                $warranty = !$until ? 'no warranty' : (($until->isFuture() || $until->isToday()) ? 'warranty to ' : 'warranty ended ') . $until->format('d M Y');

                foreach (StockGuard::serialList($line->serial_no) ?: [null] as $serial) {
                    $out[] = [
                        'sort'  => ($sale->date?->timestamp ?? 0) . str_pad((string) $sale->id, 10, '0', STR_PAD_LEFT),
                        'value' => $this->deviceValue($sale->id, $serial),
                        'text'  => $sale->sale_invoice_no . ' · ' . $name . ($serial ? ' · ' . $serial : '') . ' · ' . $warranty,
                    ];
                }
            }

            usort($out, fn ($a, $b) => strcmp($b['sort'], $a['sort']));

            return array_map(fn ($o) => ['value' => $o['value'], 'text' => $o['text']], $out);
        });
    }

    /**
     * Read the device picker: [sale_master_id, serial_no] for the patient's own invoice (and a serial on it), or nulls when none is chosen.
     *
     * @throws ValidationException
     */
    private function deviceFields(Request $request, int $customerId): array
    {
        $raw = trim((string) $request->input('device', ''));
        if ($raw === '') {
            return ['sale_master_id' => null, 'serial_no' => null];
        }

        [$saleId, $serial] = array_pad(explode('|', $raw, 2), 2, '');
        $serial = trim($serial) !== '' ? trim($serial) : null;
        $valid = Helper::readAllBranches(function () use ($saleId, $serial, $customerId) {
            $sale = SaleMaster::where('id', (int) $saleId)->where('customer_id', $customerId)->first();
            if (!$sale) {
                return false;
            }
            if (!$serial) {
                return true;
            }

            return SaleDetail::where('sale_master_id', $sale->id)->get()->contains(
                fn ($l) => in_array(mb_strtoupper($serial), array_map('mb_strtoupper', StockGuard::serialList($l->serial_no)), true)
            );
        });
        if (!$valid) {
            throw ValidationException::withMessages(['device' => 'That invoice or device does not belong to this patient.']);
        }

        return ['sale_master_id' => (int) $saleId, 'serial_no' => $serial];
    }

    /** The option shown for a device that is already chosen: [value, text] or null. */
    private function deviceOption(?int $customerId, $saleId, ?string $serial): ?array
    {
        if (!$customerId || !$saleId) {
            return null;
        }
        $value = $this->deviceValue($saleId, $serial ?: null);

        return collect($this->devicesFor($customerId))->firstWhere('value', $value);
    }

    /** Devices the patient has bought, for the picker on the ticket forms. */
    public function devices(Request $request): JsonResponse
    {
        $customerId = (int) $request->input('customer_id');

        return response()->json($customerId ? $this->devicesFor($customerId) : []);
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

    /** Saved views of the inbox, in the order they are shown. */
    public const VIEWS = [
        'mine'    => 'My work',
        'unassigned' => 'Unassigned',
        'today'   => 'Due today',
        'overdue' => 'Overdue',
        'waiting' => 'Waiting on patient',
        'solved'  => 'Solved this week',
        'open'    => 'All open',
        'all'     => 'All tickets',
    ];

    /** Narrow a ticket query to one saved view. */
    private function applyView($query, string $view)
    {
        $done = ['Solved', 'Closed'];
        $active = ['Open', 'In Progress'];
        $due = Ticket::dueSql();

        return match ($view) {
            'mine'       => $query->where('assigned_to', auth()->id())->whereNotIn('status', $done),
            'unassigned' => $query->whereNull('assigned_to')->whereNotIn('status', $done),
            'today'      => $query->whereIn('status', $active)->whereRaw("$due >= ?", [now()])->whereRaw("$due <= ?", [now()->endOfDay()]),
            'overdue'    => $query->whereIn('status', $active)->whereRaw("$due < ?", [now()]),
            'waiting'    => $query->whereIn('status', Ticket::WAITING),
            'solved'     => $query->whereIn('status', $done)->where('resolved_at', '>=', now()->startOfWeek()),
            'open'       => $query->whereNotIn('status', $done),
            default      => $query,
        };
    }

    /**
     * The support inbox: saved views with counts, search, and the tickets that need attention first.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $legacy = $request->hasAny(['status', 'customer_id', 'start_date', 'end_date', 'open_only', 'done_only']);

        return Helper::readAllBranches(function () use ($request, $legacy) {
            // Counts for the views (one query each, on the same scope as the list)
            $counts = [];
            foreach (array_keys(self::VIEWS) as $key) {
                $counts[$key] = $this->applyView(Ticket::query(), $key)->count();
            }

            $view = (string) $request->query('view', '');
            if (!isset(self::VIEWS[$view])) {
                // No choice made: legacy links show everything, otherwise start with my work (or all open when there is none)
                $view = $legacy ? 'all' : ($counts['mine'] > 0 ? 'mine' : 'open');
            }

            $query = $this->applyView(Ticket::with(['customer.lead', 'assignedTo']), $view);

            // Search: ticket number, subject, patient name or phone
            if ($request->filled('q')) {
                $q = trim((string) $request->q);
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
                $digits = preg_replace('/\D/', '', $q);
                $query->where(function ($w) use ($q, $like, $digits) {
                    $w->where('subject', 'like', $like)
                      ->orWhereHas('customer.lead', fn ($l) => $l->where('full_name', 'like', $like)->orWhere('phone_number', 'like', $like)
                          ->when($digits !== '', fn ($p) => $p->orWhere('normalized_phone', 'like', '%' . $digits . '%')));
                    if (preg_match('/^(tk-?)?0*(\d+)$/i', $q, $m)) {
                        $w->orWhere('id', (int) $m[2]);
                    }
                });
            }

            if ($request->filled('priority') && in_array($request->priority, array_column(TicketPriority::cases(), 'value'), true)) {
                $query->where('priority', $request->priority);
            }
            if ($request->filled('assigned_to')) {
                $request->assigned_to === 'none' ? $query->whereNull('assigned_to') : $query->where('assigned_to', (int) $request->assigned_to);
            }

            // Older links and bookmarks
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('customer_id')) {
                $query->where('customer_id', $request->customer_id);
            }
            if ($request->filled('start_date') && $request->filled('end_date')) {
                // The whole of the end day is included
                $query->whereBetween('created_at', [
                    date('Y-m-d 00:00:00', strtotime($request->start_date)),
                    date('Y-m-d 23:59:59', strtotime($request->end_date)),
                ]);
            }
            if ($request->boolean('open_only')) {
                $query->whereNotIn('status', ['Solved', 'Closed']);
            }
            if ($request->boolean('done_only')) {
                $query->whereIn('status', ['Solved', 'Closed']);
            }

            // Needs attention first for the working views; newest first for the history views
            if (in_array($view, ['solved', 'all'], true)) {
                $query->orderByDesc('created_at')->orderByDesc('id');
            } else {
                $due = Ticket::dueSql();
                $query->orderByRaw("CASE WHEN status IN ('Solved','Closed') THEN 3 WHEN status IN ('Pending','On Hold') THEN 2 WHEN $due < ? THEN 0 ELSE 1 END", [now()])
                    ->orderByRaw("FIELD(priority, 'Critical','High','Medium','Low')")
                    ->orderBy('created_at');
            }

            return view('admin.ticket.index', [
                'tickets'    => $query->paginate(20)->withQueryString(),
                'views'      => self::VIEWS,
                'counts'     => $counts,
                'view'       => $view,
                'users'      => $this->assignableUsers(),
                'priorities' => TicketPriority::cases(),
                'q'          => (string) $request->query('q', ''),
            ]);
        });
    }

    /**
     * Patient search for the ticket forms (name, phone or patient ID), across branches for head office users who may view them.
     */
    public function patientSearch(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('search', ''));
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
        $digits = preg_replace('/\D/', '', $term);

        $rows = Helper::readAllBranches(fn () => Customer::with('lead:id,full_name,phone_number')
            ->where(function ($q) use ($like, $digits) {
                $q->where('customer_uid', 'like', $like)
                  ->orWhereHas('lead', fn ($l) => $l->where('full_name', 'like', $like)->orWhere('phone_number', 'like', $like)
                      ->when($digits !== '', fn ($p) => $p->orWhere('normalized_phone', 'like', '%' . $digits . '%')));
            })
            ->orderBy('id', 'desc')->limit(20)->get());

        return response()->json($rows->map(fn ($c) => [
            'id'   => $c->id,
            'text' => ($c->lead->full_name ?? 'Unknown') . ($c->lead?->phone_number ? ' (' . $c->lead->phone_number . ')' : '') . ($c->customer_uid ? ' [' . $c->customer_uid . ']' : ''),
        ])->values());
    }
    /**
     * The ticket page: details, quick actions and the timeline of notes and changes.
     */
    public function show(int $id): View
    {
        // Same reach as the list: head office users who may view all branches can open any branch's ticket
        $ticket = Helper::readAllBranches(fn () => Ticket::with(['customer.lead', 'assignedTo', 'notes.user'])->findOrFail($id));

        $user = auth()->user();

        return view('admin.ticket.show', [
            'ticket'     => $ticket,
            'device'     => $ticket->linkedDevice(),
            'repair'     => $ticket->repair_job_id ? Helper::readAllBranches(fn () => \App\Models\RepairJob::find($ticket->repair_job_id)) : null,
            'canOpenRepair' => $ticket->isOpen() && !$ticket->repair_job_id && $user->hasAccess('new_repair_intake.create'),
            'canSeeRepair'  => $user->hasAccess('repair_jobs_board.view') || $user->hasAccess('repair_jobs_board.index'),
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
        // The patient is chosen with a search box; one may arrive preselected (?customer_id=)
        $data['selectedCustomer'] = $this->patientOption(old('customer_id', request('customer_id')));
        $data['selectedDevice'] = $this->deviceOption((int) old('customer_id', request('customer_id')), old('device') ? (int) explode('|', old('device'))[0] : request('sale_master_id'), old('device') ? (explode('|', old('device'))[1] ?? null) : request('serial'));
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

        $ticket = new Ticket($request->only(self::FORM_FIELDS) + $this->deviceFields($request, (int) $request->customer_id));

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
        $data['selectedCustomer'] = $this->patientOption(old('customer_id', $data['ticket']->customer_id));
        $data['selectedDevice'] = $this->deviceOption((int) $data['ticket']->customer_id, $data['ticket']->sale_master_id, $data['ticket']->serial_no);
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
        if ($request->has('device') || (int) $request->customer_id !== (int) $ticket->customer_id) {
            // A different patient clears a link that belonged to the old one
            $ticket->fill($this->deviceFields($request, (int) $request->customer_id));
        }

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
