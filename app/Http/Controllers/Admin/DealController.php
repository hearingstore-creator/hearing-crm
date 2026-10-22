<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InquiryType;
use App\Enums\LeadTemperature;
use App\Enums\SourceOfEntry;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\LostReason;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\DealService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

/**
 * Deals board (Kanban). All state changes go through DealService so history, the customer conversion
 * and the legacy leads.lead_status mirror stay consistent.
 *
 * Visibility: branch users see their branch (Deal global scope); professionals see deals they own;
 * Head Office sees everything. Transfers are Head Office only.
 */
class DealController extends Controller
{
    private const PER_COLUMN = 25;
    private const PER_LIST = 50;
    private const BULK_MAX = 200;
    private const LIST_SORTS = ['created', 'value', 'stage', 'next', 'name', 'age', 'score'];

    public function __construct(private DealService $deals)
    {
        $this->middleware('permission:deal_board.index')->only(['index', 'column', 'show', 'bulk', 'myDay']);
        $this->middleware('permission:deal_board.move')->only(['move', 'storeActivity', 'completeActivity', 'storeNote', 'pinNote', 'destroyNote', 'logContact', 'changePipeline']);
        $this->middleware('permission:add_appointment.create')->only(['bookAppointment']);
        $this->middleware('permission:deal_board.won')->only(['won']);
        $this->middleware('permission:deal_board.lost')->only(['lost']);
        $this->middleware('permission:deal_board.transfer')->only(['transfer']);
        $this->middleware('permission:add_lead.create')->only(['store', 'duplicateCheck']);
    }

    public function index(Request $request): View
    {
        $pipeline = $this->boardPipeline($request);
        $stages = $pipeline->activeStages()->get();
        $status = in_array($request->status, [Deal::WON, Deal::LOST, 'list'], true) ? $request->status : Deal::OPEN;
        $request->merge(['pipeline' => $pipeline->id]);   // every board query below is limited to this pipeline

        $data = [
            'pipeline'     => $pipeline,
            'pipelines'    => Pipeline::usable()->get(['id', 'name', 'is_default']),
            'stages'       => $stages,
            'trialModels'  => \App\Models\Product::withoutGlobalScopes()->leftJoin('brands as b', 'b.id', '=', 'products.brand_id')->whereNotNull('products.model')->where('products.model', '!=', '')->orderBy('b.brand_name')->orderBy('products.model')->limit(500)->get(['products.id', 'products.model', 'b.brand_name'])->mapWithKeys(fn ($p) => [$p->id => trim(($p->brand_name ? $p->brand_name . ' ' : '') . $p->model)]),
            'status'       => $status,
            'filters'      => $request->only(['q', 'branch', 'owner', 'temperature', 'source']),
            'branches'     => \App\Classes\Helper::branchOptions(),
            'owners'       => $this->ownerOptions(),
            'temperatures' => LeadTemperature::cases(),
            'sources'      => SourceOfEntry::cases(),
            'inquiries'    => InquiryType::cases(),
            'lostReasons'  => LostReason::active()->get(['id', 'name']),
            'can'          => $this->abilities(),
            'isHeadOffice' => !auth()->user()->branchId(),
        ];

        if ($status === 'list') {
            [$sort, $dir] = $this->listSort($request);
            $data['sort'] = $sort;
            $data['dir'] = $dir;
            $data['listDeals'] = $this->sorted($this->filtered($request)->open(), $sort, $dir)
                ->paginate(self::PER_LIST)->withQueryString();
            $data['smsTemplates'] = $data['can']['sms']
                ? \App\Models\SmsTemplate::where('is_active', true)->orderBy('name')->get(['id', 'name', 'body'])
                : collect();

            return view('admin.deal.index', $data);
        }

        if ($status !== Deal::OPEN) {
            $data['closedDeals'] = $this->filtered($request)
                ->where('deals.status', $status)
                ->with('lostReason:id,name')
                ->latest($status === Deal::WON ? 'won_at' : 'lost_at')
                ->paginate(25)
                ->withQueryString();

            return view('admin.deal.index', $data);
        }

        $base = $this->filtered($request)->open();
        $totals = (clone $base)->reorder()->setEagerLoads([])
            ->selectRaw('deals.stage_id, COUNT(*) as deal_count, COALESCE(SUM(deals.value), 0) as deal_value')
            ->groupBy('deals.stage_id')
            ->get()
            ->keyBy('stage_id');

        $data['columns'] = $stages->map(fn (PipelineStage $stage) => [
            'stage' => $stage,
            'count' => (int) ($totals[$stage->id]->deal_count ?? 0),
            'value' => (float) ($totals[$stage->id]->deal_value ?? 0),
            'deals' => $this->columnQuery($base, $stage)->limit(self::PER_COLUMN)->get(),
        ]);
        $data['summary'] = [
            'count'    => $data['columns']->sum('count'),
            'value'    => $data['columns']->sum('value'),
            'weighted' => $data['columns']->sum(fn ($c) => $c['value'] * $c['stage']->probability / 100),
            'rotting'  => $data['columns']->sum(fn ($c) => $c['deals']->filter->isRotting()->count()),
        ];

        return view('admin.deal.index', $data);
    }

    /** "Load more" for one column (same filters as the board). */
    public function column(Request $request, int $stageId): JsonResponse
    {
        $stage = PipelineStage::whereNull('archived_at')->findOrFail($stageId);
        $offset = max(0, $request->integer('offset'));
        $deals = $this->columnQuery($this->filtered($request)->open(), $stage)
            ->offset($offset)->limit(self::PER_COLUMN + 1)->get();

        $html = $deals->take(self::PER_COLUMN)
            ->map(fn ($deal) => view('admin.deal._card', ['deal' => $deal, 'can' => $this->abilities()])->render())
            ->implode('');

        return response()->json(['html' => $html, 'has_more' => $deals->count() > self::PER_COLUMN]);
    }

    /** Drag & drop / "Move to": stage change (with stage gates) and/or new position inside the column. */
    public function move(Request $request, int $id): JsonResponse
    {
        $deal = $this->visible()->open()->with('stage')->findOrFail($id);
        $request->validate([
            'stage_id'   => ['required', 'integer'],
            'updated_at' => ['nullable', 'string'],
            'order'      => ['nullable', 'array', 'max:200'],
            'order.*'    => ['integer'],
        ]);

        // Someone else changed this deal since the board was loaded
        if ($request->filled('updated_at') && $deal->updated_at?->toIso8601String() !== $request->updated_at) {
            return response()->json(['message' => 'This deal was just changed by someone else. The board will refresh.'], 409);
        }

        $to = PipelineStage::where('pipeline_id', $deal->pipeline_id)->whereNull('archived_at')->findOrFail($request->integer('stage_id'));

        if ($to->id !== $deal->stage_id) {
            if ($to->sort_order > ($deal->stage->sort_order ?? 0)) {
                $this->deals->move($deal, $to, $this->validateGate($request, $deal, $to));
            } else {
                $note = $request->validate(['note' => ['required', 'string', 'max:1000']], [
                    'note.required' => 'Please add a short note: why is this deal moving back?',
                ])['note'];
                $this->deals->move($deal, $to, [], 'Moved back to ' . $to->name . ': ' . $note);
            }
        }

        if ($request->filled('order')) {
            $this->reposition($to, $request->input('order'));
        }

        return response()->json($this->cardPayload($deal));
    }

    public function won(Request $request, int $id): JsonResponse
    {
        $deal = $this->visible()->open()->findOrFail($id);
        $data = $request->validate([
            'note'        => ['nullable', 'string', 'max:1000'],
            'create_sale' => ['nullable', 'boolean'],
        ]);

        $customer = $this->deals->markWon($deal, null, $data['note'] ?? null);
        $redirect = !empty($data['create_sale']) && auth()->user()->hasAccess('add_sale.create')
            ? url('admin/sale/create?customer_id=' . $customer->id)
            : null;

        return response()->json([
            'message'  => 'Deal won' . ($deal->lead?->full_name ? ' – ' . $deal->lead->full_name . ' is now a customer.' : '.'),
            'redirect' => $redirect,
        ]);
    }

    public function lost(Request $request, int $id): JsonResponse
    {
        $deal = $this->visible()->open()->findOrFail($id);
        $data = $request->validate([
            'lost_reason_id' => ['required', Rule::exists('lost_reasons', 'id')->where('is_active', true)],
            'lost_to'        => ['nullable', 'string', 'max:150'],
            'note'           => ['required', 'string', 'max:1000'],
        ], [
            'lost_reason_id.required' => 'Please choose why the deal was lost.',
        ]);

        $this->deals->markLost($deal, (int) $data['lost_reason_id'], $data['lost_to'] ?? null, $data['note']);

        return response()->json(['message' => 'Deal marked as lost.']);
    }

    public function reopen(int $id): JsonResponse
    {
        $user = auth()->user();
        abort_unless($user->hasAccess('deal_board.won') || $user->hasAccess('deal_board.lost'), 403);

        $deal = $this->visible()->where('deals.status', '!=', Deal::OPEN)->findOrFail($id);
        $this->deals->reopen($deal);

        return response()->json(['message' => 'Deal reopened in ' . ($deal->stage->name ?? 'its stage') . '.']);
    }

    /** Head Office → branch (or back to Head Office), with a staff owner and a note. */
    public function transfer(Request $request, int $id): JsonResponse
    {
        abort_if(auth()->user()->branchId(), 403, 'Only Head Office can transfer deals.');

        $deal = $this->visible()->open()->findOrFail($id);
        $data = $request->validate([
            'vendor_id' => ['nullable', Rule::exists('users', 'id')->where('type', 'vendor')],
            'owner_id'  => ['required', 'integer'],
            'note'      => ['nullable', 'string', 'max:1000'],
        ], [
            'owner_id.required' => 'Please choose who will handle this deal.',
        ]);

        $vendorId = !empty($data['vendor_id']) ? (int) $data['vendor_id'] : null;
        $allowedOwners = collect($this->ownerOptions()[$vendorId ?? 'hq'] ?? [])->pluck('id');
        if (!$allowedOwners->contains((int) $data['owner_id'])) {
            return response()->json(['message' => 'The selected person does not work in that branch.', 'errors' => ['owner_id' => ['Choose a person from the selected branch.']]], 422);
        }

        $this->deals->transfer($deal, $vendorId, (int) $data['owner_id'], $data['note'] ?? null);
        $branch = $vendorId ? (\App\Classes\Helper::branchOptions()[$vendorId] ?? 'branch') : 'Head Office';

        return response()->json(['message' => 'Deal transferred to ' . $branch . '.'] + $this->cardPayload($deal));
    }

    /** Move an open deal to another pipeline: it starts again in that pipeline's first stage (logged in history). */
    public function changePipeline(Request $request, int $id): JsonResponse
    {
        $deal = $this->visible()->open()->with(['stage', 'pipeline'])->findOrFail($id);
        $data = $request->validate([
            'pipeline_id' => ['required', 'integer', Rule::in(Pipeline::usable()->whereKeyNot($deal->pipeline_id)->pluck('id')->all())],
            'note'        => ['nullable', 'string', 'max:1000'],
        ], ['pipeline_id.required' => 'Choose the pipeline to move this deal to.', 'pipeline_id.in' => 'Choose another pipeline that is switched on.']);

        $to = Pipeline::findOrFail($data['pipeline_id']);
        $this->deals->moveToPipeline($deal, $to, $data['note'] ?? null);

        return response()->json(['message' => 'Deal moved to ' . $to->name . ' (' . $deal->stage->name . ').', 'pipeline_id' => $to->id]);
    }

    /**
     * List view bulk actions on the ticked open deals (max 200). Each deal goes through DealService,
     * exactly like the single-deal action, so history and notifications stay the same.
     */
    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action'   => ['required', Rule::in(['transfer', 'owner', 'sms', 'lost'])],
            'ids'      => ['required', 'array', 'min:1', 'max:' . self::BULK_MAX],
            'ids.*'    => ['integer'],
            'note'     => ['nullable', 'string', 'max:1000'],
        ], ['ids.required' => 'Tick at least one deal.', 'ids.max' => 'You can change up to ' . self::BULK_MAX . ' deals at once.']);

        $can = $this->abilities();
        abort_unless(match ($data['action']) {
            'transfer' => $can['transfer'], 'owner' => $can['move'], 'sms' => $can['sms'], 'lost' => $can['lost'],
        }, 403, 'You are not allowed to do this.');

        $deals = $this->visible()->open()->with('lead')->whereIn('deals.id', $data['ids'])->get();
        if ($deals->isEmpty()) {
            return response()->json(['message' => 'None of the selected deals can be changed (closed or not visible to you).'], 422);
        }

        return match ($data['action']) {
            'transfer' => $this->bulkTransfer($request, $deals, $data['note'] ?? null),
            'owner'    => $this->bulkOwner($request, $deals, $data['note'] ?? null),
            'sms'      => $this->bulkSms($request, $deals),
            'lost'     => $this->bulkLost($request, $deals),
        };
    }

    private function bulkTransfer(Request $request, $deals, ?string $note): JsonResponse
    {
        $data = $request->validate([
            'vendor_id' => ['nullable', Rule::exists('users', 'id')->where('type', 'vendor')],
            'owner_id'  => ['required', 'integer'],
        ], ['owner_id.required' => 'Please choose who will handle these deals.']);

        $vendorId = !empty($data['vendor_id']) ? (int) $data['vendor_id'] : null;
        if (!collect($this->ownerOptions()[$vendorId ?? 'hq'] ?? [])->pluck('id')->contains((int) $data['owner_id'])) {
            return response()->json(['message' => 'The selected person does not work in that branch.'], 422);
        }

        DB::transaction(fn () => $deals->each(fn (Deal $d) => $this->deals->transfer($d, $vendorId, (int) $data['owner_id'], $note)));
        $branch = $vendorId ? (\App\Classes\Helper::branchOptions()[$vendorId] ?? 'branch') : 'Head Office';

        return response()->json(['message' => $deals->count() . ' deal(s) transferred to ' . $branch . '.']);
    }

    /** New owner inside each deal's own branch; deals whose branch the person is not in are skipped. */
    private function bulkOwner(Request $request, $deals, ?string $note): JsonResponse
    {
        $ownerId = (int) $request->validate(['owner_id' => ['required', 'integer']], ['owner_id.required' => 'Choose the new owner.'])['owner_id'];
        $options = $this->ownerOptions();
        $done = 0;

        DB::transaction(function () use ($deals, $ownerId, $note, $options, &$done) {
            foreach ($deals as $deal) {
                $allowed = collect($options[$deal->vendor_id ?? 'hq'] ?? [])->pluck('id');
                if ($deal->owner_id === $ownerId || !$allowed->contains($ownerId)) {
                    continue;
                }
                $this->deals->reassign($deal, $ownerId, $note);
                $done++;
            }
        });

        $skipped = $deals->count() - $done;

        return response()->json(['message' => $done . ' deal(s) reassigned.' . ($skipped ? " $skipped skipped (already theirs, or another branch)." : '')]);
    }

    /** One queued SMS campaign to the people of the selected deals (opt-out list respected). */
    private function bulkSms(Request $request, $deals): JsonResponse
    {
        $user = auth()->user();
        abort_unless($user->hasAccess('compose_&_send.create') && ($user->isSuperAdmin() || !$user->branchId()), 403, 'SMS campaigns are sent by head office only.');

        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'sms_template_id' => ['nullable', 'exists:sms_templates,id'],
        ], ['message.required' => 'Write the SMS or choose a template.']);

        $leadIds = $deals->pluck('lead_id')->filter()->unique()->values()->all();
        $campaign = \App\Models\SmsCampaign::create([
            'name'            => 'Deals list · ' . now()->format('d M Y H:i') . ' · ' . count($leadIds) . ' people',
            'message_body'    => $data['message'],
            'sms_template_id' => $data['sms_template_id'] ?? null,
            'recipient_type'  => 'lead_ids',
            'filter_criteria' => ['lead_ids' => $leadIds],
            'status'          => \App\Enums\SmsCampaignStatus::SENDING->value,
        ]);
        $sendAt = SmsController::quietHoursSendAt();
        $job = \App\Jobs\SendSmsCampaign::dispatch($campaign->id, auth()->id());
        if ($sendAt) {
            $job->delay($sendAt);
            unset($job);

            return response()->json(['message' => 'SMS to ' . count($leadIds) . ' people will be sent at ' . $sendAt->format('g:i A, d M') . ' (quiet hours).']);
        }
        unset($job);

        return response()->json(['message' => 'SMS to ' . count($leadIds) . ' people is being sent. Results appear in the SMS Send Log.']);
    }

    private function bulkLost(Request $request, $deals): JsonResponse
    {
        $data = $request->validate([
            'lost_reason_id' => ['required', Rule::exists('lost_reasons', 'id')->where('is_active', true)],
            'lost_to'        => ['nullable', 'string', 'max:150'],
            'note'           => ['required', 'string', 'max:1000'],
        ], ['lost_reason_id.required' => 'Please choose why the deals were lost.', 'note.required' => 'Please add a short note.']);

        DB::transaction(fn () => $deals->each(fn (Deal $d) => $this->deals->markLost($d, (int) $data['lost_reason_id'], $data['lost_to'] ?? null, $data['note'])));

        return response()->json(['message' => $deals->count() . ' deal(s) marked as lost.']);
    }

    /** Deal drawer: person, membership, activities, timeline, other deals, purchase history. */
    public function show(int $id): View
    {
        $deal = $this->visible()->with([
            'stage', 'owner:id,name', 'vendor:id,name', 'lostReason:id,name',
            'lead.customer' => fn ($q) => $q->withoutGlobalScopes(),
            'lead.customer.membershipTier:id,name',
            'activities' => fn ($q) => $q->with(['assignedUser:id,name', 'creator'])->orderBy('follow_up_date'),
            'statusHistories' => fn ($q) => $q->with(['fromStage:id,name,archived_at', 'toStage:id,name,archived_at', 'creator']),
            'transfers' => fn ($q) => $q->with(['fromVendor:id,name', 'toVendor:id,name', 'toOwner:id,name', 'transferredBy:id,name']),
        ])->findOrFail($id);

        $customer = $deal->lead?->customer;
        // Read-only history of the person; deal visibility already decided access
        $purchases = $customer
            ? \App\Models\SaleMaster::withoutGlobalScopes()->with('vendor:id,name')->where('customer_id', $customer->id)
                ->whereNull('deleted_at')->latest('date')->limit(20)->get()
            : collect();

        return view('admin.deal._drawer', [
            'deal'       => $deal,
            'customer'   => $customer,
            'points'     => $customer ? $customer->getAvailableRedeemPoints() : null,
            'purchases'  => $purchases,
            'otherDeals' => Deal::withoutGlobalScopes()->with(['stage:id,name,color', 'vendor:id,name'])
                ->where('lead_id', $deal->lead_id)->where('id', '!=', $deal->id)->whereNull('deleted_at')->latest('id')->get(),
            'timeline'   => $this->timeline($deal),
            'stages'     => $deal->pipeline->activeStages()->get(['id', 'name', 'color', 'sort_order']),
            'pipelines'  => Pipeline::usable()->get(['id', 'name']),
            'types'      => \App\Enums\ActivityType::cases(),
            'assignees'  => collect($this->ownerOptions())->flatten(1)->sortBy('name')->values(),
            'can'        => $this->abilities(),
            // Notes belong to the person, so they follow them across deals; pinned first
            'notes'      => \App\Models\DealNote::with('creator:id,name')->where('lead_id', $deal->lead_id)
                ->orderByDesc('is_pinned')->latest('id')->limit(100)->get(),
            'appointments' => \App\Models\Appointment::with(['service:id,name', 'user:id,name', 'professionalTimeSlot.timeSlot'])
                ->where('lead_id', $deal->lead_id)->whereDate('appointment_date', '>=', today()->subDays(30))
                ->orderBy('appointment_date')->limit(20)->get(),
            'enrollments' => \App\Models\SequenceEnrollment::with('sequence:id,name')->where('deal_id', $deal->id)->latest('id')->limit(5)->get(),
            'trials'      => \App\Models\DealTrial::where('deal_id', $deal->id)->latest('id')->get(),
            'trialModels' => \App\Models\Product::withoutGlobalScopes()->leftJoin('brands as b', 'b.id', '=', 'products.brand_id')->whereNotNull('products.model')->where('products.model', '!=', '')->orderBy('b.brand_name')->orderBy('products.model')->limit(500)->get(['products.id', 'products.model', 'b.brand_name'])->mapWithKeys(fn ($p) => [$p->id => trim(($p->brand_name ? $p->brand_name . ' ' : '') . $p->model)]),
            'sequenceOptions' => \App\Models\Sequence::orderByDesc('is_active')->orderBy('name')->whereHas('steps')->get(['id', 'name', 'is_active']),
            'services'   => $this->abilities()['appointment']
                ? \App\Models\AppointmentService::where('status', 1)->orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    /** Schedule an activity or log one that already happened (notes are always logged). */
    public function storeActivity(Request $request, int $id): JsonResponse
    {
        $deal = $this->visible()->findOrFail($id);
        $data = $request->validate([
            'activity_type' => ['required', new Enum(\App\Enums\ActivityType::class)],
            'due_at'        => ['exclude_if:activity_type,note', 'nullable', 'required_unless:done,1', 'date'],
            'note'          => ['nullable', 'required_if:activity_type,note', 'string', 'max:1000'],
            'assigned_user' => ['nullable', Rule::in(collect($this->ownerOptions())->flatten(1)->pluck('id')->all())],
            'done'          => ['nullable', 'boolean'],
        ], [
            'due_at.required_unless' => 'Choose when this should happen, or tick "Already done".',
            'note.required_if'       => 'Write the note.',
        ]);
        $type = \App\Enums\ActivityType::from($data['activity_type']);

        $this->deals->addActivity($deal, $type, $data['due_at'] ?? null, $data['note'] ?? null,
            isset($data['assigned_user']) ? (int) $data['assigned_user'] : null, !empty($data['done']));

        $warn = (!empty($data['due_at']) && empty($data['done'])) ? \App\Services\DealService::offHoursWarning(\Carbon\Carbon::parse($data['due_at'])) : null;

        return response()->json(['message' => ($type === \App\Enums\ActivityType::NOTE ? 'Note added.' : $type->label() . (!empty($data['done']) ? ' logged.' : ' scheduled.')) . ($warn ? ' ' . $warn : '')]
            + $this->cardPayload($deal));
    }

    public function completeActivity(Request $request, int $activityId): JsonResponse
    {
        $activity = \App\Models\LeadFollowUp::where('status', 'pending')->findOrFail($activityId);
        $deal = $this->visible()->findOrFail($activity->deal_id);
        $data = $request->validate([
            'outcome'     => ['nullable', 'string', 'max:1000'],
            'result'      => ['nullable', new Enum(\App\Enums\CallOutcome::class)],
            'callback_at' => ['nullable', 'date', 'after:now'],
        ], ['callback_at.after' => 'Choose a future time for the call back.']);

        if (!empty($data['result'])) {
            [$message, $suggestLost, $threeMissed] = $this->deals->recordOutcome($activity,
                \App\Enums\CallOutcome::from($data['result']), $data['outcome'] ?? null, $data['callback_at'] ?? null);

            return response()->json([
                'message'      => $message . ($threeMissed ? ' 3 calls in a row without answer.' : ''),
                'suggest_lost' => $suggestLost,
                'deal_id'      => $deal->id,
            ] + $this->cardPayload($deal));
        }

        $this->deals->completeActivity($activity, $data['outcome'] ?? null);

        return response()->json(['message' => 'Marked as done.'] + $this->cardPayload($deal));
    }

    /** My Day: the signed-in user's overdue, today's and next activities on open deals, one list. */
    public function myDay(Request $request): View
    {
        $user = auth()->user();
        $everyone = $request->query('who') === 'all' && !$user->isProfessional();
        $dealIds = $this->visible()->open()->select('deals.id');

        $activities = \App\Models\LeadFollowUp::with([
                'deal' => fn ($q) => $q->withoutGlobalScopes()->with('stage:id,name,color'),
                'lead' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'full_name', 'phone_number', 'lead_uid'),
                'assignedUser:id,name',
            ])
            ->where('status', 'pending')
            ->whereIn('deal_id', $dealIds)
            ->when(!$everyone, fn ($q) => $q->where('assigned_user', $user->id))
            ->where('follow_up_date', '<', now()->addDays(2)->endOfDay())
            ->orderBy('follow_up_date')
            ->limit(300)
            ->get();

        $groups = [
            'overdue'  => $activities->filter(fn ($a) => \Carbon\Carbon::parse($a->follow_up_date)->lt(now()->startOfDay())
                || (\Carbon\Carbon::parse($a->follow_up_date)->isToday() && \Carbon\Carbon::parse($a->follow_up_date)->lt(now()))),
            'today'    => $activities->filter(fn ($a) => \Carbon\Carbon::parse($a->follow_up_date)->isToday() && \Carbon\Carbon::parse($a->follow_up_date)->gte(now())),
            'upcoming' => $activities->filter(fn ($a) => \Carbon\Carbon::parse($a->follow_up_date)->gt(now()->endOfDay())),
        ];

        $doneToday = \App\Models\LeadFollowUp::where('status', 'completed')->whereIn('deal_id', $this->visible()->select('deals.id'))
            ->when(!$everyone, fn ($q) => $q->where('assigned_user', $user->id))
            ->whereDate('completed_at', today())->where('activity_type', '!=', 'note')->count();

        return view('admin.deal.my_day', [
            'groups'    => $groups,
            'everyone'  => $everyone,
            'canAll'    => !$user->isProfessional(),
            'doneToday' => $doneToday,
            'outcomes'  => \App\Enums\CallOutcome::cases(),
            'can'       => $this->abilities(),
        ]);
    }

    /**
     * Click-to-call / WhatsApp: the phone app opens on the client; here we log it as a done activity.
     * Repeated taps within 2 minutes are logged once.
     */
    public function logContact(Request $request, int $id): JsonResponse
    {
        $deal = $this->visible()->findOrFail($id);
        $channel = $request->validate(['channel' => ['required', Rule::in(['call', 'whatsapp'])]])['channel'];
        $type = $channel === 'call' ? \App\Enums\ActivityType::CALL : \App\Enums\ActivityType::WHATSAPP;

        if (\Illuminate\Support\Facades\Cache::add('contact:' . auth()->id() . ':' . $deal->id . ':' . $channel, 1, 120)) {
            $this->deals->addActivity($deal, $type, null, $channel === 'call' ? 'Called from the CRM' : 'WhatsApp opened from the CRM', auth()->id(), true);
        }

        return response()->json(['message' => $type->label() . ' logged.'] + $this->cardPayload($deal));
    }

    public function storeNote(Request $request, int $id): JsonResponse
    {
        $deal = $this->visible()->findOrFail($id);
        $data = $request->validate([
            'body'      => ['required', 'string', 'max:2000'],
            'is_pinned' => ['nullable', 'boolean'],
        ], ['body.required' => 'Write the note.']);

        \App\Models\DealNote::create([
            'lead_id'    => $deal->lead_id,
            'deal_id'    => $deal->id,
            'body'       => $data['body'],
            'is_pinned'  => !empty($data['is_pinned']),
            'created_by' => auth()->id(),
        ]);

        return response()->json(['message' => 'Note saved.']);
    }

    public function pinNote(int $noteId): JsonResponse
    {
        $note = $this->ownNote($noteId, false);
        $note->update(['is_pinned' => !$note->is_pinned]);

        return response()->json(['message' => $note->is_pinned ? 'Note pinned.' : 'Note unpinned.']);
    }

    public function destroyNote(int $noteId): JsonResponse
    {
        $this->ownNote($noteId, true)->delete();

        return response()->json(['message' => 'Note deleted.']);
    }

    /** A note on a deal the user can see. Deleting is limited to its author and Super Admin. */
    private function ownNote(int $noteId, bool $mustOwn): \App\Models\DealNote
    {
        $note = \App\Models\DealNote::findOrFail($noteId);
        abort_unless($this->visible()->where('lead_id', $note->lead_id)->exists(), 404);
        abort_if($mustOwn && (int) $note->created_by !== (int) auth()->id() && !auth()->user()->isSuperAdmin(), 403, 'Only the author can delete this note.');

        return $note;
    }

    /**
     * Book a real appointment (Appointments module) from the drawer, linked to the deal,
     * with a planned Visit activity at the slot time and the "Appointment Booked" SMS.
     */
    public function bookAppointment(Request $request, int $id): JsonResponse
    {
        $deal = $this->visible()->with('lead.customer')->findOrFail($id);
        abort_unless($deal->isOpen(), 422, 'This deal is closed.');
        $data = $request->validate([
            'service_id'                => ['required', 'integer', 'exists:appointment_services,id'],
            'user_id'                   => ['required', 'integer', Rule::exists('users', 'id')->where('type', 'professional')],
            'appointment_date'          => ['required', 'date', 'after_or_equal:today'],
            'professional_time_slot_id' => ['required', 'integer', Rule::exists('professional_time_slots', 'id')->where('user_id', $request->integer('user_id'))],
            'notes'                     => ['nullable', 'string', 'max:1000'],
        ], [
            'professional_time_slot_id.required' => 'Choose a time slot.',
            'professional_time_slot_id.exists'   => 'That slot does not belong to the chosen professional.',
        ]);

        $professional = User::findOrFail($data['user_id']);
        $appointment = DB::transaction(function () use ($deal, $data, $professional) {
            // Lock the professional's day so two people cannot take the same slot at once
            \App\Models\Appointment::where('user_id', $professional->id)->whereDate('appointment_date', $data['appointment_date'])->lockForUpdate()->get(['id']);
            if (!(new \App\Services\SlotGeneratorService())->isSlotAvailable($professional, $data['appointment_date'], $data['professional_time_slot_id'])) {
                return null;
            }

            $slot = \App\Models\ProfessionalTimeSlot::with('timeSlot')->find($data['professional_time_slot_id']);
            $startTime = $slot?->timeSlot?->start_time?->format('H:i:s');
            $endTime = $slot?->timeSlot?->end_time?->format('H:i:s');
            $duration = 45;
            if ($startTime && $endTime) {
                $duration = (int) ((\Illuminate\Support\Carbon::parse($endTime)->diffInMinutes(\Illuminate\Support\Carbon::parse($startTime))) ?: 45);
            }

            $appointment = \App\Models\Appointment::create($data + [
                'lead_id'          => $deal->lead_id,
                'customer_id'      => $deal->lead?->customer?->id,
                'deal_id'          => $deal->id,
                'branch_id'        => $deal->vendor_id ?? $professional->vendor_id ?? \App\Classes\Helper::currentBranchId(),
                'start_time'       => $startTime,
                'end_time'         => $endTime,
                'duration_minutes' => $duration,
                'status'           => \App\Enums\AppointmentStatus::SCHEDULED->value,
            ]);

            $start = $slot?->timeSlot?->start_time;
            $at = \Illuminate\Support\Carbon::parse($data['appointment_date'])->setTime((int) $start?->format('H'), (int) $start?->format('i'));
            $this->deals->addActivity($deal, \App\Enums\ActivityType::VISIT, $at->format('Y-m-d H:i:s'),
                trim('Appointment: ' . ($appointment->service->name ?? '') . ' with ' . $professional->name . '. ' . ($data['notes'] ?? '')), $deal->owner_id);

            return $appointment;
        });

        if (!$appointment) {
            return response()->json(['message' => 'This slot was just booked. Choose another one.', 'errors' => ['professional_time_slot_id' => ['This slot was just booked. Choose another one.']]], 422);
        }
        $appointment->notifyBooked();

        return response()->json(['message' => 'Appointment booked for ' . $appointment->appointment_date->format('d M Y') . '.'] + $this->cardPayload($deal));
    }

    /**
     * Quick Add duplicate check across ALL branches: same phone (normalised) or membership card number.
     * Branch users see only the name and branch of people outside their branch.
     */
    public function duplicateCheck(Request $request): JsonResponse
    {
        return response()->json(['matches' => $this->findPeople($request->input('phone'), $request->input('card'))]);
    }

    /** Quick Add: new person + deal, or (match chosen) a new deal for the existing person. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'existing_lead_id' => ['nullable', 'integer'],
            'full_name'        => ['required_without:existing_lead_id', 'nullable', 'string', 'min:2', 'max:255'],
            'phone_number'     => ['required_without:existing_lead_id', 'nullable', 'string', 'max:20'],
            'inquiry_for'      => ['required', 'array', 'min:1'],
            'inquiry_for.*'    => [new Enum(InquiryType::class)],
            'source'           => ['nullable', new Enum(SourceOfEntry::class)],
            'temperature'      => ['nullable', new Enum(LeadTemperature::class)],
            'value'            => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'note'             => ['nullable', 'string', 'max:1000'],
            'pipeline_id'      => ['nullable', 'integer', Rule::in(Pipeline::usable()->pluck('id')->all())],
            'hearing_loss'                => ['nullable', 'array'],
            'hearing_loss.*.enabled'      => ['nullable', 'boolean'],
            'hearing_loss.*.degree'       => ['nullable', new Enum(\App\Enums\HearingDegree::class)],
            'hearing_loss.*.type'         => ['nullable', new Enum(\App\Enums\HearingType::class)],
        ], [
            'inquiry_for.required' => 'What does the patient need? Choose at least one interest.',
        ]);

        $attributes = array_filter([
            'inquiry_for' => array_values($data['inquiry_for']),
            'source'      => $data['source'] ?? null,
            'temperature' => $data['temperature'] ?? null,
            'value'       => $data['value'] ?? null,
            'pipeline_id' => $data['pipeline_id'] ?? null,
        ], fn ($v) => $v !== null);

        $hearing = collect($data['hearing_loss'] ?? [])->only(['right', 'left'])
            ->filter(fn ($e) => !empty($e['enabled']))
            ->map(fn ($e) => ['enabled' => true, 'degree' => $e['degree'] ?? null, 'type' => $e['type'] ?? null])
            ->all();

        $deal = DB::transaction(function () use ($data, $attributes, $hearing) {
            if (!empty($data['existing_lead_id'])) {
                $lead = \App\Models\Lead::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($data['existing_lead_id']);
                if ($hearing && !$lead->hearingEars()) {
                    $lead->update(['hearing_loss' => $hearing]); // fill only when nothing is recorded yet
                }
                $deal = $this->deals->openNewDeal($lead, $attributes);
            } else {
                // Never create a second person with the same phone: the user must pick the existing one
                abort_if(!empty($this->findPeople($data['phone_number'], null)), 422, 'This phone number already belongs to someone. Choose them from the list to add a new deal.');

                $lead = \App\Models\Lead::create([
                    'lead_uid'         => \App\Models\Lead::generateUid(),
                    'full_name'        => $data['full_name'],
                    'phone_number'     => $data['phone_number'],
                    'vendor_id'        => auth()->user()->branchId(),
                    'inquiry_for'      => $attributes['inquiry_for'],
                    'source_of_entry'  => $attributes['source'] ?? null,
                    'lead_temperature' => $attributes['temperature'] ?? null,
                    'approx_budget'    => $attributes['value'] ?? null,
                    'hearing_loss'     => $hearing ?: null,
                    'lead_status'      => \App\Enums\LeadStatus::NEW->value,
                    'lead_type'        => \App\Enums\LeadType::NEW_CUSTOMER->value,
                ]);
                $deal = $this->deals->openInitialDeal($lead, array_intersect_key($attributes, ['pipeline_id' => true]));
                app(\App\Services\SmsAutomationService::class)->fireAfterCommit(\App\Enums\SmsAutomationTrigger::LEAD_CREATED,
                    $lead->phone_number, 'lead', $lead->id, ['name' => $lead->full_name, 'customer_name' => $lead->full_name], 'lead-welcome:' . $lead->id);
            }

            // First step for every new deal: a call today (or the note, logged)
            $this->deals->addActivity($deal, \App\Enums\ActivityType::CALL, \App\Services\DealService::dueInOfficeHours(60)->toDateTimeString(), $data['note'] ?? 'First call');

            return $deal;
        });

        return response()->json(['message' => 'Deal added for ' . $deal->lead->full_name . '.'] + $this->cardPayload($deal));
    }

    // ------------------------------------------------------------------

    /** People matching a phone (normalised) or a membership card number, across all branches. */
    private function findPeople(?string $phone, ?string $card): array
    {
        $normalized = \App\Classes\Helper::normalizePhone($phone);
        $card = trim((string) $card);
        if ((!$normalized || strlen($normalized) < 10) && $card === '') {
            return [];
        }

        $leads = \App\Models\Lead::withoutGlobalScopes()->whereNull('leads.deleted_at')
            ->with(['customer' => fn ($q) => $q->withoutGlobalScopes()->with('membershipTier:id,name'), 'vendor:id,name'])
            ->where(function ($q) use ($normalized, $card) {
                if ($normalized && strlen($normalized) >= 10) {
                    $q->orWhere('normalized_phone', $normalized);
                }
                if ($card !== '') {
                    $q->orWhereIn('id', DB::table('customers')->whereNull('deleted_at')->where('membership_id', $card)->select('lead_id'));
                }
            })
            ->limit(5)->get();

        $myBranch = auth()->user()->branchId();
        $openDeals = Deal::withoutGlobalScopes()->whereNull('deleted_at')->open()->whereIn('lead_id', $leads->pluck('id'))
            ->with('stage:id,name')->get()->groupBy('lead_id');

        return $leads->map(function ($lead) use ($myBranch, $openDeals) {
            $sameScope = !$myBranch || (int) $lead->vendor_id === $myBranch;
            $open = $openDeals->get($lead->id, collect());

            return [
                'id'         => $lead->id,
                'name'       => $lead->full_name,
                'phone'      => $sameScope ? $lead->phone_number : null,
                'lead_uid'   => $lead->lead_uid,
                'branch'     => $lead->vendor->name ?? 'Head Office',
                'customer'   => (bool) $lead->customer,
                'tier'       => $lead->customer?->membershipTier?->name,
                'card'       => $sameScope ? $lead->customer?->membership_id : null,
                'open_deals' => $open->map(fn ($d) => $d->display_title . ' · ' . ($d->stage->name ?? ''))->values(),
            ];
        })->values()->all();
    }

    /** Stage moves, transfers and finished activities, newest first. */
    private function timeline(Deal $deal)
    {
        $items = collect();
        foreach ($deal->statusHistories as $h) {
            $moved = $h->from_stage_id && $h->to_stage_id && $h->from_stage_id !== $h->to_stage_id;
            $items->push([
                'at'    => $h->created_at,
                'icon'  => $moved ? 'fas fa-exchange-alt' : 'fas fa-flag',
                'color' => 'var(--accent-500)',
                'title' => $moved ? ($h->fromStage->display_name ?? '?') . ' → ' . ($h->toStage->display_name ?? '?') : ($h->notes ?: $h->to_status_label),
                'text'  => $moved ? $h->notes : null,
                'by'    => $h->creator->name ?? null,
            ]);
        }
        foreach ($deal->transfers as $t) {
            $items->push([
                'at'    => $t->created_at,
                'icon'  => 'fas fa-share-square',
                'color' => 'var(--info-500)',
                'title' => 'Transferred to ' . ($t->toVendor->name ?? 'Head Office') . ($t->toOwner ? ' · ' . $t->toOwner->name : ''),
                'text'  => $t->note,
                'by'    => $t->transferredBy->name ?? null,
            ]);
        }
        foreach ($deal->activities->where('status', 'completed') as $a) {
            $items->push([
                'at'    => $a->completed_at ?? $a->updated_at,
                'icon'  => $a->type->icon(),
                'color' => 'var(--success-500)',
                'title' => $a->type === \App\Enums\ActivityType::NOTE ? 'Note' : $a->type->label() . ' done',
                'text'  => trim(($a->follow_up_notes ?? '') . ($a->outcome_notes ? ' → ' . $a->outcome_notes : '')),
                'by'    => $a->creator->name ?? null,
            ]);
        }

        return $items->sortByDesc('at')->values();
    }

    /** Deals the current user may see. */
    private function visible(): Builder
    {
        $user = auth()->user();
        $query = Deal::query();
        if ($user->isProfessional()) {
            $query->where('deals.owner_id', $user->id);
        }

        return $query;
    }

    /** Visible deals + board filters + everything a card needs. */
    private function filtered(Request $request): Builder
    {
        $query = $this->visible()->with([
            'stage', 'owner:id,name', 'vendor:id,name',
            'lead:id,lead_uid,full_name,phone_number,vendor_id',
            'nextActivity',
            // Membership badge: deal visibility already applies, so read the customer across branch scopes
            'lead.customer' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'lead_id', 'membership_tier_id', 'membership_id'),
            'lead.customer.membershipTier:id,name',
        ]);

        if ($request->filled('pipeline')) {
            $query->where('deals.pipeline_id', (int) $request->pipeline);
        }
        if (!auth()->user()->branchId() && $request->filled('branch')) {
            $request->branch === 'hq'
                ? $query->whereNull('deals.vendor_id')
                : $query->where('deals.vendor_id', (int) $request->branch);
        }
        if ($request->filled('owner')) {
            $query->where('deals.owner_id', $request->owner === 'me' ? auth()->id() : (int) $request->owner);
        }
        if ($request->filled('temperature') && LeadTemperature::tryFrom($request->temperature)) {
            $query->where('deals.temperature', $request->temperature);
        }
        if ($request->filled('source') && SourceOfEntry::tryFrom($request->source)) {
            $query->where('deals.source', $request->source);
        }
        if ($request->filled('q')) {
            $term = trim($request->q);
            $phone = \App\Classes\Helper::normalizePhone($term);
            $query->whereHas('lead', function ($lead) use ($term, $phone) {
                $lead->where(function ($w) use ($term, $phone) {
                    $w->where('full_name', 'like', "%$term%")
                        ->orWhere('lead_uid', $term)
                        ->orWhere('phone_number', 'like', "%$term%")
                        ->orWhereIn('id', DB::table('customers')->select('lead_id')
                            ->where('membership_id', $term)->orWhere('customer_uid', $term));
                    if ($phone && strlen($phone) >= 6) {
                        $w->orWhere('normalized_phone', 'like', "%$phone%");
                    }
                });
            });
        }

        return $query;
    }

    private function columnQuery(Builder $base, PipelineStage $stage): Builder
    {
        return (clone $base)->where('deals.stage_id', $stage->id)->orderBy('deals.position')->orderByDesc('deals.id');
    }

    /** ?sort=created|value|stage|next|name|age & dir=asc|desc (List view). */
    private function listSort(Request $request): array
    {
        $sort = in_array($request->sort, self::LIST_SORTS, true) ? $request->sort : 'created';
        $dir = $request->dir === 'asc' ? 'asc' : 'desc';

        return [$sort, $dir];
    }

    private function sorted(Builder $query, string $sort, string $dir): Builder
    {
        match ($sort) {
            'value' => $query->orderBy('deals.value', $dir),
            'score' => $query->orderByRaw('deals.score IS NULL')->orderBy('deals.score', $dir),
            'age'   => $query->orderBy('deals.stage_entered_at', $dir === 'asc' ? 'desc' : 'asc'),
            'stage' => $query->orderBy(PipelineStage::select('sort_order')->whereColumn('pipeline_stages.id', 'deals.stage_id'), $dir),
            'name'  => $query->orderBy(\App\Models\Lead::withoutGlobalScopes()->select('full_name')->whereColumn('leads.id', 'deals.lead_id'), $dir),
            // Deals without a planned activity go last
            'next'  => $query->orderByRaw('(' . $this->nextActivitySql() . ') IS NULL')->orderByRaw('(' . $this->nextActivitySql() . ') ' . $dir),
            default => $query->orderBy('deals.created_at', $dir),
        };

        return $query->orderByDesc('deals.id');
    }

    private function nextActivitySql(): string
    {
        return "SELECT MIN(f.follow_up_date) FROM lead_follow_ups f WHERE f.deal_id = deals.id AND f.status = 'pending'";
    }

    /**
     * Stage gate: the info the target stage requires (configured in Pipeline Settings).
     * Interest and value fall back to what the deal already has.
     */
    private function validateGate(Request $request, Deal $deal, PipelineStage $to): array
    {
        return \App\Services\StageGate::validate($request, $to, $deal);
    }

    /** Save the column order after a drop (only deals the user can see, open, in that stage). */
    private function reposition(PipelineStage $stage, array $orderedIds): void
    {
        $ids = $this->visible()->open()->where('deals.stage_id', $stage->id)
            ->whereIn('deals.id', $orderedIds)->pluck('deals.id')->all();

        foreach (array_values(array_intersect(array_map('intval', $orderedIds), $ids)) as $position => $dealId) {
            // Query builder: position changes must not bump updated_at (used for the concurrency check)
            DB::table('deals')->where('id', $dealId)->update(['position' => $position]);
        }
    }

    /**
     * The pipeline shown on the board: ?pipeline= (remembered for the user), else the one the user last chose,
     * else the default. Switched-off and archived pipelines are never shown.
     */
    private function boardPipeline(Request $request): Pipeline
    {
        $user = auth()->user();
        if ($request->filled('pipeline') && ($chosen = Pipeline::usable()->find((int) $request->pipeline))) {
            if ((int) $user->deal_pipeline_id !== $chosen->id) {
                $user->forceFill(['deal_pipeline_id' => $chosen->id])->saveQuietly();
            }

            return $chosen;
        }

        return Pipeline::usableOrDefault($user->deal_pipeline_id ? (int) $user->deal_pipeline_id : null);
    }

    private function cardPayload(Deal $deal): array
    {
        $deal = $this->filtered(new Request())->find($deal->id);
        if (!$deal) {
            return ['html' => null, 'value' => 0];
        }

        return [
            'html'     => view('admin.deal._card', ['deal' => $deal, 'can' => $this->abilities()])->render(),
            'value'    => (float) $deal->value,
            'stage_id' => $deal->stage_id,
        ];
    }

    private function abilities(): array
    {
        return once(function () {
            $user = auth()->user();

            return [
                'move'     => $user->hasAccess('deal_board.move'),
                'won'      => $user->hasAccess('deal_board.won'),
                'lost'     => $user->hasAccess('deal_board.lost'),
                'transfer' => !$user->branchId() && $user->hasAccess('deal_board.transfer'),
                'settings' => $user->hasAccess('pipeline_settings.manage'),
                'sale'     => $user->hasAccess('add_sale.create'),
                'lead'     => $user->hasAccess('lead_list.view') || $user->hasAccess('lead_list.edit'),
                'create'   => $user->hasAccess('add_lead.create'),
                // SMS campaigns are head office / Super Admin only
                'sms'      => $user->hasAccess('compose_&_send.create') && ($user->isSuperAdmin() || !$user->branchId()),
                'appointment' => $user->hasAccess('add_appointment.create'),
            ];
        });
    }

    /** Active staff who can own deals, grouped by branch: ['hq' => [...], branchId => [...]]. */
    private function ownerOptions(): array
    {
        return once(function () {
            $users = User::where('status', 1)->orderBy('name')->get(['id', 'name', 'type', 'vendor_id']);
            $groups = ['hq' => []];
            foreach ($users as $u) {
                $key = $u->type === 'vendor' ? $u->id : ($u->vendor_id ?: 'hq');
                $groups[$key][] = ['id' => $u->id, 'name' => $u->name . ($u->type === 'vendor' ? ' (Branch Admin)' : '')];
            }

            return $groups;
        });
    }
}
