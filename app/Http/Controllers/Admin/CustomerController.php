<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Enums\ActivityType;
use App\Enums\SmsAutomationTrigger;
use App\Services\DealService;
use App\Services\SmsAutomationService;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Deal;
use App\Models\District;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\SaleMaster;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\ExcelImportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    /**
     * Configure middleware to handle authorization for different customer operations
     * 
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:add_customer.create')->only(['create', 'store']);
        $this->middleware('permission:customer_list.index')->only(['index', 'customerList', 'createCustomerLedger', 'customerLedger']);
        $this->middleware('permission:customer_list.edit')->only(['edit', 'update', 'payment', 'paymentStore', 'openDeal']);
        $this->middleware('permission:customer_list.index')->only(['show']);
        $this->middleware('permission:customer_list.import')->only(['importCustomers']);
        // Patient search feeds the sale, payment, appointment, proposal and return forms
        $this->middleware('permission:customer_list.index|add_sale.create|sale_list.index|customer_payment.create|add_appointment.create|appointment_list.index|proposals.create|sale_return.create|customer_lookup.index')
            ->only(['customerSearch']);
        $this->middleware('permission:customer_list.edit|customer_list.index|dashboard.index')->only(['recordAfterSalesAction']);

        // Delete: Super Admin only (data protection)
        $this->middleware(function ($request, $next) {
            abort_unless(auth()->user()->isSuperAdmin(), 403, 'Only Super Admin can delete customers.');

            return $next($request);
        })->only(['destroy']);
    }

    /**
     * Display a listing of the customer.
     *
     * @return View
     */
    public function index(Request $request): View
    {
        $data['start_date'] = date('Y-m-d', strtotime('2021-11-01'));
        $data['end_date'] = date('Y-m-d');

        if ((isset($request->start_date) && $request->start_date != "") && (isset($request->end_date) && $request->end_date != "")) {
            $data['start_date'] = date('Y-m-d', strtotime($request->start_date));
            $data['end_date'] = date('Y-m-d', strtotime($request->end_date));
        }

        // Build query with relationships, spend metrics and last purchase
        $query = Customer::with(['lead.district', 'lead.vendor', 'membershipTier'])
            ->withSum('saleMasters as total_spent', 'total_amount')
            ->withCount('saleMasters')
            ->withMax('saleMasters as last_purchase_date', 'date')
            ->whereDate('created_at', '>=', $data['start_date'])
            ->whereDate('created_at', '<=', $data['end_date']);

        // Apply filters on lead relationship
        if ($request->filled('name')) {
            $name = trim($request->name);
            $query->whereHas('lead', function($q) use ($name) {
                $q->where('full_name', 'LIKE', '%' . $name . '%');
            });
        }

        if ($request->filled('phone_number')) {
            $phone = trim($request->phone_number);
            $normalized = Helper::normalizePhone($phone);
            $query->whereHas('lead', function($q) use ($phone, $normalized) {
                $q->where('phone_number', 'LIKE', '%' . $phone . '%');
                if ($normalized) {
                    $q->orWhere('normalized_phone', 'LIKE', '%' . $normalized . '%');
                }
            });
        }

        if ($request->filled('lead_uid')) {
            $query->whereHas('lead', function($q) use ($request) {
                $q->where('lead_uid', $request->lead_uid);
            });
        }

        if ($request->filled('email')) {
            $query->whereHas('lead', function($q) use ($request) {
                $q->where('email', $request->email);
            });
        }

        // Branch filter (vendor_id)
        if ($request->filled('vendor_id')) {
            $vendorId = (int) $request->vendor_id;
            $query->whereHas('lead', function($q) use ($vendorId) {
                $q->where('vendor_id', $vendorId);
            });
        }

        // Purchase activity filter (bought in last X months)
        if ($request->filled('purchase_period')) {
            $period = $request->purchase_period;
            if ($period === 'never') {
                $query->whereDoesntHave('saleMasters');
            } elseif (in_array((int)$period, [1, 3, 6, 12])) {
                $query->whereHas('saleMasters', function($q) use ($period) {
                    $q->where('date', '>=', now()->subMonths((int)$period)->startOfDay());
                });
            }
        }

        // Card-driven filters (membership tier / frequent buyers)
        $frequentIds = SaleMaster::where('created_at', '>=', now()->subMonths(12))
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) >= 3')
            ->pluck('customer_id');

        if ($request->filled('membership_tier_id')) {
            $request->membership_tier_id === 'none'
                ? $query->whereNull('membership_tier_id')
                : $query->where('membership_tier_id', (int) $request->membership_tier_id);
        }
        if ($request->boolean('frequent')) {
            $query->whereIn('id', $frequentIds);
        }

        $data['customers'] = $query->orderBy('id', 'desc')->get();
        $data['branches'] = Helper::branchOptions();
        $data['purchase_period'] = $request->purchase_period ?? '';
        $data['vendor_id'] = $request->vendor_id ?? '';

        // Summary cards (whole customer base visible to this user, independent of date filter)
        $tierCounts = Customer::selectRaw('membership_tier_id, COUNT(*) as total')
            ->groupBy('membership_tier_id')
            ->pluck('total', 'membership_tier_id');
        $data['customerStats'] = [
            'total' => (int) $tierCounts->sum(),
            'new_month' => Customer::where('created_at', '>=', now()->startOfMonth())->count(),
            'frequent' => Customer::whereIn('id', $frequentIds)->count(),
            'no_tier' => (int) ($tierCounts[''] ?? 0),
            'tiers' => \App\Models\MembershipTier::orderBy('min_purchase')->get(['id', 'name', 'min_purchase'])
                ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'count' => (int) ($tierCounts[$t->id] ?? 0)]),
        ];

        return view('admin.customer.index', $data);
    }


    /**
     * Show the form for creating a new customer.
     *
     * @return View
     */
    public function create(): View
    {
        $data['districts'] = District::orderBy('name')->get();

        return view('admin.customer.create', $data);
    }

    /**
     * Store a newly created customer in storage.
     *
     * @param Request $request
     * @return RedirectResponse|JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'full_name'     => ['required', 'string', 'max:255', 'min:2'],
            'phone_number'  => ['required', 'string', 'max:20'],
            'age'           => ['nullable', 'integer', 'min:0', 'max:150'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'email'         => ['nullable', 'email', 'max:255'],
            'district_id'   => ['nullable', 'exists:districts,id'],
            'address'       => ['nullable', 'string', 'max:1000'],
            'gender'        => ['nullable', 'in:male,female,other'],
            'membership_id' => ['nullable', 'string', 'unique:customers,membership_id'],
        ]);

        $normalized = Helper::normalizePhone($request->phone_number);
        $existingLead = null;
        $existingCust = null;

        if ($normalized) {
            $existingLead = Lead::withoutGlobalScopes()
                ->where(function ($q) use ($normalized, $request) {
                    $q->where('normalized_phone', $normalized)
                      ->orWhere('phone_number', $request->phone_number);
                })
                ->latest('id')
                ->first();

            if ($existingLead) {
                $existingCust = Customer::withoutGlobalScopes()
                    ->where('lead_id', $existingLead->id)
                    ->first();
            }
        }

        // Case 1: Existing Customer already exists for this phone
        if ($existingCust) {
            $leadName = $existingLead ? $existingLead->full_name : 'Customer';
            $custUid = $existingCust->customer_uid ?? ('#' . $existingCust->id);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success'        => true,
                    'already_exists' => true,
                    'message'        => "Customer {$leadName} ({$custUid}) already exists and has been selected.",
                    'customer_id'    => $existingCust->id,
                    'customer_name'  => $leadName,
                    'customer_uid'   => $custUid,
                    'phone'          => $existingLead ? $existingLead->phone_number : $request->phone_number,
                ], 200);
            }

            return redirect()->route('customers.show', $existingCust->id)
                ->with('info', "A customer with phone {$request->phone_number} already exists ({$leadName}, {$custUid}).");
        }

        $maxAttempts = 5;
        $attempt = 0;
        $customer = null;
        $lead = null;

        while ($attempt < $maxAttempts && !$customer) {
            $attempt++;
            DB::beginTransaction();
            try {
                // If lead already exists without customer, convert that lead; otherwise create new lead
                if ($existingLead) {
                    $lead = $existingLead;
                    if (!$lead->normalized_phone && $normalized) {
                        $lead->normalized_phone = $normalized;
                    }
                    if (!$lead->vendor_id && auth()->user()->branchId()) {
                        $lead->vendor_id = auth()->user()->branchId();
                    }
                    $lead->save();
                } else {
                    $lead = new Lead();
                    $lead->full_name = $request->full_name;
                    $lead->date_of_birth = $request->date_of_birth ?: null;
                    $lead->age = $request->filled('age')
                        ? $request->age
                        : ($request->filled('date_of_birth') ? \Carbon\Carbon::parse($request->date_of_birth)->age : null);
                    $lead->gender = $request->gender;
                    $lead->phone_number = $request->phone_number;
                    $lead->normalized_phone = $normalized;
                    $lead->email = $request->email;
                    $lead->district_id = $request->district_id;
                    $lead->address = $request->address;
                    $lead->vendor_id = auth()->user()->branchId() ?: null;
                    $lead->created_by = auth()->id();
                    $lead->save();
                }

                // Strictly ensure a lead is never linked to two customers
                $alreadyCust = Customer::withoutGlobalScopes()->where('lead_id', $lead->id)->first();
                if ($alreadyCust) {
                    $customer = $alreadyCust;
                    DB::commit();
                    break;
                }

                $customer = new Customer();
                $customer->lead_id = $lead->id;
                $customer->customer_uid = Customer::generateCustomerUID();
                $customer->membership_id = $request->membership_id;
                $customer->referral_code = Helper::generateReferralCode($lead->full_name);
                $customer->created_by = auth()->id();
                $customer->save();

                $this->audit('customer_created', $customer, [], [
                    'customer_uid'  => $customer->customer_uid,
                    'name'          => $lead->full_name,
                    'phone'         => $lead->phone_number,
                    'membership_id' => $customer->membership_id,
                ]);

                DB::commit();
                break;
            } catch (\Illuminate\Database\QueryException $qe) {
                DB::rollBack();
                $customer = null;
                // If duplicate key (SQLSTATE 23000 / Error 1062), retry with fresh incremented UID
                if (($qe->getCode() == 23000 || (isset($qe->errorInfo[1]) && $qe->errorInfo[1] == 1062)) && $attempt < $maxAttempts) {
                    usleep(50000);
                    continue;
                }
                \Illuminate\Support\Facades\Log::error('Customer create database error: ' . $qe->getMessage(), [
                    'exception' => $qe,
                    'phone'     => $request->phone_number,
                ]);
                $userMsg = 'Customer could not be saved. Please try again or contact admin.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $userMsg], 500);
                }
                return redirect()->back()->with('warning', $userMsg)->withInput();
            } catch (\Exception $e) {
                DB::rollBack();
                $customer = null;
                \Illuminate\Support\Facades\Log::error('Customer create error: ' . $e->getMessage(), [
                    'exception' => $e,
                    'phone'     => $request->phone_number,
                ]);
                $userMsg = 'Customer could not be saved. Please try again or contact admin.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $userMsg], 500);
                }
                return redirect()->back()->with('warning', $userMsg)->withInput();
            }
        }

        if ($lead && $customer) {
            // "Customer Registered" SMS (text managed in SMS Center → Automation)
            app(SmsAutomationService::class)->fire(
                SmsAutomationTrigger::CUSTOMER_CREATED,
                $lead->phone_number,
                'customer',
                (int) $customer->id,
                ['name' => $lead->full_name, 'customer_name' => $lead->full_name],
                'customer-welcome:' . $customer->id
            );
        }

        // Check if request is AJAX
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success'       => true,
                'message'       => 'Customer Created Successfully',
                'customer_id'   => $customer->id,
                'customer_name' => $lead->full_name,
                'customer_uid'  => $customer->customer_uid,
                'phone'         => $lead->phone_number,
            ], 200);
        }

        return redirect('admin/customers')->with('success', 'Customer Created Successfully');
    }

    /**
     * Display the specified customer details with sales information.
     *
     * @param int $id
     * @return View
     */
    public function show(int $id): View
    {
        $customer = Customer::with([
            'lead.district',
            'lead.creator',
            'lead.vendor',
            'membershipTier',
            'referredBy.lead'
        ])->findOrFail($id);

        // Get all sale masters with nested product, brand, category, type and service
        $saleMasters = SaleMaster::with([
            'saleDetails.product.category',
            'saleDetails.product.brand',
            'saleDetails.product.type',
            'serviceDetails.serviceType.appointmentService',
            'serviceDetails.appointment.user',
            'serviceDetails.doneByUser'
        ])
        ->where('customer_id', $id)
        ->orderBy('date', 'desc')
        ->get();

        // 1. Financial & Hearing Summary Metrics
        $totalSpent = (float) $saleMasters->sum('total_amount');
        $totalPaid = (float) $saleMasters->sum('paid');
        $totalPurchases = $saleMasters->count();

        $ledgerDebit = (float) CustomerLedger::where('customer_id', $id)->sum('debit');
        $ledgerCredit = (float) CustomerLedger::where('customer_id', $id)->sum('credit');
        $dueBalance = $ledgerDebit - $ledgerCredit;
        if ($dueBalance == 0 && $saleMasters->count() > 0) {
            $dueBalance = (float) $saleMasters->sum(fn ($s) => max(0, $s->total_amount - $s->paid));
        }

        $rewardPoints = $customer->getAvailableRedeemPoints();
        $lastVisit = $saleMasters->first()?->date ?? null;

        // 2. Devices Owned & Warranties
        $devices = collect();
        foreach ($saleMasters as $sale) {
            foreach ($sale->saleDetails as $detail) {
                $product = $detail->product;
                if (!$product) continue;

                $warrantyMonths = (int) ($product->warranty_sales_months ?? 0);
                $purchaseDate = $sale->date ? \Carbon\Carbon::parse($sale->date) : null;
                $warrantyExpiry = ($purchaseDate && $warrantyMonths > 0)
                    ? $purchaseDate->copy()->addMonths($warrantyMonths)
                    : null;
                $isWarrantyActive = $warrantyExpiry ? $warrantyExpiry->isFuture() : false;
                $daysLeft = $warrantyExpiry && $isWarrantyActive ? (int) now()->diffInDays($warrantyExpiry, false) : 0;

                $serials = $detail->serial_no;
                if (is_string($serials)) {
                    $decoded = json_decode($serials, true);
                    $serials = is_array($decoded) ? $decoded : [$serials];
                }
                $serialList = is_array($serials) ? implode(', ', array_filter($serials)) : (string) $serials;

                $devices->push([
                    'invoice_no'         => $sale->sale_invoice_no,
                    'sale_id'            => $sale->id,
                    'product_name'       => ($product->category->category_name ?? '') . ' ' . ($product->model ?? ''),
                    'model'              => $product->model ?? 'N/A',
                    'brand'              => $product->brand->brand_name ?? 'N/A',
                    'category'           => $product->category->category_name ?? 'N/A',
                    'type'               => $product->type->type_name ?? 'N/A',
                    'battery_size'       => $product->battery_size,
                    'service_interval'   => $product->service_interval_months,
                    'serial_no'          => $serialList ?: 'N/A',
                    'qty'                => $detail->qty,
                    'purchase_date'      => $purchaseDate,
                    'warranty_months'    => $warrantyMonths,
                    'warranty_expiry'    => $warrantyExpiry,
                    'is_warranty_active' => $isWarrantyActive,
                    'days_left'          => $daysLeft,
                ]);
            }
        }

        // 3. Hearing History
        $hearingLoss = $customer->lead ? array_map(fn ($e) => [
            'ear'    => $e['ear'],
            'degree' => $e['degree'] ?? 'Not recorded',
            'type'   => $e['type'] ?? 'Not recorded',
        ], $customer->lead->hearingEars()) : [];

        // 4. Deals & Enquiries
        $deals = \App\Models\Deal::where('lead_id', $customer->lead_id)
            ->with(['stage', 'owner:id,name'])
            ->orderByDesc('id')
            ->get();
        $openDeal = $deals->firstWhere('status', 'open');

        // 5. Appointments for this customer & lead
        $appointments = \App\Models\Appointment::with(['service', 'user', 'customerAdvance'])
            ->where(function($q) use ($id, $customer) {
                $q->where('customer_id', $id);
                if ($customer->lead_id) {
                    $q->orWhere('lead_id', $customer->lead_id);
                }
            })
            ->orderBy('appointment_date', 'desc')
            ->get();

        // Advances & deposits history
        $advances = \App\Models\CustomerAdvance::with('appointment')
            ->where('customer_id', $id)
            ->orderByDesc('id')
            ->get();

        // 6. Build Communication Timeline
        $timelineItems = collect();

        $smsLogs = \App\Models\SmsLog::with('campaign')
            ->where('recipient_type', 'customer')
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

        if ($customer->lead_id) {
            $followUps = \App\Models\LeadFollowUp::with('assignedUser')
                ->where('lead_id', $customer->lead_id)
                ->orderBy('follow_up_date', 'desc')
                ->get();
            foreach ($followUps as $fu) {
                $timelineItems->push([
                    'type' => 'follow_up',
                    'date' => \Carbon\Carbon::parse($fu->follow_up_date),
                    'data' => $fu,
                ]);
            }
        }

        foreach ($appointments as $apt) {
            $timelineItems->push([
                'type' => 'appointment',
                'date' => \Carbon\Carbon::parse($apt->appointment_date),
                'data' => $apt,
            ]);
        }

        $timelineItems = $timelineItems->sortByDesc('date')->values()->toArray();

        $professionals = User::where('status', 1)->orderBy('name')->get();

        return view('admin.customer.details', compact(
            'customer',
            'saleMasters',
            'timelineItems',
            'totalSpent',
            'totalPaid',
            'totalPurchases',
            'dueBalance',
            'rewardPoints',
            'lastVisit',
            'devices',
            'hearingLoss',
            'deals',
            'openDeal',
            'appointments',
            'professionals',
            'advances'
        ));
    }

    /**
     * @param Request $request
     * @return View
     */
    public function createCustomerLedger(Request $request): View
    {
        $customers = Customer::with('lead:id,full_name,phone_number')->orderBy('id', 'desc')->get();
        $customerId = $request->query('customer_id');
        $customerName = '';
        $ledgers = null;
        $startDate = $request->query('start_date', date('Y-m-01'));
        $endDate = $request->query('end_date', date('Y-m-d'));

        if ($customerId) {
            $customer = Customer::with('lead')->find($customerId);
            if ($customer) {
                $customerName = $customer->lead->full_name ?? $customer->name;
                $ledgers = CustomerLedger::where('customer_id', $customerId)
                    ->whereBetween('date', [$startDate, $endDate])
                    ->orderBy('id', 'desc')
                    ->get();
            }
        }

        return view('admin.customer.ledger', compact('customers', 'customerId', 'customerName', 'ledgers', 'startDate', 'endDate'));
    }

    /**
     * @param Request $request
     * @return View
     */
    public function customerLedger(Request $request): View
    {
        $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $customerId = (int) $request->customer_id;
        $customers = Customer::with('lead:id,full_name,phone_number')->orderBy('id', 'desc')->get();
        $customer = Customer::with('lead')->find($customerId);
        $customerName = $customer ? ($customer->lead->full_name ?? '') : '';

        $ledgers = CustomerLedger::where('customer_id', $customerId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('id', 'desc')
            ->get();

        return view('admin.customer.ledger', compact('ledgers', 'startDate', 'endDate', 'customers', 'customerId', 'customerName'));
    }

    /**
     * Show the form for editing the specified customer.
     *
     * @param int $id
     * @return View
     */
    public function edit(int $id): View
    {
        $customer = Customer::with('lead')->findOrFail($id);
        $data['customer'] = $customer;
        $data['districts'] = District::orderBy('name')->get();

        return view('admin.customer.edit', $data);
    }

    /**
     * Update the specified customer in storage.
     *
     * @param Request $request
     * @param int $id
     * @return RedirectResponse
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'full_name'     => ['required', 'string', 'max:255', 'min:2'],
            'phone_number'  => ['required', 'string', 'max:20'],
            'age'           => ['nullable', 'integer', 'min:0', 'max:150'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'email'         => ['nullable', 'email', 'max:255'],
            'district_id'   => ['nullable', 'exists:districts,id'],
            'address'       => ['nullable', 'string', 'max:1000'],
            'gender'        => ['nullable', 'in:male,female,other'],
            'membership_id' => ['nullable', 'string', 'unique:customers,membership_id,' . $id],
        ]);

        $customer = Customer::with('lead')->findOrFail($id);

        $normalized = Helper::normalizePhone($request->phone_number);
        if ($normalized) {
            $existing = Lead::withoutGlobalScopes()
                ->where('normalized_phone', $normalized)
                ->where('id', '!=', $customer->lead_id)
                ->first();
            if ($existing) {
                return redirect()->back()->with('warning', "Phone number {$request->phone_number} is already used by {$existing->full_name} ({$existing->lead_uid}).")->withInput();
            }
        }

        DB::beginTransaction();
        try {
            $oldData = [
                'name'  => $customer->lead?->full_name,
                'phone' => $customer->lead?->phone_number,
                'membership_id' => $customer->membership_id,
            ];

            // Update Lead
            if ($customer->lead) {
                $customer->lead->full_name = $request->full_name;
                $customer->lead->email = $request->email;
                $customer->lead->district_id = $request->district_id;
                $customer->lead->address = $request->address;
                $customer->lead->date_of_birth = $request->date_of_birth ?: null;
                $customer->lead->age = $request->filled('age')
                    ? $request->age
                    : ($request->filled('date_of_birth') ? \Carbon\Carbon::parse($request->date_of_birth)->age : null);
                $customer->lead->gender = $request->gender;
                $customer->lead->phone_number = $request->phone_number;
                $customer->lead->normalized_phone = $normalized;
                $customer->lead->save();
            }

            // Update Customer
            $customer->membership_id = $request->membership_id;
            $customer->save();

            $this->audit('customer_updated', $customer, $oldData, [
                'name'  => $request->full_name,
                'phone' => $request->phone_number,
                'membership_id' => $request->membership_id,
            ]);

            DB::commit();
            return redirect('admin/customers')->with('success', 'Customer Updated Successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('warning', 'Not Updated: ' . \App\Classes\Helper::userMessage($e))->withInput();
        }
    }

    /**
     * @param Request $request
     * @return View
     */
    public function payment(Request $request): View
    {
        $transactionType = Helper::transactionType();
        $preselectedCustomer = null;
        if ($request->filled('customer_id')) {
            $preselectedCustomer = Customer::with('lead:id,full_name,phone_number')->find($request->customer_id);
        }

        return view('admin.customer.payment', compact('transactionType', 'preselectedCustomer'));
    }

    /**
     * 1-Click New Enquiry / Deal for a repeat customer.
     *
     * @param Request $request
     * @param Customer $customer
     * @return RedirectResponse|JsonResponse
     */
    public function openDeal(Request $request, Customer $customer)
    {
        $lead = $customer->lead;
        if (!$lead) {
            return redirect()->back()->with('warning', 'No linked lead found for this customer.');
        }

        $request->validate([
            'inquiry_for'   => ['nullable', 'array'],
            'inquiry_for.*' => ['string'],
            'value'         => ['nullable', 'numeric', 'min:0'],
            'notes'         => ['nullable', 'string', 'max:2000'],
            'activity_type' => ['nullable', 'string'],
            'due_at'        => ['nullable', 'date'],
        ]);

        $deals = app(DealService::class);
        $deal = $deals->openNewDeal($lead, [
            'inquiry_for' => $request->inquiry_for ?: $lead->inquiry_for,
            'value'       => $request->value,
            'notes'       => $request->notes,
        ]);

        if ($request->filled('activity_type') && $request->filled('due_at')) {
            $activityType = ActivityType::tryFrom($request->activity_type) ?? ActivityType::CALL;
            $deals->addActivity($deal, $activityType, $request->due_at, $request->notes, auth()->id());
        }

        $this->audit('customer_deal_opened', $customer, [], [
            'deal_id'     => $deal->id,
            'customer_uid'=> $customer->customer_uid,
            'name'        => $lead->full_name,
            'value'       => $deal->value,
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'New deal opened successfully on the Deals Board.',
                'deal_id' => $deal->id,
                'redirect_url' => url('admin/deals?q=' . urlencode($lead->phone_number)),
            ]);
        }

        return redirect('admin/deals?q=' . urlencode($lead->phone_number))
            ->with('success', "New enquiry/deal opened for {$lead->full_name} on the pipeline.");
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function paymentStore(Request $request): RedirectResponse
    {
        $request->validate([
            'customer_id'     => ['required', 'integer', 'exists:customers,id'],
            'type'            => ['required', 'string', Rule::in(Helper::transactionType())],
            'paid_amount'     => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'date'            => ['required', 'date'],
            'sale_invoice_no' => ['nullable', 'string', 'exists:sale_masters,sale_invoice_no'],
            'new_due_date'    => ['nullable', 'date'],
            'details'         => ['nullable', 'string', 'max:1000'],
        ]);

        $customer = Customer::with('lead')->findOrFail($request->customer_id);

        $refInvoiceNo = strtoupper(uniqid());
        $invoiceNo = date('ymdhis');

        DB::beginTransaction();
        try {
            if ($request->type == Helper::$payment) {
                $type = Helper::$payment;
                $request['grand_total'] = $request->paid_amount;
                Helper::customerLedgerDebit($request, $refInvoiceNo, $type);
                Helper::expenseLedger($request, $invoiceNo, $refInvoiceNo, $type);
            } else {
                $type = Helper::$receive;
                $request['paid'] = $request->paid_amount;

                // Settle against specific invoice if provided
                if ($request->filled('sale_invoice_no')) {
                    $sale = SaleMaster::where('sale_invoice_no', $request->sale_invoice_no)->first();
                    if ($sale) {
                        $refInvoiceNo = $sale->sale_invoice_no;
                        $sale->paid = round($sale->paid + (float) $request->paid_amount, 2);
                        $remaining = round($sale->total_amount - $sale->paid, 2);
                        if ($remaining <= 0.009) {
                            $sale->due_date = null;
                        } elseif ($request->filled('new_due_date')) {
                            $sale->due_date = $request->new_due_date;
                        }
                        $sale->save();
                    }
                }

                Helper::customerLedgerCredit($request, $refInvoiceNo, $type);
                Helper::incomeLedger($request, $invoiceNo, $refInvoiceNo, $type);
            }

            $this->audit('customer_payment', $customer, [], [
                'type'        => $type,
                'paid_amount' => (float) $request->paid_amount,
                'date'        => $request->date,
                'ref_invoice' => $refInvoiceNo,
                'details'     => $request->details,
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Payment recorded successfully' . ($request->filled('sale_invoice_no') ? " for invoice #{$request->sale_invoice_no}" : ''));
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('warning', 'An error occurred: ' . \App\Classes\Helper::userMessage($e))->withInput();
        }
    }

     /**
     * Search for customers based on a search term.
     *
     * Searches by name, phone, customer UID, or membership ID.
     * Requires min 2 chars, returns max 20 matches.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function customerSearch(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('search', ''));
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
        $normalizedPhone = Helper::normalizePhone($term);

        $data = Customer::with('lead:id,full_name,phone_number')
            ->where(function ($query) use ($escaped, $normalizedPhone) {
                $query->where('customer_uid', 'LIKE', "%{$escaped}%")
                    ->orWhere('membership_id', 'LIKE', "%{$escaped}%")
                    ->orWhereHas('lead', function ($q) use ($escaped, $normalizedPhone) {
                        $q->where('full_name', 'LIKE', "%{$escaped}%")
                            ->orWhere('phone_number', 'LIKE', "%{$escaped}%");
                        if ($normalizedPhone) {
                            $q->orWhere('normalized_phone', 'LIKE', "%{$normalizedPhone}%");
                        }
                    });
            })
            ->limit(20)
            ->get();

        $response = [];
        foreach ($data as $val) {
            $name = $val->lead->full_name ?? 'Unknown';
            $phone = $val->lead->phone_number ?? '';
            $uid = $val->customer_uid ?: ('#' . $val->id);
            $response[] = [
                'id'   => $val->id,
                'text' => $name . ($phone ? " ({$phone})" : '') . " [{$uid}]",
            ];
        }

        return response()->json($response);
    }

    /**
     * Remove the specified customer from storage.
     * Blocked if the customer has sales or ledger records. Super Admin only.
     *
     * @param int $id
     * @return RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403, 'Only Super Admin can delete customers.');

        $customer = Customer::withoutGlobalScopes()->with('lead')->findOrFail($id);

        $salesCount = $customer->saleMasters()->count();
        if ($salesCount > 0) {
            return redirect('admin/customers')->with('warning', "Cannot delete customer: they have {$salesCount} sales record(s).");
        }

        $ledgerCount = CustomerLedger::where('customer_id', $customer->id)->count();
        if ($ledgerCount > 0) {
            return redirect('admin/customers')->with('warning', "Cannot delete customer: they have existing ledger transactions.");
        }

        $this->audit('customer_deleted', $customer, [
            'customer_uid'  => $customer->customer_uid,
            'name'          => $customer->lead?->full_name,
            'phone'         => $customer->lead?->phone_number,
            'membership_id' => $customer->membership_id,
        ], []);

        $customer->delete();

        return redirect('admin/customers')->with('success', 'Customer deleted successfully.');
    }

    /**
     * Import customers from Excel/CSV file.
     *
     * @param Request $request
     * @param ExcelImportService $importService
     * @return RedirectResponse
     */
    public function importCustomers(Request $request, ExcelImportService $importService): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240'
        ]);

        // Parse the file
        $excelData = $importService->getData($request->file('file'));

        // Check if file is empty
        if (empty($excelData)) {
            return back()->withErrors(['file' => 'The uploaded file is empty.']);
        }

        $errors = [];
        $validRows = [];
        $rowNumber = 2; // Starting from row 2 (row 1 is header)

        foreach ($excelData as $row) {
            // Validate each row based on customer creation form fields
            $validator = Validator::make($row, [
                'full_name' => ['required', 'string', 'max:255', 'min:2'],
                'phone_number' => ['required', 'string', 'max:20'],
                'age' => ['nullable', 'integer', 'min:0', 'max:150'],
                'email' => ['nullable', 'email', 'max:255'],
                'address' => ['nullable', 'string', 'max:1000'],
                'gender' => ['nullable', 'in:male,female,other'],
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

        // Check for duplicate phone numbers in database (both raw and normalized)
        $normalizedMap = [];
        foreach ($phoneNumbers as $p) {
            $norm = Helper::normalizePhone($p);
            if ($norm) {
                $normalizedMap[$norm] = $p;
            }
        }

        $existingPhones = Lead::withoutGlobalScopes()
            ->where(function ($q) use ($phoneNumbers, $normalizedMap) {
                $q->whereIn('phone_number', $phoneNumbers);
                if (!empty($normalizedMap)) {
                    $q->orWhereIn('normalized_phone', array_keys($normalizedMap));
                }
            })
            ->pluck('phone_number')
            ->toArray();

        if (!empty($existingPhones)) {
            return back()->withErrors([
                'file' => 'Some phone numbers already exist in the database: ' . implode(', ', array_unique($existingPhones))
            ]);
        }

        // Import customers
        DB::beginTransaction();
        try {
            $importedCount = 0;
            $branchId = auth()->user()->branchId() ?: null;
            $userId = auth()->id();

            foreach ($validRows as $row) {
                $normPhone = Helper::normalizePhone($row['phone_number']);

                // Create Lead first with proper branch assignment
                $lead = new Lead();
                $lead->full_name = $row['full_name'];
                $lead->email = $row['email'] ?? null;
                $lead->district_id = $row['district_id'] ?? null;
                $lead->address = $row['address'] ?? null;
                $lead->age = !empty($row['age']) ? (int) $row['age'] : null;
                $lead->gender = $row['gender'] ?? null;
                $lead->phone_number = $row['phone_number'];
                $lead->normalized_phone = $normPhone;
                $lead->vendor_id = $branchId;
                $lead->created_by = $userId;
                $lead->save();

                // Create Customer
                $customer = new Customer();
                $customer->lead_id = $lead->id;
                $customer->referral_code = Helper::generateReferralCode($lead->full_name);
                $customer->created_by = $userId;
                $customer->save();

                $importedCount++;
            }

            $this->audit('customers_imported', null, [], [
                'count' => $importedCount,
            ]);

            DB::commit();
            return back()->with('success', "File imported successfully. Total Customers Imported: {$importedCount}");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['file' => 'Error importing customers: ' . \App\Classes\Helper::userMessage($e)])->withInput();
        }
    }

    /**
     * Record a quick after-sales care touch (service check, warranty alert, birthday greeting, upgrade)
     * and log it into the customer's / lead's communication timeline.
     */
    public function recordAfterSalesAction(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate([
            'type'    => ['required', 'string', 'in:service_check,warranty_check,birthday_wish,upgrade_pitch,battery_reminder'],
            'notes'   => ['nullable', 'string', 'max:500'],
            'channel' => ['nullable', 'string', 'in:call,whatsapp,sms,visit,other'],
        ]);

        $user = auth()->user();
        $channel = !empty($data['channel']) ? ucfirst($data['channel']) : 'Call';

        $typeLabels = [
            'service_check'    => '6-Month Service & Cleaning Check',
            'warranty_check'   => 'Warranty Expiry Follow-up',
            'birthday_wish'    => 'Birthday Greeting',
            'upgrade_pitch'    => 'Hearing Aid Upgrade Consultation',
            'battery_reminder' => 'Battery Refill & Expiry Reminder',
        ];
        $label = $typeLabels[$data['type']] ?? 'After-Sales Care';
        $summary = !empty($data['notes']) ? $data['notes'] : "Completed {$label} via {$channel}.";

        // If customer is tied to a lead, log as completed LeadFollowUp so it appears on timeline & updates service touch
        if ($customer->lead_id) {
            LeadFollowUp::create([
                'lead_id'         => $customer->lead_id,
                'activity_type'   => ($data['channel'] ?? '') === 'whatsapp' ? 'whatsapp' : (($data['channel'] ?? '') === 'sms' ? 'sms' : 'call'),
                'assigned_user'   => $user->id,
                'follow_up_date'  => now(),
                'follow_up_notes' => "[{$label}] " . $summary,
                'status'          => 'completed',
                'completed_at'    => now(),
                'outcome_notes'   => $summary,
                'created_by'      => $user->id,
            ]);
        }

        $this->audit('after_sales_touch', $customer, [], [
            'type'    => $data['type'],
            'label'   => $label,
            'channel' => $channel,
            'notes'   => $summary,
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$label} recorded to customer timeline.",
        ]);
    }

    /**
     * Record an audit event for customer actions
     */
    private function audit(string $action, ?Customer $customer, array $old = [], array $new = []): void
    {
        ActivityLog::create([
            'user_id'      => auth()->id(),
            'subject_id'   => $customer?->id ?? auth()->id(),
            'subject_type' => $customer ? Customer::class : \App\Models\User::class,
            'action'       => $action,
            'old_values'   => $old ?: null,
            'new_values'   => $new ?: null,
            'ip_address'   => request()->ip(),
            'user_agent'   => mb_substr((string) request()->userAgent(), 0, 255),
        ]);
    }
}
