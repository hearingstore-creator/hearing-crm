<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FollowUpMode;
use App\Enums\Gender;
use App\Enums\HearingDegree;
use App\Enums\HearingLoss;
use App\Enums\HearingType;
use App\Enums\InquiryType;
use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Enums\Relation;
use App\Enums\SourceOfEntry;
use App\Exports\LeadExport;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\District;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\LeadStatusHistory;
use App\Models\User;
use App\Services\DealService;
use App\Services\ExcelImportService;
use DB;
use Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class LeadController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:lead_list.index')->only(['index']);
        $this->middleware('permission:add_lead.create')->only(['create', 'store']);
        $this->middleware('permission:lead_list.edit')->only(['edit', 'update']);
        $this->middleware('permission:lead_list.import')->only(['importLeads']);
        // Export + delete: Super Admin only (customer data protection)
        $this->middleware(function ($request, $next) {
            abort_unless(auth()->user()->isSuperAdmin(), 403, 'Only Super Admin can do this.');

            return $next($request);
        })->only(['destroy', 'exportToXlsx']);
        $this->middleware('permission:lead_list.edit')->only(['storeFollowUp', 'updateFollowUp', 'completeFollowUp']);
        // Profile + search: anyone who works leads, from the list or the Deals board
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            abort_unless($user->hasAccess('lead_list.index') || $user->hasAccess('deal_board.index'), 403, 'Unauthorized');

            return $next($request);
        })->only(['show', 'leadSearch']);
    }

    /**
     * Display a listing of all categories.
     *
     * @return View
     */
    public function index(Request $request): View
    {
        $options = [];
        $data['stageMap'] = Lead::stageMap();
        $data['leadTemperatures'] = \App\Enums\LeadTemperature::cases();
        $data['sourceOfEntries'] = SourceOfEntry::cases();
        $data['marketingCampaigns'] = \App\Models\MarketingCampaign::whereIn('status', ['active', 'paused'])->orderBy('name')->get(['id', 'name', 'channel']);
        $data['vendors'] = User::where('type', 'vendor')->get();
        $data['start_date'] = date('Y-m-d', strtotime('2021-11-01'));
        $data['end_date'] = date('Y-m-d');

        if ((isset($request->start_date) && $request->start_date !="") && (isset($request->end_date) && $request->end_date !="")) {
            $data['start_date'] = date('Y-m-d',  strtotime($request->start_date));
            $data['end_date'] = date('Y-m-d', strtotime($request->end_date));
        }
        if (isset($request->phone_number)) {
            $options['phone_number'] = $request->phone_number;
        }
        // Stage filter also matches old statuses folded into that stage
        $stageKeys = $request->filled('lead_status')
            ? array_merge([$request->lead_status], array_keys(Lead::LEGACY_STAGE, $request->lead_status, true))
            : null;
        if (isset($request->lead_temperature) && \App\Enums\LeadTemperature::tryFrom($request->lead_temperature)) {
            $options['lead_temperature'] = $request->lead_temperature;
        }
        if (isset($request->lead_uid)) {
            $options['lead_uid'] = $request->lead_uid;
        }
        if (isset($request->email)) {
            $options['email'] = $request->email;
        }
        if (isset($request->source_of_entry)) {
            $options['source_of_entry'] = $request->source_of_entry;
        }
        if (isset($request->vendor_id)) {
            $options['vendor_id'] = $request->vendor_id;
        }

        $data['leads'] = Lead::with('vendor:id,name')
        ->where($options)
        ->when($stageKeys, fn ($q) => $q->whereIn('lead_status', $stageKeys))
        ->whereDate('created_at', '>=', $data['start_date'])
        ->whereDate('created_at', '<=', $data['end_date'])
        ->orderBy('id', 'desc')
        ->paginate(20);

        return view('admin.lead.index', $data);
    }

    /**
     * Show the form for creating a new leads.
     *
     * @return View
     */
    public function create(): View
    {
        $data['users'] = User::select('id', 'name')->get();
        $data['leadTypes'] = LeadType::cases();
        $data['sourceOfEntries'] = SourceOfEntry::cases();
        $data['marketingCampaigns'] = \App\Models\MarketingCampaign::whereIn('status', ['active', 'paused'])->orderBy('name')->get(['id', 'name', 'channel']);
        $data['relations'] = Relation::cases();
        $data['inquiries'] = InquiryType::cases();
        $data['genders'] = Gender::cases();
        $data['hearingDegrees'] = HearingDegree::cases();
        $data['hearingTypes'] = HearingType::cases();
        $data['stages'] = \App\Models\Pipeline::default()->activeStages()->get(['id', 'name', 'color', 'required_fields']);
        $data['activityTypes'] = [\App\Enums\ActivityType::CALL, \App\Enums\ActivityType::WHATSAPP, \App\Enums\ActivityType::VISIT, \App\Enums\ActivityType::TASK];
        $data['districts'] = District::all();
        $data['vendors'] = User::where('type', 'vendor')->whereStatus(true)->select('id', 'name')->get();

        return view('admin.lead.create', $data);
    }

    /**
     * Store a newly created leads in storage.
     *
     * @param Request $request HTTP request containing leads data
     * @return RedirectResponse Redirects to leads list or back with warning
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'full_name' => ['required', 'string', 'max:255', 'min:2'],
            'email' => [
                'nullable',
                'email',
                'max:191',
                Rule::unique('leads', 'email')
                    ->whereNull('deleted_at'),
            ],
            'phone_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('leads', 'phone_number')
                    ->whereNull('deleted_at'),
            ],
            'age' => ['nullable', 'integer', 'min:1', 'max:150'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', new Enum(Gender::class)],
            'district_id' => ['nullable', 'integer'],
            'vendor_id' => ['nullable', 'exists:users,id'],
            'address' => ['nullable', 'string', 'max:1000'],
            
            // Contact  
            'contact_person_name' => ['nullable', 'string', 'max:255'],
            'relation' => ['nullable', new Enum(Relation::class)],
            'contact_person_phone' => ['nullable', 'string', 'max:20'],
            
            // Lead Details
            'inquiry_for' => ['required', 'array', 'min:1'],
            'inquiry_for.*' => ['required', new Enum(InquiryType::class)],
            'stage_id' => ['required', Rule::in(\App\Models\Pipeline::default()->activeStages()->pluck('id')->all())],
            'lead_temperature' => ['nullable', new Enum(\App\Enums\LeadTemperature::class)],
            'interest_brand_product' => ['nullable', 'string', 'max:255'],
            'hearing_loss' => ['nullable', 'array'],
            'hearing_loss.right' => ['nullable', 'array'],
            'hearing_loss.right.enabled' => ['nullable', 'boolean'],
            'hearing_loss.right.degree' => ['nullable', new Enum(HearingDegree::class)],
            'hearing_loss.right.type' => ['nullable', new Enum(HearingType::class)],
            'hearing_loss.left' => ['nullable', 'array'],
            'hearing_loss.left.enabled' => ['nullable', 'boolean'],
            'hearing_loss.left.degree' => ['nullable', new Enum(HearingDegree::class)],
            'hearing_loss.left.type' => ['nullable', new Enum(HearingType::class)],
            'approx_budget' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'ref_by' => ['nullable', 'string', 'max:255'],
            'referred_by' => ['nullable', 'string', 'max:100'],
            'loss_to_whom' => ['nullable', 'string', 'max:255'],
            'loss_reason' => ['nullable', 'string', 'max:255'],
            // Source and Type
            'source_of_entry' => ['required', new Enum(SourceOfEntry::class)],
            'campaign_id' => ['nullable', 'integer', Rule::exists('marketing_campaigns', 'id')->whereNull('deleted_at')],
            'lead_type' => ['nullable', new Enum(LeadType::class)],

            // Notes
            'notes' => ['nullable', 'string', 'max:1000'],

            // First activity (same types as the Deals Board)
            'activity_type' => ['required', Rule::in(['call', 'whatsapp', 'visit', 'task'])],
            'due_at' => ['required', 'date'],
            'activity_note' => ['nullable', 'string', 'max:1000'],
            'assigned_user' => ['nullable', 'exists:users,id'],
        ], [
            'due_at.required' => 'Choose when the first activity should happen.',
            'source_of_entry.required' => 'How did this person hear about us? Choose the source.',
        ]);

        // The chosen stage's requirements, exactly as when a card is moved on the board
        $stage = \App\Models\PipelineStage::findOrFail($request->stage_id);
        if (!$request->filled('value') && $request->filled('approx_budget')) {
            $request->merge(['value' => $request->approx_budget]);
        }
        $gate = \App\Services\StageGate::validate($request, $stage);

        $referrer = null;
        if ($request->filled('referred_by')) {
            $referrer = app(\App\Services\MembershipService::class)->findReferrer($request->referred_by);
            if (!$referrer) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'referred_by' => 'No member found with that referral code or phone number.',
                ]);
            }
        }

        return DB::transaction(function () use ($request, $stage, $gate, $referrer) {
            $leads = $request->except([
                'activity_type', 'due_at', 'activity_note', 'assigned_user', 'stage_id', 'loss_reason', 'loss_to_whom',
                'note', 'walk_in', 'appointment_at', 'trial_product', 'trial_return_date', 'value', '_token', 'referred_by',
            ]);
            $leads['referrer_customer_id'] = $referrer?->id;
            $leads['lead_uid'] = Lead::generateUid();
            $leads['lead_status'] = LeadStatus::NEW->value; // mirrored from the deal below
            $leads['vendor_id'] = auth()->user()->branchId() ?: $request->vendor_id;
            if (!empty($leads['campaign_id'])) {
                $leads['utm_campaign'] = \App\Models\MarketingCampaign::find($leads['campaign_id'])?->code;
            }

            $lead = new Lead($leads);
            if (!$lead->save()) {
                return redirect()->back()->with('warning', 'Not Saved');
            }

            // Deal opens in the first stage, then moves like a board card so stage side-effects run
            // (appointment activity, trial return + check-in calls, stage history, value)
            $deals = app(DealService::class);
            $first = \App\Models\Pipeline::default()->activeStages()->first();
            $deal = $deals->openInitialDeal($lead, ['stage_id' => $first->id]);
            if ($stage->id !== $first->id) {
                $deals->move($deal, $stage, $gate, 'Created from lead form');
            } elseif (!empty($gate['note'])) {
                $deals->move($deal, $stage, $gate);
            }

            $deals->addActivity($deal->fresh(), \App\Enums\ActivityType::from($request->activity_type), $request->due_at,
                $request->activity_note, $request->filled('assigned_user') ? (int) $request->assigned_user : null);

            $this->sendWelcomeSms($lead);

            return redirect('admin/leads')->with('success', 'Saved Successfully');
        });
    }

    /** "Lead Created" automation from the SMS Center (inactive automation = no SMS). */
    protected function sendWelcomeSms(Lead $lead): void
    {
        app(\App\Services\SmsAutomationService::class)->fireAfterCommit(\App\Enums\SmsAutomationTrigger::LEAD_CREATED,
            $lead->phone_number, 'lead', $lead->id, ['name' => $lead->full_name ?? '', 'customer_name' => $lead->full_name ?? ''], 'lead-welcome:' . $lead->id);
    }



    /**
     * Show the form for editing the specified leads.
     *
     * @param int $id leads ID
     * @return View
     */
    public function edit(int $id): View
    {
        $data['lead'] = Lead::findOrFail($id);
        $data['users'] = User::select('id', 'name')->get();
        $data['leadTypes'] = LeadType::cases();
        $data['sourceOfEntries'] = SourceOfEntry::cases();
        $data['marketingCampaigns'] = \App\Models\MarketingCampaign::whereIn('status', ['active', 'paused'])->orderBy('name')->get(['id', 'name', 'channel']);
        $data['relations'] = Relation::cases();
        $data['inquiries'] = InquiryType::cases();
        $data['genders'] = Gender::cases();
        $data['hearingDegrees'] = HearingDegree::cases();
        $data['hearingTypes'] = HearingType::cases();
        $data['deal'] = \App\Models\Deal::with('stage:id,name,color')->where('lead_id', $id)->latest('updated_at')->first();
        $data['districts'] = District::all();
        $data['vendors'] = User::where('type', 'vendor')->whereStatus(true)->select('id', 'name')->get();

        return view('admin.lead.edit', $data);
    }

    public function show(int $id): View
    {
        $data['lead'] = Lead::with('creator:id,name', 'district:id,name', 'statusHistories.creator')->findOrFail($id);
        $data['followUps'] = $data['lead']->followUps()->with('assignedUser')
        ->orderBy('follow_up_date', 'desc')
        ->get();

        // Activities: same engine as the Deals board (open deal → pending activities + one-tap results)
        $data['openDeal'] = $data['lead']->deals()->open()->with('stage:id,name,color')->latest('id')->first();
        $data['pendingActivities'] = $data['followUps']->where('status', 'pending')
            ->filter(fn ($f) => $data['openDeal'] && (int) $f->deal_id === (int) $data['openDeal']->id)
            ->sortBy('follow_up_date')->values();
        $data['canWorkDeal'] = auth()->user()->hasAccess('deal_board.move');
        $data['users'] = User::select('id', 'name')->get();

        // Lead Journey: active stages, then Won or Lost (only the outcome reached is shown)
        $lead = $data['lead'];
        $current = $lead->stage_key;
        $map = Lead::stageMap();
        $keys = array_values(array_diff(array_keys($map), ['won', 'lost']));
        $keys[] = $current === 'lost' ? 'lost' : 'won';
        $pos = array_search($current, $keys, true);
        $reached = $lead->statusHistories->pluck('to_status')->map(fn ($s) => Lead::LEGACY_STAGE[$s] ?? $s)->all();

        $data['leadJourney'] = collect($keys)->values()->map(fn ($key, $i) => [
            'value'      => $key,
            'label'      => $map[$key]['label'],
            'icon'       => $map[$key]['icon'],
            'color'      => $map[$key]['color'],
            'is_current' => $key === $current,
            'is_reached' => ($pos !== false && $i <= $pos) || in_array($key, $reached, true),
        ]);

        // Hearing loss formatted for view (old and new stored formats)
        $data['hearingLoss'] = array_map(fn ($e) => [
            'ear'    => $e['ear'],
            'degree' => $e['degree'] ?? 'Not recorded',
            'type'   => $e['type'] ?? 'Not recorded',
        ], $data['lead']->hearingEars());

        // Build Communication Timeline
        $timelineItems = collect();

        // SMS logs for this lead
        $smsLogs = \App\Models\SmsLog::with('campaign')
            ->where('recipient_type', 'lead')
            ->where('recipient_id', $id)
            ->orderBy('sent_at', 'desc')
            ->get();
        foreach ($smsLogs as $log) {
            $timelineItems->push([
                'type' => 'sms',
                'date' => $log->sent_at ? \Carbon\Carbon::parse($log->sent_at) : $log->created_at,
                'data' => $log,
            ]);
        }

        // Follow-ups
        foreach ($data['followUps'] as $fu) {
            $timelineItems->push([
                'type' => 'follow_up',
                'date' => \Carbon\Carbon::parse($fu->follow_up_date),
                'data' => $fu,
            ]);
        }

        // Appointments
        $appointments = \App\Models\Appointment::with('service')
            ->where('lead_id', $id)
            ->orderBy('appointment_date', 'desc')
            ->get();
        foreach ($appointments as $apt) {
            $timelineItems->push([
                'type' => 'appointment',
                'date' => \Carbon\Carbon::parse($apt->appointment_date),
                'data' => $apt,
            ]);
        }

        // Proposals & Treatment Quotations
        $proposals = \App\Models\Proposal::with('items')
            ->where('lead_id', $id)
            ->orderBy('id', 'desc')
            ->get();
        foreach ($proposals as $prop) {
            $timelineItems->push([
                'type' => 'proposal',
                'date' => $prop->created_at,
                'data' => $prop,
            ]);
        }
        $data['proposals'] = $proposals;

        $data['timelineItems'] = $timelineItems->sortByDesc('date')->values()->toArray();

        return view('admin.lead.show', $data);
    }


    /**
     * Update the specified leads in storage.
     *
     * @param Request $request HTTP request containing updated leads data
     * @param int $id leads ID
     * @return RedirectResponse Redirects to leads list or back with warning
     */
    public function update(Request $request, int $id): RedirectResponse
    {
         $this->validate($request, [
            'full_name' => ['required', 'string', 'max:255', 'min:2'],
            'email' => [
                'nullable',
                'email',
                'max:191',
                Rule::unique('leads', 'email')
                    ->ignore($id) // for update only
                    ->whereNull('deleted_at'),
            ],
            'phone_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('leads', 'phone_number')
                    ->ignore($id) // for update only
                    ->whereNull('deleted_at'),
            ],
            'age' => ['nullable', 'integer', 'min:1', 'max:150'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', new Enum(Gender::class)],
            'district_id' => ['nullable', 'integer'],
            'vendor_id' => ['nullable', 'exists:users,id'],
            'address' => ['nullable', 'string', 'max:1000'],
            
            // Contact Person
            'contact_person_name' => ['nullable', 'string', 'max:255'],
            'relation' => ['nullable', new Enum(Relation::class)],
            'contact_person_phone' => ['nullable', 'string', 'max:20'],
            
            // Lead Details
            'inquiry_for' => ['required', 'array', 'min:1'],
            'inquiry_for.*' => ['required', new Enum(InquiryType::class)],
            'lead_temperature' => ['nullable', new Enum(\App\Enums\LeadTemperature::class)],
            'interest_brand_product' => ['nullable', 'string', 'max:255'],
            'hearing_loss' => ['nullable', 'array'],
            'hearing_loss.right' => ['nullable', 'array'],
            'hearing_loss.right.enabled' => ['nullable', 'boolean'],
            'hearing_loss.right.degree' => ['nullable', new Enum(HearingDegree::class)],
            'hearing_loss.right.type' => ['nullable', new Enum(HearingType::class)],
            'hearing_loss.left' => ['nullable', 'array'],
            'hearing_loss.left.enabled' => ['nullable', 'boolean'],
            'hearing_loss.left.degree' => ['nullable', new Enum(HearingDegree::class)],
            'hearing_loss.left.type' => ['nullable', new Enum(HearingType::class)],
            'approx_budget' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'ref_by' => ['nullable', 'string', 'max:255'],
            'loss_to_whom' => ['nullable', 'string', 'max:255'],
            'loss_reason' => ['nullable', 'string', 'max:255'],

            // Source and Type
            'source_of_entry' => ['nullable', new Enum(SourceOfEntry::class)],
            'campaign_id' => ['nullable', 'integer', Rule::exists('marketing_campaigns', 'id')->whereNull('deleted_at')],
            'lead_type' => ['nullable', new Enum(LeadType::class)],

            // Notes
            'notes' => ['nullable', 'string', 'max:1000']
        ]);
        
        $lead = Lead::findOrFail($id);
        $oldStatus = $lead->lead_status;
        $oldVendorId = $lead->vendor_id;
        $requestAll = $request->except(['lead_status', 'lead_uid', '_token', '_method']); // stage / status changes happen on the Deals Board

        // A new phone must not belong to another person (same rule as Quick Add / website form)
        if ($request->filled('phone_number')) {
            $normalized = \App\Classes\Helper::normalizePhone($request->phone_number);
            $taken = Lead::withoutGlobalScopes()->whereNull('deleted_at')->where('id', '!=', $lead->id)
                ->where('normalized_phone', $normalized)->value('lead_uid');
            if ($normalized && $taken) {
                return back()->withInput()->withErrors(['phone_number' => "This phone number already belongs to lead {$taken}. Use Merge People if it is the same person."]);
            }
        }

        if (auth()->user()->branchId()) {
            $requestAll['vendor_id'] = auth()->user()->branchId();
        } else {
            $requestAll['vendor_id'] = $request->vendor_id;
        }

        $lead->fill($requestAll);

        if ($lead->save()) {
            // Branch changes move the person's open deal (transfer history)
            app(DealService::class)->syncFromLeadForm($lead, $oldStatus, $oldVendorId);

            return redirect('admin/leads')->with('success', 'Updated Successfully');
        }

        return redirect()->back()->with('warning', 'Not Updated');
    }

    public function storeFollowUp(Request $request, int $id)
    {
        $this->validate($request, [
            'follow_up_date' => ['required', 'date', 'after_or_equal:today'],
            'follow_up_mode' => ['required', 'array', 'min:1'],
            'follow_up_mode.*' => ['required', new Enum(FollowUpMode::class)],
            'follow_up_notes' => ['nullable', 'string', 'max:500'],
            'assigned_user' => ['nullable', 'exists:users,id'],
        ]);

        $lead = Lead::findOrFail($id);

        $followUpData = [
            'follow_up_date' => $request->input('follow_up_date'),
            'follow_up_mode' => $request->input('follow_up_mode'),
            'follow_up_notes' => $request->input('follow_up_notes'),
            'assigned_user' => $request->input('assigned_user'),
        ];

        if ($lead->followUps()->create($followUpData)) {
            return redirect()->back()->with('success', 'Follow-up Added Successfully');
        }

        return redirect()->back()->with('warning', 'Follow-up Not Added');
    }

    public function updateFollowUp(Request $request, int $id)
    {
        $this->validate($request, [
            'follow_up_date' => ['required', 'date', 'after_or_equal:today'],
            'follow_up_mode' => ['required', 'array', 'min:1'],
            'follow_up_mode.*' => ['required', new Enum(FollowUpMode::class)],
            'follow_up_notes' => ['nullable', 'string', 'max:500'],
            'assigned_user' => ['nullable', 'exists:users,id'],
        ]);

        $followUp = $this->ownFollowUp($id);

        $followUpData = [
            'follow_up_date' => $request->input('follow_up_date'),
            'follow_up_mode' => $request->input('follow_up_mode'),
            'follow_up_notes' => $request->input('follow_up_notes'),
            'assigned_user' => $request->input('assigned_user'),
        ];

        if ($followUp->update($followUpData)) {
            return redirect()->back()->with('success', 'Follow-up Updated Successfully');
        }

        return redirect()->back()->with('warning', 'Follow-up Not Updated');
    }

    /**
     * Mark a follow-up as complete (AJAX from timeline)
     */
    public function completeFollowUp(Request $request, int $id)
    {
        $followUp = $this->ownFollowUp($id);

        $followUp->update([
            'status' => 'completed',
            'completed_at' => now(),
            'outcome_notes' => mb_substr((string) $request->input('outcome_notes', ''), 0, 1000),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Follow-up marked as complete']);
        }

        return redirect()->back()->with('success', 'Follow-up marked as complete');
    }

    public function importLeads(Request $request, ExcelImportService $importService)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240'
        ]);

        //️parse the file
        $excelData = $importService->getData($request->file('file'));

        // Check if file is empty
        if (empty($excelData)) {
            return back()->withErrors(['file' => 'The uploaded file is empty.']);
        }

        $validInquiryFor = array_column(InquiryType::cases(), 'value');
        $validHearingLoss = array_column(HearingLoss::cases(), 'value');

        $errors = [];
        $validRows = [];
        $rowNumber = 2; // Starting from row 2 (row 1 is header)

        foreach ($excelData as $row) {
            // Validate each row
            $validator = Validator::make($row, [
            'full_name' => ['required', 'string', 'max:255', 'min:2'],
            'email' => [
                'nullable',
                'email',
                'max:191',
                Rule::unique('leads', 'email')
                    ->whereNull('deleted_at'),
            ],
            'phone_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('leads', 'phone_number')
                    ->whereNull('deleted_at'),
            ],
            'age' => ['nullable', 'integer', 'min:1', 'max:150'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', new Enum(Gender::class)],
            'district_id' => ['nullable', 'integer'],
            'address' => ['nullable', 'string', 'max:1000'],

            // Contact Person
            'contact_person_name' => ['nullable', 'string', 'max:255'],
            'relation' => ['nullable', new Enum(Relation::class)],
            'contact_person_phone' => ['nullable', 'string', 'max:20'],
            
            // Lead Details
            'inquiry_for' => ['nullable', function ($attribute, $value, $fail) use ($validInquiryFor) {
                    if ($value) {
                        $items = array_map('trim', explode(',', $value));
                        foreach ($items as $item) {
                            if (!in_array(strtolower($item), $validInquiryFor)) {
                                $fail("Invalid inquiry type: {$item}. Must be one of: " . implode(', ', $validInquiryFor));
                                break;
                            }
                        }
                    }
                },
            ],            
            'lead_status' => ['nullable',new Enum(LeadStatus::class)],
            'interest_brand_product' => ['nullable', 'string', 'max:255'],
            'hearing_loss.*' => ['nullable', function ($attribute, $value, $fail) use ($validHearingLoss) {
                    if ($value) {
                        $items = array_map('trim', explode(',', $value));
                        foreach ($items as $item) {
                            if (!in_array(strtolower($item), $validHearingLoss)) {
                                $fail("Invalid hearing loss value: {$item}. Must be one of: " . implode(', ', $validHearingLoss));
                                break;
                            }
                        }
                    }
                },
            ],
            'approx_budget' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'ref_by' => ['nullable', 'string', 'max:255'],
            'loss_to_whom' => ['nullable', 'string', 'max:255'],
            'loss_reason' => ['nullable', 'string', 'max:255'],

            // Source and Type
            'source_of_entry' => ['nullable', new Enum(SourceOfEntry::class)],
            'lead_type' => ['nullable', new Enum(LeadType::class)],
            
            // Notes
            'notes' => ['required', 'string', 'max:1000']
        ]);

            if ($validator->fails()) {
                // Collect errors with row number
                foreach ($validator->errors()->all() as $error) {
                    $errors[] = "Row {$rowNumber}: {$error}";
                }
            } else {
                $validRows[] = $row;
            }

            $rowNumber++;
        }

        // If there are validation errors, return them
        if (!empty($errors)) {
            return back()->withErrors([
                'file' => 'Validation failed for some rows:',
                'rows' => $errors
            ])->withInput();
        }

        // Check for duplicate phone numbers in the file
        $phoneNumbers = array_column($validRows, 'phone_number');
        $duplicates = array_filter(array_count_values($phoneNumbers), fn($count) => $count > 1);
        
        if (!empty($duplicates)) {
            return back()->withErrors([
                'file' => 'Duplicate phone numbers found in the file: ' . implode(', ', array_keys($duplicates))
            ]);
        }

        // Check for duplicate phone numbers in database
        $existingPhones = Lead::whereIn('phone_number', $phoneNumbers)->pluck('phone_number')->toArray();
        if (!empty($existingPhones)) {
            return back()->withErrors([
                'file' => 'Some phone numbers already exist in the database: ' . implode(', ', $existingPhones)
            ]);
        }

        foreach ($excelData as $row) {
            $row['lead_uid'] = Lead::generateUid();
            if (empty($row['lead_type'])) {
                $row['lead_type'] = LeadType::NEW_CUSTOMER->value;
            }
            if (empty($row['lead_status'])) {
                $row['lead_status'] = LeadStatus::NEW->value;
            }
            app(DealService::class)->openInitialDeal(Lead::create($row));
        }

        return back()->with('success', 'File imported successfully. Total Leads Imported: ' . count($excelData));
    }

    public function exportToXlsx()
    {
        $this->audit('leads_exported', null, [], ['rows' => Lead::count()]);

        return Excel::download(new LeadExport, 'leads_' . date('Y-m-d_His') . '.xlsx');
    }

    /** Who did what with lead data (export, delete) for later review. */
    private function audit(string $action, ?Lead $lead, array $old = [], array $new = []): void
    {
        \App\Models\ActivityLog::create([
            'user_id'      => auth()->id(),
            'subject_id'   => $lead?->id ?? auth()->id(),
            'subject_type' => $lead ? Lead::class : User::class, // export: no single lead, so the exporting user
            'action'       => $action,
            'old_values'   => $old ?: null,
            'new_values'   => $new ?: null,
            'ip_address'   => request()->ip(),
            'user_agent'   => mb_substr((string) request()->userAgent(), 0, 255),
        ]);
    }

    public function leadSearch(Request $request): JsonResponse
    {
        $term = trim((string) $request->search);
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $data = Lead::select('id', 'full_name', 'lead_uid')
            ->where('full_name', 'LIKE', '%' . addcslashes($term, '%_\\') . '%')
            ->orderBy('full_name')->limit(20)->get();

        $response = [];

        foreach ($data as $val) {
            $response[] = [
                "id" => $val['id'],
                "text" => "$val->full_name ($val->lead_uid)",
            ];
        }

        return response()->json($response);
    }

    /** A follow-up on a lead this user can see (the lead's branch scope applies). */
    private function ownFollowUp(int $id): LeadFollowUp
    {
        $followUp = LeadFollowUp::findOrFail($id);
        Lead::findOrFail($followUp->lead_id); // 404 for another branch's lead

        return $followUp;
    }

    /**
     * Remove the specified leads from storage.
     *
     * @param int $id leads ID
     * @return RedirectResponse Redirects to leads list with success message
     */
    public function destroy(int $id): RedirectResponse
    {
        return DB::transaction(function () use ($id) {
            $lead = Lead::findOrFail($id);
            $this->audit('lead_deleted', $lead, $lead->only(['lead_uid', 'full_name', 'phone_number', 'vendor_id']));

            // Delete all status history records and deals for this lead
            LeadStatusHistory::where('lead_id', $lead->id)->delete();
            Deal::withoutGlobalScopes()->where('lead_id', $lead->id)->get()->each->delete();

            $lead->delete();

            return redirect('/admin/leads')->with('success', 'Deleted Successfully');
        });
    }
}
