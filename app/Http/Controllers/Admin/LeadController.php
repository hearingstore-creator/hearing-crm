<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
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
use App\Models\Customer;
use App\Models\District;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\LeadStatusHistory;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExcelImportService;
use App\Services\SmsService;
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
    protected SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
        $this->middleware('permission:lead_list.index')->only(['index']);
        $this->middleware('permission:add_lead.create')->only(['create', 'store']);
        $this->middleware('permission:lead_list.edit')->only(['edit', 'update']);
        $this->middleware('permission:lead_list.delete')->only(['destroy']);
        $this->middleware('permission:lead_list.import')->only(['importLeads']);
        $this->middleware('permission:lead_list.export')->only(['exportToXlsx']);
    }

    private function generateLeadUID(): string
    {
        $prefix = 'L-' . date('Ymd');
        $lastLead = Lead::withTrashed()
            ->where('lead_uid', 'LIKE', $prefix . '%')
            ->orderByDesc('lead_uid')
            ->value('lead_uid');

        if ($lastLead) {
            $lastNumber = (int) substr($lastLead, strlen($prefix));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Display a listing of all categories.
     *
     * @return View
     */
    public function index(Request $request): View
    {
        $options = [];
        $data['leadStatus'] = LeadStatus::cases();
        $data['sourceOfEntries'] = SourceOfEntry::cases();
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
        if (isset($request->lead_status)) {
            $options['lead_status'] = $request->lead_status;
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
        $data['relations'] = Relation::cases();
        $data['inquiries'] = InquiryType::cases();
        $data['followUpModes'] = FollowUpMode::cases();
        $data['genders'] = Gender::cases();
        $data['hearingDegrees'] = HearingDegree::cases();
        $data['hearingTypes'] = HearingType::cases();
        $data['leadStatus'] = LeadStatus::cases();
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
                'string',
                'max:20',
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
            'age' => ['required', 'integer', 'min:1', 'max:150'],
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
            'lead_status' => ['required',new Enum(LeadStatus::class)],
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
            'lead_type' => ['nullable', new Enum(LeadType::class)],

            // Notes
            'notes' => ['nullable', 'string', 'max:1000'],

            // Follow-up Details
            'follow_up_date' => ['required', 'date', 'after_or_equal:today'],
            'follow_up_mode' => ['required', 'array', 'min:1'],
            'follow_up_mode.*' => ['required', new Enum(FollowUpMode::class)],
            'follow_up_notes' => ['nullable', 'string', 'max:500'],
            'assigned_user' => ['nullable', 'exists:users,id'],
        ]);

      return  DB::transaction(function () use ($request) {
            $leads = $request->all();

            $followUpData = [
                'follow_up_date' => $leads['follow_up_date'],
                'follow_up_mode' => $leads['follow_up_mode'],
                'follow_up_notes' => $leads['follow_up_notes'] ?? null,
                'assigned_user' => $leads['assigned_user'] ?? null,
            ];

            // Remove follow up keys from $leads
            unset(
                $leads['follow_up_date'],
                $leads['follow_up_mode'],
                $leads['follow_up_notes'],
                $leads['assigned_user']
            );
            
            $leads['lead_uid'] = $this->generateLeadUID();

            if (auth()->user()->isVendor()) {
                $leads['vendor_id'] = auth()->id();
            } else {
                $leads['vendor_id'] = $request->vendor_id;
            }

            $leads = new Lead($leads);

            if ($leads->save()) {
                $leads->followUps()->create($followUpData);

                // Create initial status history
                LeadStatusHistory::create([
                    'lead_id' => $leads->id,
                    'from_status' => null,
                    'to_status' => $leads->lead_status,
                    'notes' => 'Lead created with initial status',
                ]);

                // Send welcome SMS
                $this->sendWelcomeSms($leads);

                return redirect('admin/leads')->with('success', 'Saved Successfully');
            }

            return redirect()->back()->with('warning', 'Not Saved');
        });
    }

    protected function sendWelcomeSms(Lead $lead): void
    {
        if (empty($lead->phone_number)) {
            return;
        }

        $companyPhone = Setting::find(1)?->phone ?? '';
        $message = 'Welcome to Hearing Store, ' . ($lead->full_name ?? '') . '! Thank you for reaching out. Our team will contact you shortly to discuss your hearing needs. Call ' . $companyPhone . ' for any queries.';

        $csmsId = 'LEAD-WELCOME-' . $lead->id . '-' . time();
        $this->smsService->sendAndLogSms($lead->phone_number, $message, 'lead', $lead->id, $csmsId);
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
        $data['relations'] = Relation::cases();
        $data['inquiries'] = InquiryType::cases();
        $data['followUpModes'] = FollowUpMode::cases();
        $data['genders'] = Gender::cases();
        $data['hearingDegrees'] = HearingDegree::cases();
        $data['hearingTypes'] = HearingType::cases();
        $data['leadStatus'] = LeadStatus::cases();
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
        $data['followUpModes'] = FollowUpMode::cases();
        $data['users'] = User::select('id', 'name')->get();

        // Lead Journey Pipeline: all statuses with icon, label, and state (reached / current / upcoming)
        $reachedStatuses = $data['lead']->statusHistories->pluck('to_status')->toArray();
        $currentStatus = $data['lead']->lead_status;

        $data['leadJourney'] = collect(LeadStatus::cases())->map(function ($status) use ($reachedStatuses, $currentStatus) {
            $isCurrent = $status->value === $currentStatus;
            $isReached = in_array($status->value, $reachedStatuses);

            return [
                'value'      => $status->value,
                'label'      => $status->label(),
                'icon'       => $status->icon(),
                'is_current' => $isCurrent,
                'is_reached' => $isReached || $isCurrent,
            ];
        });

        // Hearing loss formatted for view
        $hl = $data['lead']->hearing_loss ?? [];
        if (!is_array($hl)) $hl = json_decode($hl, true) ?? [];

        $data['hearingLoss'] = [];
        foreach (['right', 'left'] as $ear) {
            $d = $hl[$ear] ?? [];
            if (!empty($d['enabled'])) {
                $data['hearingLoss'][] = [
                    'ear'    => ucfirst($ear),
                    'degree' => HearingDegree::tryFrom($d['degree'] ?? '')?->label() ?? 'N/A',
                    'type'   => HearingType::tryFrom($d['type'] ?? '')?->label() ?? 'N/A',
                ];
            }
        }

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
                'string',
                'max:20',
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
            'age' => ['required', 'integer', 'min:1', 'max:150'],
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
            'lead_status' => ['required',new Enum(LeadStatus::class)],
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
            'lead_type' => ['nullable', new Enum(LeadType::class)],

            // Notes
            'notes' => ['nullable', 'string', 'max:1000']
        ]);
        
        $lead = Lead::findOrFail($id);
        $oldStatus = $lead->lead_status;
        $requestAll = $request->all();

        if (auth()->user()->isVendor()) {
            $requestAll['vendor_id'] = auth()->id();
        } else {
            $requestAll['vendor_id'] = $request->vendor_id;
        }

        $lead->fill($requestAll);

        if ($lead->save()) {
            // Track status change if status was changed
            if ($oldStatus !== $lead->lead_status) {
                LeadStatusHistory::create([
                    'lead_id' => $lead->id,
                    'from_status' => $oldStatus,
                    'to_status' => $lead->lead_status,
                    'notes' => 'Status updated from ' . ($oldStatus ?? 'none') . ' to ' . $lead->lead_status,
                ]);
            }

            if ($request->lead_status == LeadStatus::WON->value) {
              Customer::create([
                'lead_id' => $lead->id,
                'referral_code' => Helper::generateReferralCode($request->full_name),
              ]);
            }
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

        $followUp = LeadFollowUp::where('id', $id)->first();

        if (!$followUp) {
            return redirect()->back()->with('warning', 'Follow-up Not Found');
        }

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
                'string',
                'max:20',
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
            $row['lead_uid'] = $this->generateLeadUID();
            if (empty($row['lead_type'])) {
                $row['lead_type'] = LeadType::NEW_CUSTOMER->value;
            }
            if (empty($row['lead_status'])) {
                $row['lead_status'] = LeadStatus::NEW->value;
            }
            Lead::create($row);
        }

        return back()->with('success', 'File imported successfully. Total Leads Imported: ' . count($excelData));
    }

    public function exportToXlsx()
    {
       return Excel::download(new LeadExport, 'leads_' . date('Y-m-d_His') . '.xlsx');
    }

    public function leadSearch(Request $request): JsonResponse
    {
        $data = Lead::where('full_name', 'LIKE', '%' . $request->search . '%')->get();

        $response = [];

        foreach ($data as $val) {
            $response[] = [
                "id" => $val['id'],
                "text" => "$val->full_name ($val->lead_uid)" ?? null ,
            ];
        }

        return response()->json($response);
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

            // Delete all status history records for this lead
            LeadStatusHistory::where('lead_id', $lead->id)->delete();

            $lead->delete();

            return redirect('/admin/leads')->with('success', 'Deleted Successfully');
        });
    }
}
