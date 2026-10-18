<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Ledger;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Models\PurchaseDetail;
use App\Models\PurchaseMaster;
use App\Models\Setting;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Log;

class PurchaseController extends Controller
{
    /**
     * Configure middleware to handle authorization for different purchase operations
     * 
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:purchase_list.index')->only(['index', 'purchaseList']);
        $this->middleware('permission:add_purchase.create')->only(['create', 'store']);
        $this->middleware('permission:purchase_list.edit')->only(['edit', 'update']);
        $this->middleware('permission:purchase_list.delete')->only(['destroy']);
        $this->middleware('permission:purchase_list.view')->only(['show']);
        $this->middleware('permission:purchase_report.index')->only(['purchaseReportCreate', 'purchaseReport']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index(Request $request): View
    {
        // Read-only cross-branch view for Super Admin / roles with vendor_list.view_all_branches
        return Helper::readAllBranches(fn () => $this->purchasesIndex($request));
    }

    private function purchasesIndex(Request $request): View
    {
        $data['allBranches'] = Helper::isAllBranchesMode();
        $data['branchFilter'] = $request->input('branch', 'all');
        $data['branches'] = $data['allBranches'] ? Helper::branchOptions() : [];

        $query = PurchaseMaster::with(['supplier', 'vendor']);
        Helper::applyBranchFilter($query, $data['branchFilter']);

        if ($request->filled('purchase_invoice_no')) {
            $query->where('purchase_invoice_no', $request->purchase_invoice_no);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('supplier_memo_no')) {
            $query->where('supplier_memo_no', 'like', '%' . $request->supplier_memo_no . '%');
        }

        $data['start_date'] = $request->start_date ?? null;
        $data['end_date'] = $request->end_date ?? null;

        if ($data['start_date'] && $data['end_date']) {
            $start = date('Y-m-d', strtotime($data['start_date']));
            $end = date('Y-m-d', strtotime($data['end_date']));
            $query->whereBetween('date', [$start, $end]);
        }

        $data['purchases'] = $query->latest()->paginate(20);

        $data['suppliers'] = Supplier::select('id', 'supplier_name')->where('status', 1)->orderBy('id', 'desc')->get();
        return view('admin.purchase.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return View
     */
    public function create(): View
    {
        $data['suppliers'] = Supplier::where('status', 1)->orderBy('id', 'desc')->get();
        $data['paymentTypes'] = Helper::paymentType();

        return view('admin.purchase.create', $data);
    }

    /**
     * Adds a new row item to the purchase form.
     *
     * @return View
     */
    public function addRowItem(): View
    {
        return view('admin.purchase.add_row_item');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'supplier_id' => ['required'],
            'payment_type_id' => ['required'],
            'date'            => ['required']
        ], [
            'supplier_id.required' => 'The supplier field is required.',
            'payment_type_id.required' => 'The payment type field is required.',
        ]);

        DB::beginTransaction();
        $type = Helper::$purchase;
        $purchaseInvoiceNo = strtoupper(uniqid());
        $expenseInvoiceNo = date('ymdhis');

        try {
            $purchaseMaster = new PurchaseMaster;
            $purchaseMaster->supplier_id = $request->supplier_id;
            $purchaseMaster->payment_id = $request->payment_type_id;
            $purchaseMaster->purchase_invoice_no = $request->purchase_invoice_no;
            $purchaseMaster->date = $request->date;
            $purchaseMaster->details = $request->details;
            $purchaseMaster->purchase_invoice_no = $purchaseInvoiceNo;
            $purchaseMaster->supplier_memo_no = $request->supplier_memo_no;
            $purchaseMaster->vat = $request->vat;
            $purchaseMaster->discount = $request->discount;
            $purchaseMaster->total_amount = $request->total_amount;
            $purchaseMaster->paid = $request->paid;

            // Set vendor_id based on auth type
            // If user is vendor, set vendor_id to their user id
            // If user is general auth, vendor_id remains null (global purchase)
            if (auth()->user()->branchId()) {
                $purchaseMaster->vendor_id = auth()->user()->branchId();
            } else {
                $purchaseMaster->vendor_id = null;
            }

            if ($purchaseMaster->save()) {
                if (isset($request->product_id)) {
                    foreach ($request->product_id as $key => $val) {
                        $batch_id = null;
                        $serial_no = null;
                        
                        if (!empty($request->batch_no[$key])) {
                          $batch_id = Helper::storeBatch(
                            $request->product_id[$key],
                            $request->batch_no[$key],
                            $request->purchase_price[$key],
                            $request->qty[$key],
                            $purchaseInvoiceNo,
                            $type
                          );
                          $qty = $request->qty[$key];
                        } else {
                            $serial_no = array_map('trim', explode(",", $request->serial_no[$key]));
                            $qty = $request->qty[$key];
                        }
                        
                        $purchaseDetails = new PurchaseDetail;
                        $purchaseDetails->purchase_master_id = $purchaseMaster->id;
                        $purchaseDetails->product_id = $request->product_id[$key];
                        $purchaseDetails->batch_id = $batch_id ?? null;
                        $purchaseDetails->serial_no = $serial_no ?? null;
                        $purchaseDetails->qty = $qty;
                        $purchaseDetails->purchase_price = $request->purchase_price[$key];
                        $purchaseDetails->mrp_price = $request->mrp_price[$key];
                        $purchaseDetails->save();

                        Helper::stockIn(
                            $request->product_id[$key],
                            $request->date,
                            $request->qty[$key],
                            $request->details,
                            $purchaseInvoiceNo,
                            $type
                        );
                    }

                    Helper::supplierLedgerCredit($request, $purchaseInvoiceNo, $type);

                    // Only record payment outflow if NOT pure credit and paid > 0
                    if ((float) $request->paid > 0 && (int) $request->payment_type_id !== 4) {
                        Helper::supplierLedgerDebit($request, $purchaseInvoiceNo, $type);

                        // Attach payment method info to expense ledger entry
                        $paymentTypes = Helper::paymentType();
                        $methodName = $paymentTypes[$request->payment_type_id] ?? 'Cash Payment';
                        $paymentNote = "Purchase Payment ({$methodName})";
                        if (!empty($request->transaction_no)) {
                            $paymentNote .= " [Txn/Chq: {$request->transaction_no}]";
                        }
                        if (!empty($request->bank_name)) {
                            $paymentNote .= " ({$request->bank_name})";
                        }
                        $originalDetails = $request->details;
                        $request->details = $originalDetails ? "{$originalDetails} | {$paymentNote}" : $paymentNote;

                        Helper::expenseLedger($request, $expenseInvoiceNo, $purchaseInvoiceNo, $type);
                        $request->details = $originalDetails;
                    }

                    DB::commit();
                    return redirect('admin/purchase')->with('success', 'Saved Successfully');
                } else {
                    DB::commit();
                    return redirect()->back()->with('warning', 'Please select product!');
                }
            } else {
                DB::commit();
                return redirect()->back()->with('warning', 'Not Saved');
            }
        } catch (\Exception $ex) {
            DB::rollback();
            return redirect()->back()->with('warning', $ex->getMessage());
        }
    }

    /**
     * Display the specified resource.
    /**
     * Display the specified resource.
     *
     * @param string $idOrInvoice
     * @return View
     */
    public function show(string $idOrInvoice): View
    {
        $data = Helper::readAllBranches(function () use ($idOrInvoice) {
            $purchase = null;

            // 1. If numeric, match by primary key ID first
            if (is_numeric($idOrInvoice)) {
                $purchase = PurchaseMaster::withoutGlobalScopes()
                    ->with(['supplier', 'vendor'])
                    ->where('id', (int) $idOrInvoice)
                    ->first();
            }

            // 2. If not found or not numeric, match by exact purchase_invoice_no
            if (!$purchase) {
                $purchase = PurchaseMaster::withoutGlobalScopes()
                    ->with(['supplier', 'vendor'])
                    ->where('purchase_invoice_no', $idOrInvoice)
                    ->first();
            }

            // 3. Fallback to supplier_memo_no
            if (!$purchase) {
                $purchase = PurchaseMaster::withoutGlobalScopes()
                    ->with(['supplier', 'vendor'])
                    ->where('supplier_memo_no', $idOrInvoice)
                    ->first();
            }

            // Check if the user has access to this purchase
            if (!$purchase || !$purchase->isAccessibleByAuthUser()) {
                abort(403, 'You do not have permission to view this purchase.');
            }

            $data['purchaseMaster'] = $purchase;
            $data['purchaseDetail'] = PurchaseDetail::withoutGlobalScopes()
                ->where('purchase_master_id', $purchase->id)
                ->with([
                    'product' => function ($pq) {
                        $pq->withoutGlobalScopes()->with(['brand', 'category']);
                    },
                    'batch'
                ])
                ->get();
            $data['setting'] = \App\Classes\Helper::invoiceBranding($purchase->vendor_id);

            return $data;
        });

        return view('admin.purchase.details', $data);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param string $id
     * @return View
     */
    public function edit(string $id)
    {
        $purchase = null;
        if (is_numeric($id)) {
            $purchase = PurchaseMaster::where('id', (int) $id)->first();
        }
        if (!$purchase) {
            $purchase = PurchaseMaster::where('purchase_invoice_no', $id)->first();
        }

        // Check if the user has access to this purchase
        if (!$purchase || !$purchase->isAccessibleByAuthUser()) {
            abort(403, 'You do not have permission to edit this purchase.');
        }

        $data['suppliers'] = Supplier::where('status', 1)->orderBy('id', 'desc')->get();
        $data['paymentTypes'] = Helper::paymentType();
        $data['purchaseMaster'] = $purchase;
        $data['purchaseDetail'] = PurchaseDetail::with('batch', 'product.category')
            ->where('purchase_master_id', $purchase->id)->get();

        return view('admin.purchase.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param string $id
     * @return RedirectResponse
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $this->validate($request, [
            'supplier_id' => ['required'],
            'payment_type_id' => ['required'],
            'date'            => ['required']
        ], [
            'supplier_id.required' => 'The supplier field is required.',
            'payment_type_id.required' => 'The payment type field is required.',
        ]);

        DB::beginTransaction();
        $type = Helper::$purchase;

        try {
            $purchaseMaster = PurchaseMaster::where('id', $id)->first();

            // Check if the user has access to this purchase
            if (!$purchaseMaster || !$purchaseMaster->isAccessibleByAuthUser()) {
                abort(403, 'You do not have permission to update this purchase.');
            }

            $purchaseInvoiceNo = $purchaseMaster->purchase_invoice_no;
            $purchaseMaster->supplier_id = $request->supplier_id;
            $purchaseMaster->payment_id = $request->payment_type_id;
            $purchaseMaster->date = $request->date;
            $purchaseMaster->details = $request->details;
            $purchaseMaster->supplier_memo_no = $request->supplier_memo_no;
            $purchaseMaster->vat = $request->vat;
            $purchaseMaster->discount = $request->discount;
            $purchaseMaster->total_amount = $request->total_amount;
            $purchaseMaster->paid = $request->paid;

            if ($purchaseMaster->update()) {
                SupplierLedger::where('ref_invoice_no', $purchaseInvoiceNo)
                    ->where('type', Helper::$purchase)->delete();
                Stock::where('ref_invoice_no', $purchaseInvoiceNo)
                    ->where('type', Helper::$purchase)->delete();

                $ledger = Ledger::where('ref_invoice_no', $purchaseInvoiceNo)
                    ->where('type', Helper::$purchase)->first();

                if ((float) $request->paid > 0 && (int) $request->payment_type_id !== 4) {
                    if (isset($ledger)) {
                        $ledger->expense_amount = $request->paid;
                        $ledger->date = $request->date;
                        $ledger->update();
                    } else {
                        $expenseInvoiceNo = date('ymdhis');
                        Helper::expenseLedger($request, $expenseInvoiceNo, $purchaseInvoiceNo, $type);
                    }
                } else {
                    if (isset($ledger)) {
                        $ledger->delete();
                    }
                }

                if (isset($request->old)) {
                    $purchaseDetailsId = [];
                    $batchId = [];

                    foreach ($request->old as $key => $val) {
                        if (! empty($request->old[$key]['batch_id'])) {
                            Helper::updateBatch(
                                $request->old[$key]['product_id'],
                                $request->old[$key]['batch_id'],
                                $request->old[$key]['batch_no'],
                                $request->old[$key]['qty'],
                                $type
                            );
                            $batchId[] = $request->old[$key]['batch_id'];
                            $qty = $request->old[$key]['qty'];
                        } 

                        $serial_no = null;
                        if (! empty($request->old[$key]['serial_no'])) {
                            $serial_no = array_map('trim', explode(",", $request->old[$key]['serial_no']));
                            $qty = count($serial_no);
                        }

                        $purchaseDetailsId[] = $val['id'];
                        $purchaseDetails = PurchaseDetail::find($val['id']);
                        $purchaseDetails->product_id = $request->old[$key]['product_id'];
                        $purchaseDetails->qty = $qty;
                        $purchaseDetails->serial_no = $serial_no;
                        $purchaseDetails->purchase_price = $request->old[$key]['purchase_price'];
                        $purchaseDetails->mrp_price = $request->old[$key]['mrp_price'];
                        $purchaseDetails->update();

                        Helper::stockIn($request->old[$key]['product_id'], $request->date, $request->old[$key]['qty'], $request->details, $purchaseInvoiceNo, $type);
                    }
                }

                PurchaseDetail::where('purchase_master_id', $id)
                    ->whereNotIn('id', $purchaseDetailsId)->delete();
                Batch::where('ref_invoice_no', $purchaseInvoiceNo)
                    ->whereNotIn('id', $batchId)->delete();

                if (isset($request->product_id)) {
                    foreach ($request->product_id as $key => $val) {
                        $batch_id = null;
                        $serial_no = null;
                        
                        if (!empty($request->batch_no[$key])) {
                          $batch_id = Helper::storeBatch(
                            $request->product_id[$key],
                            $request->batch_no[$key],
                            $request->purchase_price[$key],
                            $request->qty[$key],
                            $purchaseInvoiceNo,
                            $type
                          );
                         $qty = $request->qty[$key];
                        } else {
                            $serial_no = array_map('trim', explode(",", $request->serial_no[$key]));
                            $qty = count($serial_no);
                        }

                        $purchaseDetails = new PurchaseDetail;
                        $purchaseDetails->purchase_master_id = $purchaseMaster->id;
                        $purchaseDetails->product_id = $request->product_id[$key];
                        $purchaseDetails->batch_id = $batch_id ?? null;
                        $purchaseDetails->serial_no = $serial_no ?? null;
                        $purchaseDetails->qty = $qty;
                        $purchaseDetails->purchase_price = $request->purchase_price[$key];
                        $purchaseDetails->mrp_price = $request->mrp_price[$key];
                        $purchaseDetails->save();

                        Helper::stockIn($request->product_id[$key], $request->date, $request->qty[$key], $request->details, $purchaseInvoiceNo, $type);
                    }
                }

                Helper::supplierLedgerCredit($request, $purchaseInvoiceNo, $type);

                if ((float) $request->paid > 0 && (int) $request->payment_type_id !== 4) {
                    Helper::supplierLedgerDebit($request, $purchaseInvoiceNo, $type);
                }

                DB::commit();
                return redirect('admin/purchase')->with('success', 'Updated Successfully');
            } else {
                DB::commit();
                return redirect()->back()->with('warning', 'Not Updated');
            }
        } catch (\Exception $ex) {
            DB::rollback();
            return redirect()->back()->with('warning', $ex->getMessage());
        }
    }

    public function purchaseReportCreate(): View
    {
        $data['suppliers'] = Supplier::where('status', 1)->orderBy('id', 'desc')->get();
        return view('admin.purchase.report', $data);
    }

    /**
     * Generates a purchase report based on the given request.
     *
     * @param Request $request
     * @return View
     */
    public function purchaseReport(Request $request): View
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $supplierId = $request->supplier_id;
        $suppliers = Supplier::where('status', 1)->orderBy('id', 'desc')->get();
        $supplierName = Supplier::where('id', $supplierId)->value('supplier_name');
        $setting = Setting::where('id', 1)->first(['company_name']);
        $purchaseMaster = PurchaseMaster::with('supplier')
            ->when($supplierId, fn($q) => $q->where('supplier_id', $supplierId))
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('id', 'desc')
            ->get();

        return view('admin.purchase.report', compact(
            'purchaseMaster',
            'setting',
            'startDate',
            'endDate',
            'suppliers',
            'supplierId',
            'supplierName'
        ));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param string $purchaseInvoiceNo The purchase invoice number.
     * @return RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        DB::beginTransaction();

        $purchaseMaster = PurchaseMaster::findOrFail($id);

        // Check if the user has access to this purchase
        if (!$purchaseMaster || !$purchaseMaster->isAccessibleByAuthUser()) {
            abort(403, 'You do not have permission to delete this purchase.');
        }

        $purchaseInvoiceNo = $purchaseMaster->purchase_invoice_no;

        $batches = Batch::where('ref_invoice_no', $purchaseInvoiceNo)->where('type', Helper::$purchase)->get();
        foreach ($batches as $val) {
            if ($val->sale_qty > 0) {
                return redirect()->back()->with('warning', 'Cannot delete, it is already used!');
            }
        }

        $purchaseMaster->delete();
        PurchaseDetail::where('purchase_master_id', $id)->delete();
        SupplierLedger::where('ref_invoice_no', $purchaseInvoiceNo)->where('type', Helper::$purchase)->delete();
        Stock::where('ref_invoice_no', $purchaseInvoiceNo)->where('type', Helper::$purchase)->delete();
        Ledger::where('ref_invoice_no', $purchaseInvoiceNo)->where('type', Helper::$purchase)->delete();
        Batch::where('ref_invoice_no', $purchaseInvoiceNo)->where('type', Helper::$purchase)->delete();

        DB::commit();
        return redirect()->back()->with('success', 'Deleted Successfully');
    }
}
