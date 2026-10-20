<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Enums\DeliveryStatus;
use App\Enums\ReferralStatus;
use App\Enums\RewardLedgerType;
use App\Exceptions\StockGuardException;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\District;
use App\Models\Doctor;
use App\Models\DoctorLedger;
use App\Models\Ledger;
use App\Models\MembershipTier;
use App\Models\MembershipUpgradeHistory;
use App\Models\PurchaseDetail;
use App\Models\Referral;
use App\Models\RewardLedger;
use App\Models\SaleDetail;
use App\Models\SaleMaster;
use App\Models\ServiceDetail;
use App\Models\ServiceType;
use App\Models\Setting;
use App\Models\Stock;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\LedgerReversal;
use App\Services\StockGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Proposal;
use App\Models\LoyaltySetting;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Log;
use Yajra\DataTables\Facades\DataTables;

class SaleController extends Controller
{
    /**
     * Configure middleware to handle authorization for different sale operations
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:sale_list.index')->only(['index']);
        $this->middleware('permission:add_sale.create')->only(['create', 'store']);
        $this->middleware('permission:sale_list.edit')->only(['edit', 'update']);
        $this->middleware('permission:sale_list.view')->only(['show']);
        $this->middleware('permission:sale_list.delete')->only(['destroy']);
        $this->middleware('permission:doctor_payment.index')->only(['doctorPaymentIndex']);
        $this->middleware('permission:doctor_payment.create')->only(['doctorPaymentCreate', 'doctorPaymentStore']);
        $this->middleware('permission:doctor_payment.edit')->only(['doctorPaymentEdit', 'doctorPaymentUpdate']);
        $this->middleware('permission:doctor_payment.delete')->only(['doctorPaymentDestroy']);
        $this->middleware('permission:sale_fulfillment.index|sale_list.index')->only(['fulfillmentHub']);
        $this->middleware('permission:pending_services.index|sale_fulfillment.index|sale_list.index')->only(['pendingServices']);
        $this->middleware('permission:sale_fulfillment.edit|sale_list.edit')->only(['updateDeliveryStatus']);
        $this->middleware('permission:pending_services.edit|sale_fulfillment.edit|sale_list.edit')->only(['markServiceDoneNow', 'markServiceBookLater']);
        $this->middleware('permission:add_sale.create|sale_list.edit')
            ->only(['addRowItem', 'addServiceRowItem', 'getProductSerialNumbers', 'getCustomerRedeemPoints']);
        $this->middleware('permission:sale_fulfillment.index|add_sale.create|sale_list.index')->only(['scanLookup']);
        $this->middleware('permission:patient_due_list.index|patient_due_list.create|customer_payment.create')->only(['unpaidInvoices']);
        $this->middleware('permission:patient_due_list.create|customer_payment.create')->only(['collectDue']);
        $this->middleware('permission:sale_list.index|sales_performance.index')->only(['saleReportCreate', 'saleReport']);
    }

    /**
     * Display a listing of the sales.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function index(Request $request)
    {
        // Read-only list: view-all-branches users see HQ + every branch (others keep their normal scope)
        return Helper::readAllBranches(fn () => $this->salesIndex($request));
    }

    protected function salesIndex(Request $request)
    {
        $data['allBranches'] = Helper::isAllBranchesMode();
        $data['branchFilter'] = $data['allBranches'] ? (string) $request->input('branch', 'all') : null;
        $data['branches'] = $data['allBranches'] ? Helper::branchOptions() : [];
        $bf = fn ($q) => Helper::applyBranchFilter($q, $data['branchFilter']);

        $query = $bf(SaleMaster::with(relations: ['customer.lead:id,full_name', 'vendor']));

        if ($request->filled('sale_invoice_no')) {
            $query->where('sale_invoice_no', $request->sale_invoice_no);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $data['start_date'] = $request->start_date ?? null;
        $data['end_date'] = $request->end_date ?? null;

        if ($data['start_date'] && $data['end_date']) {
            $start = date('Y-m-d', strtotime($data['start_date']));
            $end = date('Y-m-d', strtotime($data['end_date']));
            $query->whereBetween('date', [$start, $end]);
        }

        if ($request->filled('serial_no')) {
            $query->whereHas('saleDetails', function ($q) use ($request) {
                $q->whereJsonContains('serial_no', $request->serial_no);
            });
        }

        if ($request->boolean('due_only')) {
            $query->whereRaw('(total_amount - paid) > 0.009');
        }

        $data['sales'] = $query->latest()->paginate(20);

        $data['customers'] = Customer::with('lead:id,full_name')->select('id', 'lead_id')
        ->orderBy('id', 'desc')
        ->get();

        // Summary cards (all invoices visible to this user; vendor scope applies automatically)
        $agg = fn ($q) => $q->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as amount, COALESCE(SUM(paid),0) as paid')->first();
        $today = $agg($bf(SaleMaster::whereDate('date', today())));
        $month = $agg($bf(SaleMaster::whereDate('date', '>=', now()->startOfMonth()->toDateString())));
        $all = $agg($bf(SaleMaster::query()));
        $due = $bf(SaleMaster::whereRaw('(total_amount - paid) > 0.009'))
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total_amount - paid),0) as amount')->first();
        $data['saleStats'] = [
            'today_amount' => (float) $today->amount, 'today_count' => (int) $today->cnt,
            'month_amount' => (float) $month->amount, 'month_count' => (int) $month->cnt,
            'total_amount' => (float) $all->amount, 'total_count' => (int) $all->cnt, 'total_paid' => (float) $all->paid,
            'due_amount' => (float) $due->amount, 'due_count' => (int) $due->cnt,
        ];

        return view('admin.sales.index', $data);
    }

    /**
     * Get the list of sales for DataTables.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function saleList(): JsonResponse
    {
        $data = SaleMaster::join('customers', 'customers.id', '=', 'sale_masters.customer_id')
            ->select('sale_masters.*', 'customers.name')
            ->orderBy('id', 'desc')
            ->get();

        return DataTables::of($data)
            ->addIndexColumn()
                ->addColumn('action', function ($row) {
                    $action = '<div class="d-flex flex-wrap align-items-center">';
                    if (Helper::hasPermission('sale_list.edit')) {
                        $action .= '<a href="' . route('sale.edit', $row->sale_invoice_no) . '" class="btn btn-success btn-sm mr-1" title="Edit"><i class="fas fa-edit"></i></a>';
                    }
                    if (Helper::hasPermission('sale_list.view')) {
                        $action .= '<a href="' . route('sale.show', $row->sale_invoice_no) . '" class="btn btn-info btn-sm mr-1" title="Details"><i class="fas fa-eye"></i></a>';
                    }

                    $action .= '<a href="' . route('sale.pos-print', $row->sale_invoice_no) . '" class="btn btn-secondary btn-sm mr-1" target="_blank" title="POS Print"><i class="fas fa-print"></i></a>';

                    if (Helper::hasPermission('sale_return.create')) {
                        $action .= '<a href="' . route('sale-returns.create', ['sale_invoice_no' => $row->sale_invoice_no]) . '" class="btn btn-warning btn-sm mr-1" title="Return / Credit Note"><i class="fas fa-undo-alt"></i></a>';
                    }

                    if (auth()->user()->isSuperAdmin()) {
                        $action .= '<form action="' . route('sale.destroy', $row->sale_invoice_no) . '" method="POST" onsubmit="return confirmCancelWithReason(event, \'this sale invoice\')">' . csrf_field() . method_field('DELETE') . '<button type="submit" class="btn btn-danger btn-sm" title="Delete"><i class="far fa-trash-alt"></i></button></form>';
                    }
                    
                    $action .= '</div>';
                    return $action;
                })
            ->rawColumns(['action'])
            ->toJson();
        
        
    }

    /**
     * Show the form for creating a new sale.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function create(Request $request): View
    {
        $data['paymentTypes'] = Helper::paymentType();
        $data['serviceTypes'] = ServiceType::where('status', 1)->get();
        $data['doctors'] = Doctor::where('status', 1)->with(['chambers', 'categoryCommissions', 'serviceCommissions', 'productCommissions'])->get();
        $data['districts'] = District::orderBy('name')->get();
        // Pre-selected customer when coming from a won deal on the pipeline board or proposal
        $data['prefillCustomer'] = $request->filled('customer_id')
            ? Customer::with('lead:id,full_name,phone_number')->find($request->integer('customer_id'))
            : null;

        $data['fromProposal'] = null;
        if ($request->filled('from_proposal_id')) {
            $proposal = Proposal::with(['items.product.category', 'items.product.brand', 'items.service', 'customer.lead', 'lead', 'doctor'])->find($request->input('from_proposal_id'));
            if ($proposal) {
                $data['fromProposal'] = $proposal;
                if ($proposal->customer_id) {
                    $data['prefillCustomer'] = $proposal->customer;
                } elseif ($proposal->lead_id) {
                    $cust = Customer::firstOrCreate(
                        ['lead_id' => $proposal->lead_id],
                        ['referral_code' => 'REF-' . strtoupper(Str::random(6))]
                    );
                    $data['prefillCustomer'] = $cust->load('lead:id,full_name,phone_number');
                }
            }
        }

        return view('admin.sales.create', $data);
    }

    /**
     * Add a new row item to the sale form.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function addRowItem(Request $request): View
    {
        $index = $request->index;
        return view('admin.sales.add_row_item', compact('index'));
    }

    /**
     * Add a new service row item to the sale form.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function addServiceRowItem(Request $request): View
    {
        $index = $request->index;
        $serviceTypes = ServiceType::where('status', 1)->get();
        return view('admin.sales.add_service_row_item', compact('index', 'serviceTypes'));
    }

    /**
     * Get serial numbers for a specific product with MRP price.
     *
     * @param int $productId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductSerialNumbers(int $productId)
    {
        $availableSerials = Helper::getAvailableSerialNumbers($productId);

        return response()->json($availableSerials);
    }

    /**
     * Get customer redeem points.
     *
     * @param int $customerId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCustomerRedeemPoints(int $customerId)
    {
        $customer = Customer::find($customerId);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }

        $availablePoints = $customer->getAvailableRedeemPoints();
        $setting = LoyaltySetting::current();

        return response()->json([
            'success'            => true,
            'available_points'   => $availablePoints,
            'point_value'        => LoyaltySetting::pointValueFor($customer),
            'min_redeem_points'  => (int) $setting->min_redeem_points,
            'max_redeem_percent' => (float) $setting->max_redeem_percent,
        ]);
    }


    /**
     * Store a newly created sale in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $requestProductIds = array_filter($request->product_id ?? []);
        $requestServiceTypeIds = array_filter($request->service_type_id ?? []);

        $rules = [
            'customer_id' => ['required'],
            'payment_type_id' => ['required'],
            'date' => ['required'],
            'due_date' => ['nullable', 'date'],
            'delivery_date_time' => ['nullable', 'date'],
            'lab_name' => ['nullable', 'string', 'max:255'],
            'delivery_status' => ['nullable', 'in:' . implode(',', array_column(DeliveryStatus::cases(), 'value'))],
        ];

        if (! empty($requestProductIds)) {
            foreach ($requestProductIds as $key => $val) {
                $rules["product_id.$key"] = 'required';
                $rules["qty.$key"] = 'required|numeric|gt:0';
                $rules["unit_price.$key"] = 'required';
                $rules["product_id"] = 'array';
            }
        }

        if (! empty($requestServiceTypeIds)) {
            foreach ($requestServiceTypeIds as $key => $val) {
                $rules["service_type_id.$key"] = 'required';
                $rules["service_qty.$key"] = 'required';
                $rules["service_price.$key"] = 'required';
                $rules["service_type_id"] = 'array';
            }
        }

        $this->validate(
            $request,
            $rules,
            [
                'customer_id.required' => 'The customer field is required.',
                'payment_type_id.required' => 'The payment type field is required.',
                'product_id.required' => 'At least one product must be selected.',
            ]
        );

        if ($request->filled('redeem_points') && (float) $request->redeem_points > 0) {
            $customer = Customer::find($request->customer_id);
            if ($customer) {
                $setting = LoyaltySetting::current();
                $pointValue = LoyaltySetting::pointValueFor($customer);
                $redeemPoints = (float) $request->redeem_points;
                $invoiceGross = (float) $request->total_amount + ($redeemPoints * $pointValue);
                $maxPoints = MembershipService::maxRedeemablePoints($customer, $invoiceGross);
                if ($redeemPoints > $maxPoints) {
                    $msg = ($maxPoints === 0 && $customer->getAvailableRedeemPoints() < (float) $setting->min_redeem_points)
                        ? "Customer balance is below the minimum {$setting->min_redeem_points} points required to redeem."
                        : "Redeem points cannot exceed {$maxPoints} points for this invoice.";
                    throw ValidationException::withMessages([
                        'redeem_points' => $msg,
                    ]);
                }
            }
        }

        $batchIds = json_decode($request->batch_id_formatted, true) ?? [];
        $serialNos = json_decode($request->serial_no_formatted, true) ?? [];

        foreach ($requestProductIds as $key => $productId) {
            $hasBatch = !empty($batchIds[$key]);
            $hasSerial = !empty($serialNos[$key]) && is_array($serialNos[$key]) && count($serialNos[$key]) > 0;

            if (!$hasBatch && !$hasSerial) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['product_validation' => 'Please select either a batch or serial number for each product.']);
            }

        }

        DB::beginTransaction();
        $type = Helper::$sales;
        $saleInvoiceNo = strtoupper(uniqid());
        $incomeInvoiceNo = date('ymdhis');

        try {
            // Re-check serials and batch stock on the server, with row locks (stale forms, two counters)
            $stockLines = [];
            foreach ($requestProductIds as $key => $productId) {
                $stockLines[] = [
                    'product_id' => $productId,
                    'qty'        => $request->qty[$key] ?? null,
                    'batch_id'   => $batchIds[$key] ?? null,
                    'serials'    => $serialNos[$key] ?? null,
                ];
            }
            app(StockGuard::class)->assertSaleLines($stockLines, auth()->user()->branchId() ?: null);

            $saleMaster = new SaleMaster();
            $saleMaster->customer_id = $request->customer_id;
            $saleMaster->payment_id = $request->payment_type_id;
            $saleMaster->payment_method = $request->payment_method ?: Helper::channelKeyFromPaymentId($request->payment_type_id);
            $saleMaster->payment_trx_no = $request->payment_trx_no ?: null;
            $saleMaster->pos_terminal_name = $request->pos_terminal_name ?: null;
            if (Helper::isReconcilableGatewayPayment($request->payment_type_id) && (float) $request->paid > 0) {
                $saleMaster->gateway_settlement_status = 'pending';
            } else {
                $saleMaster->gateway_settlement_status = 'not_applicable';
            }
            $saleMaster->date = $request->date;
            $saleMaster->details = $request->details;
            $saleMaster->sale_invoice_no = $saleInvoiceNo;
            $saleMaster->vat = $request->vat;
            $saleMaster->total_discount = $request->total_discount;
            $saleMaster->total_amount = $request->total_amount;
            $saleMaster->paid = $request->paid;
            $saleMaster->due_date = ($request->filled('due_date') && ($request->total_amount - $request->paid) > 0.009) ? $request->due_date : null;
            $saleMaster->delivery_status = $request->delivery_status ?? DeliveryStatus::ORDERED->value;
            $saleMaster->delivery_date_time = $request->filled('delivery_date_time') ? date('Y-m-d H:i:s', strtotime($request->delivery_date_time)) : null;
            $saleMaster->lab_name = $request->filled('lab_name') ? $request->lab_name : null;
            if ($saleMaster->delivery_status === DeliveryStatus::DELIVERED->value) {
                if (empty($saleMaster->delivery_date_time)) {
                    $saleMaster->delivery_date_time = now();
                }
                $saleMaster->delivered_at = now();
                $saleMaster->delivered_by = auth()->id();
            }
            $saleMaster->is_percent_product_commission = $request->has('is_percent_product_commission') ? 1 : 0;
            $saleMaster->is_percent_service_commission = $request->has('is_percent_service_commission') ? 1 : 0;

            // If user is vendor, set vendor_id to their user id
            // If user is general auth, vendor_id remains null (global sale)
            if (auth()->user()->branchId()) {
                $saleMaster->vendor_id = auth()->user()->branchId();
            } else {
                $saleMaster->vendor_id = null;
            }

            // Handle doctor reference, chamber and commission (including dual/multi referrals)
            if (!empty($request->reference_by) || !empty($request->secondary_reference_by)) {
                $primaryDocId = !empty($request->reference_by) ? (int) $request->reference_by : (int) $request->secondary_reference_by;
                $refAmount = (float) ($request->primary_reference_amount ?? ($request->reference_amount ?? 0));
                if (!empty($request->reference_by) && $refAmount <= 0.001) {
                    $prods = [];
                    if (!empty($requestProductIds)) {
                        foreach ($requestProductIds as $k => $pId) {
                            $prods[] = [
                                'product_id' => $pId,
                                'qty' => $request->qty[$k] ?? 1,
                                'line_total' => (float)($request->qty[$k] ?? 1) * (float)($request->unit_price[$k] ?? 0) * (1 - (float)($request->discount[$k] ?? 0) / 100),
                            ];
                        }
                    }
                    $servs = [];
                    if (!empty($requestServiceTypeIds)) {
                        foreach ($requestServiceTypeIds as $k => $sId) {
                            $servs[] = [
                                'service_type_id' => $sId,
                                'qty' => $request->service_qty[$k] ?? 1,
                                'line_total' => (float)($request->service_qty[$k] ?? 1) * (float)($request->service_price[$k] ?? 0) * (1 - (float)($request->service_discount[$k] ?? 0) / 100),
                            ];
                        }
                    }
                    $refAmount = Helper::calculateDoctorCommission(
                        (int) $request->reference_by,
                        $prods,
                        $servs,
                        $request->has('is_percent_product_commission'),
                        $request->has('is_percent_service_commission')
                    );
                    // Hand the computed amount on: the referral records below read it from the request, so without this
                    // a commission worked out from the doctor's rules would be dropped and the sale left "pending".
                    $request->merge(['primary_reference_amount' => $refAmount]);
                }

                $secAmount = (float) ($request->secondary_reference_amount ?? 0);
                $saleMaster->reference_amount = $refAmount + $secAmount;
                $saleMaster->reference_by = $primaryDocId;
                $saleMaster->doctor_chamber_id = !empty($request->reference_by) ? ($request->doctor_chamber_id ?: null) : null;
                $saleMaster->referral_settlement_status = ($refAmount > 0.001 || $secAmount > 0.001) ? 'settled' : 'pending';
            } else {
                $saleMaster->reference_amount = 0;
                $saleMaster->reference_by = null;
                $saleMaster->doctor_chamber_id = null;
                $saleMaster->referral_settlement_status = 'none';
            }

            if ($saleMaster->save()) {
                if (! empty($requestProductIds)) {
                    foreach ($requestProductIds as $key => $val) {
                        $saleDetails = new SaleDetail();
                        $saleDetails->sale_master_id = $saleMaster->id;
                        $saleDetails->product_id = $requestProductIds[$key];

                        // Use formatted arrays - either batch_id or serial_no will be set, not both
                        $saleDetails->batch_id = isset($batchIds[$key]) ? $batchIds[$key] : null;
                        $saleDetails->serial_no = isset($serialNos[$key]) ? $serialNos[$key] : null;

                        $saleDetails->qty = $request->qty[$key];
                        $saleDetails->purchase_price = \App\Services\SaleCost::resolve((int) $saleDetails->product_id, $saleDetails->batch_id, $saleDetails->serial_no, (float) $request->purchase_price[$key]);
                        $saleDetails->mrp_price = $request->unit_price[$key];
                        $saleDetails->discount = $request->discount[$key];
                        $saleDetails->save();

                        Helper::stockOut($requestProductIds[$key], $request->date, $request->qty[$key], $request->details, $saleInvoiceNo, $type);

                        if (!empty($batchIds[$key])) {
                            Helper::updateBatch($requestProductIds[$key], $batchIds[$key], null, $request->qty[$key], $type);
                        }
                    }
                }
                

                if (! empty($requestServiceTypeIds)) {
                    foreach ($requestServiceTypeIds as $key => $val) {
                        $serviceDetails = new ServiceDetail();
                        $serviceDetails->sale_master_id = $saleMaster->id;
                        $serviceDetails->service_type_id = $requestServiceTypeIds[$key];
                        $serviceDetails->qty = $request->service_qty[$key];
                        $serviceDetails->discount = $request->service_discount[$key];
                        $serviceDetails->service_price = $request->service_price[$key];
                        $serviceDetails->save();
                    }
                } 

                if (! empty($requestProductIds) || ! empty($requestServiceTypeIds)) {
                    Helper::customerLedgerDebit($request, $saleInvoiceNo, $type);

                    // Apply customer advance if selected (Part C)
                    $advanceAppliedAmount = 0;
                    if ($request->filled('applied_advance_id') && (float) $request->applied_advance_amount > 0) {
                        $adv = \App\Models\CustomerAdvance::find($request->applied_advance_id);
                        if ($adv && $adv->status === \App\Models\CustomerAdvance::STATUS_ACTIVE) {
                            $advanceAppliedAmount = min((float) $request->applied_advance_amount, (float) $adv->balance);
                            $advService = app(\App\Services\CustomerAdvanceService::class);
                            $advService->applyAdvanceToSale($saleMaster, $adv, $advanceAppliedAmount);
                        }
                    }

                    // Cash paid on checkout (excluding advance which was credited when received)
                    $totalReceived = (float) $request->paid;
                    $netNewCash = max(0, round($totalReceived - $advanceAppliedAmount, 2));

                    if ($netNewCash > 0) {
                        $cashRequest = clone $request;
                        $cashRequest->paid = $netNewCash;
                        Helper::customerLedgerCredit($cashRequest, $saleInvoiceNo, $type);
                        Helper::incomeLedger($cashRequest, $incomeInvoiceNo, $saleInvoiceNo, $type);
                    }

                    // Add/sync doctor referrals and ledger entries
                    if (!empty($request->reference_by) || !empty($request->secondary_reference_by)) {
                        Helper::syncSaleReferrals($saleMaster, $request, $type);
                    }

                    // Create new redeem entry if points provided
                    if (!empty($request->redeem_points) && $request->redeem_points > 0) {
                        $customer = Customer::find($saleMaster->customer_id);
                        if ($customer) {
                            $membershipService = new MembershipService();
                            $membershipService->redeemPoints(
                                $customer,
                                $saleMaster->id,
                                (float) $request->redeem_points,
                                $saleInvoiceNo
                            );
                        }
                    }

                    // Process membership rewards and referral
                    $membershipService = new MembershipService();
                    $pointsPosted = $membershipService->processSaleRewards(
                        $saleMaster->customer_id,
                        $saleMaster->id,
                        $saleMaster->total_amount,
                        $saleInvoiceNo
                    );
                    if (!$pointsPosted) {
                        session()->flash('warning', 'The sale was saved, but the reward points could not be posted. Please tell the administrator (details are in the log).');
                    }

                    // Link converted proposal if present
                    if ($request->filled('from_proposal_id')) {
                        $proposal = Proposal::find($request->input('from_proposal_id'));
                        if ($proposal) {
                            $proposal->converted_sale_id = $saleMaster->id;
                            $proposal->status = \App\Enums\ProposalStatus::CONVERTED->value;
                            $proposal->save();
                        }
                    }

                    DB::commit();
                    if ($saleMaster->serviceDetails()->exists()) {
                        return redirect()->route('sale.show', $saleMaster->sale_invoice_no)
                            ->with('success', 'Sale saved successfully. Please review the services below to fulfill or book appointments.');
                    }
                    return redirect('admin/sale')->with('success', 'Saved Successfully');
                }

                DB::commit();
                return redirect()->back()->with('warning', 'Please select product or service.');
            }

            DB::commit();
            return redirect()->back()->with('warning', 'Not Saved');
        } catch (StockGuardException $ex) {
            DB::rollback();
            return redirect()->back()->withInput()->withErrors(['stock' => $ex->problems()]);
        } catch (\Exception $ex) {
            DB::rollback();
            return redirect()->back()->with('warning', \App\Classes\Helper::userMessage($ex));
        }
    }

    /**
     * Resolve a SaleMaster instance by numeric ID or invoice number.
     *
     * @param string|int $id
     * @param array $with
     * @return \App\Models\SaleMaster|null
     */
    protected function resolveSaleMaster(string|int $id, array $with = []): ?SaleMaster
    {
        $query = SaleMaster::with($with);

        if (is_numeric($id)) {
            $sale = (clone $query)->where('id', (int) $id)->first();
            if ($sale) {
                return $sale;
            }
        }

        return (clone $query)->where('sale_invoice_no', (string) $id)->first();
    }

    /**
     * Display the specified sale.
     *
     * @param string $id
     * @return \Illuminate\Contracts\View\View
     */
    public function show(string $id): View
    {
        $data = Helper::readAllBranches(function () use ($id) {
            $saleMaster = $this->resolveSaleMaster($id, ['customer.lead', 'vendor', 'doctor', 'doctorChamber', 'creator']);

            // Check if the user has access to this sale
            if (!$saleMaster || !$saleMaster->isAccessibleByAuthUser()) {
                abort(403, 'You do not have permission to view this sale.');
            }

            $data['saleMaster'] = $saleMaster;
            $data['saleDetail'] = SaleDetail::with('product.category', 'batch')->where('sale_master_id', $saleMaster->id)->get();
            $data['serviceDetails'] = ServiceDetail::with(['serviceType.appointmentService', 'appointment.user', 'doneByUser'])->where('sale_master_id', $saleMaster->id)->get();
            $data['professionals'] = User::where('status', 1)->orderBy('name')->get(['id', 'name']);
            $data['setting'] = \App\Classes\Helper::invoiceBranding($saleMaster->vendor_id);

            return $data;
        });

        return view('admin.sales.details', $data);
    }

    /**
     * Print the POS receipt for the specified sale.
     *
     * @param string $id
     * @return \Illuminate\Contracts\View\View
     */
    public function posPrint(string $id): View
    {
        $data = Helper::readAllBranches(function () use ($id) {
            $saleMaster = $this->resolveSaleMaster($id, ['customer.lead', 'vendor', 'doctor', 'doctorChamber', 'creator']);

            // Check if the user has access to this sale
            if (!$saleMaster || !$saleMaster->isAccessibleByAuthUser()) {
                abort(403, 'You do not have permission to view this sale.');
            }

            $data['saleMaster'] = $saleMaster;
            $data['saleDetail'] = SaleDetail::with('product.category', 'batch')->where('sale_master_id', $saleMaster->id)->get();
            $data['serviceDetails'] = ServiceDetail::with('serviceType')->where('sale_master_id', $saleMaster->id)->get();
            $data['setting'] = \App\Classes\Helper::invoiceBranding($saleMaster->vendor_id);

            return $data;
        });
        return view('admin.sales.pos_print', $data);
    }

    /**
     * Show the form for editing the specified sale.
     *
     * @param string $id
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(string $id): View
    {
        $data['paymentTypes'] = Helper::paymentType();
        $data['serviceTypes'] = ServiceType::where('status', 1)->get();
        $data['doctors'] = Doctor::where('status', 1)->with(['chambers', 'categoryCommissions', 'serviceCommissions', 'productCommissions'])->get();

        $saleMaster = $this->resolveSaleMaster($id, ['customer', 'doctorChamber', 'referrals.doctor']);

        // Check if the user has access to this sale
        if (!$saleMaster || !$saleMaster->isAccessibleByAuthUser()) {
            abort(403, 'You do not have permission to edit this sale.');
        }

        $data['saleMaster'] = $saleMaster;
        $realId = $saleMaster->id;

        $data['saleDetail'] = SaleDetail::with('product.category', 'batch')->where('sale_master_id', $realId)->get();
        $data['serviceDetails'] = ServiceDetail::where('sale_master_id', $realId)->get();

        // Get available serial numbers and stock quantity for each product in the sale details
        $data['productSerials'] = [];
        $data['productStocks'] = [];

        foreach ($data['saleDetail'] as $detail) {
            if ($detail->product_id) {
                // Get purchased serials for this product - filter by vendor
                $query = PurchaseDetail::where('product_id', $detail->product_id)
                    ->whereNotNull('serial_no');

                // If user is vendor, only get serials from their own purchases
                if (auth()->user()->branchId()) {
                    $query->whereHas('purchaseMaster', function ($q) {
                        $q->where('vendor_id', auth()->user()->branchId());
                    });
                } else {
                    // General users can only get serials from global purchases
                    $query->whereHas('purchaseMaster', function ($q) {
                        $q->whereNull('vendor_id');
                    });
                }

                $purchases = $query->get(['id', 'serial_no', 'mrp_price']);

                // Get sold serials (excluding current sale's serials)
                $currentSaleSerials = SaleDetail::where('sale_master_id', $realId)
                    ->where('product_id', $detail->product_id)
                    ->whereNotNull('serial_no')
                    ->pluck('serial_no')
                    ->flatten()
                    ->toArray();

                $soldSerials = SaleDetail::where('product_id', $detail->product_id)
                    ->whereNotNull('serial_no')
                    ->where('sale_master_id', '!=', $realId)
                    ->pluck('serial_no')
                    ->flatten()
                    ->toArray();

                $customerReturnedSerials = \App\Models\SaleReturnDetail::where('product_id', $detail->product_id)
                    ->whereNotNull('serial_no')
                    ->pluck('serial_no')
                    ->flatten()
                    ->toArray();

                $supplierReturnedSerials = \App\Models\PurchaseReturnDetail::where('product_id', $detail->product_id)
                    ->whereNotNull('serial_no')
                    ->pluck('serial_no')
                    ->flatten()
                    ->toArray();

                $activeSoldSerials = array_diff($soldSerials, $customerReturnedSerials);

                $result = [];

                // Filter available serials
                foreach ($purchases as $purchase) {
                    foreach ($purchase->serial_no as $serialNumber) {
                        // Include serials that are either currently selected in this sale or not sold/returned yet
                        if (!in_array($serialNumber, $activeSoldSerials) && !in_array($serialNumber, $supplierReturnedSerials)) {
                            $isSelected = in_array($serialNumber, $currentSaleSerials);
                            $result[] = [
                                'purchase_detail_id' => $purchase->id,
                                'serial_number' => $serialNumber,
                                'mrp_price' => $purchase->mrp_price,
                                'selected' => $isSelected
                            ];
                        }
                    }
                }

                $data['productSerials'][$detail->product_id] = $result;
            }
        }

        // Get existing redeemed points for this sale
        $data['existingRedeemPoints'] = RewardLedger::where('sale_master_id', $id)
            ->where('type', RewardLedgerType::REDEEM->value)
            ->sum('points');
        $data['existingRedeemPoints'] = abs($data['existingRedeemPoints']);

        return view('admin.sales.edit', $data);
    }

    /**
     * Update the specified sale in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $requestProductIds = array_filter($request->product_id ?? []);
        $requestServiceTypeIds = array_filter($request->service_type_id ?? []);

        $rules = [
            'customer_id' => ['required'],
            'payment_type_id' => ['required'],
            'date' => ['required'],
            'due_date' => ['nullable', 'date'],
            'delivery_date_time' => ['nullable', 'date'],
            'lab_name' => ['nullable', 'string', 'max:255'],
            'delivery_status' => ['nullable', 'in:' . implode(',', array_column(DeliveryStatus::cases(), 'value'))],
        ];

        if (! empty($requestProductIds)) {
            foreach ($requestProductIds as $key => $val) {
                $rules["product_id.$key"] = 'required';
                $rules["qty.$key"] = 'required|numeric|gt:0';
                $rules["unit_price.$key"] = 'required';
                $rules["product_id"] = 'array';
            }
        }

        if (! empty($requestServiceTypeIds)) {
            foreach ($requestServiceTypeIds as $key => $val) {
                $rules["service_type_id.$key"] = 'required';
                $rules["service_qty.$key"] = 'required';
                $rules["service_price.$key"] = 'required';
                $rules["service_type_id"] = 'array';
            }
        }

        foreach (array_keys($request->old ?? []) as $key) {
            $rules["old.$key.qty"] = 'required|numeric|gt:0';
        }

        $this->validate(
            $request,
            $rules,
            [
                'customer_id.required' => 'The customer field is required.',
                'payment_type_id.required' => 'The payment type field is required.',
                'product_id.required' => 'At least one product must be selected.',
            ]
        );

        // Additional validation to ensure batch/serial exclusivity
        $batchIds = json_decode($request->batch_id_formatted, true) ?? [];
        $serialNos = json_decode($request->serial_no_formatted, true) ?? [];
        $oldBatches = json_decode($request->old_batch_formatted, true) ?? [];
        $oldSerials = json_decode($request->old_serial_formatted, true) ?? [];

        // Validate new items
        if (isset($requestProductIds)) {
            foreach ($requestProductIds as $key => $productId) {
                $hasBatch = !empty($batchIds[$key]);
                $hasSerial = !empty($serialNos[$key]) && is_array($serialNos[$key]) && count($serialNos[$key]) > 0;

                if (!$hasBatch && !$hasSerial) {
                    return redirect()->back()
                        ->withInput()
                        ->withErrors(['product_validation' => 'Please select either a batch or serial number for each product.']);
                }
            }
        }
        

        // Validate existing items
        if (isset($request->old)) {
            foreach ($request->old as $key => $item) {
                $hasBatch = !empty($oldBatches[$key]);
                $hasSerial = !empty($oldSerials[$key]) && is_array($oldSerials[$key]) && count($oldSerials[$key]) > 0;

                if (!$hasBatch && !$hasSerial) {
                    return redirect()->back()
                        ->withInput()
                        ->withErrors(['product_validation' => 'Please select either a batch or serial number for each product.']);
                }
            }
        }

        // Validate existing services
        if (isset($request->old_service)) {
            foreach ($request->old_service as $key => $service) {
                if (empty($service['service_type_id']) || empty($service['service_qty']) || empty($service['service_price'])) {
                    return redirect()->back()
                        ->withInput()
                        ->withErrors(['service_validation' => 'Please fill all required fields for services.']);
                }
            }
        }

        if ($request->filled('redeem_points') && (float) $request->redeem_points > 0) {
            $customer = Customer::find($request->customer_id);
            if ($customer) {
                $setting = LoyaltySetting::current();
                $pointValue = LoyaltySetting::pointValueFor($customer);
                $redeemPoints = (float) $request->redeem_points;
                $invoiceGross = (float) $request->total_amount + ($redeemPoints * $pointValue);
                $maxPoints = MembershipService::maxRedeemablePoints($customer, $invoiceGross, (int) $id);
                if ($redeemPoints > $maxPoints) {
                    $msg = ($maxPoints === 0 && $customer->getAvailableRedeemPoints() < (float) $setting->min_redeem_points)
                        ? "Customer balance is below the minimum {$setting->min_redeem_points} points required to redeem."
                        : "Redeem points cannot exceed {$maxPoints} points for this invoice.";
                    throw ValidationException::withMessages([
                        'redeem_points' => $msg,
                    ]);
                }
            }
        }

        DB::beginTransaction();
        $type = Helper::$sales;
        try {
            $saleMaster = SaleMaster::where('id', $id)->first();

            // Check if the user has access to this sale
            if (!$saleMaster || !$saleMaster->isAccessibleByAuthUser()) {
                abort(403, 'You do not have permission to update this sale.');
            }

            // Re-check the final product lines against this sale's branch stock (its own units stay available)
            $stockLines = [];
            foreach ($request->old ?? [] as $key => $val) {
                $stockLines[] = [
                    'product_id' => $val['product_id'] ?? null,
                    'qty'        => $val['qty'] ?? null,
                    'batch_id'   => $oldBatches[$key] ?? null,
                    'serials'    => $oldSerials[$key] ?? null,
                ];
            }
            foreach ($requestProductIds as $key => $productId) {
                $stockLines[] = [
                    'product_id' => $productId,
                    'qty'        => $request->qty[$key] ?? null,
                    'batch_id'   => $batchIds[$key] ?? null,
                    'serials'    => $serialNos[$key] ?? null,
                ];
            }
            app(StockGuard::class)->assertSaleLines($stockLines, $saleMaster->vendor_id ?: null, $saleMaster->id);

            // Advance applied at checkout was already credited when it was received; keep it out of cash paid
            $appliedAdvance = (float) ($saleMaster->applied_advance_amount ?? 0);
            $netCashPaid = max(0, round((float) $request->paid - $appliedAdvance, 2));

            $saleMaster->customer_id = $request->customer_id;
            $saleMaster->payment_id = $request->payment_type_id;
            $saleMaster->payment_method = $request->payment_method ?: Helper::channelKeyFromPaymentId($request->payment_type_id);
            if ($request->filled('payment_trx_no')) {
                $saleMaster->payment_trx_no = $request->payment_trx_no;
            }
            if ($request->filled('pos_terminal_name')) {
                $saleMaster->pos_terminal_name = $request->pos_terminal_name;
            }
            // Only update settlement status if not already reconciled
            if ($saleMaster->gateway_settlement_status !== 'reconciled') {
                if (Helper::isReconcilableGatewayPayment($request->payment_type_id) && (float) $request->paid > 0) {
                    $saleMaster->gateway_settlement_status = 'pending';
                } else {
                    $saleMaster->gateway_settlement_status = 'not_applicable';
                }
            }
            $saleMaster->date = $request->date;
            $saleMaster->details = $request->details;
            $saleMaster->vat = $request->vat;
            $saleMaster->total_discount = $request->total_discount;
            $saleMaster->total_amount = $request->total_amount;
            $saleMaster->paid = $request->paid;
            $saleMaster->due_date = ($request->filled('due_date') && ($request->total_amount - $request->paid) > 0.009) ? $request->due_date : null;
            $saleMaster->delivery_status = $request->delivery_status ?? DeliveryStatus::ORDERED->value;
            $saleMaster->delivery_date_time = $request->filled('delivery_date_time') ? date('Y-m-d H:i:s', strtotime($request->delivery_date_time)) : null;
            $saleMaster->lab_name = $request->filled('lab_name') ? $request->lab_name : null;
            if ($saleMaster->delivery_status === DeliveryStatus::DELIVERED->value) {
                if (empty($saleMaster->delivered_at)) {
                    $saleMaster->delivered_at = now();
                    $saleMaster->delivered_by = auth()->id();
                }
            } else {
                $saleMaster->delivered_at = null;
                $saleMaster->delivered_by = null;
            }
            $saleMaster->is_percent_product_commission = $request->has('is_percent_product_commission') ? 1 : 0;
            $saleMaster->is_percent_service_commission = $request->has('is_percent_service_commission') ? 1 : 0;
            $saleInvoiceNo = $saleMaster->sale_invoice_no;

            // Handle doctor reference, chamber and commission (including dual/multi referrals)
            if (!empty($request->reference_by) || !empty($request->secondary_reference_by)) {
                $primaryDocId = !empty($request->reference_by) ? (int) $request->reference_by : (int) $request->secondary_reference_by;
                $refAmount = (float) ($request->primary_reference_amount ?? ($request->reference_amount ?? 0));
                if (!empty($request->reference_by) && $refAmount <= 0.001) {
                    $prods = [];
                    if (isset($request->old)) {
                        foreach ($request->old as $k => $val) {
                            $prods[] = [
                                'product_id' => $val['product_id'] ?? null,
                                'qty' => $val['qty'] ?? 1,
                                'line_total' => (float)($val['qty'] ?? 1) * (float)($val['unit_price'] ?? 0) * (1 - (float)($val['discount'] ?? 0) / 100),
                            ];
                        }
                    }
                    if (isset($requestProductIds)) {
                        foreach ($requestProductIds as $k => $pId) {
                            $prods[] = [
                                'product_id' => $pId,
                                'qty' => $request->qty[$k] ?? 1,
                                'line_total' => (float)($request->qty[$k] ?? 1) * (float)($request->unit_price[$k] ?? 0) * (1 - (float)($request->discount[$k] ?? 0) / 100),
                            ];
                        }
                    }
                    $servs = [];
                    if (isset($request->old_service)) {
                        foreach ($request->old_service as $k => $val) {
                            $servs[] = [
                                'service_type_id' => $val['service_type_id'] ?? null,
                                'qty' => $val['service_qty'] ?? 1,
                                'line_total' => (float)($val['service_qty'] ?? 1) * (float)($val['service_price'] ?? 0) * (1 - (float)($val['service_discount'] ?? 0) / 100),
                            ];
                        }
                    }
                    if (isset($requestServiceTypeIds)) {
                        foreach ($requestServiceTypeIds as $k => $sId) {
                            $servs[] = [
                                'service_type_id' => $sId,
                                'qty' => $request->service_qty[$k] ?? 1,
                                'line_total' => (float)($request->service_qty[$k] ?? 1) * (float)($request->service_price[$k] ?? 0) * (1 - (float)($request->service_discount[$k] ?? 0) / 100),
                            ];
                        }
                    }
                    $refAmount = Helper::calculateDoctorCommission(
                        (int) $request->reference_by,
                        $prods,
                        $servs,
                        $request->has('is_percent_product_commission'),
                        $request->has('is_percent_service_commission')
                    );
                    // Hand the computed amount on: the referral records below read it from the request, so without this
                    // a commission worked out from the doctor's rules would be dropped and the sale left "pending".
                    $request->merge(['primary_reference_amount' => $refAmount]);
                }

                $secAmount = (float) ($request->secondary_reference_amount ?? 0);
                $saleMaster->reference_amount = $refAmount + $secAmount;
                $saleMaster->reference_by = $primaryDocId;
                $saleMaster->doctor_chamber_id = !empty($request->reference_by) ? ($request->doctor_chamber_id ?: null) : null;
                $saleMaster->referral_settlement_status = ($refAmount > 0.001 || $secAmount > 0.001) ? 'settled' : 'pending';
            } else {
                $saleMaster->reference_amount = 0;
                $saleMaster->reference_by = null;
                $saleMaster->doctor_chamber_id = null;
                $saleMaster->referral_settlement_status = 'none';
            }

            if ($saleMaster->update()) {
                CustomerLedger::where('ref_invoice_no', $saleInvoiceNo)->where('type', Helper::$sales)->delete();
                Stock::where('ref_invoice_no', $saleInvoiceNo)->where('type', Helper::$sales)->delete();

                $cashRequest = clone $request;
                $cashRequest->paid = $netCashPaid;

                $ledger = Ledger::where('ref_invoice_no', $saleInvoiceNo)->where('type', Helper::$sales)->first();
                if (isset($ledger)) {
                    $ledger->income_amount = $netCashPaid;
                    $ledger->update();
                } elseif ($netCashPaid > 0) {
                    $incomeInvoiceNo = date('ymdhis');
                    Helper::incomeLedger($cashRequest, $incomeInvoiceNo, $saleInvoiceNo, $type);
                }

                // Give back every batch quantity this sale held; the final lines below take it again.
                // (Covers lines removed in the edit and lines moved to another batch.)
                $currentDetails = SaleDetail::where('sale_master_id', $saleMaster->id)->whereNotNull('batch_id')->get(['batch_id', 'product_id', 'qty']);
                foreach ($currentDetails as $detail) {
                    DB::table('batches')->where('id', $detail->batch_id)->where('product_id', $detail->product_id)
                        ->decrement('sale_qty', (float) $detail->qty);
                }

                // Handle existing products
                if (isset($request->old)) {
                    $saleDetailsId = [];
                    foreach ($request->old as $key => $val) {
                        $saleDetailsId[] = $val['id'];
                        $saleDetails = SaleDetail::where('sale_master_id', $saleMaster->id)->findOrFail($val['id']);

                        // Use formatted old arrays - either batch_id or serial_no will be set, not both
                        $batchId = isset($oldBatches[$key]) ? $oldBatches[$key] : null;
                        $serialNo = isset($oldSerials[$key]) ? $oldSerials[$key] : null;

                        if (! empty($batchId)) {
                            DB::table('batches')->where('id', $batchId)->where('product_id', $val['product_id'])
                                ->increment('sale_qty', (float) $val['qty']);
                        }

                        $saleDetails->product_id = $val['product_id'];
                        $saleDetails->batch_id = $batchId;
                        $saleDetails->serial_no = $serialNo;
                        $saleDetails->qty = $val['qty'];
                        $saleDetails->purchase_price = \App\Services\SaleCost::resolve((int) $saleDetails->product_id, $saleDetails->batch_id, $saleDetails->serial_no, (float) $val['purchase_price']);
                        $saleDetails->mrp_price = $val['unit_price'];
                        $saleDetails->discount = $val['discount'];
                        $saleDetails->update();
                        Helper::stockOut($val['product_id'], $request->date, $val['qty'], $request->details, $saleInvoiceNo, $type);
                    }
                }

                // Handle existing services
                if (isset($request->old_service)) {
                    $serviceDetailsId = [];
                    foreach ($request->old_service as $key => $val) {
                        $serviceDetailsId[] = $val['id'];
                        $serviceDetails = ServiceDetail::find($val['id']);

                        $serviceDetails->service_type_id = $val['service_type_id'];
                        $serviceDetails->qty = $val['service_qty'];
                        $serviceDetails->service_price = $val['service_price'];
                        $serviceDetails->discount = $val['service_discount'] ?? 0;
                        $serviceDetails->update();
                    }
                }

                // Delete removed products (all of them when no existing line was kept; their batch qty was released above)
                SaleDetail::where('sale_master_id', $saleMaster->id)->whereNotIn('id', $saleDetailsId ?? [])->delete();

                // Delete removed services
                if (isset($serviceDetailsId)) {
                    ServiceDetail::where('sale_master_id', $saleMaster->id)->whereNotIn('id', $serviceDetailsId)->delete();
                }

                // Handle new products
                if (isset($requestProductIds)) {
                    foreach ($requestProductIds as $key => $val) {
                        $saleDetails = new SaleDetail();
                        $saleDetails->sale_master_id = $id;
                        $saleDetails->product_id = $requestProductIds[$key];

                        // Use formatted arrays - either batch_id or serial_no will be set, not both
                        $saleDetails->batch_id = isset($batchIds[$key]) ? $batchIds[$key] : null;
                        $saleDetails->serial_no = isset($serialNos[$key]) ? $serialNos[$key] : null;

                        $saleDetails->qty = $request->qty[$key];
                        $saleDetails->purchase_price = \App\Services\SaleCost::resolve((int) $saleDetails->product_id, $saleDetails->batch_id, $saleDetails->serial_no, (float) $request->purchase_price[$key]);
                        $saleDetails->mrp_price = $request->unit_price[$key];
                        $saleDetails->discount = $request->discount[$key];
                        $saleDetails->save();

                        Helper::stockOut($requestProductIds[$key], $request->date, $request->qty[$key], $request->details, $saleInvoiceNo, $type);

                        if (!empty($batchIds[$key])) {
                            DB::table('batches')->where('id', $batchIds[$key])->where('product_id', $requestProductIds[$key])
                                ->increment('sale_qty', (float) $request->qty[$key]);
                        }
                    }
                }

                // Handle new services
                if (isset($requestServiceTypeIds)) {
                    foreach ($requestServiceTypeIds as $key => $val) {
                        $serviceDetails = new ServiceDetail();
                        $serviceDetails->sale_master_id = $id;
                        $serviceDetails->service_type_id = $requestServiceTypeIds[$key];
                        $serviceDetails->qty = $request->service_qty[$key];
                        $serviceDetails->service_price = $request->service_price[$key];
                        $serviceDetails->discount = $request->service_discount[$key] ?? 0;
                        $serviceDetails->save();
                    }
                }
                Helper::customerLedgerDebit($request, $saleInvoiceNo, $type);

                if ($netCashPaid > 0) {
                    Helper::customerLedgerCredit($cashRequest, $saleInvoiceNo, $type);
                }

                // Update doctor referral entries and ledger credits
                if (!empty($request->reference_by) || !empty($request->secondary_reference_by)) {
                    Helper::syncSaleReferrals($saleMaster, $request, $type);
                } else {
                    DoctorLedger::where('ref_invoice_no', $saleInvoiceNo)->where('type', '!=', 'Payment')->delete();
                    SaleReferral::where('sale_master_id', $saleMaster->id)->delete();
                }

                // Process redeem points (create, update, or give back when set to 0 on the edit form)
                $redeemPoints = (float) ($request->redeem_points ?? 0);
                $hadRedemption = RewardLedger::where('sale_master_id', $saleMaster->id)
                    ->where('type', RewardLedgerType::REDEEM->value)->exists();
                if ($redeemPoints > 0 || ($hadRedemption && $request->has('redeem_points'))) {
                    $customer = Customer::find($saleMaster->customer_id);
                    if ($customer) {
                        $membershipService = new MembershipService();
                        $membershipService->redeemPoints(
                            $customer,
                            $saleMaster->id,
                            $redeemPoints,
                            $saleInvoiceNo
                        );
                    }
                }

                // Process membership rewards and referral (for updates; earned points follow the new total)
                $membershipService = new MembershipService();
                $pointsPosted = $membershipService->processSaleRewards(
                    $saleMaster->customer_id,
                    $saleMaster->id,
                    $saleMaster->total_amount,
                    $saleInvoiceNo
                );
                if (!$pointsPosted) {
                    session()->flash('warning', 'The sale was updated, but the reward points could not be posted. Please tell the administrator (details are in the log).');
                }

                DB::commit();
                return redirect('admin/sale')->with('success', 'Updated Successfully');
            }

            DB::commit();
            return redirect()->back()->with('warning', 'Not Updated');
        } catch (StockGuardException $ex) {
            DB::rollback();
            return redirect()->back()->withInput()->withErrors(['stock' => $ex->problems()]);
        } catch (\Exception $ex) {
            DB::rollback();
            return redirect()->back()->with('warning', \App\Classes\Helper::userMessage($ex));
        }
    }

    /**
     * Display the sale report creation form.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function saleReportCreate(): View
    {
        $data['customers'] = Customer::with('lead')->orderBy('id', 'desc')->get();

        return view('admin.sales.report', $data);
    }

    /**
     * Generate and display the sale report.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function saleReport(Request $request): View
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $customerId = $request->customer_id;
        $customers = Customer::with('lead')->orderBy('id', 'desc')->get();
        $customerName = $customerId ? Customer::with('lead')->find($customerId)?->name : null;

        $saleMaster = SaleMaster::with('customer.lead')
            ->when($customerId, fn($q) => $q->where('customer_id', $customerId))
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('id', 'desc')
            ->get();
        $setting = Setting::where('id', 1)->first(['company_name']);
        return view('admin.sales.report', compact('saleMaster', 'setting', 'startDate', 'endDate', 'customers', 'customerId', 'customerName'));
    }

    /**
     * Display the Patient Due List.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse
     */
    public function dueList(Request $request)
    {
        if ($request->ajax() && $request->has('draw')) {
            $data = SaleMaster::join('customers', 'customers.id', '=', 'sale_masters.customer_id')
                ->whereNull('sale_masters.deleted_at')
                ->select('sale_masters.*', 'customers.name', DB::raw("SUM(total_amount-paid) as due"))
                ->groupBy('sale_masters.sale_invoice_no')
                ->orderBy('id', 'desc')
                ->havingRaw('due > 0')
                ->get();

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('action', function ($row) {
                    $action = '<div class="btn-group" role="group" aria-label="Action Buttons">';
                    if (Helper::hasPermission('sale_list.edit')) {
                        $action .= '<a href="' . route('sale.edit', $row->sale_invoice_no) . '" class="btn btn-success btn-sm mr-1" title="Edit"><i class="fas fa-edit"></i></a>';
                    }
                    if (Helper::hasPermission('sale_list.view')) {
                        $action .= '<a href="' . route('sale.show', $row->sale_invoice_no) . '" class="btn btn-info btn-sm mr-1" title="Details"><i class="fas fa-eye"></i></a>';
                    }
                    $action .= '<a href="' . route('sale.pos-print', $row->sale_invoice_no) . '" class="btn btn-secondary btn-sm mr-1" target="_blank" title="POS Print"><i class="fas fa-print"></i></a>';
                    if (auth()->user()->isSuperAdmin()) {
                        $action .= '<form action="' . route('sale.destroy', $row->sale_invoice_no) . '" method="POST" style="display:inline-block;" onsubmit="return confirmCancelWithReason(event, \'this sale invoice\')">' . csrf_field() . method_field('DELETE') . '<button type="submit" class="btn btn-danger btn-sm" title="Delete"><i class="far fa-trash-alt"></i></button></form>';
                    }
                    $action .= '</div>';
                    return $action;
                })
                ->rawColumns(['action'])
                ->toJson();
        }

        return Helper::readAllBranches(fn () => $this->dueListIndex($request));
    }

    protected function dueListIndex(Request $request)
    {
        $data['allBranches'] = Helper::isAllBranchesMode();
        $data['branchFilter'] = $data['allBranches'] ? (string) $request->input('branch', 'all') : null;
        $data['branches'] = $data['allBranches'] ? Helper::branchOptions() : [];
        $bf = fn ($q) => Helper::applyBranchFilter($q, $data['branchFilter']);

        // Base query for invoices with an outstanding balance
        $baseQuery = $bf(SaleMaster::with(['customer.lead:id,full_name,phone_number', 'vendor']))
            ->whereRaw('(total_amount - paid) > 0.009');

        // Status filter: all | overdue | today | upcoming | no_date
        $status = $request->input('status', 'all');
        $todayStr = today()->toDateString();

        if ($status === 'overdue') {
            $baseQuery->whereNotNull('due_date')->whereDate('due_date', '<', $todayStr);
        } elseif ($status === 'today') {
            $baseQuery->whereDate('due_date', '=', $todayStr);
        } elseif ($status === 'upcoming') {
            $baseQuery->whereNotNull('due_date')->whereDate('due_date', '>', $todayStr);
        } elseif ($status === 'no_date') {
            $baseQuery->whereNull('due_date');
        }

        // Search query: invoice no, customer name, customer phone, customer UID
        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $baseQuery->where(function ($sub) use ($q) {
                $sub->where('sale_invoice_no', 'like', "%{$q}%")
                    ->orWhereHas('customer', function ($c) use ($q) {
                        $c->where('customer_uid', 'like', "%{$q}%")
                            ->orWhere('name', 'like', "%{$q}%")
                            ->orWhereHas('lead', function ($l) use ($q) {
                                $l->where('full_name', 'like', "%{$q}%")
                                    ->orWhere('phone_number', 'like', "%{$q}%");
                            });
                    });
            });
        }

        // Filter by customer_id if passed
        if ($request->filled('customer_id')) {
            $baseQuery->where('customer_id', $request->customer_id);
        }

        // Filter by date ranges: promise date or sale date
        if ($request->filled('start_due_date') && $request->filled('end_due_date')) {
            $baseQuery->whereBetween('due_date', [$request->start_due_date, $request->end_due_date]);
        }
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $baseQuery->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        // Summary KPI statistics (scoped to branch, across all due statuses)
        $aggBase = $bf(SaleMaster::whereRaw('(total_amount - paid) > 0.009'));
        
        $totalAgg = (clone $aggBase)->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total_amount - paid),0) as amount, COUNT(DISTINCT customer_id) as patients')->first();
        $overdueAgg = (clone $aggBase)->whereNotNull('due_date')->whereDate('due_date', '<', $todayStr)->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total_amount - paid),0) as amount')->first();
        $todayAgg = (clone $aggBase)->whereDate('due_date', '=', $todayStr)->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total_amount - paid),0) as amount')->first();
        $upcomingAgg = (clone $aggBase)->whereNotNull('due_date')->whereDate('due_date', '>', $todayStr)->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total_amount - paid),0) as amount')->first();
        $noDateAgg = (clone $aggBase)->whereNull('due_date')->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total_amount - paid),0) as amount')->first();

        $data['stats'] = [
            'total_count'    => (int) ($totalAgg->cnt ?? 0),
            'total_amount'   => (float) ($totalAgg->amount ?? 0),
            'total_patients' => (int) ($totalAgg->patients ?? 0),
            'overdue_count'  => (int) ($overdueAgg->cnt ?? 0),
            'overdue_amount' => (float) ($overdueAgg->amount ?? 0),
            'today_count'    => (int) ($todayAgg->cnt ?? 0),
            'today_amount'   => (float) ($todayAgg->amount ?? 0),
            'upcoming_count' => (int) ($upcomingAgg->cnt ?? 0),
            'upcoming_amount'=> (float) ($upcomingAgg->amount ?? 0),
            'nodate_count'   => (int) ($noDateAgg->cnt ?? 0),
            'nodate_amount'  => (float) ($noDateAgg->amount ?? 0),
        ];

        // Ordering: Overdue first, then Due Today, then Upcoming promise dates, then no promise date
        $query = (clone $baseQuery)->orderByRaw("
            CASE 
                WHEN due_date IS NOT NULL AND due_date < '{$todayStr}' THEN 1
                WHEN due_date = '{$todayStr}' THEN 2
                WHEN due_date IS NOT NULL AND due_date > '{$todayStr}' THEN 3
                ELSE 4
            END ASC,
            due_date ASC,
            date DESC,
            id DESC
        ");

        $data['sales'] = $query->paginate(25);
        $data['currentStatus'] = $status;

        return view('admin.sales.due_list', $data);
    }

    /**
     * Get list of unpaid / partially paid invoices for a customer or search query (AJAX).
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unpaidInvoices(Request $request): JsonResponse
    {
        $query = SaleMaster::with('customer.lead:id,full_name,phone_number')
            ->whereRaw('(total_amount - paid) > 0.009');

        if (auth()->check() && !auth()->user()->canViewAllBranches() && auth()->user()->branchId()) {
            $query->where('vendor_id', auth()->user()->branchId());
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('sale_invoice_no', 'like', "%{$q}%")
                    ->orWhereHas('customer', function ($c) use ($q) {
                        $c->where('customer_uid', 'like', "%{$q}%")
                            ->orWhereHas('lead', function ($l) use ($q) {
                                $l->where('full_name', 'like', "%{$q}%")
                                    ->orWhere('phone_number', 'like', "%{$q}%");
                            });
                    });
            });
        }

        $invoices = $query->latest('date')->limit(30)->get()->map(function ($sale) {
            $due = round($sale->total_amount - $sale->paid, 2);
            $customerName = $sale->customer?->lead?->full_name ?? $sale->customer?->name ?? 'Customer';
            $customerPhone = $sale->customer?->lead?->phone_number ?? '';

            return [
                'id'              => $sale->id,
                'sale_invoice_no' => $sale->sale_invoice_no,
                'date'            => $sale->date ? $sale->date->format('Y-m-d') : null,
                'date_formatted'  => $sale->date ? $sale->date->format('d M Y') : '—',
                'total_amount'    => (float) $sale->total_amount,
                'paid'            => (float) $sale->paid,
                'due'             => (float) $due,
                'due_date'        => $sale->due_date ? $sale->due_date->format('Y-m-d') : null,
                'due_date_formatted' => $sale->due_date ? $sale->due_date->format('d M Y') : null,
                'customer_id'     => $sale->customer_id,
                'customer_name'   => $customerName,
                'customer_phone'  => $customerPhone,
                'customer_uid'    => $sale->customer?->customer_uid ?? '',
                'label'           => "#{$sale->sale_invoice_no} ({$sale->date?->format('d M Y')}) - Due: ৳" . number_format($due, 2) . " [{$customerName}]",
            ];
        });

        return response()->json($invoices);
    }

    /**
     * Collect due payment against a specific invoice.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function collectDue(Request $request)
    {
        $request->validate([
            'sale_invoice_no' => ['required', 'string', 'exists:sale_masters,sale_invoice_no'],
            'paid_amount'     => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'date'            => ['required', 'date'],
            'new_due_date'    => ['nullable', 'date'],
            'details'         => ['nullable', 'string', 'max:500'],
        ]);

        $sale = SaleMaster::with('customer.lead')->where('sale_invoice_no', $request->sale_invoice_no)->firstOrFail();

        if (!$sale->isAccessibleByAuthUser()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'You do not have permission to collect due for this invoice.'], 403);
            }
            return redirect()->back()->with('warning', 'You do not have permission to collect due for this invoice.');
        }

        $currentDue = round($sale->total_amount - $sale->paid, 2);
        if ($currentDue <= 0.009) {
            $msg = "Invoice #{$sale->sale_invoice_no} is already fully paid.";
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return redirect()->back()->with('warning', $msg);
        }

        if ($request->paid_amount > ($currentDue + 0.01)) {
            $msg = "Payment amount (৳" . number_format($request->paid_amount, 2) . ") cannot exceed the current invoice due (৳" . number_format($currentDue, 2) . ").";
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return redirect()->back()->with('warning', $msg)->withInput();
        }

        DB::beginTransaction();
        try {
            $paidAmount = (float) $request->paid_amount;
            $newPaid = round($sale->paid + $paidAmount, 2);
            $remainingDue = round($sale->total_amount - $newPaid, 2);

            $sale->paid = $newPaid;

            if ($remainingDue <= 0.009) {
                // Fully cleared - remove due promise date
                $sale->due_date = null;
            } elseif ($request->filled('new_due_date')) {
                // Updated promise date for the remaining balance
                $sale->due_date = $request->new_due_date;
            }
            $sale->save();

            // Record in Customer Ledger (Credit = paid)
            $type = Helper::$receive;
            $ledgerDetails = $request->details ? $request->details . " (Due collection for inv #{$sale->sale_invoice_no})" : "Due collection for invoice #{$sale->sale_invoice_no}";
            
            $ledgerRequest = (object) [
                'customer_id' => $sale->customer_id,
                'date'        => $request->date,
                'paid'        => $paidAmount,
                'details'     => $ledgerDetails,
            ];
            Helper::customerLedgerCredit($ledgerRequest, $sale->sale_invoice_no, $type);

            // Record in General Ledger (Income)
            $incomeInvoiceNo = date('ymdhis') . rand(10, 99);
            Helper::incomeLedger($ledgerRequest, $incomeInvoiceNo, $sale->sale_invoice_no, $type);

            DB::commit();

            $customerName = $sale->customer?->lead?->full_name ?? $sale->customer?->name ?? 'Customer';
            $successMessage = "Payment of ৳" . number_format($paidAmount, 2) . " successfully collected for Invoice #{$sale->sale_invoice_no} ({$customerName}). Remaining Due: ৳" . number_format(max(0, $remainingDue), 2);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success'       => true,
                    'message'       => $successMessage,
                    'sale_id'       => $sale->id,
                    'invoice_no'    => $sale->sale_invoice_no,
                    'paid_collected'=> $paidAmount,
                    'total_paid'    => $newPaid,
                    'remaining_due' => max(0, $remainingDue),
                    'new_due_date'  => $sale->due_date ? $sale->due_date->format('Y-m-d') : null,
                    'is_settled'    => ($remainingDue <= 0.009),
                ]);
            }

            return redirect()->back()->with('success', $successMessage);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Due collection failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Due collection failed: ' . \App\Classes\Helper::userMessage($e)], 500);
            }
            return redirect()->back()->with('warning', 'Due collection failed: ' . \App\Classes\Helper::userMessage($e))->withInput();
        }
    }

    /**
     * Display the discount list creation form.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function discountListCreate(): View
    {
        return view('admin.sales.discount_list');
    }

    /**
     * Get the list of discounted sales for DataTables.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function discountList(): JsonResponse
    {
        $data = SaleMaster::join('customers', 'customers.id', '=', 'sale_masters.customer_id')
            ->whereNull('sale_masters.deleted_at')
            ->select('sale_masters.*', 'customers.name', DB::raw("SUM(total_amount-paid) as due"))
            ->groupBy('sale_masters.sale_invoice_no')
            ->orderBy('id', 'desc')
            ->havingRaw('total_discount > 0')
            ->get();

            return DataTables::of($data)
            ->addIndexColumn()
                ->addColumn('action', function ($row) {
                    $action = '<div class="text-center">';
                    if (Helper::hasPermission('sale_list.edit')) {
                        $action .= '<a href="' . route('sale.edit', $row->sale_invoice_no) . '" class="btn btn-success btn-sm mr-1" title="Edit"><i class="fas fa-edit"></i></a>';
                    }
                    if (Helper::hasPermission('sale_list.view')) {
                        $action .= '<a href="' . route('sale.show', $row->sale_invoice_no) . '" class="btn btn-info btn-sm mr-1" title="Details"><i class="fas fa-eye"></i></a>';
                    }

                    $action .= '<a href="' . route('sale.pos-print', $row->sale_invoice_no) . '" class="btn btn-secondary btn-sm mr-1" target="_blank" title="POS Print"><i class="fas fa-print"></i></a>';
                    
                    if (Helper::hasPermission('sale_return.create')) {
                        $action .= '<a href="' . route('sale-returns.create', ['sale_invoice_no' => $row->sale_invoice_no]) . '" class="btn btn-warning btn-sm mr-1" title="Return / Credit Note"><i class="fas fa-undo-alt"></i></a>';
                    }
                    
                    if (auth()->user()->isSuperAdmin()) {
                        $action .= '<form action="' . route('sale.destroy', $row->sale_invoice_no) . '" method="POST" style="display:inline-block;" onsubmit="return confirmCancelWithReason(event, \'this sale invoice\')">' . csrf_field() . method_field('DELETE') . '<button type="submit" class="btn btn-danger btn-sm" title="Delete"><i class="far fa-trash-alt"></i></button></form>';
                    }
                    $action .= '</div>';
                    return $action;
                })
            ->rawColumns(['action'])
            ->toJson();
        
    }

    /**
     * Remove the specified sale from storage.
     *
     * @param string $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        // Cancelling an invoice: Super Admin only, with a reason
        $reason = LedgerReversal::reasonFrom($request);

        $saleMaster = $this->resolveSaleMaster($id);

        // Check if the user has access to this sale
        if (!$saleMaster || !$saleMaster->isAccessibleByAuthUser()) {
            abort(403, 'You do not have permission to delete this sale.');
        }

        $saleInvoiceNo = $saleMaster->sale_invoice_no;
        $customerId = $saleMaster->customer_id;
        $realId = $saleMaster->id;

        // A credit note already returned money or stock against this invoice; deleting the sale would orphan it
        $creditNotes = DB::table('sale_returns')
            ->where(fn ($q) => $q->where('sale_master_id', $realId)->orWhere('sale_invoice_no', $saleInvoiceNo))
            ->whereNull('deleted_at')
            ->pluck('credit_note_no');
        if ($creditNotes->isNotEmpty()) {
            return redirect()->back()->with('warning', 'Cannot delete: this sale has credit note(s) ' . $creditNotes->take(3)->implode(', ') . '. Delete or reverse the credit notes first.');
        }

        DB::beginTransaction();
        try {

            $details = SaleDetail::where('sale_master_id', $realId)->get();

            foreach ($details as $val) {
                if (!empty($val->batch_id)) {
                    DB::table('batches')->where('id', $val->batch_id)->where('product_id', $val->product_id)
                        ->decrement('sale_qty', (float) $val->qty);
                }
            }

            // Return any customer advance this sale used, so it can be applied again or refunded
            app(\App\Services\CustomerAdvanceService::class)->releaseAdvanceFromSale($saleMaster);

            // Delete reward ledgers associated with this sale
            $rewardLedgers = RewardLedger::where('sale_master_id', $realId)->get();
            foreach ($rewardLedgers as $ledger) {
                $ledger->delete();
            }

            // Check if this was the customer's first sale and handle referral reversal
            $saleCount = SaleMaster::where('customer_id', $customerId)->count();

            // If this was the only sale (first sale), reverse any referral reward
            if ($saleCount === 1) {
                $referral = Referral::where('referred_customer_id', $customerId)->first();
                Log::info("Checking referral for customer ID {$customerId}: " . ($referral ? "Found referral with status {$referral->status->value}" : "No referral found"));

                if ($referral && $referral->status->value === ReferralStatus::COMPLETED->value) {
                    // Find and delete the reward ledger entry for this referral
                    $referralRewardLedger = RewardLedger::where('sale_master_id', $realId)->first();
                    if ($referralRewardLedger) {
                        $referralRewardLedger->delete();
                    }

                    // Reset referral status to pending
                    $referral->status = ReferralStatus::PENDING->value;
                    $referral->rewarded_at = null;
                    $referral->save();
                }
            }

            SaleMaster::where('sale_invoice_no', $saleInvoiceNo)->delete();
            SaleDetail::where('sale_master_id', $realId)->delete();
            ServiceDetail::where('sale_master_id', $realId)->delete();
            Stock::where('ref_invoice_no', $saleInvoiceNo)->where('type', Helper::$sales)->delete();

            // Money history is kept: patient, company and doctor ledger rows get reversal entries
            $reversal = app(LedgerReversal::class);
            $what = "Sale {$saleInvoiceNo}";
            $sameInvoice = fn ($q) => $q->where('ref_invoice_no', $saleInvoiceNo)->where('type', Helper::$sales);
            $reversal->reverse('customer_ledgers', $sameInvoice, $reason, $what);
            $reversal->reverse('ledgers', $sameInvoice, $reason, $what);
            $reversal->reverse('doctor_ledgers', $sameInvoice, $reason, $what);
            $reversal->log('sale_cancelled', $saleMaster, [
                'sale_invoice_no' => $saleInvoiceNo,
                'customer_id'     => $customerId,
                'total_amount'    => $saleMaster->total_amount,
                'paid'            => $saleMaster->paid,
            ], $reason);

            // Delete membership upgrade history if customer's tier should be downgraded
            $this->handleMembershipTierDowngrade($customerId);

            DB::commit();
        } catch (\Throwable $ex) {
            DB::rollback();
            Log::error("Sale delete failed for {$saleInvoiceNo}: " . $ex->getMessage());
            return redirect()->back()->with('warning', 'The sale could not be cancelled. Nothing was changed. Please try again or contact the administrator.');
        }

        return redirect()->back()->with('success', "Sale {$saleInvoiceNo} cancelled. Its ledger entries were reversed, not deleted.");
    }

    /**
     * Handle membership tier downgrade when sale is deleted
     *
     * @param int $customerId
     * @return void
     */
    private function handleMembershipTierDowngrade(int $customerId): void
    {
        $customer = Customer::find($customerId);
        if (!$customer) {
            return;
        }

        $currentTierId = $customer->membership_tier_id;

        // Calculate total purchases remaining after deletion
        $totalPurchases = SaleMaster::where('customer_id', $customerId)
            ->sum('total_amount');

        // Find appropriate tier based on remaining purchases
        $newTier = MembershipTier::where('min_purchase', '<=', $totalPurchases)
            ->where(function ($query) use ($totalPurchases) {
                $query->whereNull('max_purchase')
                      ->orWhere('max_purchase', '>=', $totalPurchases);
            })
            ->orderByDesc('min_purchase')
            ->first();

        // If tier should be downgraded
        if ($newTier && $customer->membership_tier_id != $newTier->id) {
            // Update customer's tier
            $customer->update(['membership_tier_id' => $newTier->id]);

            // Record downgrade history
            MembershipUpgradeHistory::create([
                'customer_id' => $customer->id,
                'old_tier_id' => $currentTierId,
                'new_tier_id' => $newTier->id,
                'upgraded_at' => now(),
            ]);
        }
    }

    /**
     * Display a listing of the doctor payments.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function doctorPaymentIndex(Request $request): View
    {
        $data['payments'] = DoctorLedger::with('doctor')
            ->where('type', 'Payment')
            ->latest()
            ->paginate(20);

        return view('admin.doctor_payment.index', $data);
    }

    /**
     * Show the form for creating a new doctor payment.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function doctorPaymentCreate(): View
    {
        $data['doctors'] = Doctor::where('status', 1)->get();
        return view('admin.doctor_payment.create', $data);
    }

    /**
     * Store a newly created doctor payment in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function doctorPaymentStore(Request $request): RedirectResponse
    {
        $request->validate([
            'doctor_id' => ['required'],
            'date' => ['required'],
            'debit' => ['required', 'numeric', 'min:0'],
            'details' => ['nullable'],
        ]);

        // Calculate doctor's due amount
        $ledgers = DoctorLedger::where('doctor_id', $request->doctor_id)->get();
        $totalCredit = $ledgers->sum('credit');
        $totalDebit = $ledgers->sum('debit');
        $dueAmount = $totalCredit - $totalDebit;

        // Validate payment amount doesn't exceed due amount
        if ($request->debit > $dueAmount) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['debit' => "Payment amount ({$request->debit}) cannot exceed due amount ({$dueAmount})."]);
        }

        DB::beginTransaction();
        try {
            $refInvoiceNo = 'DP-' . date('ymdhis');

            $doctorLedger = new DoctorLedger();
            $doctorLedger->doctor_id = $request->doctor_id;
            $doctorLedger->date = $request->date;
            $doctorLedger->details = $request->details ? $request->details . ' (Payment ref no - ' . $refInvoiceNo . ')' : 'Payment ref no - ' . $refInvoiceNo;
            $doctorLedger->debit = $request->debit; // Payment to doctor is debit in their ledger
            $doctorLedger->credit = 0;
            $doctorLedger->type = 'Payment';
            $doctorLedger->ref_invoice_no = $refInvoiceNo;
            $doctorLedger->save();

            DB::commit();
            return redirect('admin/doctors-payment')->with('success', 'Payment Saved Successfully');
        } catch (\Exception $ex) {
            DB::rollback();
            return redirect()->back()->with('warning', \App\Classes\Helper::userMessage($ex));
        }
    }

    /**
     * Show the form for editing the specified doctor payment.
     *
     * @param int $id
     * @return \Illuminate\Contracts\View\View
     */
    public function doctorPaymentEdit(int $id): View
    {
        $data['doctors'] = Doctor::where('status', 1)->get();
        $data['payment'] = DoctorLedger::where('id', $id)->where('type', 'Payment')->first();

        // Calculate current due amount for this doctor
        if ($data['payment']) {
            $dueResponse = $this->getDoctorDue($data['payment']->doctor_id);
            $data['due_amount'] = $dueResponse['due'] ?? 0.00;
        }

        return view('admin.doctor_payment.edit', $data);
    }

    /**
     * Update the specified doctor payment in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function doctorPaymentUpdate(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'doctor_id' => ['required'],
            'date' => ['required'],
            'debit' => ['required', 'numeric', 'min:0'],
            'details' => ['nullable'],
        ]);

        DB::beginTransaction();
        try {
            $doctorLedger = DoctorLedger::where('id', $id)->where('type', 'Payment')->first();

            if (!$doctorLedger) {
                return redirect()->back()->with('warning', 'Payment not found');
            }

            // Calculate doctor's due amount (excluding current payment being edited)
            $ledgers = DoctorLedger::where('doctor_id', $request->doctor_id)
                ->where('id', '!=', $id)
                ->get();
            $totalCredit = $ledgers->sum('credit');
            $totalDebit = $ledgers->sum('debit');
            $dueAmount = $totalCredit - $totalDebit;

            // Validate payment amount doesn't exceed due amount
            if ($request->debit > $dueAmount) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['debit' => "Payment amount ({$request->debit}) cannot exceed due amount ({$dueAmount})."]);
            }

            $refInvoiceNo = $doctorLedger->ref_invoice_no;

            $doctorLedger->doctor_id = $request->doctor_id;
            $doctorLedger->date = $request->date;
            $doctorLedger->details = $request->details ? $request->details . ' (Payment ref no - ' . $refInvoiceNo . ')' : 'Payment ref no - ' . $refInvoiceNo;
            $doctorLedger->debit = $request->debit;
            $doctorLedger->credit = 0;
            $doctorLedger->update();

            DB::commit();
            return redirect('admin/doctors-payment')->with('success', 'Payment Updated Successfully');
        } catch (\Exception $ex) {
            DB::rollback();
            return redirect()->back()->with('warning', \App\Classes\Helper::userMessage($ex));
        }
    }

    /**
     * Remove the specified doctor payment from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function doctorPaymentDestroy(int $id): RedirectResponse
    {
        try {
            $ledger = DoctorLedger::where('id', $id)->where('type', 'Payment')->first();

            if ($ledger) {
                $ledger->delete(); 
            }

            return redirect()->back()->with('success', 'Payment Deleted Successfully');
        } catch (\Exception $ex) {
            return redirect()->back()->with('warning', \App\Classes\Helper::userMessage($ex));
        }
    }

    public function getDoctorDue(int $doctorId)
    {
        $ledgers = DoctorLedger::where('doctor_id', $doctorId)->get();

        $totalCredit = $ledgers->sum('credit'); // Amount owed to doctor (from sales)
        $totalDebit = $ledgers->sum('debit');   // Amount paid to doctor

        $due = $totalCredit - $totalDebit;

        return [
            'due' => max(0, $due),
            'total_credit' => $totalCredit,
            'total_debit' => $totalDebit,
        ];
    }

    /**
     * Display the Report & Delivery Fulfillment Hub.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function fulfillmentHub(Request $request)
    {
        return Helper::readAllBranches(fn () => $this->fulfillmentHubData($request));
    }

    /**
     * Data loader for the Fulfillment Hub supporting multi-branch scoping.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View
     */
    protected function fulfillmentHubData(Request $request)
    {
        $data['allBranches'] = Helper::isAllBranchesMode();
        $data['branchFilter'] = $data['allBranches'] ? (string) $request->input('branch', 'all') : null;
        $data['branches'] = $data['allBranches'] ? Helper::branchOptions() : [];
        $bf = fn ($q) => Helper::applyBranchFilter($q, $data['branchFilter']);

        $tab = $request->input('tab', 'services'); // 'services' | 'products' | 'all'
        $status = $request->input('status', 'all'); // 'all' | DeliveryStatus values
        $q = trim((string) $request->input('q', ''));
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Base query with relations
        $query = $bf(SaleMaster::with([
            'customer.lead:id,full_name,phone_number',
            'vendor:id,name',
            'deliveredBy:id,name',
            'saleDetails.product.category',
            'saleDetails.batch',
            'serviceDetails.serviceType',
        ]));

        // Filter by tab
        if ($tab === 'services') {
            $query->whereHas('serviceDetails');
        } elseif ($tab === 'products') {
            $query->whereHas('saleDetails');
        }

        // Status filter
        if ($status !== 'all' && in_array($status, DeliveryStatus::values(), true)) {
            $query->where('delivery_status', $status);
        }

        // Search query
        if (!empty($q)) {
            $query->where(function ($sub) use ($q) {
                $sub->where('sale_invoice_no', 'like', "%{$q}%")
                    ->orWhere('lab_name', 'like', "%{$q}%")
                    ->orWhereHas('customer', function ($c) use ($q) {
                        $c->where('customer_uid', 'like', "%{$q}%")
                            ->orWhere('name', 'like', "%{$q}%")
                            ->orWhereHas('lead', function ($l) use ($q) {
                                $l->where('full_name', 'like', "%{$q}%")
                                    ->orWhere('phone_number', 'like', "%{$q}%");
                            });
                    })
                    ->orWhereHas('serviceDetails.serviceType', function ($st) use ($q) {
                        $st->where('service_type_name', 'like', "%{$q}%");
                    })
                    ->orWhereHas('saleDetails.product', function ($p) use ($q) {
                        $p->where('model', 'like', "%{$q}%");
                    });
            });
        }

        // Date range filter
        if (!empty($startDate) && !empty($endDate)) {
            $query->whereBetween('date', [$startDate, $endDate]);
        }

        // Aggregate statistics for KPI cards (scoped to active branch filter)
        $aggBase = $bf(SaleMaster::query());
        $todayStr = today()->toDateString();

        $servicesBase = (clone $aggBase)->whereHas('serviceDetails');
        $productsBase = (clone $aggBase)->whereHas('saleDetails');

        $stats = [
            'service_total'       => (int) (clone $servicesBase)->count(),
            'service_ordered'     => (int) (clone $servicesBase)->where('delivery_status', DeliveryStatus::ORDERED->value)->count(),
            'service_ready'       => (int) (clone $servicesBase)->where('delivery_status', DeliveryStatus::READY_FOR_DELIVERY->value)->count(),
            'service_delivered'   => (int) (clone $servicesBase)->where('delivery_status', DeliveryStatus::DELIVERED->value)->count(),

            'product_total'       => (int) (clone $productsBase)->count(),
            'product_ordered'     => (int) (clone $productsBase)->where('delivery_status', DeliveryStatus::ORDERED->value)->count(),
            'product_lab'         => (int) (clone $productsBase)->where('delivery_status', DeliveryStatus::SENT_TO_LAB->value)->count(),
            'product_ready'       => (int) (clone $productsBase)->where('delivery_status', DeliveryStatus::READY_FOR_DELIVERY->value)->count(),
            'product_delivered'   => (int) (clone $productsBase)->where('delivery_status', DeliveryStatus::DELIVERED->value)->count(),

            'delivered_today'     => (int) (clone $aggBase)->whereDate('delivered_at', $todayStr)->count(),
            'total_pending'       => (int) (clone $aggBase)->whereIn('delivery_status', [
                DeliveryStatus::ORDERED->value,
                DeliveryStatus::SENT_TO_LAB->value,
                DeliveryStatus::READY_FOR_DELIVERY->value,
            ])->count(),
        ];

        // Smart priority ordering: Ready first (ready to be picked up), then Lab, then Ordered, then Delivered
        $sales = $query->orderByRaw("
            CASE 
                WHEN delivery_status = 'ready_for_delivery' THEN 1
                WHEN delivery_status = 'sent_to_lab' THEN 2
                WHEN delivery_status = 'ordered' THEN 3
                WHEN delivery_status = 'delivered' THEN 4
                ELSE 5
            END ASC,
            CASE WHEN delivery_date_time IS NOT NULL THEN 0 ELSE 1 END ASC,
            delivery_date_time ASC,
            date DESC,
            id DESC
        ")->paginate(20)->withQueryString();

        $data['sales'] = $sales;
        $data['stats'] = $stats;
        $data['tab'] = $tab;
        $data['status'] = $status;
        $data['q'] = $q;
        $data['startDate'] = $startDate;
        $data['endDate'] = $endDate;

        return view('admin.sales.fulfillment', $data);
    }

    /**
     * Fast update for fulfillment delivery status, promise date, lab name,
     * inline due collection, and patient SMS notifications.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function updateDeliveryStatus(Request $request, string $id)
    {
        $request->validate([
            'delivery_status'    => ['required', 'string', 'in:' . implode(',', DeliveryStatus::values())],
            'delivery_date_time' => ['nullable', 'date'],
            'lab_name'           => ['nullable', 'string', 'max:255'],
            'collect_due'        => ['nullable'],
            'paid_amount'        => ['nullable', 'numeric', 'min:0.01', 'max:99999999'],
            'payment_date'       => ['nullable', 'date'],
            'payment_details'    => ['nullable', 'string', 'max:500'],
            'send_sms'           => ['nullable'],
            'custom_message'     => ['nullable', 'string', 'max:500'],
        ]);

        $sale = SaleMaster::with(['customer.lead', 'saleDetails', 'serviceDetails'])->findOrFail($id);

        if (!$sale->isAccessibleByAuthUser()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'You do not have permission to update this invoice.'], 403);
            }
            return redirect()->back()->with('warning', 'You do not have permission to update this invoice.');
        }

        $currentDue = round($sale->total_amount - $sale->paid, 2);
        $collectDue = $request->boolean('collect_due');
        $paidAmount = (float) ($request->paid_amount ?? 0);

        if ($collectDue && $paidAmount > ($currentDue + 0.01)) {
            $msg = "Payment amount (৳" . number_format($paidAmount, 2) . ") cannot exceed current invoice due (৳" . number_format($currentDue, 2) . ").";
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return redirect()->back()->with('warning', $msg)->withInput();
        }

        DB::beginTransaction();
        try {
            $newStatus = DeliveryStatus::from($request->delivery_status);
            $sale->delivery_status = $newStatus->value;

            if ($request->has('delivery_date_time')) {
                $sale->delivery_date_time = $request->filled('delivery_date_time')
                    ? date('Y-m-d H:i:s', strtotime($request->delivery_date_time))
                    : null;
            }

            if ($request->has('lab_name')) {
                $sale->lab_name = $request->filled('lab_name') ? trim($request->lab_name) : null;
            }

            if ($newStatus === DeliveryStatus::DELIVERED) {
                if (empty($sale->delivered_at)) {
                    $sale->delivered_at = now();
                    $sale->delivered_by = auth()->id();
                }
            } else {
                $sale->delivered_at = null;
                $sale->delivered_by = null;
            }

            // Inline Due Collection
            $remainingDue = $currentDue;
            if ($collectDue && $paidAmount > 0) {
                $newPaid = round($sale->paid + $paidAmount, 2);
                $remainingDue = round($sale->total_amount - $newPaid, 2);
                $sale->paid = $newPaid;

                if ($remainingDue <= 0.009) {
                    $sale->due_date = null;
                }

                $paymentDate = $request->payment_date ?: today()->toDateString();
                $statusName = $sale->deliveryStatusLabel();
                $ledgerDetails = $request->payment_details
                    ? $request->payment_details . " (Collected on delivery: {$statusName} for inv #{$sale->sale_invoice_no})"
                    : "Fulfillment collection for invoice #{$sale->sale_invoice_no} ({$statusName})";

                $ledgerRequest = (object) [
                    'customer_id' => $sale->customer_id,
                    'date'        => $paymentDate,
                    'paid'        => $paidAmount,
                    'details'     => $ledgerDetails,
                ];

                Helper::customerLedgerCredit($ledgerRequest, $sale->sale_invoice_no, Helper::$receive);

                $incomeInvoiceNo = date('ymdhis') . rand(10, 99);
                Helper::incomeLedger($ledgerRequest, $incomeInvoiceNo, $sale->sale_invoice_no, Helper::$receive);
            }

            $sale->save();
            DB::commit();

            // Optional Patient SMS Alert
            $smsSent = false;
            $smsPhone = $sale->customer?->lead?->phone_number ?? $sale->customer?->phone ?? null;
            $customerName = $sale->customer?->lead?->full_name ?? $sale->customer?->name ?? 'Valued Patient';

            if ($request->boolean('send_sms') && !empty($smsPhone)) {
                try {
                    $messageBody = $request->filled('custom_message')
                        ? trim($request->custom_message)
                        : $this->buildFulfillmentSmsMessage($sale, $newStatus, $customerName);

                    app(\App\Services\SmsService::class)->sendAndLogSms(
                        $smsPhone,
                        $messageBody,
                        'customer',
                        (int) $sale->customer_id
                    );
                    $smsSent = true;
                } catch (\Exception $smsEx) {
                    Log::warning('Fulfillment SMS delivery failed: ' . $smsEx->getMessage());
                }
            }

            $statusLabel = $sale->deliveryStatusLabel();
            $message = "Invoice #{$sale->sale_invoice_no} status updated to '{$statusLabel}'.";
            if ($collectDue && $paidAmount > 0) {
                $message .= " Payment of ৳" . number_format($paidAmount, 2) . " collected.";
            }
            if ($smsSent) {
                $message .= " Patient SMS alert sent.";
            }

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success'           => true,
                    'message'           => $message,
                    'sale_id'           => $sale->id,
                    'invoice_no'        => $sale->sale_invoice_no,
                    'delivery_status'   => $sale->delivery_status,
                    'delivery_label'    => $statusLabel,
                    'badge_color'       => $sale->deliveryStatusEnum()->badgeColor(),
                    'badge_bg'          => $sale->deliveryStatusEnum()->badgeBg(),
                    'delivered_at'      => $sale->delivered_at ? $sale->delivered_at->format('d M Y, h:i A') : null,
                    'delivered_by'      => $sale->deliveredBy?->name ?? null,
                    'paid'              => (float) $sale->paid,
                    'remaining_due'     => max(0, $remainingDue),
                    'sms_sent'          => $smsSent,
                ]);
            }

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Fulfillment status update failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Status update failed: ' . \App\Classes\Helper::userMessage($e)], 500);
            }
            return redirect()->back()->with('warning', 'Status update failed: ' . \App\Classes\Helper::userMessage($e))->withInput();
        }
    }

    /**
     * Build contextual SMS message for patients based on sale type and fulfillment status.
     *
     * @param \App\Models\SaleMaster $sale
     * @param \App\Enums\DeliveryStatus $status
     * @param string $customerName
     * @return string
     */
    protected function buildFulfillmentSmsMessage(SaleMaster $sale, DeliveryStatus $status, string $customerName): string
    {
        $company = Setting::first()?->company_name ?? 'Our Hearing Care Center';
        $isService = $sale->isServiceSale();

        if ($isService) {
            return match($status) {
                DeliveryStatus::READY_FOR_DELIVERY => "Dear {$customerName}, your hearing assessment report (Inv #{$sale->sale_invoice_no}) is ready for collection at {$company}. Thank you.",
                DeliveryStatus::DELIVERED => "Dear {$customerName}, thank you for collecting your assessment report from {$company}. Wishing you good hearing health.",
                default => "Dear {$customerName}, your hearing assessment at {$company} is booked. Inv #{$sale->sale_invoice_no}.",
            };
        }

        return match($status) {
            DeliveryStatus::SENT_TO_LAB => "Dear {$customerName}, your order (Inv #{$sale->sale_invoice_no}) has been dispatched to our lab for fabrication.",
            DeliveryStatus::READY_FOR_DELIVERY => "Dear {$customerName}, your hearing device (Inv #{$sale->sale_invoice_no}) is ready for fitting & trial at {$company}. Please visit us at your convenience.",
            DeliveryStatus::DELIVERED => "Dear {$customerName}, thank you for choosing {$company}! Your hearing device has been delivered and fitted. Please contact us for any follow-up care.",
            default => "Dear {$customerName}, your order (Inv #{$sale->sale_invoice_no}) has been booked at {$company}.",
        };
    }

    /**
     * Fast barcode / invoice scan lookup endpoint.
     * Looks up an invoice by scanned barcode, serial number, or invoice number.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function scanLookup(Request $request): JsonResponse
    {
        $code = trim((string) $request->input('code', ''));
        // Strip common prefix/suffix scanner characters (e.g. * or # or whitespace)
        $cleanCode = trim($code, "*# \t\n\r\0\x0B");

        if (empty($cleanCode)) {
            return response()->json(['success' => false, 'message' => 'Please enter or scan a barcode.'], 422);
        }

        // Search by sale_invoice_no exact or partial, or by product serial number
        $sale = SaleMaster::with([
            'customer.lead:id,full_name,phone_number,address',
            'vendor:id,name',
            'deliveredBy:id,name',
            'saleDetails.product.category',
            'serviceDetails.serviceType',
        ])
        ->where(function ($q) use ($cleanCode) {
            $q->where('sale_invoice_no', $cleanCode)
              ->orWhere('sale_invoice_no', 'like', "%{$cleanCode}")
              ->orWhereHas('saleDetails', function ($sd) use ($cleanCode) {
                  $sd->where('serial_no', 'like', "%{$cleanCode}%");
              });
        })
        ->latest('id')
        ->first();

        if (!$sale) {
            return response()->json([
                'success' => false,
                'message' => "No invoice found matching barcode: \"{$code}\"",
            ], 404);
        }

        $due = max(0, (float) $sale->total_amount - (float) $sale->paid);
        $isService = $sale->isServiceSale();
        $statusEnum = $sale->deliveryStatusEnum();

        // Build list of items summary
        $itemNames = [];
        foreach ($sale->serviceDetails as $sd) {
            $itemNames[] = ($sd->serviceType?->service_type_name ?? 'Service') . " (x{$sd->qty})";
        }
        foreach ($sale->saleDetails as $pd) {
            $name = trim(($pd->product?->category?->category_name ?? '') . ' - ' . ($pd->product?->model ?? 'Product'), ' -');
            $itemNames[] = $name . " (x{$pd->qty})";
        }

        $lead = $sale->customer?->lead;
        $patientName = $lead?->full_name ?? $sale->customer?->name ?? 'Walk-in Patient';
        $patientPhone = $lead?->phone_number ?? $sale->customer?->phone ?? '';

        return response()->json([
            'success'            => true,
            'sale_id'            => $sale->id,
            'invoice_no'         => $sale->sale_invoice_no,
            'patient_name'       => $patientName,
            'patient_phone'      => $patientPhone,
            'items_summary'      => !empty($itemNames) ? implode(', ', $itemNames) : 'No items listed',
            'total_amount'       => (float) $sale->total_amount,
            'paid_amount'        => (float) $sale->paid,
            'due_amount'         => (float) $due,
            'has_due'            => ($due > 0.009),
            'is_service'         => $isService,
            'delivery_status'    => $sale->delivery_status,
            'delivery_label'     => $statusEnum->label($isService),
            'badge_color'        => $statusEnum->badgeColor(),
            'badge_bg'           => $statusEnum->badgeBg(),
            'icon'               => $statusEnum->icon(),
            'delivery_date_time' => $sale->delivery_date_time ? $sale->delivery_date_time->format('d M Y, h:i A') : null,
            'lab_name'           => $sale->lab_name,
            'delivered_at'       => $sale->delivered_at ? $sale->delivered_at->format('d M Y, h:i A') : null,
            'delivered_by'       => $sale->deliveredBy?->name,
            'detail_url'         => url('admin/sale/' . $sale->id),
            'pos_print_url'      => route('sale.pos-print', $sale->id),
        ]);
    }

    /**
     * Mark a sold service line item as Delivered/Done now.
     */
    public function markServiceDoneNow(Request $request, int $id)
    {
        $serviceDetail = ServiceDetail::with(['saleMaster', 'appointment'])->findOrFail($id);

        if (!$serviceDetail->saleMaster || !$serviceDetail->saleMaster->isAccessibleByAuthUser()) {
            abort(403, 'Unauthorized access to this service.');
        }

        $doneBy = $request->input('done_by') ? (int) $request->input('done_by') : auth()->id();
        $notes = $request->input('notes');

        $serviceDetail->fulfillment_status = ServiceDetail::STATUS_DONE;
        $serviceDetail->done_by = $doneBy;
        $serviceDetail->done_at = now();
        if ($notes) {
            $serviceDetail->notes = $notes;
        }
        $serviceDetail->save();

        // If this service was linked to a scheduled appointment, automatically complete it
        if ($serviceDetail->appointment && $serviceDetail->appointment->status !== \App\Enums\AppointmentStatus::COMPLETED) {
            $serviceDetail->appointment->status = \App\Enums\AppointmentStatus::COMPLETED;
            $serviceDetail->appointment->save();
        }

        $staffName = User::find($doneBy)?->name ?? 'Staff';
        $message = "Service ({$serviceDetail->serviceType?->service_type_name}) marked as Done by {$staffName}.";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success'      => true,
                'message'      => $message,
                'status'       => ServiceDetail::STATUS_DONE,
                'status_label' => $serviceDetail->statusLabel(),
                'status_badge' => $serviceDetail->statusBadgeClass(),
                'done_by_name' => $staffName,
                'done_at'      => $serviceDetail->done_at->format('d M Y, h:i A'),
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Mark a sold service line item to book later.
     */
    public function markServiceBookLater(Request $request, int $id)
    {
        $serviceDetail = ServiceDetail::with('saleMaster')->findOrFail($id);

        if (!$serviceDetail->saleMaster || !$serviceDetail->saleMaster->isAccessibleByAuthUser()) {
            abort(403, 'Unauthorized access to this service.');
        }

        $serviceDetail->fulfillment_status = ServiceDetail::STATUS_NOT_BOOKED;
        $serviceDetail->save();

        $message = "Service ({$serviceDetail->serviceType?->service_type_name}) marked to book later. It remains in the pending services list.";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success'      => true,
                'message'      => $message,
                'status'       => ServiceDetail::STATUS_NOT_BOOKED,
                'status_label' => $serviceDetail->statusLabel(),
                'status_badge' => $serviceDetail->statusBadgeClass(),
            ]);
        }

        return redirect()->back()->with('info', $message);
    }

    /**
     * Display list of services sold that are not yet booked or done.
     */
    public function pendingServices(Request $request)
    {
        $statusFilter = $request->input('status', 'not_booked');
        $bookingTypeFilter = $request->input('booking_type');
        $branchId = $request->input('vendor_id');
        $search = trim($request->input('search', ''));

        $query = ServiceDetail::with([
            'serviceType.appointmentService',
            'saleMaster.customer.lead',
            'saleMaster.vendor',
            'appointment.user',
            'doneByUser'
        ])
        ->whereHas('saleMaster', function ($q) use ($branchId) {
            if ($branchId) {
                $q->where('vendor_id', $branchId);
            }
        });

        // Filter by fulfillment status
        if ($statusFilter === 'all') {
            // all
        } elseif ($statusFilter === 'pending') {
            $query->whereIn('fulfillment_status', [ServiceDetail::STATUS_NOT_BOOKED, ServiceDetail::STATUS_BOOKED]);
        } elseif ($statusFilter) {
            $query->where('fulfillment_status', $statusFilter);
        }

        // Filter by booking type
        if ($bookingTypeFilter) {
            $query->whereHas('serviceType', function ($q) use ($bookingTypeFilter) {
                $q->where('booking_type', $bookingTypeFilter);
            });
        }

        // Search by customer name, phone, or invoice no
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('saleMaster', function ($sq) use ($search) {
                    $sq->where('sale_invoice_no', 'like', "%{$search}%")
                        ->orWhereHas('customer.lead', function ($lq) use ($search) {
                            $lq->where('full_name', 'like', "%{$search}%")
                                ->orWhere('phone_number', 'like', "%{$search}%");
                        });
                })->orWhereHas('serviceType', function ($tq) use ($search) {
                    $tq->where('service_type_name', 'like', "%{$search}%");
                });
            });
        }

        // Order by oldest sale first (highest days waiting first)
        $services = $query->orderBy('created_at', 'asc')->paginate(25);

        // Stats for quick tabs
        $notBookedCount = ServiceDetail::where('fulfillment_status', ServiceDetail::STATUS_NOT_BOOKED)->count();
        $bookedCount    = ServiceDetail::where('fulfillment_status', ServiceDetail::STATUS_BOOKED)->count();
        $doneCount      = ServiceDetail::where('fulfillment_status', ServiceDetail::STATUS_DONE)->count();

        $professionals = User::where('status', 1)->orderBy('name')->get(['id', 'name']);
        $branches = Helper::branchOptions();

        return view('admin.sales.pending_services', compact(
            'services',
            'statusFilter',
            'bookingTypeFilter',
            'branchId',
            'search',
            'notBookedCount',
            'bookedCount',
            'doneCount',
            'professionals',
            'branches'
        ));
    }
}
