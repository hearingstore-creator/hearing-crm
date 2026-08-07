<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Enums\DeliveryStatus;
use App\Enums\ReferralStatus;
use App\Enums\RewardLedgerType;
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
use App\Services\MembershipService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    }

    /**
     * Display a listing of the sales.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function index(Request $request)
    {
        $query = SaleMaster::with(relations: ['customer.lead:id,full_name', 'vendor']);

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

        $data['sales'] = $query->latest()->paginate(20);

        $data['customers'] = Customer::with('lead:id,full_name')->select('id', 'lead_id')
        ->orderBy('id', 'desc')
        ->get();

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

                    if (Helper::hasPermission('sale_list.delete')) {
                        $action .= '<form action="' . route('sale.destroy', $row->sale_invoice_no) . '" method="POST" onsubmit="return confirmDelete(event)">' . csrf_field() . method_field('DELETE') . '<button type="submit" class="btn btn-danger btn-sm" title="Delete"><i class="far fa-trash-alt"></i></button></form>';
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
    public function create(): View
    {
        $data['paymentTypes'] = Helper::paymentType();
        $data['serviceTypes'] = ServiceType::where('status', 1)->get();
        $data['doctors'] = Doctor::where('status', 1)->get();
        $data['districts'] = District::orderBy('name')->get();

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

        return response()->json([
            'success' => true,
            'available_points' => $availablePoints
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
            'delivery_status' => ['nullable', 'in:' . implode(',', array_column(DeliveryStatus::cases(), 'value'))],
        ];

        if (! empty($requestProductIds)) {
            foreach ($requestProductIds as $key => $val) {
                $rules["product_id.$key"] = 'required';
                $rules["qty.$key"] = 'required';
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
            $saleMaster = new SaleMaster();
            $saleMaster->customer_id = $request->customer_id;
            $saleMaster->payment_id = $request->payment_type_id;
            $saleMaster->date = $request->date;
            $saleMaster->details = $request->details;
            $saleMaster->sale_invoice_no = $saleInvoiceNo;
            $saleMaster->vat = $request->vat;
            $saleMaster->total_discount = $request->total_discount;
            $saleMaster->total_amount = $request->total_amount;
            $saleMaster->paid = $request->paid;
            $saleMaster->delivery_status = $request->delivery_status ?? DeliveryStatus::ORDERED->value;
            $saleMaster->is_percent_product_commission = $request->has('is_percent_product_commission') ? 1 : 0;
            $saleMaster->is_percent_service_commission = $request->has('is_percent_service_commission') ? 1 : 0;

            // If user is vendor, set vendor_id to their user id
            // If user is general auth, vendor_id remains null (global sale)
            if (auth()->user()->isVendor()) {
                $saleMaster->vendor_id = auth()->id();
            } else {
                $saleMaster->vendor_id = null;
            }

            // Handle doctor reference and commission
            if (!empty($request->reference_by)) {
                $saleMaster->reference_amount = $request->reference_amount;
                $saleMaster->reference_by = $request->reference_by;
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
                        $saleDetails->purchase_price = $request->purchase_price[$key];
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

                    if ($request->paid > 0) {
                        Helper::customerLedgerCredit($request, $saleInvoiceNo, $type);
                        Helper::incomeLedger($request, $incomeInvoiceNo, $saleInvoiceNo, $type);
                    }

                    // Add doctor ledger entry if doctor is referenced
                    if (!empty($request->reference_by)) {
                        Helper::doctorLedgerCredit($saleMaster, $saleInvoiceNo, $type);
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
                    $membershipService->processSaleRewards(
                        $saleMaster->customer_id,
                        $saleMaster->id,
                        $saleMaster->total_amount,
                        $saleInvoiceNo
                    );

                    DB::commit();
                    return redirect('admin/sale')->with('success', 'Saved Successfully');
                }

                DB::commit();
                return redirect()->back()->with('warning', 'Please select product or service.');
            }

            DB::commit();
            return redirect()->back()->with('warning', 'Not Saved');
        } catch (\Exception $ex) {
            DB::rollback();
            return redirect()->back()->with('warning', $ex->getMessage());
        }
    }

    /**
     * Display the specified sale.
     *
     * @param string $id
     * @return \Illuminate\Contracts\View\View
     */
    public function show(string $id): View
    {
        $data['saleMaster'] = SaleMaster::with('customer.lead', 'vendor')->where('id', $id)->first();

        // Check if the user has access to this sale
        if (!$data['saleMaster'] || !$data['saleMaster']->isAccessibleByAuthUser()) {
            abort(403, 'You do not have permission to view this sale.');
        }

        $data['saleDetail'] = SaleDetail::with( 'product.category', 'batch')->where('sale_master_id', $id)->get();
        $data['serviceDetails'] = ServiceDetail::where('sale_master_id', $id)->get();
        $data['setting'] = Setting::where('id', 1)->first();

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
        $data['saleMaster'] = SaleMaster::with('customer')->where('id', $id)->first();

        // Check if the user has access to this sale
        if (!$data['saleMaster'] || !$data['saleMaster']->isAccessibleByAuthUser()) {
            abort(403, 'You do not have permission to view this sale.');
        }

        $data['saleDetail'] = SaleDetail::with( 'product.category', 'batch')->where('sale_master_id', $id)->get();
        $data['setting'] = Setting::where('id', 1)->first();
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
        $data['doctors'] = Doctor::where('status', 1)->get();
        $data['saleMaster'] = SaleMaster::with(['customer'])->where('id', $id)->first();

        // Check if the user has access to this sale
        if (!$data['saleMaster'] || !$data['saleMaster']->isAccessibleByAuthUser()) {
            abort(403, 'You do not have permission to edit this sale.');
        }

        $data['saleDetail'] = SaleDetail::with( 'product.category', 'batch')->where('sale_master_id', $id)->get();
        $data['serviceDetails'] = ServiceDetail::where('sale_master_id', $id)->get();

        // Get available serial numbers and stock quantity for each product in the sale details
        $data['productSerials'] = [];
        $data['productStocks'] = [];

        foreach ($data['saleDetail'] as $detail) {
            if ($detail->product_id) {
                // Get purchased serials for this product - filter by vendor
                $query = PurchaseDetail::where('product_id', $detail->product_id)
                    ->whereNotNull('serial_no');

                // If user is vendor, only get serials from their own purchases
                if (auth()->user()->isVendor()) {
                    $query->whereHas('purchaseMaster', function ($q) {
                        $q->where('vendor_id', auth()->id());
                    });
                } else {
                    // General users can only get serials from global purchases
                    $query->whereHas('purchaseMaster', function ($q) {
                        $q->whereNull('vendor_id');
                    });
                }

                $purchases = $query->get(['id', 'serial_no', 'mrp_price']);

                // Get sold serials (excluding current sale's serials)
                $currentSaleSerials = SaleDetail::where('sale_master_id', $id)
                    ->where('product_id', $detail->product_id)
                    ->whereNotNull('serial_no')
                    ->pluck('serial_no')
                    ->flatten()
                    ->toArray();

                $soldSerials = SaleDetail::where('product_id', $detail->product_id)
                    ->whereNotNull('serial_no')
                    ->where('sale_master_id', '!=', $id)
                    ->pluck('serial_no')
                    ->flatten()
                    ->toArray();

                $result = [];

                // Filter available serials
                foreach ($purchases as $purchase) {
                    foreach ($purchase->serial_no as $serialNumber) {
                        // Include serials that are either currently selected in this sale or not sold yet
                        if (!in_array($serialNumber, $soldSerials)) {
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
            'delivery_status' => ['nullable', 'in:' . implode(',', array_column(DeliveryStatus::cases(), 'value'))],
        ];

        if (! empty($requestProductIds)) {
            foreach ($requestProductIds as $key => $val) {
                $rules["product_id.$key"] = 'required';
                $rules["qty.$key"] = 'required';
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

        DB::beginTransaction();
        $type = Helper::$sales;
        try {
            $saleMaster = SaleMaster::where('id', $id)->first();

            // Check if the user has access to this sale
            if (!$saleMaster || !$saleMaster->isAccessibleByAuthUser()) {
                abort(403, 'You do not have permission to update this sale.');
            }

            $saleMaster->customer_id = $request->customer_id;
            $saleMaster->payment_id = $request->payment_type_id;
            $saleMaster->date = $request->date;
            $saleMaster->details = $request->details;
            $saleMaster->vat = $request->vat;
            $saleMaster->total_discount = $request->total_discount;
            $saleMaster->total_amount = $request->total_amount;
            $saleMaster->paid = $request->paid;
            $saleMaster->delivery_status = $request->delivery_status ?? DeliveryStatus::ORDERED->value;
            $saleMaster->is_percent_product_commission = $request->has('is_percent_product_commission') ? 1 : 0;
            $saleMaster->is_percent_service_commission = $request->has('is_percent_service_commission') ? 1 : 0;
            $saleInvoiceNo = $saleMaster->sale_invoice_no;

            // Handle doctor reference and commission
            if (!empty($request->reference_by)) {
                $saleMaster->reference_amount = $request->reference_amount;
                $saleMaster->reference_by = $request->reference_by;
            }

            if ($saleMaster->update()) {
                CustomerLedger::where('ref_invoice_no', $saleInvoiceNo)->where('type', Helper::$sales)->delete();
                Stock::where('ref_invoice_no', $saleInvoiceNo)->where('type', Helper::$sales)->delete();

                $ledger = Ledger::where('ref_invoice_no', $saleInvoiceNo)->where('type', Helper::$sales)->first();
                if (isset($ledger)) {
                    $ledger->income_amount = $request->paid;
                    $ledger->update();
                } else {
                    $incomeInvoiceNo = date('ymdhis');
                    Helper::incomeLedger($request, $incomeInvoiceNo, $saleInvoiceNo, $type);
                }

                // Handle existing products
                if (isset($request->old)) {
                    $saleDetailsId = [];
                    foreach ($request->old as $key => $val) {
                        $saleDetailsId[] = $val['id'];
                        $saleDetails = SaleDetail::find($val['id']);

                        // Use formatted old arrays - either batch_id or serial_no will be set, not both
                        $batchId = isset($oldBatches[$key]) ? $oldBatches[$key] : null;
                        $serialNo = isset($oldSerials[$key]) ? $oldSerials[$key] : null;

                        if (! empty($batchId)) {
                            if ($saleDetails->qty < $val['qty']) {
                                $batch = Batch::where('id', $batchId)->where('product_id', $val['product_id'])->first();
                                $new_qty = $val['qty'] - $saleDetails->qty;
                                $batch->sale_qty += $new_qty;
                                $batch->update();
                            } elseif ($saleDetails->qty > $val['qty']) {
                                $batch = Batch::where('id', $batchId)->where('product_id', $val['product_id'])->first();
                                $new_qty = $saleDetails->qty - $val['qty'];
                                $batch->sale_qty -= $new_qty;
                                $batch->update();
                            }
                        }

                        $saleDetails->product_id = $val['product_id'];
                        $saleDetails->batch_id = $batchId;
                        $saleDetails->serial_no = $serialNo;
                        $saleDetails->qty = $val['qty'];
                        $saleDetails->purchase_price = $val['purchase_price'];
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

                // Delete removed products
                if (isset($saleDetailsId)) {
                    SaleDetail::where('sale_master_id', $saleMaster->id)->whereNotIn('id', $saleDetailsId)->delete();
                }

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
                        $saleDetails->purchase_price = $request->purchase_price[$key];
                        $saleDetails->mrp_price = $request->unit_price[$key];
                        $saleDetails->discount = $request->discount[$key];
                        $saleDetails->save();

                        Helper::stockOut($requestProductIds[$key], $request->date, $request->qty[$key], $request->details, $saleInvoiceNo, $type);

                        if (!empty($batchIds[$key])) {
                          Helper::updateBatch($requestProductIds[$key], $batchIds[$key], null, $request->qty[$key], $type);
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

                if ($request->paid > 0) {
                    Helper::customerLedgerCredit($request, $saleInvoiceNo, $type);
                }

                // Update doctor ledger entry if doctor is referenced
                DoctorLedger::where('ref_invoice_no', $saleInvoiceNo)->delete();
                if (!empty($request->reference_by)) {
                    Helper::doctorLedgerCredit($saleMaster, $saleInvoiceNo, $type);
                }

                // Process redeem points (create or update)
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

                // Process membership rewards and referral (for updates)
                $membershipService = new MembershipService();
                $membershipService->processSaleRewards(
                    $saleMaster->customer_id,
                    $saleMaster->id,
                    $saleMaster->total_amount,
                    $saleInvoiceNo
                );

                DB::commit();
                return redirect('admin/sale')->with('success', 'Updated Successfully');
            }

            DB::commit();
            return redirect()->back()->with('warning', 'Not Updated');
        } catch (\Exception $ex) {
            DB::rollback();
            return redirect()->back()->with('warning', $ex->getMessage());
        }
    }

    /**
     * Display the sale report creation form.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function saleReportCreate(): View
    {
        $data['customers'] = Customer::where('status', 1)->orderBy('id', 'desc')->get();

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
        $customers = Customer::where('status', 1)->orderBy('id', 'desc')->get();
        $customerName = Customer::where('id', $customerId)->value('name');

        $saleMaster = SaleMaster::with('customer')->whereBetween('date', [$startDate, $endDate])
            ->orderBy('id', 'desc')
            ->get();
        $setting = Setting::where('id', 1)->first(['company_name']);
        return view('admin.sales.report', compact('saleMaster', 'setting', 'startDate', 'endDate', 'customers', 'customerId', 'customerName'));
    }

    /**
     * Display the due list creation form.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function dueListCreate(): View
    {
        return view('admin.sales.due_list');
    }

    /**
     * Get the list of due sales for DataTables.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function dueList(): JsonResponse
    {
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
                    
                    if (Helper::hasPermission('sale_list.delete')) {
                        $action .= '<form action="' . route('sale.destroy', $row->sale_invoice_no) . '" method="POST" style="display:inline-block;" onsubmit="return confirmDelete(event)">' . csrf_field() . method_field('DELETE') . '<button type="submit" class="btn btn-danger btn-sm" title="Delete"><i class="far fa-trash-alt"></i></button></form>';
                    }
                    $action .= '</div>';
                    return $action;
                })
            ->rawColumns(['action'])
            ->toJson();
        
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
                    
                    if (Helper::hasPermission('sale_list.delete')) {
                        $action .= '<form action="' . route('sale.destroy', $row->sale_invoice_no) . '" method="POST" style="display:inline-block;" onsubmit="return confirmDelete(event)">' . csrf_field() . method_field('DELETE') . '<button type="submit" class="btn btn-danger btn-sm" title="Delete"><i class="far fa-trash-alt"></i></button></form>';
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
    public function destroy(string $id): RedirectResponse
    {
        DB::beginTransaction();

        $saleMaster = SaleMaster::where('id', $id)->first();

        // Check if the user has access to this sale
        if (!$saleMaster || !$saleMaster->isAccessibleByAuthUser()) {
            abort(403, 'You do not have permission to delete this sale.');
        }

        $saleInvoiceNo = $saleMaster->sale_invoice_no;
        $customerId = $saleMaster->customer_id;

        $details = SaleDetail::where('sale_master_id', $id)->get();

        foreach ($details as $val) {
            if (!empty($val->batch_id)) {
                $batch = Batch::where('id', $val->batch_id)->where('product_id', $val->product_id)->first();
                $batch->sale_qty -= $val->qty;
                $batch->update();
            }
        }

        // Delete reward ledgers associated with this sale
        $rewardLedgers = RewardLedger::where('sale_master_id', $id)->get();
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
                $referralRewardLedger = RewardLedger::where('sale_master_id', $saleMaster->id)->first();
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
        SaleDetail::where('sale_master_id', $id)->delete();
        ServiceDetail::where('sale_master_id', $id)->delete();
        CustomerLedger::where('ref_invoice_no', $saleInvoiceNo)->where('type', Helper::$sales)->delete();
        Stock::where('ref_invoice_no', $saleInvoiceNo)->where('type', Helper::$sales)->delete();
        Ledger::where('ref_invoice_no', $saleInvoiceNo)->where('type', Helper::$sales)->delete();
        DoctorLedger::where('ref_invoice_no', $saleInvoiceNo)->where('type', Helper::$sales)->delete();
        // Delete membership upgrade history if customer's tier should be downgraded
        $this->handleMembershipTierDowngrade($customerId);
        
        DB::commit();
        return redirect()->back()->with('success', 'Deleted Successfully');
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
            return redirect()->back()->with('warning', $ex->getMessage());
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
            return redirect()->back()->with('warning', $ex->getMessage());
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
            return redirect()->back()->with('warning', $ex->getMessage());
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
}
