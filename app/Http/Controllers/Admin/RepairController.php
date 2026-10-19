<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Classes\SMS;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\RepairAttachment;
use App\Models\RepairItem;
use App\Models\RepairJob;
use App\Models\RepairStatusLog;
use App\Models\RepairTask;
use App\Models\SaleDetail;
use App\Models\SaleMaster;
use App\Models\SerialChain;
use App\Models\ServiceType;
use App\Models\SmsLog;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Models\User;
use App\Services\WarrantyVerificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class RepairController extends Controller
{
    protected WarrantyVerificationService $warrantyService;

    /**
     * Permissions per action; the user needs any one of the listed permissions.
     * Actions not listed here are denied (403), so new actions must be added.
     */
    protected const ACTION_PERMISSIONS = [
        'index'                       => ['repair_jobs_board.index'],
        'show'                        => ['repair_jobs_board.view', 'repair_history.view'],
        'printJobCard'                => ['repair_jobs_board.view', 'repair_history.view'],
        'printSticker'                => ['repair_jobs_board.view', 'repair_history.view'],
        'printDeliveryNote'           => ['repair_jobs_board.view', 'repair_history.view'],
        'printInvoice'                => ['repair_jobs_board.view', 'repair_history.view'],
        'printReplacementCertificate' => ['repair_jobs_board.view', 'repair_history.view'],
        'printDispatchNote'           => ['repair_jobs_board.view', 'repair_history.view'],
        'printEstimate'               => ['repair_jobs_board.view', 'repair_history.view'],
        'printAdvanceReceipt'         => ['repair_jobs_board.view', 'repair_history.view'],
        'create'                      => ['new_repair_intake.index', 'warranty_&_serial_check.index'],
        'quickService'                => ['quick_walk-in_service.index'],
        'store'                       => ['new_repair_intake.create', 'quick_walk-in_service.create'],
        'verifyWarranty'              => ['new_repair_intake.index', 'quick_walk-in_service.index', 'warranty_&_serial_check.index'],
        'searchPurchases'             => ['new_repair_intake.index', 'quick_walk-in_service.index', 'warranty_&_serial_check.index'],
        'updateStatus'                => ['repair_jobs_board.edit'],
        'addItem'                     => ['repair_jobs_board.edit'],
        'deleteItem'                  => ['repair_jobs_board.edit'],
        'updateEstimate'              => ['repair_jobs_board.edit'],
        'patientDecision'             => ['repair_jobs_board.edit'],
        'dispatchToLab'               => ['repair_jobs_board.edit'],
        'logLabReport'                => ['repair_jobs_board.edit'],
        'addAttachment'               => ['repair_jobs_board.edit'],
        'deleteAttachment'            => ['repair_jobs_board.edit'],
        'completeTask'                => ['repair_jobs_board.edit'],
        'collectPayment'              => ['repair_jobs_board.payment'],
        'syncLabBill'                 => ['repair_jobs_board.payment'],
        'voidWarranty'                => ['repair_jobs_board.void_warranty'],
        'authorizeReplacement'        => ['repair_jobs_board.replacement'],
        'rmaIndex'                    => ['supplier_rma_&_replacements.index'],
        'updateRmaStatus'             => ['supplier_rma_&_replacements.edit'],
        'deleteRma'                   => ['supplier_rma_&_replacements.delete'],
        'clearSettledRma'             => ['supplier_rma_&_replacements.delete'],
        'supplierReconciliation'      => ['supplier_reconciliation.index'],
        'history'                     => ['repair_history.index'],
        'exportHistory'               => ['repair_history.view'],
    ];

    public function __construct(WarrantyVerificationService $warrantyService)
    {
        $this->warrantyService = $warrantyService;

        $this->middleware(function ($request, $next) {
            $user = $request->user();
            if (!$user) {
                return redirect()->route('login');
            }

            $action = $request->route()?->getActionMethod();
            $allowed = collect(self::ACTION_PERMISSIONS[$action] ?? [])
                ->contains(fn ($permission) => $user->hasAccess($permission));

            abort_unless($allowed, 403, 'Unauthorized');

            return $next($request);
        });
    }

    /**
     * Display Repair Status Board & Job List (Plan B with Plan A Legacy Switch)
     */
    public function index(Request $request): View
    {
        $mode = $request->get('mode', 'plan_b');
        $status = $request->get('status', 'all');
        $stage = $request->get('stage', 'all');
        $search = trim($request->get('search', ''));
        $vendorId = $request->get('vendor_id');
        $viewMode = $request->get('view', 'board'); // 'board' (6-stage kanban) or 'list'

        $baseKpiQuery = RepairJob::branchScope();

        // 1. Dual-Run: If user or staff requested Plan A Legacy View
        if ($mode === 'legacy') {
            $query = RepairJob::with(['customer.lead', 'product', 'vendor', 'supplier'])
                ->branchScope()
                ->latest('id');

            if ($status === 'in_clinic') {
                $query->whereIn('current_status', ['received', 'waiting_patient_approval', 'patient_approved', 'lab_received']);
            } elseif ($status === 'in_lab') {
                $query->whereIn('current_status', ['sent_to_lab', 'in_lab']);
            } elseif ($status === 'delayed_lab') {
                $query->whereIn('current_status', ['sent_to_lab', 'in_lab'])
                      ->where('sent_to_lab_at', '<=', Carbon::now()->subDays(7));
            } elseif ($status === 'ready') {
                $query->where('current_status', 'ready_for_pickup');
            } elseif ($status === 'delivered') {
                $query->where('current_status', 'delivered');
            } elseif ($status === 'quick') {
                $query->where('is_quick_service', true);
            } elseif ($status !== 'all' && !empty($status)) {
                $query->where('current_status', $status);
            }

            if ($vendorId) {
                $query->where('vendor_id', $vendorId);
            }

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('job_no', 'like', "%{$search}%")
                      ->orWhere('original_serial_no', 'like', "%{$search}%")
                      ->orWhere('replaced_serial_no', 'like', "%{$search}%")
                      ->orWhere('custom_model', 'like', "%{$search}%")
                      ->orWhereHas('customer.lead', function ($lq) use ($search) {
                          $lq->where('full_name', 'like', "%{$search}%")
                             ->orWhere('phone_number', 'like', "%{$search}%");
                      });
                });
            }

            $jobs = $query->paginate(20)->withQueryString();

            $counts = [
                'total'           => (clone $baseKpiQuery)->count(),
                'in_clinic'       => (clone $baseKpiQuery)->whereIn('current_status', ['received', 'waiting_patient_approval', 'patient_approved', 'lab_received'])->count(),
                'in_lab'          => (clone $baseKpiQuery)->whereIn('current_status', ['sent_to_lab', 'in_lab'])->count(),
                'delayed_lab'     => (clone $baseKpiQuery)->whereIn('current_status', ['sent_to_lab', 'in_lab'])->where('sent_to_lab_at', '<=', Carbon::now()->subDays(7))->count(),
                'ready'           => (clone $baseKpiQuery)->where('current_status', 'ready_for_pickup')->count(),
                'delivered_month' => (clone $baseKpiQuery)->where('current_status', 'delivered')->whereMonth('delivered_at', Carbon::now()->month)->count(),
                'ready_due'       => (clone $baseKpiQuery)->where('current_status', 'ready_for_pickup')->sum(DB::raw('total_customer_cost - total_customer_paid')),
            ];

            $branches = User::where('type', 'vendor')->orderBy('name')->get(['id', 'name']);

            return view('admin.repairs.legacy.index', compact('jobs', 'counts', 'status', 'search', 'branches'));
        }

        // 2. Default: Plan B 6-Stage Pipeline
        $query = RepairJob::with(['customer.lead', 'product', 'vendor', 'supplier', 'pendingTasks'])
            ->branchScope()
            ->latest('id');

        // Stage filter
        if ($stage === '1' || $stage === 'received') {
            $query->where('current_status', 'received');
        } elseif ($stage === '2' || $stage === 'at_lab') {
            $query->where(function ($q) {
                $q->whereIn('current_status', ['sent_to_lab', 'at_lab'])
                  ->orWhere(fn($sub) => $sub->where('current_status', 'in_lab')->whereNull('estimate_approved'));
            });
        } elseif ($stage === '3' || $stage === 'waiting_approval') {
            $query->where('current_status', 'waiting_patient_approval');
        } elseif ($stage === '4' || $stage === 'in_repair') {
            $query->where(function ($q) {
                $q->whereIn('current_status', ['patient_approved', 'in_repair'])
                  ->orWhere(fn($sub) => $sub->where('current_status', 'in_lab')->where('estimate_approved', true));
            });
        } elseif ($stage === '5' || $stage === 'ready') {
            $query->whereIn('current_status', ['lab_received', 'ready_for_pickup']);
        } elseif ($stage === '6' || $stage === 'delivered') {
            $query->where('current_status', 'delivered');
        } elseif ($stage === 'declined') {
            $query->where('current_status', 'patient_declined');
        } elseif ($stage === 'cancelled') {
            $query->where('current_status', 'cancelled');
        }

        if ($vendorId) {
            $query->where('vendor_id', $vendorId);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('job_no', 'like', "%{$search}%")
                  ->orWhere('original_serial_no', 'like', "%{$search}%")
                  ->orWhere('replaced_serial_no', 'like', "%{$search}%")
                  ->orWhere('custom_model', 'like', "%{$search}%")
                  ->orWhere('courier_tracking_no', 'like', "%{$search}%")
                  ->orWhere('lab_ref_no', 'like', "%{$search}%")
                  ->orWhereHas('customer.lead', function ($lq) use ($search) {
                      $lq->where('full_name', 'like', "%{$search}%")
                         ->orWhere('phone_number', 'like', "%{$search}%");
                  });
            });
        }

        // Pipeline stage counts
        $stageCounts = [
            'all'               => (clone $baseKpiQuery)->count(),
            'stage1_received'   => (clone $baseKpiQuery)->where('current_status', 'received')->count(),
            'stage2_at_lab'     => (clone $baseKpiQuery)->where(function ($q) {
                $q->whereIn('current_status', ['sent_to_lab', 'at_lab'])
                  ->orWhere(fn($sub) => $sub->where('current_status', 'in_lab')->whereNull('estimate_approved'));
            })->count(),
            'stage3_approval'   => (clone $baseKpiQuery)->where('current_status', 'waiting_patient_approval')->count(),
            'stage4_in_repair'  => (clone $baseKpiQuery)->where(function ($q) {
                $q->whereIn('current_status', ['patient_approved', 'in_repair'])
                  ->orWhere(fn($sub) => $sub->where('current_status', 'in_lab')->where('estimate_approved', true));
            })->count(),
            'stage5_ready'      => (clone $baseKpiQuery)->whereIn('current_status', ['lab_received', 'ready_for_pickup'])->count(),
            'stage6_delivered'  => (clone $baseKpiQuery)->where('current_status', 'delivered')->count(),
            'declined'          => (clone $baseKpiQuery)->where('current_status', 'patient_declined')->count(),
            'cancelled'         => (clone $baseKpiQuery)->where('current_status', 'cancelled')->count(),
        ];

        // Active Urgent Tasks Ribbon (Top 5 due tasks)
        $urgentTasks = RepairTask::with(['repairJob.customer.lead', 'repairJob.product'])
            ->where('is_completed', false)
            ->where('due_date', '<=', Carbon::now()->endOfDay())
            ->orderBy('due_date')
            ->take(5)
            ->get();
        $totalPendingTasks = RepairTask::where('is_completed', false)->count();

        $jobs = $query->paginate(25)->withQueryString();
        $branches = User::where('type', 'vendor')->orderBy('name')->get(['id', 'name']);

        // For Kanban board view, load active pipeline jobs grouped by stage
        // For Kanban board view, load active pipeline jobs grouped by stage (all 6 stages)
        $boardJobs = [];
        if ($viewMode === 'board') {
            $baseBoardQuery = RepairJob::with(['customer.lead', 'product', 'vendor', 'supplier', 'pendingTasks'])
                ->branchScope()
                ->when($vendorId, fn($q) => $q->where('vendor_id', $vendorId))
                ->when(!empty($search), function ($q) use ($search) {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('job_no', 'like', "%{$search}%")
                            ->orWhere('original_serial_no', 'like', "%{$search}%")
                            ->orWhere('replaced_serial_no', 'like', "%{$search}%")
                            ->orWhere('custom_model', 'like', "%{$search}%")
                            ->orWhere('courier_tracking_no', 'like', "%{$search}%")
                            ->orWhere('lab_ref_no', 'like', "%{$search}%")
                            ->orWhereHas('customer.lead', function ($lq) use ($search) {
                                $lq->where('full_name', 'like', "%{$search}%")
                                   ->orWhere('phone_number', 'like', "%{$search}%");
                            });
                    });
                });

            $activeJobs = (clone $baseBoardQuery)
                ->whereNotIn('current_status', ['delivered', 'cancelled', 'patient_declined'])
                ->latest('id')
                ->take(120)
                ->get();

            $deliveredJobs = (clone $baseBoardQuery)
                ->where('current_status', 'delivered')
                ->latest('delivered_at')
                ->latest('id')
                ->take(20)
                ->get();

            $boardJobs = [
                'received'         => $activeJobs->where('stage', 'received')->values(),
                'at_lab'           => $activeJobs->where('stage', 'at_lab')->values(),
                'waiting_approval' => $activeJobs->where('stage', 'waiting_approval')->values(),
                'in_repair'        => $activeJobs->where('stage', 'in_repair')->values(),
                'ready_for_pickup' => $activeJobs->where('stage', 'ready_for_pickup')->values(),
                'delivered'        => $deliveredJobs->values(),
            ];
        }

        return view('admin.repairs.index', compact(
            'jobs',
            'stageCounts',
            'stage',
            'search',
            'branches',
            'urgentTasks',
            'totalPendingTasks',
            'viewMode',
            'boardJobs'
        ));
    }

    /**
     * Show the 4-Step Intake Wizard
     */
    public function create(Request $request): View
    {
        $suppliers = Supplier::orderBy('supplier_name')->get();
        $prefillSerial = $request->get('serial');
        $prefillCustomer = $request->get('customer_id');

        return view('admin.repairs.create', compact('suppliers', 'prefillSerial', 'prefillCustomer'));
    }

    /**
     * Show Quick Walk-in Counter Service (Case 8)
     */
    public function quickService(Request $request): View
    {
        $suppliers = Supplier::orderBy('supplier_name')->get();
        $quickServices = ServiceType::where('status', 1)
            ->whereIn('booking_type', [ServiceType::BOOKING_WALK_IN, ServiceType::BOOKING_EITHER])
            ->orderByRaw("CASE WHEN booking_type = 'walk_in' THEN 0 ELSE 1 END")
            ->orderBy('id', 'asc')
            ->get();

        return view('admin.repairs.quick', compact('suppliers', 'quickServices'));
    }

    /**
     * Store new Repair Job from Intake Wizard or Quick Service
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'serial_no'        => 'required|string|max:100',
            'customer_id'      => 'nullable|required_without:third_party_customer_name|exists:customers,id',
            'job_type'         => 'required|string',
            'warranty_status'  => 'required|string',
        ]);

        $serialNo = trim($request->input('serial_no'));

        // Unified eligibility check (open repair job, replaced, decommissioned)
        $eligibility = $this->warrantyService->intakeEligibility($serialNo);
        if (!$eligibility['allow_repair']) {
            return redirect()->back()
                ->withInput()
                ->with('error', $eligibility['message']);
        }

        DB::beginTransaction();
        try {
            $user = Auth::user();
            $vendorId = method_exists($user, 'branchId') ? $user->branchId() : ($user->vendor_id ?? null);
            $customerId = $request->input('customer_id');

            // Handle third-party walk-in patient if customer not yet in DB
            if (!$customerId && $request->filled('third_party_customer_name')) {
                // Find or create lead/customer
                $lead = \App\Models\Lead::create([
                    'full_name'    => $request->input('third_party_customer_name'),
                    'phone_number' => $request->input('third_party_phone') ?? '01000000000',
                    'vendor_id'    => $vendorId,
                ]);
                $customer = Customer::create([
                    'lead_id'      => $lead->id,
                    'customer_uid' => Customer::generateCustomerUID(),
                ]);
                $customerId = $customer->id;
            }

            $jobNo = RepairJob::generateJobNo();
            $isQuick = $request->boolean('is_quick_service');
            $immediateDeliver = $isQuick && $request->boolean('quick_deliver_now');

            // Calculate dates & amounts
            $inspectionFee = (float)$request->input('inspection_fee', 0);
            $estimateAmount = (float)$request->input('estimate_amount', 0);
            $advancePaid = (float)$request->input('advance_paid', 0);
            $isChargeable = $request->boolean('is_chargeable') || $inspectionFee > 0 || $estimateAmount > 0;

            $totalCustomerCost = $isQuick ? (float)$request->input('quick_service_charge', 0) : ($estimateAmount + $inspectionFee);
            if ($isQuick) {
                $estimateAmount = $totalCustomerCost;
                $isChargeable = $totalCustomerCost > 0;
            }
            $totalCustomerPaid = $immediateDeliver ? $totalCustomerCost : $advancePaid;

            $initialStatus = 'received';
            if ($immediateDeliver) {
                $initialStatus = 'delivered';
            } elseif ($request->filled('supplier_id') && $request->boolean('send_to_lab_immediately')) {
                $initialStatus = 'sent_to_lab';
            }

            $warrantyStatus = $request->input('warranty_status', 'under_product_warranty');
            $pipelinePath = 'paid_repair';
            if ($isQuick) {
                $pipelinePath = 'in_house';
            } elseif (in_array($warrantyStatus, ['under_product_warranty', 'under_repair_warranty'])) {
                $pipelinePath = $request->filled('void_reason') ? 'paid_repair' : 'supplier_warranty';
            } else {
                $pipelinePath = 'paid_repair';
            }

            $job = RepairJob::create([
                'job_no'                => $jobNo,
                'vendor_id'             => $vendorId,
                'customer_id'           => $customerId,
                'sale_master_id'        => $request->input('sale_master_id'),
                'sale_detail_id'        => $request->input('sale_detail_id'),
                'product_id'            => $request->input('product_id'),
                'is_third_party'        => $request->boolean('is_third_party'),
                'custom_brand'          => $request->input('custom_brand'),
                'custom_model'          => $request->input('custom_model'),
                'original_serial_no'    => trim($request->input('serial_no')),
                'side'                  => $request->input('side', 'na'),
                'job_type'              => $request->input('job_type', 'repair'),
                'is_quick_service'      => $isQuick,
                'pipeline_path'         => $pipelinePath,
                'warranty_status'       => $warrantyStatus,
                'parent_repair_job_id'  => $request->input('parent_repair_job_id'),
                'void_reason'           => $request->input('void_reason'),
                'void_by'               => $request->filled('void_reason') ? $user->id : null,
                'reported_issues'       => $request->input('reported_issues'),
                'physical_condition'    => $request->input('physical_condition'),
                'accessories_included'  => $request->input('accessories_included', []),
                'current_status'        => $initialStatus,
                'supplier_id'           => $request->input('supplier_id'),
                'lab_ref_no'            => $request->input('lab_ref_no'),
                'sent_to_lab_at'        => $initialStatus === 'sent_to_lab' ? now() : null,
                'is_chargeable'         => $isChargeable,
                'inspection_fee'        => $inspectionFee,
                'estimate_amount'       => $estimateAmount,
                'estimate_approved'     => $isQuick ? true : ($request->has('estimate_approved') ? $request->boolean('estimate_approved') : null),
                'approved_at'           => ($isQuick || $request->boolean('estimate_approved')) ? now() : null,
                'total_customer_cost'   => $totalCustomerCost,
                'total_customer_paid'   => $totalCustomerPaid,
                'total_supplier_cost'   => (float)$request->input('supplier_cost', 0),
                'supplier_paid'         => 0,
                'repair_warranty_months'=> ($request->input('repair_warranty_months') === 'retain_original' || !is_numeric($request->input('repair_warranty_months'))) ? 0 : (int)$request->input('repair_warranty_months'),
                'repair_warranty_until' => ($request->input('repair_warranty_months') !== 'retain_original' && is_numeric($request->input('repair_warranty_months')) && (int)$request->input('repair_warranty_months') > 0)
                    ? Carbon::today()->addMonths((int)$request->input('repair_warranty_months'))
                    : null,
                'promised_date'         => $request->filled('promised_date') ? Carbon::parse($request->input('promised_date')) : Carbon::now()->addDays(5),
                'delivered_at'          => $immediateDeliver ? now() : null,
                'delivered_by'          => $immediateDeliver ? $user->id : null,
                'received_by'           => $user->id,
                'remarks'               => $request->input('remarks'),
            ]);

            // 1. Initial Status Log
            RepairStatusLog::create([
                'repair_job_id' => $job->id,
                'from_status'   => null,
                'to_status'     => $initialStatus,
                'remarks'       => $immediateDeliver ? 'Quick walk-in counter service completed and delivered.' : 'Device received at clinic counter.',
                'user_id'       => $user->id,
                'created_at'    => now(),
            ]);

            // 1.1 Automatic Dispatch Task (Plan B)
            if ($pipelinePath !== 'in_house' && $initialStatus !== 'delivered') {
                $now = Carbon::now();
                $isAfter5Pm = $now->hour >= 17;
                $dueDate = $isAfter5Pm ? Carbon::tomorrow()->setTime(12, 0) : Carbon::today()->setTime(18, 0);
                $taskTitle = $isAfter5Pm ? "Send to lab tomorrow (received after 5 PM)" : "Send to lab today";

                RepairTask::create([
                    'repair_job_id' => $job->id,
                    'task_type'     => 'send_to_lab',
                    'title'         => $taskTitle,
                    'due_date'      => $dueDate,
                    'is_completed'  => $initialStatus === 'sent_to_lab',
                    'completed_at'  => $initialStatus === 'sent_to_lab' ? now() : null,
                    'completed_by'  => $initialStatus === 'sent_to_lab' ? $user->id : null,
                ]);

                if ($initialStatus === 'sent_to_lab') {
                    RepairTask::create([
                        'repair_job_id' => $job->id,
                        'task_type'     => 'supplier_followup',
                        'title'         => "Follow up with supplier lab",
                        'due_date'      => Carbon::tomorrow()->setTime(11, 0),
                        'is_completed'  => false,
                    ]);
                }
            }

            // 2. Serial Chain record
            SerialChain::firstOrCreate(
                ['serial_no' => trim($request->input('serial_no'))],
                [
                    'product_id'          => $request->input('product_id'),
                    'customer_id'         => $customerId,
                    'original_sale_id'    => $request->input('sale_master_id'),
                    'status'              => 'active_with_customer',
                    'warranty_start_date' => $request->input('warranty_start_date'),
                    'warranty_end_date'   => $request->input('warranty_end_date'),
                    'is_void'             => $request->filled('void_reason'),
                    'void_reason'         => $request->input('void_reason'),
                ]
            );

            // 3. Customer Ledger Billing Synchronization (Establish debit entry for service fees)
            $this->recalculateJobFinancials($job);

            // Credit payment & General Ledger income if advance or quick payment collected
            if ($totalCustomerPaid > 0) {
                // Customer Ledger Credit (for payment collected)
                $credit = new CustomerLedger();
                $credit->customer_id = $customerId;
                $credit->date = date('Y-m-d');
                $credit->details = "Payment Received for Job #{$job->job_no}";
                $credit->debit = 0;
                $credit->credit = $totalCustomerPaid;
                $credit->type = 'repair_payment';
                $credit->ref_invoice_no = $job->job_no;
                $credit->save();

                // General Ledger Income
                $ledger = new Ledger();
                $ledger->vendor_id = $vendorId;
                $ledger->invoice_no = \App\Classes\Helper::freeLedgerInvoiceNo('REP-' . date('ymdHis') . rand(10, 99));
                $ledger->date = date('Y-m-d');
                $ledger->details = "Advance Income from Repair Job #{$job->job_no}";
                $ledger->expense_amount = 0;
                $ledger->income_amount = $totalCustomerPaid;
                $ledger->amount_type = Helper::$income;
                $ledger->type = 'income';
                $ledger->ref_invoice_no = $job->job_no;
                $ledger->save();
            }

            // Sync Supplier Ledger if supplier and cost/memo provided at intake
            if ($request->filled('supplier_id') && ($request->filled('supplier_cost') || $request->filled('lab_ref_no'))) {
                $initCost = (float)$request->input('supplier_cost', 0);
                $initMemo = $request->input('lab_ref_no');
                if ($initCost > 0 || !empty($initMemo)) {
                    $this->syncSupplierLedger($job, (int)$request->input('supplier_id'), $initCost, $initMemo, 'Repair Lab Bill');
                }
            }

            // 4. Send Intake SMS (Touchpoint 1)
            if ($request->boolean('send_intake_sms') && !$immediateDeliver) {
                $this->sendIntakeSms($job);
            }

            // Opened from a support ticket: link the ticket to the job and say so in its timeline
            if ($ticketId = (int) $request->input('ticket_id')) {
                $ticket = \App\Models\Ticket::withoutGlobalScopes()->find($ticketId);
                if ($ticket && !$ticket->repair_job_id) {
                    $ticket->update(['repair_job_id' => $job->id]);
                    $ticket->addNote('system', 'Repair job ' . $job->job_no . ' opened for this ticket');
                }
            }

            DB::commit();

            if ($request->boolean('print_after_save')) {
                return redirect()->route('repairs.print-job-card', $job->id);
            }

            return redirect()->route('repairs.show', $job->id)->with('success', "Service Job #{$job->job_no} created successfully.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('RepairJob store error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->withInput()->with('error', 'Error creating repair job: ' . \App\Classes\Helper::userMessage($e));
        }
    }

    /**
     * Show Repair Job Card & Lifecycle Control
     */
    public function show(int $id): View
    {
        $job = RepairJob::branchScope()->with([
            'customer.lead',
            'product',
            'vendor',
            'supplier',
            'receivedBy',
            'deliveredBy',
            'parentRepairJob',
            'childRepairJobs',
            'statusLogs.user',
            'items.product',
            'attachments.uploader',
        ])->findOrFail($id);

        $suppliers = Supplier::orderBy('supplier_name')->get();
        $products = Product::with('brand')->take(100)->get();

        // Support ticket this job was opened from (if any)
        $linkedTicket = \App\Models\Ticket::withoutGlobalScopes()->where('repair_job_id', $job->id)->first();
        $canSeeTicket = auth()->user()->hasAccess('ticket_list.index');

        return view('admin.repairs.show', compact('job', 'suppliers', 'products', 'linkedTicket', 'canSeeTicket'));
    }

    /**
     * Update Lifecycle Status (Ajax or POST)
     */
    public function updateStatus(Request $request, int $id)
    {
        $job = RepairJob::branchScope()->findOrFail($id);
        $user = Auth::user();
        $oldStatus = $job->current_status;
        $newStatus = $request->input('to_status');

        $request->validate([
            'to_status' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $job->current_status = $newStatus;

            // Handle specific status updates
            if ($newStatus === 'sent_to_lab') {
                $job->supplier_id = $request->input('supplier_id', $job->supplier_id);
                $job->lab_ref_no = $request->input('lab_ref_no', $job->lab_ref_no);
                $job->sent_to_lab_at = now();
            } elseif ($newStatus === 'lab_received' || $newStatus === 'ready_for_pickup') {
                $job->current_status = 'ready_for_pickup';
                $job->lab_received_at = now();
                if ($request->filled('lab_diagnosis')) {
                    $job->lab_diagnosis = $request->input('lab_diagnosis');
                }
                if ($request->filled('total_supplier_cost')) {
                    $job->total_supplier_cost = (float)$request->input('total_supplier_cost');
                    if ($job->total_supplier_cost > 0 && $job->supplier_id) {
                        $this->syncSupplierLedger($job, $job->supplier_id, $job->total_supplier_cost, $job->lab_ref_no);
                    }
                }
                // Touchpoint 2: Send Ready SMS
                if ($request->boolean('send_ready_sms', true)) {
                    $this->sendReadySms($job);
                }
            } elseif ($newStatus === 'delivered') {
                if ($oldStatus === 'delivered') {
                    // Already delivered! Idempotent no-op
                    DB::commit();
                    if ($request->ajax()) {
                        return response()->json(['success' => true, 'message' => "Job #{$job->job_no} is already delivered."]);
                    }
                    return redirect()->back()->with('info', "Job #{$job->job_no} is already marked as delivered.");
                }

                $job->delivered_at = now();
                $job->delivered_by = $user->id;

                // Grant service warranty if specified
                if ($request->filled('repair_warranty_months')) {
                    $months = (int)$request->input('repair_warranty_months');
                    $job->repair_warranty_months = $months;
                    $job->repair_warranty_until = $months > 0 ? Carbon::today()->addMonths($months) : null;
                }

                // Check if delivery settlement was already recorded (double-click / re-submit prevention)
                $existingDeliveryCredit = CustomerLedger::where('ref_invoice_no', $job->job_no)
                    ->where(function ($q) {
                        $q->where('details', 'like', '%Delivery Balance Settlement%')
                          ->orWhere('details', 'like', '%Delivery settlement%');
                    })
                    ->first();

                // Collect remaining due if paid at counter (strictly capped at remaining due)
                $remainingDue = max(0, (float)$job->customer_due);
                $collectAmount = min((float)$request->input('collected_amount', 0), $remainingDue);

                if ($collectAmount > 0 && !$existingDeliveryCredit) {
                    $job->total_customer_paid += $collectAmount;

                    // Customer Ledger Credit
                    $credit = new CustomerLedger();
                    $credit->customer_id = $job->customer_id;
                    $credit->date = date('Y-m-d');
                    $credit->details = "Delivery Balance Settlement for Job #{$job->job_no}";
                    $credit->debit = 0;
                    $credit->credit = $collectAmount;
                    $credit->type = 'repair_payment';
                    $credit->ref_invoice_no = $job->job_no;
                    $credit->save();

                    // General Ledger Income
                    $ledger = new Ledger();
                    $ledger->vendor_id = $job->vendor_id;
                    $ledger->invoice_no = \App\Classes\Helper::freeLedgerInvoiceNo('REP-' . date('ymdHis') . rand(10, 99));
                    $ledger->date = date('Y-m-d');
                    $ledger->details = "Delivery settlement for Job #{$job->job_no}";
                    $ledger->expense_amount = 0;
                    $ledger->income_amount = $collectAmount;
                    $ledger->amount_type = Helper::$income;
                    $ledger->type = 'income';
                    $ledger->ref_invoice_no = $job->job_no;
                    $ledger->save();
                }
            }

            $job->save();

            // Log status transition
            RepairStatusLog::create([
                'repair_job_id' => $job->id,
                'from_status'   => $oldStatus,
                'to_status'     => $newStatus,
                'remarks'       => $request->input('remarks', "Status updated to " . ucfirst(str_replace('_', ' ', $newStatus))),
                'user_id'       => $user->id,
                'created_at'    => now(),
            ]);

            DB::commit();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Job status updated to " . ucfirst(str_replace('_', ' ', $newStatus)),
                    'current_status' => $job->current_status,
                ]);
            }

            return redirect()->back()->with('success', "Job status updated successfully.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Repair status update error: ' . $e->getMessage());

            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => \App\Classes\Helper::userMessage($e)], 422);
            }

            return redirect()->back()->with('error', 'Status update failed: ' . \App\Classes\Helper::userMessage($e));
        }
    }

    /**
     * Warranty & Serial Verification API (for Counter Scanner / AJAX)
     */
    public function verifyWarranty(Request $request): JsonResponse
    {
        $serial = $request->get('serial') ?? $request->get('query');
        if (empty($serial)) {
            return response()->json(['found' => false, 'message' => 'Please enter a serial number.']);
        }

        $result = $this->warrantyService->checkBySerial($serial);
        return response()->json($result);
    }

    /**
     * Search sold items by invoice or customer phone/name
     */
    public function searchPurchases(Request $request): JsonResponse
    {
        $query = $request->get('query');
        if (empty($query)) {
            return response()->json([]);
        }

        $results = $this->warrantyService->searchPurchases($query);
        return response()->json($results);
    }

    /**
     * Printable A4/A5 Job Card
     */
    public function printJobCard(int $id): View
    {
        $job = RepairJob::branchScope()->with(['customer.lead', 'product', 'vendor', 'supplier', 'receivedBy'])->findOrFail($id);
        return view('admin.repairs.print_job_card', compact('job'));
    }

    /**
     * Printable 40x25mm Thermal Label Sticker
     */
    public function printSticker(int $id): View
    {
        $job = RepairJob::branchScope()->with(['customer.lead', 'product'])->findOrFail($id);
        return view('admin.repairs.print_sticker', compact('job'));
    }

    /**
     * Printable Delivery Note & Service Warranty Certificate
     */
    public function printDeliveryNote(int $id): View
    {
        $job = RepairJob::branchScope()->with(['customer.lead', 'product', 'vendor', 'deliveredBy'])->findOrFail($id);
        return view('admin.repairs.print_delivery_note', compact('job'));
    }

    /**
     * Printable Itemized Tax Invoice & Receipt
     */
    public function printInvoice(int $id): View
    {
        $job = RepairJob::branchScope()->with([
            'customer.lead',
            'product',
            'vendor',
            'supplier',
            'items.product',
            'customerLedgers',
        ])->findOrFail($id);

        return view('admin.repairs.print_invoice', compact('job'));
    }

    /**
     * Add Line Item (Spare Part, Service Charge, Lab Fee)
     */
    public function addItem(Request $request, int $id): RedirectResponse
    {
        $job = RepairJob::branchScope()->findOrFail($id);

        $request->validate([
            'item_type'           => 'required|string',
            'item_name'           => 'required|string|max:255',
            'qty'                 => 'required|numeric|min:0.01',
            'customer_unit_price' => 'nullable|numeric|min:0',
            'supplier_unit_cost'  => 'nullable|numeric|min:0',
        ]);

        $qty = (float)$request->input('qty', 1);
        $isWarranty = $request->boolean('is_warranty_covered');
        $customerPrice = $isWarranty ? 0.00 : (float)$request->input('customer_unit_price', 0);
        $customerTotal = $qty * $customerPrice;

        $supplierCost = (float)$request->input('supplier_unit_cost', 0);
        $supplierTotal = $qty * $supplierCost;

        DB::beginTransaction();
        try {
            RepairItem::create([
                'repair_job_id'       => $job->id,
                'item_type'           => $request->input('item_type', 'service_charge'),
                'product_id'          => $request->input('product_id'),
                'item_name'           => $request->input('item_name'),
                'qty'                 => $qty,
                'customer_unit_price' => $customerPrice,
                'customer_total'      => $customerTotal,
                'supplier_unit_cost'  => $supplierCost,
                'supplier_total'      => $supplierTotal,
                'is_warranty_covered' => $isWarranty,
            ]);

            $this->recalculateJobFinancials($job);

            DB::commit();
            return redirect()->back()->with('success', 'Line item added successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add repair item: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to add item: ' . \App\Classes\Helper::userMessage($e));
        }
    }

    /**
     * Delete Line Item
     */
    public function deleteItem(int $id, int $itemId): RedirectResponse
    {
        $job = RepairJob::branchScope()->findOrFail($id);
        $item = RepairItem::where('repair_job_id', $job->id)->findOrFail($itemId);

        DB::beginTransaction();
        try {
            $item->delete();
            $this->recalculateJobFinancials($job);
            DB::commit();
            return redirect()->back()->with('success', 'Line item removed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to remove repair item: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to remove item: ' . \App\Classes\Helper::userMessage($e));
        }
    }

    /**
     * Collect Due Payment from Patient (Intake or Handover)
     */
    public function collectPayment(Request $request, int $id): RedirectResponse
    {
        $job = RepairJob::branchScope()->findOrFail($id);

        $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string',
        ]);

        $amount = (float)$request->input('amount');
        $maxDue = max(0, (float)$job->customer_due);

        if ($maxDue <= 0) {
            return redirect()->back()->with('info', "Job #{$job->job_no} has already been fully paid.");
        }

        if ($amount > $maxDue) {
            $amount = $maxDue;
        }

        // Duplicate submit shield: check if an identical payment was recorded for this job within the last 10 seconds
        $recentDuplicate = CustomerLedger::where('ref_invoice_no', $job->job_no)
            ->where('type', 'repair_payment')
            ->where('credit', $amount)
            ->where('created_at', '>=', now()->subSeconds(10))
            ->first();

        if ($recentDuplicate) {
            return redirect()->back()->with('warning', "Payment of ৳" . number_format($amount, 2) . " was already recorded a moment ago (duplicate submission prevented).");
        }

        DB::beginTransaction();
        try {
            $job->total_customer_paid += $amount;
            $job->save();

            $method = $request->input('payment_method', 'Cash');

            // Customer Ledger Credit
            $credit = new CustomerLedger();
            $credit->customer_id = $job->customer_id;
            $credit->date = date('Y-m-d');
            $credit->details = "Payment Received ({$method}) for Service Job #{$job->job_no}";
            $credit->debit = 0;
            $credit->credit = $amount;
            $credit->type = 'repair_payment';
            $credit->ref_invoice_no = $job->job_no;
            $credit->save();

            // General Ledger Income
            $ledger = new Ledger();
            $ledger->vendor_id = $job->vendor_id;
            $ledger->invoice_no = \App\Classes\Helper::freeLedgerInvoiceNo('REP-' . date('ymdHis') . rand(10, 99));
            $ledger->date = date('Y-m-d');
            $ledger->details = "Repair & Service Payment ({$method}) for Job #{$job->job_no}";
            $ledger->expense_amount = 0;
            $ledger->income_amount = $amount;
            $ledger->amount_type = Helper::$income;
            $ledger->type = 'income';
            $ledger->ref_invoice_no = $job->job_no;
            $ledger->save();

            DB::commit();
            return redirect()->back()->with('success', "Payment of ৳" . number_format($amount, 2) . " recorded successfully.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment collection error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Payment recording failed: ' . \App\Classes\Helper::userMessage($e));
        }
    }

    /**
     * Synchronize Supplier Ledger entry for Repair / Replacement / Lab Bill
     */
    protected function syncSupplierLedger(
        RepairJob $job,
        ?int $supplierId = null,
        float $cost = 0.0,
        ?string $memoNo = null,
        string $type = 'Repair Lab Bill',
        ?string $details = null
    ): ?SupplierLedger {
        $supplierId = $supplierId ?: $job->supplier_id;
        if (!$supplierId) {
            return null;
        }

        $memoNo = $memoNo ?: $job->lab_ref_no;

        // Strictly idempotent: find all existing supplier ledger rows for this job
        $existingRows = SupplierLedger::where('ref_invoice_no', $job->job_no)
            ->where(function ($q) use ($type, $job) {
                $q->where('type', $type)
                  ->orWhere('type', 'like', '%Repair%')
                  ->orWhere('type', 'like', '%Replacement%')
                  ->orWhere('details', 'like', '%Job #' . $job->job_no . '%');
            })
            ->get();

        // If duplicate rows exist from past double clicks or multiple syncs, prune all except the first
        $existing = $existingRows->first();
        if ($existingRows->count() > 1) {
            $existingRows->slice(1)->each->delete();
        }

        if ($cost > 0 || !empty($memoNo) || str_contains($type, 'Replacement')) {
            if (!$existing) {
                $existing = new SupplierLedger();
                $existing->ref_invoice_no = $job->job_no;
            }
            $existing->supplier_id = $supplierId;
            $existing->date = date('Y-m-d');
            $existing->type = $type;
            $existing->supplier_memo_no = $memoNo;
            $existing->credit = $cost; // Accounts Payable
            $existing->debit = 0;
            $existing->details = $details ?: ($cost > 0
                ? "Supplier {$type} for Job #{$job->job_no} (S/N: {$job->original_serial_no})"
                : "Supplier {$type} for Job #{$job->job_no} (Memo #{$memoNo}, Free under Warranty)");
            $existing->save();
            return $existing;
        } elseif ($existing) {
            $existing->delete();
        }

        return null;
    }

    /**
     * Sync Supplier Lab Bill & Accounts Payable (supplier_ledgers)
     */
    public function syncLabBill(Request $request, int $id): RedirectResponse
    {
        $job = RepairJob::branchScope()->findOrFail($id);

        $request->validate([
            'supplier_id'   => 'required|exists:suppliers,id',
            'supplier_cost' => 'required|numeric|min:0',
            'lab_ref_no'    => 'nullable|string|max:100',
        ]);

        $supplierId = (int)$request->input('supplier_id');
        $cost = (float)$request->input('supplier_cost');
        $memoNo = $request->input('lab_ref_no');

        DB::beginTransaction();
        try {
            $job->supplier_id = $supplierId;
            $job->lab_ref_no = $memoNo;
            $job->total_supplier_cost = $cost;
            $job->save();

            $memoType = $cost > 0 ? 'Repair Lab Bill' : 'Warranty Repair (Zero Charge)';
            $this->syncSupplierLedger($job, $supplierId, $cost, $memoNo, $memoType);

            DB::commit();
            return redirect()->back()->with('success', "Supplier lab bill & payable updated successfully.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lab bill sync error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Lab bill sync failed: ' . \App\Classes\Helper::userMessage($e));
        }
    }

    /**
     * Recalculate Total Customer Bill from Items & Sync Ledger Debit
     */
    protected function recalculateJobFinancials(RepairJob $job): void
    {
        $items = $job->items()->get();
        if ($items->isNotEmpty()) {
            $itemsCustomerTotal = (float)$items->where('is_warranty_covered', false)->sum('customer_total');
            $job->total_customer_cost = $itemsCustomerTotal + (float)$job->inspection_fee;
        } else {
            $job->total_customer_cost = (float)$job->estimate_amount + (float)$job->inspection_fee;
        }

        $job->save();

        // Sync CustomerLedger debit entry (Strictly Idempotent)
        $debitRows = CustomerLedger::where('ref_invoice_no', $job->job_no)
            ->where('type', 'repair_service')
            ->get();

        $debit = $debitRows->first();
        if ($debitRows->count() > 1) {
            $debitRows->slice(1)->each->delete();
        }

        if ($job->total_customer_cost > 0) {
            if (!$debit) {
                $debit = new CustomerLedger();
                $debit->customer_id = $job->customer_id;
                $debit->date = $job->created_at->format('Y-m-d');
                $debit->type = 'repair_service';
                $debit->ref_invoice_no = $job->job_no;
            }
            $debit->details = "Service Job #{$job->job_no} Charge";
            $debit->debit = $job->total_customer_cost;
            $debit->credit = 0;
            $debit->save();
        } elseif ($debit) {
            $debit->delete();
        }
    }

    /**
     * Helper: Touchpoint 1 - Intake SMS
     */
    protected function sendIntakeSms(RepairJob $job): void
    {
        $phone = $job->customer?->lead?->phone_number;
        if (empty($phone)) {
            return;
        }

        $patientName = $job->customer?->lead?->full_name ?? 'Valued Customer';
        $branchName = $job->vendor?->name ?? 'Hearing Care Clinic';
        $promised = $job->promised_date ? $job->promised_date->format('d M') : 'soon';

        $text = "Dear {$patientName}, your hearing aid (S/N: {$job->original_serial_no}) is received at {$branchName}. Job Card: #{$job->job_no}. Est. completion: {$promised}. Thank you.";

        $this->dispatchSms($phone, $text, $job->job_no, 'intake');
    }

    /**
     * Helper: Touchpoint 2 - Ready for Pickup SMS
     */
    protected function sendReadySms(RepairJob $job): void
    {
        $phone = $job->customer?->lead?->phone_number;
        if (empty($phone)) {
            return;
        }

        $patientName = $job->customer?->lead?->full_name ?? 'Valued Customer';
        $branchName = $job->vendor?->name ?? 'Hearing Care Clinic';
        $due = number_format($job->customer_due, 2);

        $text = "Dear {$patientName}, your device is ready for pickup at {$branchName}. Job: #{$job->job_no}. Due: Tk {$due}. Please bring your Job Card. Ph: {$job->vendor?->phone_number}";

        $this->dispatchSms($phone, $text, $job->job_no, 'ready_for_pickup');
    }

    protected function dispatchSms(string $phone, string $message, string $refNo, string $event): void
    {
        try {
            $sms = app(SMS::class);
            $csmsId = 'REP-' . substr(md5($refNo . time()), 0, 12);
            $sms->singleSms($phone, $message, $csmsId);

            SmsLog::create([
                'phone_number' => $phone,
                'message'      => $message,
                'type'         => 'repair_notice',
                'status'       => 'SENT',
                'sent_at'      => now(),
                'campaign_name'=> "Repair Notice ({$event}) #{$refNo}",
            ]);
        } catch (\Exception $e) {
            Log::warning("Repair SMS dispatch failed for {$phone}: " . $e->getMessage());
        }
    }

    /**
     * Authorize Warranty or Paid Replacement & Track Serial Lineage (Phase 3)
     */
    public function authorizeReplacement(Request $request, int $id): RedirectResponse
    {
        $job = RepairJob::branchScope()->with(['customer.lead', 'product', 'saleDetail', 'saleMaster'])->findOrFail($id);

        $request->validate([
            'replacement_serial_no'  => 'required|string|max:100',
            'replacement_product_id' => 'nullable|exists:products,id',
            'supplier_id'            => 'nullable|exists:suppliers,id',
            'supplier_memo_no'       => 'nullable|string|max:100',
            'supplier_cost'          => 'nullable|numeric|min:0',
            'charge_amount'          => 'nullable|numeric|min:0',
            'replacement_reason'     => 'nullable|string|max:500',
            'deduct_stock'           => 'nullable|boolean',
            'transfer_old_to_rma'    => 'nullable|boolean',
        ]);

        $newSerial = trim($request->input('replacement_serial_no'));
        $oldSerial = trim($job->original_serial_no);

        if ($newSerial === $oldSerial) {
            return redirect()->back()->with('error', 'Replacement serial number cannot be the same as the original serial number.');
        }

        // Check if new serial is actively assigned elsewhere
        $existingActive = SerialChain::where('serial_no', $newSerial)
            ->where('status', 'active_with_customer')
            ->first();
        if ($existingActive) {
            return redirect()->back()->with('error', "Serial number '{$newSerial}' is already actively assigned to another customer/device.");
        }

        $oldStatus = $job->current_status;
        $supplierId = $request->filled('supplier_id') ? (int)$request->input('supplier_id') : $job->supplier_id;
        $supplierMemoNo = trim($request->input('supplier_memo_no', ''));
        $supplierCost = (float)$request->input('supplier_cost', 0);
        $chargeAmount = (float)$request->input('charge_amount', 0);
        $setReady = $request->boolean('set_ready_status', true);
        $sendSms = $request->boolean('send_ready_sms', true);

        DB::beginTransaction();
        try {
            // 1. Locate or create SerialChain for the original unit (permanently retained by supplier)
            $originalChain = SerialChain::where('serial_no', $oldSerial)->first();
            if (!$originalChain) {
                $warrantyMonths = 24;
                if ($job->saleDetail && $job->saleDetail->warranty) {
                    $warrantyMonths = (int)$job->saleDetail->warranty;
                }
                $startDate = $job->saleMaster?->date ?? $job->created_at->format('Y-m-d');
                $endDate = Carbon::parse($startDate)->addMonths($warrantyMonths)->format('Y-m-d');

                $originalChain = SerialChain::create([
                    'serial_no'           => $oldSerial,
                    'product_id'          => $job->product_id,
                    'customer_id'         => $job->customer_id,
                    'original_sale_id'    => $job->sale_master_id,
                    'status'              => 'retained_by_supplier',
                    'warranty_start_date' => $startDate,
                    'warranty_end_date'   => $endDate,
                    'is_void'             => ($job->warranty_status === 'void'),
                    'void_reason'         => $job->void_reason,
                ]);
            } else {
                $originalChain->status = 'retained_by_supplier';
                $originalChain->save();
            }

            // 2. Policy: Option A - Inherits remaining original product warranty
            if ($chargeAmount > 0 || $job->warranty_status === 'out_of_warranty' || $job->warranty_status === 'void') {
                $inheritedEndDate = Carbon::now()->addMonths(12)->format('Y-m-d');
            } else {
                $inheritedEndDate = $originalChain->warranty_end_date ?: Carbon::now()->addMonths(12)->format('Y-m-d');
            }

            // 3. Create new SerialChain record
            $replacementProductId = $request->input('replacement_product_id') ?: $job->product_id;
            $newChain = SerialChain::create([
                'serial_no'           => $newSerial,
                'product_id'          => $replacementProductId,
                'customer_id'         => $job->customer_id,
                'original_sale_id'    => $job->sale_master_id,
                'parent_serial_no'    => $oldSerial,
                'replacement_job_id'  => $job->id,
                'status'              => 'active_with_customer',
                'warranty_start_date' => $originalChain->warranty_start_date ?? date('Y-m-d'),
                'warranty_end_date'   => $inheritedEndDate,
                'is_void'             => false,
            ]);

            // 4. Update the Repair Job & Advance Lifecycle Status
            $job->replaced_serial_no = $newSerial;
            $job->job_type = 'replacement';
            if ($supplierId) {
                $job->supplier_id = $supplierId;
            }
            if (!empty($supplierMemoNo)) {
                $job->lab_ref_no = $supplierMemoNo;
            }
            if ($supplierCost > 0 || $job->total_supplier_cost == 0) {
                $job->total_supplier_cost = $supplierCost;
            }
            $job->lab_received_at = now();

            if ($setReady) {
                $job->current_status = 'ready_for_pickup';
            }

            $reason = $request->input('replacement_reason', 'Warranty replacement issued by Supplier Lab');
            $memoText = !empty($supplierMemoNo) ? " [Supplier Challan: {$supplierMemoNo}]" : "";
            $job->remarks = trim(($job->remarks ? $job->remarks . "\n" : '') . "[Replacement Received from Lab]: S/N {$oldSerial} -> {$newSerial}.{$memoText} Reason: {$reason}");
            $job->save();

            // 5. Deduct from clinic inventory only if explicitly selected (for rare local shelf swaps)
            if ($request->boolean('deduct_stock') && $replacementProductId) {
                Helper::stockOut(
                    (int)$replacementProductId,
                    date('Y-m-d'),
                    1,
                    "Replacement for Job #{$job->job_no} (Old S/N: {$oldSerial} -> New S/N: {$newSerial})",
                    $job->job_no,
                    'replacement'
                );
            }

            // 6. Record line item
            RepairItem::create([
                'repair_job_id'       => $job->id,
                'item_type'           => 'part',
                'product_id'          => $replacementProductId,
                'item_name'           => "Replacement Unit (S/N: {$newSerial})" . (!empty($supplierMemoNo) ? " [Challan: {$supplierMemoNo}]" : ""),
                'qty'                 => 1,
                'customer_unit_price' => $chargeAmount,
                'customer_total'      => $chargeAmount,
                'supplier_unit_cost'  => $supplierCost,
                'supplier_total'      => $supplierCost,
                'is_warranty_covered' => ($chargeAmount == 0),
            ]);

            $this->recalculateJobFinancials($job);

            // 7. Hit the Supplier Account / Ledger (Charged or Free with Memo documentation)
            if ($supplierId && (!empty($supplierMemoNo) || $supplierCost > 0)) {
                $memoType = $supplierCost > 0 ? 'Repair Replacement Memo' : 'Warranty Replacement (Zero Charge)';
                $details = $supplierCost > 0
                    ? "Supplier Replacement Challan #{$supplierMemoNo} for Job #{$job->job_no} (Old S/N: {$oldSerial} -> New S/N: {$newSerial})"
                    : "Warranty Replacement Challan #{$supplierMemoNo} for Job #{$job->job_no} (Free under warranty. Old S/N: {$oldSerial} -> New S/N: {$newSerial})";

                $this->syncSupplierLedger($job, $supplierId, $supplierCost, $supplierMemoNo, $memoType, $details);
            }

            // 8. Log lifecycle audit
            RepairStatusLog::create([
                'repair_job_id' => $job->id,
                'from_status'   => $oldStatus,
                'to_status'     => $job->current_status,
                'remarks'       => "Replacement unit received from Supplier Lab. S/N: {$newSerial} (inherited warranty until " . Carbon::parse($inheritedEndDate)->format('d M Y') . "). Supplier Challan: " . ($supplierMemoNo ?: 'N/A') . ". Defective unit retained by supplier. Status set to '" . ucwords(str_replace('_', ' ', $job->current_status)) . "'.",
                'user_id'       => Auth::id() ?? 1,
            ]);

            // 9. Send Ready for Pickup SMS if enabled and job is ready
            if ($sendSms && $job->current_status === 'ready_for_pickup') {
                $this->sendReadySms($job);
            }

            DB::commit();
            return redirect()->back()->with('success', "Replacement successfully processed! New S/N {$newSerial} received under Supplier Challan #{$supplierMemoNo}. Status is now 'Ready for Pickup'.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Replacement authorization error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Failed to authorize replacement: ' . \App\Classes\Helper::userMessage($e));
        }
    }

    /**
     * Printable Warranty Replacement Certificate
     */
    public function printReplacementCertificate(int $id): View
    {
        $job = RepairJob::branchScope()->with([
            'customer.lead',
            'product.brand',
            'vendor',
            'replacementChain',
            'originalSerialChain',
            'items.product',
        ])->findOrFail($id);

        $replacementChain = SerialChain::where('replacement_job_id', $job->id)->first();
        $originalChain = SerialChain::where('serial_no', $job->original_serial_no)->first();

        return view('admin.repairs.print_replacement_certificate', compact('job', 'replacementChain', 'originalChain'));
    }

    /**
     * Supplier RMA & Faulty Units Management
     */
    public function rmaIndex(Request $request): View
    {
        $query = SerialChain::with(['product.brand', 'customer.lead', 'replacementJob.vendor', 'replacementJob.supplier', 'originalSale'])
            ->whereIn('status', ['faulty_in_clinic', 'with_supplier_lab', 'credited_by_supplier', 'scrapped', 'retained_by_supplier']);

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('search')) {
            $search = trim($request->get('search'));
            $query->where(function ($q) use ($search) {
                $q->where('serial_no', 'like', "%{$search}%")
                  ->orWhere('parent_serial_no', 'like', "%{$search}%")
                  ->orWhereHas('customer.lead', function ($cq) use ($search) {
                      $cq->where('full_name', 'like', "%{$search}%")
                         ->orWhere('phone_number', 'like', "%{$search}%");
                  });
            });
        }

        $items = $query->orderBy('updated_at', 'desc')->paginate(20)->withQueryString();

        $counts = [
            'total'                 => SerialChain::whereIn('status', ['faulty_in_clinic', 'with_supplier_lab', 'credited_by_supplier', 'scrapped', 'retained_by_supplier'])->count(),
            'faulty_in_clinic'      => SerialChain::where('status', 'faulty_in_clinic')->count(),
            'with_supplier_lab'     => SerialChain::where('status', 'with_supplier_lab')->count(),
            'credited_by_supplier'  => SerialChain::where('status', 'credited_by_supplier')->count(),
            'retained_by_supplier'  => SerialChain::where('status', 'retained_by_supplier')->count(),
            'scrapped'              => SerialChain::where('status', 'scrapped')->count(),
        ];

        $suppliers = Supplier::orderBy('supplier_name')->get();

        return view('admin.repairs.rma', compact('items', 'counts', 'suppliers'));
    }

    /**
     * Update Status of Faulty RMA Serial (Dispatch to Supplier / Mark Credited)
     */
    public function updateRmaStatus(Request $request, int $id): RedirectResponse
    {
        $chain = SerialChain::findOrFail($id);

        $request->validate([
            'status'        => 'required|in:faulty_in_clinic,with_supplier_lab,credited_by_supplier,scrapped,active_with_customer,retained_by_supplier',
            'supplier_id'   => 'nullable|exists:suppliers,id',
            'memo_no'       => 'nullable|string|max:100',
            'credit_amount' => 'nullable|numeric|min:0',
            'notes'         => 'nullable|string|max:500',
        ]);

        $newStatus = $request->input('status');
        $chain->status = $newStatus;
        $chain->save();

        // If credited by supplier and credit amount provided, post debit to supplier_ledgers
        $creditAmount = (float)$request->input('credit_amount', 0);
        if ($newStatus === 'credited_by_supplier' && $creditAmount > 0 && $request->filled('supplier_id')) {
            $ledger = new SupplierLedger();
            $ledger->supplier_id = (int)$request->input('supplier_id');
            $ledger->date = date('Y-m-d');
            $ledger->details = "Supplier RMA Credit for Faulty Unit S/N {$chain->serial_no} (Memo: " . $request->input('memo_no') . ")";
            $ledger->debit = $creditAmount;
            $ledger->credit = 0;
            $ledger->supplier_memo_no = $request->input('memo_no');
            $ledger->type = 'RMA Credit';
            $ledger->save();
        }

        return redirect()->back()->with('success', "RMA unit S/N {$chain->serial_no} updated to '" . ucwords(str_replace('_', ' ', $newStatus)) . "'.");
    }

    /**
     * Delete an individual RMA record
     */
    public function deleteRma(Request $request, int $id): RedirectResponse
    {
        $chain = SerialChain::findOrFail($id);
        $serial = $chain->serial_no;
        $chain->delete();

        return redirect()->back()->with('success', "RMA record for S/N {$serial} deleted successfully.");
    }

    /**
     * Disperse / Clear all settled RMA records
     */
    public function clearSettledRma(Request $request): RedirectResponse
    {
        $count = SerialChain::whereIn('status', ['credited_by_supplier', 'scrapped', 'retained_by_supplier'])->count();
        SerialChain::whereIn('status', ['credited_by_supplier', 'scrapped', 'retained_by_supplier'])->delete();

        return redirect()->back()->with('success', "{$count} settled RMA records dispersed and cleared successfully.");
    }

    /**
     * Upload Attachment (Damage Photos, Lab Reports, Void Proof)
     */
    public function addAttachment(Request $request, int $id): RedirectResponse
    {
        $job = RepairJob::branchScope()->findOrFail($id);

        $request->validate([
            'file'            => 'required|file|mimes:jpeg,png,jpg,webp,pdf|max:10240',
            'attachment_type' => 'required|in:intake_condition,void_proof,lab_report,customer_signed_slip',
            'caption'         => 'nullable|string|max:255',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $uploadDir = public_path('uploads/repairs');
            if (!file_exists($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            $filename = 'repair_' . $job->id . '_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move($uploadDir, $filename);

            RepairAttachment::create([
                'repair_job_id'   => $job->id,
                'file_path'       => 'uploads/repairs/' . $filename,
                'attachment_type' => $request->input('attachment_type'),
                'caption'         => $request->input('caption'),
                'uploaded_by'     => Auth::id() ?? 1,
            ]);

            return redirect()->back()->with('success', 'Photo/document attachment uploaded successfully.');
        }

        return redirect()->back()->with('error', 'No file was uploaded.');
    }

    /**
     * Delete Attachment
     */
    public function deleteAttachment(int $id, int $attachmentId): RedirectResponse
    {
        $job = RepairJob::branchScope()->findOrFail($id);
        $attachment = RepairAttachment::where('repair_job_id', $job->id)->findOrFail($attachmentId);

        if (file_exists(public_path($attachment->file_path))) {
            @unlink(public_path($attachment->file_path));
        }

        $attachment->delete();
        return redirect()->back()->with('success', 'Attachment deleted successfully.');
    }

    /**
     * Declare Warranty Void based on Lab Inspection / Teardown Findings
     */
    public function voidWarranty(Request $request, int $id): RedirectResponse
    {
        $job = RepairJob::branchScope()->findOrFail($id);

        $request->validate([
            'void_reason'         => 'required|string|max:255',
            'lab_diagnosis'       => 'nullable|string',
            'new_estimate_amount' => 'nullable|numeric|min:0',
            'supplier_cost'       => 'nullable|numeric|min:0',
            'proof_file'          => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:10240',
        ]);

        $user = Auth::user();
        $reason = $request->input('void_reason');
        $diagnosis = $request->input('lab_diagnosis');
        $oldStatus = $job->current_status;

        DB::beginTransaction();
        try {
            $job->warranty_status = 'void';
            $job->void_reason = $reason;
            $job->void_by = $user?->id ?? 1;
            $job->is_chargeable = true;

            if ($diagnosis) {
                $job->lab_diagnosis = ($job->lab_diagnosis ? $job->lab_diagnosis . "\n" : '') . "Lab Finding: " . $diagnosis;
            }

            if ($request->filled('new_estimate_amount')) {
                $job->estimate_amount = (float)$request->input('new_estimate_amount');
            }

            if ($request->filled('supplier_cost')) {
                $job->total_supplier_cost = (float)$request->input('supplier_cost');
                if ($job->supplier_id && $job->total_supplier_cost > 0) {
                    $this->syncSupplierLedger($job, $job->supplier_id, $job->total_supplier_cost, $job->lab_ref_no, 'Repair Lab Bill');
                }
            }

            $job->estimate_approved = false;
            $job->approved_at = null;
            $job->current_status = 'waiting_patient_approval';
            $job->save();

            // Mark SerialChain void
            if ($job->original_serial_no) {
                SerialChain::where('serial_no', $job->original_serial_no)->update([
                    'is_void'     => true,
                    'void_reason' => $reason,
                ]);
            }

            // Handle optional photo proof upload
            if ($request->hasFile('proof_file')) {
                $file = $request->file('proof_file');
                $uploadDir = public_path('uploads/repairs');
                if (!file_exists($uploadDir)) {
                    @mkdir($uploadDir, 0777, true);
                }
                $filename = 'void_' . $job->id . '_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move($uploadDir, $filename);

                RepairAttachment::create([
                    'repair_job_id'   => $job->id,
                    'file_path'       => 'uploads/repairs/' . $filename,
                    'attachment_type' => 'void_proof',
                    'caption'         => "Lab void proof: " . $reason,
                    'uploaded_by'     => $user?->id ?? 1,
                ]);
            }

            // Financial Recalculation
            $this->recalculateJobFinancials($job);

            // Status Log
            RepairStatusLog::create([
                'repair_job_id' => $job->id,
                'from_status'   => $oldStatus,
                'to_status'     => 'waiting_patient_approval',
                'remarks'       => "Warranty declared VOID by Lab: {$reason}. New quotation of ৳" . number_format($job->estimate_amount, 2) . " requires patient approval.",
                'user_id'       => $user?->id ?? 1,
                'created_at'    => now(),
            ]);

            DB::commit();
            return redirect()->back()->with('success', "Warranty declared VOID due to lab findings. Status set to 'Waiting for Patient Approval'.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Void warranty error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to void warranty: ' . \App\Classes\Helper::userMessage($e));
        }
    }

    /**
     * Update Estimate & Lab Quotation
     */
    public function updateEstimate(Request $request, int $id): RedirectResponse
    {
        $job = RepairJob::branchScope()->findOrFail($id);

        $request->validate([
            'estimate_amount' => 'required|numeric|min:0',
            'supplier_cost'   => 'nullable|numeric|min:0',
            'lab_ref_no'      => 'nullable|string|max:100',
            'remarks'         => 'nullable|string',
        ]);

        $user = Auth::user();
        $newEstimate = (float)$request->input('estimate_amount');
        $supplierCost = (float)$request->input('supplier_cost', 0);
        $oldStatus = $job->current_status;

        DB::beginTransaction();
        try {
            $job->estimate_amount = $newEstimate;
            if ($request->filled('supplier_cost')) {
                $job->total_supplier_cost = $supplierCost;
            }
            if ($request->filled('lab_ref_no')) {
                $job->lab_ref_no = $request->input('lab_ref_no');
            }

            if ($job->supplier_id && ($job->total_supplier_cost > 0 || !empty($job->lab_ref_no))) {
                $this->syncSupplierLedger($job, $job->supplier_id, (float)$job->total_supplier_cost, $job->lab_ref_no, 'Repair Lab Bill');
            }

            $setWaiting = $request->boolean('set_waiting_approval', true);
            if ($setWaiting) {
                $job->estimate_approved = false;
                $job->approved_at = null;
                $job->current_status = 'waiting_patient_approval';
            }

            $job->save();

            $this->recalculateJobFinancials($job);

            RepairStatusLog::create([
                'repair_job_id' => $job->id,
                'from_status'   => $oldStatus,
                'to_status'     => $job->current_status,
                'remarks'       => "Estimate updated to ৳" . number_format($newEstimate, 2) . ($request->filled('remarks') ? ' - ' . $request->input('remarks') : ''),
                'user_id'       => $user?->id ?? 1,
                'created_at'    => now(),
            ]);

            DB::commit();
            return redirect()->back()->with('success', "Service estimate updated to ৳" . number_format($newEstimate, 2) . ".");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update estimate error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update estimate: ' . \App\Classes\Helper::userMessage($e));
        }
    }

    /**
     * Patient Decision on Quotation (Approved / Declined)
     */
    public function patientDecision(Request $request, int $id): RedirectResponse
    {
        $job = RepairJob::branchScope()->findOrFail($id);

        $request->validate([
            'decision'       => 'required|in:approved,declined',
            'decision_notes' => 'nullable|string',
        ]);

        $user = Auth::user();
        $decision = $request->input('decision');
        $notes = $request->input('decision_notes');
        $oldStatus = $job->current_status;

        DB::beginTransaction();
        try {
            if ($decision === 'approved') {
                $job->estimate_approved = true;
                $job->approved_at = now();
                $job->current_status = 'in_repair';
                $job->save();

                $this->recalculateJobFinancials($job);

                RepairStatusLog::create([
                    'repair_job_id' => $job->id,
                    'from_status'   => $oldStatus,
                    'to_status'     => $job->current_status,
                    'remarks'       => "Patient APPROVED quotation of ৳" . number_format($job->estimate_amount, 2) . ($notes ? ". Notes: {$notes}" : ''),
                    'user_id'       => $user?->id ?? 1,
                    'created_at'    => now(),
                ]);

                DB::commit();
                return redirect()->back()->with('success', "Patient approved quotation. Job is moving forward with repair.");
            } else {
                $job->estimate_approved = false;
                $job->current_status = 'patient_declined';
                // Reset repair estimate so customer is not billed for unapproved repair
                $job->estimate_amount = 0;
                $job->save();

                $this->recalculateJobFinancials($job);

                RepairStatusLog::create([
                    'repair_job_id' => $job->id,
                    'from_status'   => $oldStatus,
                    'to_status'     => 'patient_declined',
                    'remarks'       => "Patient DECLINED quotation. Device marked for return unrepaired" . ($notes ? ". Notes: {$notes}" : ''),
                    'user_id'       => $user?->id ?? 1,
                    'created_at'    => now(),
                ]);

                DB::commit();
                return redirect()->back()->with('info', "Patient declined quotation. Device marked to return unrepaired with no repair charges.");
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Patient decision error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to record decision: ' . \App\Classes\Helper::userMessage($e));
        }
    }

    /**
     * Dispatch Job to Supplier Lab (Courier or By Hand)
     */
    public function dispatchToLab(Request $request, int $id): RedirectResponse
    {
        $job = RepairJob::branchScope()->findOrFail($id);

        $request->validate([
            'supplier_id'         => 'required|exists:suppliers,id',
            'dispatch_type'       => 'required|in:courier,by_hand',
            'courier_name'        => 'nullable|required_if:dispatch_type,courier|string|max:100',
            'courier_tracking_no' => 'nullable|required_if:dispatch_type,courier|string|max:100',
            'carrier_person_name' => 'nullable|required_if:dispatch_type,by_hand|string|max:100',
            'dispatch_notes'      => 'nullable|string',
        ]);

        $user = Auth::user();
        $oldStatus = $job->current_status;

        DB::beginTransaction();
        try {
            $job->supplier_id = (int)$request->input('supplier_id');
            $job->dispatch_type = $request->input('dispatch_type');
            $job->courier_name = $request->input('courier_name');
            $job->courier_tracking_no = $request->input('courier_tracking_no');
            $job->carrier_person_name = $request->input('carrier_person_name');
            $job->dispatched_at = now();
            $job->sent_to_lab_at = now();
            $job->current_status = 'sent_to_lab';
            $job->save();

            // Complete existing 'send_to_lab' task
            RepairTask::where('repair_job_id', $job->id)
                ->where('task_type', 'send_to_lab')
                ->where('is_completed', false)
                ->update([
                    'is_completed' => true,
                    'completed_at' => now(),
                    'completed_by' => $user?->id ?? 1,
                ]);

            // Create supplier follow-up task
            RepairTask::create([
                'repair_job_id' => $job->id,
                'task_type'     => 'supplier_followup',
                'title'         => "Follow up with {$job->supplier?->supplier_name} lab",
                'due_date'      => Carbon::tomorrow()->setTime(11, 0),
                'is_completed'  => false,
            ]);

            $dispatchDesc = $job->dispatch_type === 'courier'
                ? "Dispatched via Courier: {$job->courier_name} (Tracking #{$job->courier_tracking_no})"
                : "Dispatched By Hand: {$job->carrier_person_name}";

            RepairStatusLog::create([
                'repair_job_id' => $job->id,
                'from_status'   => $oldStatus,
                'to_status'     => 'sent_to_lab',
                'remarks'       => $dispatchDesc . ($request->filled('dispatch_notes') ? ". Notes: " . $request->input('dispatch_notes') : ''),
                'user_id'       => $user?->id ?? 1,
                'created_at'    => now(),
            ]);

            DB::commit();

            if ($request->boolean('print_dispatch_note')) {
                return redirect()->route('repairs.print-dispatch-note', $job->id);
            }

            return redirect()->back()->with('success', "Job #{$job->job_no} dispatched to supplier lab successfully.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Dispatch error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to dispatch: ' . \App\Classes\Helper::userMessage($e));
        }
    }

    /**
     * Log Supplier Lab Teardown & Findings Report
     * Handles:
     * 1. repaired_warranty (Free warranty repair)
     * 2. replaced_warranty (Free warranty replacement with BDT 0 memo)
     * 3. declared_void (DYNAMIC VOID DIVERSION - moisture/sweat corrosion -> quotes estimate & diverts to Stage 3)
     * 4. paid_estimate (Quotes estimate for paid repair -> diverts to Stage 3)
     */
    public function logLabReport(Request $request, int $id): RedirectResponse
    {
        $job = RepairJob::branchScope()->findOrFail($id);

        $request->validate([
            'lab_outcome'         => 'required|in:repaired_warranty,replaced_warranty,declared_void,paid_estimate',
            'supplier_memo_no'    => 'nullable|string|max:100',
            'supplier_memo_date'  => 'nullable|date',
            'supplier_cost'       => 'nullable|numeric|min:0',
            'lab_notes'           => 'nullable|string',
            'void_reason'         => 'required_if:lab_outcome,declared_void|nullable|string|max:255',
            'customer_estimate'   => 'required_if:lab_outcome,declared_void,paid_estimate|nullable|numeric|min:0',
            'new_serial_no'       => 'required_if:lab_outcome,replaced_warranty|nullable|string|max:100',
        ]);

        $user = Auth::user();
        $outcome = $request->input('lab_outcome');
        $oldStatus = $job->current_status;

        if ($outcome === 'replaced_warranty') {
            $newSerial = trim($request->input('new_serial_no', ''));
            $oldSerial = trim($job->original_serial_no ?? '');
            if ($newSerial === $oldSerial) {
                return redirect()->back()->withInput()->with('error', 'Replacement serial number cannot be the same as the original serial number.');
            }
        }

        DB::beginTransaction();
        try {
            if ($request->filled('supplier_memo_no')) {
                $job->lab_ref_no = $request->input('supplier_memo_no');
            }
            if ($request->filled('supplier_memo_date')) {
                $job->supplier_memo_date = $request->input('supplier_memo_date');
            }

            // Close supplier follow-up task
            RepairTask::where('repair_job_id', $job->id)
                ->where('task_type', 'supplier_followup')
                ->where('is_completed', false)
                ->update([
                    'is_completed' => true,
                    'completed_at' => now(),
                    'completed_by' => $user?->id ?? 1,
                ]);

            if ($outcome === 'declared_void') {
                // DYNAMIC LAB TEARDOWN VOID DIVERSION!
                $voidReason = $request->input('void_reason');
                $estimate = (float)$request->input('customer_estimate', 0);
                $supplierCost = (float)$request->input('supplier_cost', 0);

                $job->warranty_status = 'void';
                $job->void_reason = "Lab Teardown Void: " . $voidReason;
                $job->void_by = $user?->id ?? 1;
                $job->estimate_amount = $estimate;
                $job->total_supplier_cost = $supplierCost;
                $job->pipeline_path = 'paid_repair';
                $job->is_chargeable = true;
                $job->current_status = 'waiting_patient_approval';
                $job->estimate_approved = null;
                $job->save();

                $this->recalculateJobFinancials($job);

                // Update serial chain
                SerialChain::where('serial_no', $job->original_serial_no)->update([
                    'is_void'     => true,
                    'void_reason' => "Lab Inspection Void: " . $voidReason,
                ]);

                // Create Task: Call patient with estimate within 24 hours
                RepairTask::create([
                    'repair_job_id' => $job->id,
                    'task_type'     => 'patient_quote_call',
                    'title'         => "Call patient with quotation (৳" . number_format($estimate, 2) . ") - Lab Teardown Void",
                    'due_date'      => Carbon::now()->addHours(24),
                    'is_completed'  => false,
                ]);

                // Store attachment photo if uploaded
                if ($request->hasFile('teardown_photo')) {
                    $file = $request->file('teardown_photo');
                    $path = $file->store('repairs/attachments', 'public');
                    RepairAttachment::create([
                        'repair_job_id'   => $job->id,
                        'file_path'       => $path,
                        'file_name'       => $file->getClientOriginalName(),
                        'attachment_type' => 'void_proof',
                        'caption'         => "Lab Teardown: " . $voidReason,
                        'uploaded_by'     => $user?->id ?? 1,
                    ]);
                }

                RepairStatusLog::create([
                    'repair_job_id' => $job->id,
                    'from_status'   => $oldStatus,
                    'to_status'     => 'waiting_patient_approval',
                    'remarks'       => "LAB TEARDOWN VOID: {$voidReason}. Diverted to Stage 3. Estimate quoted: ৳" . number_format($estimate, 2) . ($request->filled('lab_notes') ? ". Notes: " . $request->input('lab_notes') : ''),
                    'user_id'       => $user?->id ?? 1,
                    'created_at'    => now(),
                ]);

                DB::commit();
                return redirect()->back()->with('warning', "Lab teardown declared warranty void ({$voidReason}). Job dynamically diverted to Stage 3 (Waiting Approval). Call task generated.");

            } elseif ($outcome === 'paid_estimate') {
                $estimate = (float)$request->input('customer_estimate', 0);
                $supplierCost = (float)$request->input('supplier_cost', 0);

                $job->estimate_amount = $estimate;
                $job->total_supplier_cost = $supplierCost;
                $job->pipeline_path = 'paid_repair';
                $job->is_chargeable = true;
                $job->current_status = 'waiting_patient_approval';
                $job->estimate_approved = null;
                $job->save();

                $this->recalculateJobFinancials($job);

                RepairTask::create([
                    'repair_job_id' => $job->id,
                    'task_type'     => 'patient_quote_call',
                    'title'         => "Call patient with quotation (৳" . number_format($estimate, 2) . ")",
                    'due_date'      => Carbon::now()->addHours(24),
                    'is_completed'  => false,
                ]);

                RepairStatusLog::create([
                    'repair_job_id' => $job->id,
                    'from_status'   => $oldStatus,
                    'to_status'     => 'waiting_patient_approval',
                    'remarks'       => "Lab provided estimate: ৳" . number_format($estimate, 2) . ($request->filled('lab_notes') ? ". Notes: " . $request->input('lab_notes') : ''),
                    'user_id'       => $user?->id ?? 1,
                    'created_at'    => now(),
                ]);

                DB::commit();
                return redirect()->back()->with('info', "Lab estimate logged. Job moved to Stage 3 (Waiting Approval).");

            } elseif ($outcome === 'replaced_warranty') {
                $newSerial = trim($request->input('new_serial_no'));
                $memoNo = $request->input('supplier_memo_no');
                $memoDate = $request->input('supplier_memo_date', date('Y-m-d'));

                $exists = SerialChain::where('serial_no', $newSerial)->where('status', 'active_with_customer')->first();
                if ($exists) {
                    throw new \Exception("New serial number '{$newSerial}' is already active with another customer.");
                }

                $job->replaced_serial_no = $newSerial;
                $job->current_status = 'ready_for_pickup';
                $job->pipeline_path = 'supplier_warranty';
                $job->total_customer_cost = 0;
                $job->total_supplier_cost = 0;
                $job->save();

                $parentChain = SerialChain::where('serial_no', $job->original_serial_no)->first();
                $warrantyUntil = $parentChain?->warranty_end_date ?? $job->saleDetail?->warranty_end_date;

                if ($parentChain) {
                    $parentChain->update([
                        'status' => 'retained_by_supplier',
                    ]);
                }

                SerialChain::create([
                    'serial_no'           => $newSerial,
                    'parent_serial_no'    => $job->original_serial_no,
                    'replacement_job_id'  => $job->id,
                    'product_id'          => $job->product_id,
                    'customer_id'         => $job->customer_id,
                    'original_sale_id'    => $job->sale_master_id,
                    'status'              => 'active_with_customer',
                    'warranty_start_date' => $parentChain?->warranty_start_date ?? date('Y-m-d'),
                    'warranty_end_date'   => $warrantyUntil,
                    'is_void'             => false,
                ]);

                // Create BDT 0.00 Supplier Ledger Entry for line-by-line parity!
                if ($job->supplier_id) {
                    $this->syncSupplierLedger($job, $job->supplier_id, 0.00, $memoNo, 'Warranty Replacement (BDT 0.00)');
                }

                RepairStatusLog::create([
                    'repair_job_id' => $job->id,
                    'from_status'   => $oldStatus,
                    'to_status'     => 'ready_for_pickup',
                    'remarks'       => "Warranty Replacement Received from Lab. New S/N: {$newSerial}, Supplier Memo #{$memoNo}. Unit is Ready for Pickup.",
                    'user_id'       => $user?->id ?? 1,
                    'created_at'    => now(),
                ]);

                if ($request->boolean('send_pickup_sms', true)) {
                    $this->sendReadySms($job);
                }

                DB::commit();
                return redirect()->back()->with('success', "Warranty replacement processed. New serial #{$newSerial} recorded with BDT 0.00 supplier memo. Job moved to Stage 5 (Ready for Pickup).");

            } elseif ($outcome === 'repaired_warranty') {
                $memoNo = $request->input('supplier_memo_no');
                $job->current_status = 'ready_for_pickup';
                $job->total_customer_cost = 0;
                $job->total_supplier_cost = 0;
                $job->save();

                RepairStatusLog::create([
                    'repair_job_id' => $job->id,
                    'from_status'   => $oldStatus,
                    'to_status'     => 'ready_for_pickup',
                    'remarks'       => "Lab confirmed warranty repair completed" . ($memoNo ? " (Supplier Memo: {$memoNo})" : '') . ". Unit is Ready for Pickup.",
                    'user_id'       => $user?->id ?? 1,
                    'created_at'    => now(),
                ]);

                if ($request->boolean('send_pickup_sms', true)) {
                    $this->sendReadySms($job);
                }

                DB::commit();
                return redirect()->back()->with('success', "Warranty repair confirmed. Device moved to Stage 5 (Ready for Pickup).");
            }

            DB::commit();
            return redirect()->back()->with('success', "Lab report updated successfully.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lab report error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to log lab report: ' . \App\Classes\Helper::userMessage($e));
        }
    }

    /**
     * Monthly Supplier Reconciliation Screen (Line-by-line parity with supplier statements)
     */
    public function supplierReconciliation(Request $request): View
    {
        $supplierId = $request->get('supplier_id');
        $month = $request->get('month', Carbon::now()->format('Y-m'));
        $suppliers = Supplier::orderBy('supplier_name')->get();

        $startDate = Carbon::parse($month . '-01')->startOfMonth();
        $endDate = Carbon::parse($month . '-01')->endOfMonth();

        $jobsQuery = RepairJob::with(['customer.lead', 'product', 'supplier'])
            ->whereNotNull('supplier_id')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('supplier_memo_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                  ->orWhereBetween('dispatched_at', [$startDate, $endDate])
                  ->orWhere(function ($sub) use ($startDate, $endDate) {
                      $sub->whereNotNull('replaced_serial_no')
                          ->whereBetween('updated_at', [$startDate, $endDate]);
                  });
            });

        if ($supplierId) {
            $jobsQuery->where('supplier_id', $supplierId);
        }

        $jobs = $jobsQuery->latest('id')->get();

        $ledgerQuery = SupplierLedger::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where(function ($q) {
                $q->where('details', 'like', '%Repair%')
                  ->orWhere('details', 'like', '%Replacement%')
                  ->orWhere('type', 'repair_bill');
            });

        if ($supplierId) {
            $ledgerQuery->where('supplier_id', $supplierId);
        }

        $ledgerEntries = $ledgerQuery->orderBy('date')->get();

        $totalReplacements = $jobs->whereNotNull('replaced_serial_no')->count();
        $totalLabBills = $jobs->sum('total_supplier_cost');
        $totalZeroBills = $jobs->where('total_supplier_cost', 0)->whereNotNull('replaced_serial_no')->count();

        // Gross Payment and Net Running Balance Calculations
        $selectedSupplier = $supplierId ? Supplier::find($supplierId) : null;
        $monthlyGrossPaid = $supplierId
            ? (float)SupplierLedger::where('supplier_id', $supplierId)
                ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->sum('debit')
            : (float)SupplierLedger::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])->sum('debit');

        $overallCredits = $supplierId ? (float)SupplierLedger::where('supplier_id', $supplierId)->sum('credit') : 0;
        $overallDebits  = $supplierId ? (float)SupplierLedger::where('supplier_id', $supplierId)->sum('debit') : 0;
        $supplierNetBalance = max(0, $overallCredits - $overallDebits);

        return view('admin.repairs.supplier_reconciliation', compact(
            'suppliers',
            'supplierId',
            'selectedSupplier',
            'month',
            'jobs',
            'ledgerEntries',
            'totalReplacements',
            'totalLabBills',
            'totalZeroBills',
            'monthlyGrossPaid',
            'supplierNetBalance'
        ));
    }

    /**
     * Mark a reminder/follow-up task as completed
     */
    public function completeTask(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $task = RepairTask::whereHas('repairJob', fn ($q) => $q->branchScope())->findOrFail($id);
        $user = Auth::user();

        $task->update([
            'is_completed' => true,
            'completed_at' => now(),
            'completed_by' => $user?->id ?? 1,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Task marked as completed.',
            ]);
        }

        return redirect()->back()->with('success', 'Task marked as completed.');
    }

    /**
     * Print Dispatch Note (Challan) to Supplier Lab
     */
    public function printDispatchNote(int $id): View
    {
        $job = RepairJob::branchScope()->with(['customer.lead', 'product', 'supplier', 'receivedBy', 'vendor'])->findOrFail($id);
        return view('admin.repairs.print_dispatch_note', compact('job'));
    }

    /**
     * Print Standalone Patient Cost Estimate Slip
     */
    public function printEstimate(int $id): View
    {
        $job = RepairJob::branchScope()->with(['customer.lead', 'product', 'items.product', 'receivedBy', 'vendor'])->findOrFail($id);
        return view('admin.repairs.print_estimate', compact('job'));
    }

    /**
     * Print Advance Payment Receipt Slip
     */
    public function printAdvanceReceipt(int $id): View
    {
        $job = RepairJob::branchScope()->with(['customer.lead', 'product', 'receivedBy', 'vendor'])->findOrFail($id);
        return view('admin.repairs.print_advance_receipt', compact('job'));
    }

    /**
     * Repair History (Closed & Completed Jobs: Delivered, Returned Unrepaired, Cancelled)
     */
    public function history(Request $request): View
    {
        $status = $request->get('status', 'delivered');
        $search = trim($request->get('search', ''));
        $vendorId = $request->get('vendor_id');
        $supplierId = $request->get('supplier_id');
        $warrantyStatus = $request->get('warranty_status');
        $outcome = $request->get('outcome');
        $dateType = $request->get('date_type', 'delivered');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $query = RepairJob::with(['customer.lead', 'product', 'vendor', 'supplier', 'deliveredBy'])
            ->branchScope();

        // Check if user is searching specifically for a serial number
        $isSerialSearch = false;
        if (!empty($search)) {
            $isSerialSearch = RepairJob::where('original_serial_no', $search)
                ->orWhere('replaced_serial_no', $search)
                ->exists();
        }

        // Apply status filter (if searching a serial without explicit status, include all closed statuses)
        if ($isSerialSearch && !$request->has('status')) {
            $query->whereIn('current_status', ['delivered', 'returned_unrepaired', 'patient_declined', 'cancelled']);
            $status = 'all';
        } elseif ($status === 'delivered') {
            $query->where('current_status', 'delivered');
        } elseif (in_array($status, ['declined', 'returned_unrepaired', 'patient_declined'])) {
            $query->whereIn('current_status', ['returned_unrepaired', 'patient_declined']);
        } elseif ($status === 'cancelled') {
            $query->where('current_status', 'cancelled');
        } else {
            $query->whereIn('current_status', ['delivered', 'returned_unrepaired', 'patient_declined', 'cancelled']);
        }

        // Search filter (one single box)
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('job_no', 'like', "%{$search}%")
                  ->orWhere('original_serial_no', 'like', "%{$search}%")
                  ->orWhere('replaced_serial_no', 'like', "%{$search}%")
                  ->orWhere('lab_ref_no', 'like', "%{$search}%")
                  ->orWhere('courier_tracking_no', 'like', "%{$search}%")
                  ->orWhereHas('customer.lead', function ($lq) use ($search) {
                      $lq->where('full_name', 'like', "%{$search}%")
                         ->orWhere('phone_number', 'like', "%{$search}%");
                  });
            });
        }

        // Branch filter
        if ($vendorId) {
            $query->where('vendor_id', $vendorId);
        }

        // Supplier filter
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        // Warranty status filter
        if ($warrantyStatus) {
            $query->where('warranty_status', $warrantyStatus);
        }

        // Outcome filter
        if ($outcome) {
            if ($outcome === 'cancelled') {
                $query->where('current_status', 'cancelled');
            } elseif ($outcome === 'declined') {
                $query->whereIn('current_status', ['returned_unrepaired', 'patient_declined']);
            } elseif ($outcome === 'in_house') {
                $query->where('is_quick_service', true);
            } elseif ($outcome === 'warranty_replacement') {
                $query->whereNotNull('replaced_serial_no')
                      ->where('replaced_serial_no', '<>', '')
                      ->whereRaw('replaced_serial_no <> original_serial_no')
                      ->where(function ($q) {
                          $q->where('warranty_status', 'under_product_warranty')
                            ->orWhere('pipeline_path', 'supplier_warranty');
                      });
            } elseif ($outcome === 'paid_replacement') {
                $query->whereNotNull('replaced_serial_no')
                      ->where('replaced_serial_no', '<>', '')
                      ->whereRaw('replaced_serial_no <> original_serial_no')
                      ->where('warranty_status', '<>', 'under_product_warranty')
                      ->where('pipeline_path', '<>', 'supplier_warranty');
            } elseif ($outcome === 'warranty_repair') {
                $query->where(function ($q) {
                    $q->whereNull('replaced_serial_no')
                      ->orWhere('replaced_serial_no', '')
                      ->orWhereRaw('replaced_serial_no = original_serial_no');
                })
                ->where('is_quick_service', false)
                ->whereNotIn('current_status', ['cancelled', 'returned_unrepaired', 'patient_declined'])
                ->where(function ($q) {
                    $q->where('warranty_status', 'under_product_warranty')
                      ->orWhere('pipeline_path', 'supplier_warranty');
                });
            } elseif ($outcome === 'paid_repair') {
                $query->where(function ($q) {
                    $q->whereNull('replaced_serial_no')
                      ->orWhere('replaced_serial_no', '')
                      ->orWhereRaw('replaced_serial_no = original_serial_no');
                })
                ->where('is_quick_service', false)
                ->whereNotIn('current_status', ['cancelled', 'returned_unrepaired', 'patient_declined'])
                ->where('warranty_status', '<>', 'under_product_warranty')
                ->where('pipeline_path', '<>', 'supplier_warranty');
            }
        }

        // Date range filter
        if ($startDate) {
            $field = $dateType === 'received' ? 'created_at' : DB::raw('COALESCE(delivered_at, updated_at)');
            $query->whereDate($field, '>=', $startDate);
        }
        if ($endDate) {
            $field = $dateType === 'received' ? 'created_at' : DB::raw('COALESCE(delivered_at, updated_at)');
            $query->whereDate($field, '<=', $endDate);
        }

        // Order: If searching a serial, show ALL jobs oldest to newest so full chronological device history is visible
        if ($isSerialSearch) {
            $query->orderBy('created_at', 'asc')->orderBy('id', 'asc');
        } else {
            $query->orderByRaw('COALESCE(delivered_at, updated_at) DESC')->orderByDesc('id');
        }

        $jobs = $query->paginate(20)->withQueryString();

        // Counts for tab badges
        $baseKpiQuery = RepairJob::branchScope();
        $counts = [
            'all'       => (clone $baseKpiQuery)->whereIn('current_status', ['delivered', 'returned_unrepaired', 'patient_declined', 'cancelled'])->count(),
            'delivered' => (clone $baseKpiQuery)->where('current_status', 'delivered')->count(),
            'declined'  => (clone $baseKpiQuery)->whereIn('current_status', ['returned_unrepaired', 'patient_declined'])->count(),
            'cancelled' => (clone $baseKpiQuery)->where('current_status', 'cancelled')->count(),
        ];

        // Financial totals of the filtered query
        $financialTotals = (clone $query)->selectRaw('
            SUM(total_customer_paid) as total_customer_paid,
            SUM(total_supplier_cost) as total_supplier_cost
        ')->first();

        $branches = User::where('type', 'vendor')->orderBy('name')->get();
        $suppliers = Supplier::orderBy('supplier_name')->get();

        return view('admin.repairs.history', compact(
            'jobs',
            'counts',
            'financialTotals',
            'branches',
            'suppliers',
            'status',
            'search',
            'vendorId',
            'supplierId',
            'warrantyStatus',
            'outcome',
            'dateType',
            'startDate',
            'endDate',
            'isSerialSearch'
        ));
    }

    /**
     * Export Repair History to Excel
     */
    public function exportHistory(Request $request)
    {
        $status = $request->get('status', 'delivered');
        $search = trim($request->get('search', ''));
        $vendorId = $request->get('vendor_id');
        $supplierId = $request->get('supplier_id');
        $warrantyStatus = $request->get('warranty_status');
        $outcome = $request->get('outcome');
        $dateType = $request->get('date_type', 'delivered');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $query = RepairJob::with(['customer.lead', 'product', 'vendor', 'supplier', 'deliveredBy'])
            ->branchScope();

        $isSerialSearch = false;
        if (!empty($search)) {
            $isSerialSearch = RepairJob::where('original_serial_no', $search)
                ->orWhere('replaced_serial_no', $search)
                ->exists();
        }

        if ($isSerialSearch && !$request->has('status')) {
            $query->whereIn('current_status', ['delivered', 'returned_unrepaired', 'patient_declined', 'cancelled']);
        } elseif ($status === 'delivered') {
            $query->where('current_status', 'delivered');
        } elseif (in_array($status, ['declined', 'returned_unrepaired', 'patient_declined'])) {
            $query->whereIn('current_status', ['returned_unrepaired', 'patient_declined']);
        } elseif ($status === 'cancelled') {
            $query->where('current_status', 'cancelled');
        } else {
            $query->whereIn('current_status', ['delivered', 'returned_unrepaired', 'patient_declined', 'cancelled']);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('job_no', 'like', "%{$search}%")
                  ->orWhere('original_serial_no', 'like', "%{$search}%")
                  ->orWhere('replaced_serial_no', 'like', "%{$search}%")
                  ->orWhere('lab_ref_no', 'like', "%{$search}%")
                  ->orWhere('courier_tracking_no', 'like', "%{$search}%")
                  ->orWhereHas('customer.lead', function ($lq) use ($search) {
                      $lq->where('full_name', 'like', "%{$search}%")
                         ->orWhere('phone_number', 'like', "%{$search}%");
                  });
            });
        }

        if ($vendorId) {
            $query->where('vendor_id', $vendorId);
        }
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        if ($warrantyStatus) {
            $query->where('warranty_status', $warrantyStatus);
        }

        if ($outcome) {
            if ($outcome === 'cancelled') {
                $query->where('current_status', 'cancelled');
            } elseif ($outcome === 'declined') {
                $query->whereIn('current_status', ['returned_unrepaired', 'patient_declined']);
            } elseif ($outcome === 'in_house') {
                $query->where('is_quick_service', true);
            } elseif ($outcome === 'warranty_replacement') {
                $query->whereNotNull('replaced_serial_no')
                      ->where('replaced_serial_no', '<>', '')
                      ->whereRaw('replaced_serial_no <> original_serial_no')
                      ->where(function ($q) {
                          $q->where('warranty_status', 'under_product_warranty')
                            ->orWhere('pipeline_path', 'supplier_warranty');
                      });
            } elseif ($outcome === 'paid_replacement') {
                $query->whereNotNull('replaced_serial_no')
                      ->where('replaced_serial_no', '<>', '')
                      ->whereRaw('replaced_serial_no <> original_serial_no')
                      ->where('warranty_status', '<>', 'under_product_warranty')
                      ->where('pipeline_path', '<>', 'supplier_warranty');
            } elseif ($outcome === 'warranty_repair') {
                $query->where(function ($q) {
                    $q->whereNull('replaced_serial_no')
                      ->orWhere('replaced_serial_no', '')
                      ->orWhereRaw('replaced_serial_no = original_serial_no');
                })
                ->where('is_quick_service', false)
                ->whereNotIn('current_status', ['cancelled', 'returned_unrepaired', 'patient_declined'])
                ->where(function ($q) {
                    $q->where('warranty_status', 'under_product_warranty')
                      ->orWhere('pipeline_path', 'supplier_warranty');
                });
            } elseif ($outcome === 'paid_repair') {
                $query->where(function ($q) {
                    $q->whereNull('replaced_serial_no')
                      ->orWhere('replaced_serial_no', '')
                      ->orWhereRaw('replaced_serial_no = original_serial_no');
                })
                ->where('is_quick_service', false)
                ->whereNotIn('current_status', ['cancelled', 'returned_unrepaired', 'patient_declined'])
                ->where('warranty_status', '<>', 'under_product_warranty')
                ->where('pipeline_path', '<>', 'supplier_warranty');
            }
        }

        if ($startDate) {
            $field = $dateType === 'received' ? 'created_at' : DB::raw('COALESCE(delivered_at, updated_at)');
            $query->whereDate($field, '>=', $startDate);
        }
        if ($endDate) {
            $field = $dateType === 'received' ? 'created_at' : DB::raw('COALESCE(delivered_at, updated_at)');
            $query->whereDate($field, '<=', $endDate);
        }

        if ($isSerialSearch) {
            $query->orderBy('created_at', 'asc')->orderBy('id', 'asc');
        } else {
            $query->orderByRaw('COALESCE(delivered_at, updated_at) DESC')->orderByDesc('id');
        }

        $jobs = $query->get();

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\RepairHistoryExport($jobs),
            'repair_history_' . date('Y-m-d_His') . '.xlsx'
        );
    }
}

