<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Models\PurchaseMaster;
use App\Models\Ledger;
use App\Services\LedgerReversal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupplierController extends Controller
{
    /**
     * Configure middleware to handle authorization for different supplier operations
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:add_supplier.create')->only(['create', 'store']);
        $this->middleware('permission:supplier_list.index')->only(['index', 'supplierList']);
        $this->middleware('permission:supplier_list.edit')->only(['edit', 'update']);
        $this->middleware('permission:supplier_list.delete')->only(['destroy']);
        // Old ledger URLs only redirect to the Supplier Statement report
        $this->middleware('permission:supplier_statement.index')->only(['createSupplierLedger', 'supplierLedger']);
        $this->middleware('permission:supplier_payment.index')->only(['supplierPaymentIndex', 'supplierPaymentSlip', 'supplierUnpaidPurchases']);
        $this->middleware('permission:supplier_payment.create')->only(['supplierPaymentCreate', 'supplierPaymentStore']);
        $this->middleware('permission:supplier_payment.edit')->only(['supplierPaymentEdit', 'supplierPaymentUpdate']);
        $this->middleware('permission:supplier_payment.delete')->only(['supplierPaymentDestroy']);
        $this->middleware('permission:supplier_payment.index|supplier_payment.create|add_purchase.create')->only(['getSupplierDue']);
    }

    /**
     * Display a listing of the suppliers.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        $suppliers = Supplier::orderBy('id', 'desc')->get();

        // Aggregate purchases by supplier
        $purchaseStats = DB::table('purchase_masters')
            ->whereNull('deleted_at')
            ->selectRaw('supplier_id, COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total_amount')
            ->groupBy('supplier_id')
            ->get()
            ->keyBy('supplier_id');

        // Aggregate ledger by supplier (credits and debits)
        $ledgerStats = DB::table('supplier_ledgers')
            ->whereNull('deleted_at')
            ->selectRaw('supplier_id, COALESCE(SUM(credit), 0) as total_credit, COALESCE(SUM(debit), 0) as total_debit')
            ->groupBy('supplier_id')
            ->get()
            ->keyBy('supplier_id');

        $totalPurchased = 0;
        $totalPaid = 0;
        $totalDue = 0;
        $totalInvoices = 0;

        foreach ($suppliers as $supplier) {
            $p = $purchaseStats->get($supplier->id);
            $l = $ledgerStats->get($supplier->id);

            $supplier->purchases_count = $p ? (int) $p->count : 0;
            $supplier->purchases_total = $p ? (float) $p->total_amount : 0.0;

            $credit = $l ? (float) $l->total_credit : 0.0;
            $debit = $l ? (float) $l->total_debit : 0.0;
            $supplier->total_credit = $credit;
            $supplier->total_debit = $debit;
            $supplier->due_balance = max(0, $credit - $debit);

            $totalPurchased += $supplier->purchases_total;
            $totalPaid += $debit;
            $totalDue += $supplier->due_balance;
            $totalInvoices += $supplier->purchases_count;
        }

        $summary = [
            'total' => $suppliers->count(),
            'active' => $suppliers->where('status', 1)->count(),
            'inactive' => $suppliers->where('status', '!=', 1)->count(),
            'total_purchased' => $totalPurchased,
            'total_paid' => $totalPaid,
            'total_due' => $totalDue,
            'total_invoices' => $totalInvoices,
        ];

        return view('admin.supplier.index', compact('suppliers', 'summary'));
    }


    /**
     * Show the form for creating a new supplier.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function create(): View
    {
        return view('admin.supplier.create');
    }

    /**
     * Store a newly created supplier in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'supplier_name' => ['required', 'string', 'unique:suppliers'],
            'mobile_no' => ['required', 'string'],
        ]);

        $requestAll = $request->all();

        if (auth()->user()->branchId()) {
            $requestAll['vendor_id'] = auth()->user()->branchId();
        }

        $supplier = new Supplier($requestAll);

        if ($supplier->save()) {
            return redirect('admin/suppliers')->with('success', 'Saved Successfully');
        }

        return redirect()->back()->with('warning', 'Not Saved');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function show(int $id): void
    {
        // Not implemented
    }

    /**
     * Legacy ledger route: redirect to modern Supplier Statement report.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function createSupplierLedger(): RedirectResponse
    {
        return redirect()->route('report.supplier-statement');
    }

    /**
     * Legacy ledger route: redirect to modern Supplier Statement report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function supplierLedger(Request $request): RedirectResponse
    {
        return redirect()->route('report.supplier-statement', $request->all());
    }

    /**
     * Show the form for editing the specified supplier.
     *
     * @param  int  $id
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(int $id): View
    {
        $supplier = Supplier::findOrFail($id);

        return view('admin.supplier.edit', compact('supplier'));
    }

    /**
     * Update the specified supplier in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->validate($request, [
            'supplier_name' => ['required', 'string', 'unique:suppliers,supplier_name,' . $id],
            'mobile_no' => ['required', 'string'],
        ]);

         $requestAll = $request->all();

        if (auth()->user()->branchId()) {
            $requestAll['vendor_id'] = auth()->user()->branchId();
        }

        $supplier = Supplier::findOrFail($id);
        $supplier->fill($requestAll);

        if ($supplier->save()) {
            return redirect('admin/suppliers')->with('success', 'Updated Successfully');
        }

        return redirect()->back()->with('warning', 'Not Updated');
    }

    /**
     * Show the payment creation form.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function payment(): View
    {
        $data['transactionType'] = Helper::transactionType();
        $data['suppliers'] = Supplier::where('status', 1)->orderBy('id', 'desc')->get();

        return view('admin.supplier.payment', $data);
    }

    /**
     * Store a new payment or receipt transaction.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function paymentStore(Request $request): RedirectResponse
    {
        $refInvoiceNo = strtoupper(uniqid());
        $invoiceNo = date('ymdhis');

        DB::beginTransaction();
        try {
            if ($request->type == Helper::$payment) {
                $type = Helper::$payment;
                Helper::supplierLedgerDebit($request, $refInvoiceNo, $type);
                Helper::expenseLedger($request, $invoiceNo, $refInvoiceNo, $type);
            } else {
                $type = Helper::$receive;
                $request['grand_total'] = $request->paid_amount;
                Helper::supplierLedgerCredit($request, $refInvoiceNo, $type);
                Helper::incomeLedger($request, $invoiceNo, $refInvoiceNo, $type);
            }
            DB::commit();

            return redirect()->back()->with('success', 'Saved Successfully');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('warning', 'An error occurred while saving the transaction.');
        }
    }

    /**
     * Remove the specified supplier from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        $supplier = Supplier::findOrFail($id);

        $purchaseCount = DB::table('purchase_masters')->where('supplier_id', $id)->whereNull('deleted_at')->count();
        $ledgerCount = DB::table('supplier_ledgers')->where('supplier_id', $id)->whereNull('deleted_at')->count();

        if ($purchaseCount > 0 || $ledgerCount > 0) {
            $details = [];
            if ($purchaseCount > 0) {
                $details[] = "$purchaseCount purchase invoice" . ($purchaseCount > 1 ? 's' : '');
            }
            if ($ledgerCount > 0) {
                $details[] = "$ledgerCount payment/ledger record" . ($ledgerCount > 1 ? 's' : '');
            }
            $linked = implode(' and ', $details);

            return redirect('/admin/suppliers')->with(
                'warning',
                "Cannot delete supplier '{$supplier->supplier_name}' because it has {$linked}. You can mark it Inactive instead."
            );
        }

        $supplier->delete();

        return redirect('/admin/suppliers')->with('success', 'Supplier deleted successfully');
    }

    /**
     * Display a listing of the supplier payments.
     *
     * @return View
     */
    public function supplierPaymentIndex(Request $request): View
    {
        $query = SupplierLedger::with('supplier')
            ->where('type', 'Payment');

        if ($request->filled('supplier_memo_no')) {
            $query->where('supplier_memo_no', 'like', '%' . $request->supplier_memo_no . '%');
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        $data['payments'] = $query->latest()->paginate(20)->withQueryString();
        $data['suppliers'] = Supplier::where('status', 1)->orderBy('supplier_name', 'asc')->get();

        return view('admin.supplier_payment.index', $data);
    }

    /**
     * Show the form for creating a new supplier payment.
     *
     * @return View
     */
    public function supplierPaymentCreate(): View
    {
        $data['suppliers'] = Supplier::where('status', 1)->orderBy('supplier_name', 'asc')->get();
        $data['paymentMethods'] = Helper::supplierPaymentMethods();
        return view('admin.supplier_payment.create', $data);
    }

    /**
     * Store a newly created supplier payment in storage.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function supplierPaymentStore(Request $request): RedirectResponse
    {
        $request->validate([
            'supplier_id'        => ['required', 'exists:suppliers,id'],
            'date'               => ['required', 'date'],
            'debit'              => ['required', 'numeric', 'min:0.01'],
            'payment_method'     => ['required', 'string', 'in:Cash,Bank Deposit,Online Transfer'],
            'supplier_memo_no'   => ['nullable', 'string', 'max:100'],
            'bank_name'          => ['nullable', 'string', 'max:100'],
            'transaction_no'     => ['nullable', 'string', 'max:100'],
            'linked_purchase_id' => ['nullable', 'exists:purchase_masters,id'],
            'details'            => ['nullable', 'string', 'max:500'],
        ]);

        $supplier = Supplier::findOrFail($request->supplier_id);

        // Calculate supplier's current due amount
        $ledgers = SupplierLedger::where('supplier_id', $request->supplier_id)->get();
        $totalCredit = (float) $ledgers->sum('credit');
        $totalDebit = (float) $ledgers->sum('debit');
        $dueAmount = max(0, $totalCredit - $totalDebit);

        // If paying more than current due, check confirmation or block
        if ($request->debit > $dueAmount && $dueAmount > 0) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['debit' => "Payment amount (৳" . number_format($request->debit, 2) . ") exceeds the current outstanding due (৳" . number_format($dueAmount, 2) . ")."]);
        }

        DB::beginTransaction();
        try {
            $refInvoiceNo = 'SP-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -4));

            $memoNote = 'Payment ref: ' . $refInvoiceNo . ' [' . $request->payment_method . ']';
            if ($request->supplier_memo_no) {
                $memoNote .= ' | Supplier Memo: ' . $request->supplier_memo_no;
            }
            if ($request->transaction_no) {
                $memoNote .= ' Txn/Chq: ' . $request->transaction_no;
            }
            if ($request->bank_name) {
                $memoNote .= ' (' . $request->bank_name . ')';
            }
            $fullDetails = $request->details ? $request->details . ' | ' . $memoNote : $memoNote;

            // 1. Supplier Ledger entry (reduces Accounts Payable)
            $supplierLedger = new SupplierLedger();
            $supplierLedger->supplier_id = $request->supplier_id;
            $supplierLedger->date = $request->date;
            $supplierLedger->details = $fullDetails;
            $supplierLedger->debit = $request->debit;
            $supplierLedger->credit = 0;
            $supplierLedger->type = 'Payment';
            $supplierLedger->ref_invoice_no = $refInvoiceNo;
            $supplierLedger->supplier_memo_no = $request->supplier_memo_no;
            $supplierLedger->payment_method = $request->payment_method;
            $supplierLedger->transaction_no = $request->transaction_no;
            $supplierLedger->bank_name = $request->bank_name;
            $supplierLedger->linked_purchase_id = $request->linked_purchase_id ?: null;
            $supplierLedger->save();

            // 2. Financial Accounting Ledger entry (Cash/Bank Outflow)
            $branchId = auth()->check() ? auth()->user()->branchId() : null;
            $accLedger = new Ledger();
            $accLedger->invoice_no = 'EX-' . date('ymdhis');
            $accLedger->vendor_id = $branchId;
            $accLedger->date = $request->date;
            $accLedger->details = "Supplier Payment: {$supplier->supplier_name} via {$request->payment_method}" . ($request->transaction_no ? " (Ref: {$request->transaction_no})" : '') . ($request->details ? " - {$request->details}" : '');
            $accLedger->expense_amount = $request->debit;
            $accLedger->income_amount = 0;
            $accLedger->amount_type = Helper::$expense;
            $accLedger->type = 'Supplier Payment';
            $accLedger->ref_invoice_no = $refInvoiceNo;
            $accLedger->save();

            DB::commit();

            return redirect()->route('suppliers.payment.slip', $supplierLedger->id)
                ->with('success', 'Supplier Payment saved successfully. Payment Voucher / Slip generated below.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('warning', 'An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Display printable payment voucher / slip for a supplier payment.
     *
     * @param int $id
     * @return View
     */
    public function supplierPaymentSlip(int $id): View
    {
        $payment = SupplierLedger::with(['supplier.vendor', 'linkedPurchase'])->findOrFail($id);

        if ($payment->type !== 'Payment') {
            abort(404, 'Voucher only available for payment transactions.');
        }

        // Calculate balances around this transaction
        $allCredits = SupplierLedger::where('supplier_id', $payment->supplier_id)->sum('credit');
        $allDebits = SupplierLedger::where('supplier_id', $payment->supplier_id)->sum('debit');
        $remainingDue = max(0, (float)$allCredits - (float)$allDebits);
        $previousDue = $remainingDue + (float)$payment->debit;

        $amountInWords = Helper::numberToWords($payment->debit);
        $setting = Helper::invoiceBranding($payment->supplier->vendor_id ?? (auth()->check() ? auth()->user()->branchId() : null));

        return view('admin.supplier_payment.slip', compact(
            'payment',
            'previousDue',
            'remainingDue',
            'amountInWords',
            'setting'
        ));
    }

    /**
     * Get unpaid / partially paid purchases for a supplier via AJAX.
     *
     * @param int $supplierId
     * @return JsonResponse
     */
    public function supplierUnpaidPurchases(int $supplierId): JsonResponse
    {
        $purchases = PurchaseMaster::where('supplier_id', $supplierId)
            ->select('id', 'purchase_invoice_no', 'supplier_memo_no', 'date', 'total_amount', 'paid')
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get()
            ->map(function ($p) {
                $total = (float) $p->total_amount;
                $paid = (float) $p->paid;
                $due = max(0, $total - $paid);
                return [
                    'id'         => $p->id,
                    'invoice_no' => $p->purchase_invoice_no,
                    'memo_no'    => $p->supplier_memo_no,
                    'date'       => $p->date ? $p->date->format('d M Y') : '',
                    'total'      => $total,
                    'due'        => $due,
                    'label'      => ($p->supplier_memo_no ? "Memo: {$p->supplier_memo_no} | " : '') . "Inv #{$p->purchase_invoice_no} ({$p->date->format('d M Y')}) - Bill: ৳" . number_format($total, 2) . ($due > 0 ? " [Due: ৳" . number_format($due, 2) . "]" : "")
                ];
            });

        return response()->json(['success' => true, 'purchases' => $purchases]);
    }

    /**
     * Show the form for editing the specified supplier payment.
     *
     * @param int $id
     * @return View
     */
    public function supplierPaymentEdit(int $id): View
    {
        $data['suppliers'] = Supplier::where('status', 1)->orderBy('supplier_name', 'asc')->get();
        $data['paymentMethods'] = Helper::supplierPaymentMethods();
        $data['payment'] = SupplierLedger::where('id', $id)->where('type', 'Payment')->firstOrFail();

        // Calculate current due amount for this supplier (excluding this payment)
        $ledgers = SupplierLedger::where('supplier_id', $data['payment']->supplier_id)
            ->where('id', '!=', $id)
            ->get();
        $totalCredit = (float) $ledgers->sum('credit');
        $totalDebit = (float) $ledgers->sum('debit');
        $data['due_amount'] = max(0, $totalCredit - $totalDebit);

        return view('admin.supplier_payment.edit', $data);
    }

    /**
     * Update the specified supplier payment in storage.
     *
     * @param Request $request
     * @param int $id
     * @return RedirectResponse
     */
    public function supplierPaymentUpdate(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'supplier_id'      => ['required', 'exists:suppliers,id'],
            'date'             => ['required', 'date'],
            'debit'            => ['required', 'numeric', 'min:0.01'],
            'payment_method'   => ['required', 'string', 'in:Cash,Bank Deposit,Online Transfer'],
            'supplier_memo_no' => ['nullable', 'string', 'max:100'],
            'bank_name'        => ['nullable', 'string', 'max:100'],
            'transaction_no'   => ['nullable', 'string', 'max:100'],
            'details'          => ['nullable', 'string', 'max:500'],
        ]);

        DB::beginTransaction();
        try {
            $supplierLedger = SupplierLedger::where('id', $id)->where('type', 'Payment')->firstOrFail();
            $supplier = Supplier::findOrFail($request->supplier_id);

            // Calculate supplier's due amount (excluding current payment being edited)
            $ledgers = SupplierLedger::where('supplier_id', $request->supplier_id)
                ->where('id', '!=', $id)
                ->get();
            $totalCredit = (float) $ledgers->sum('credit');
            $totalDebit = (float) $ledgers->sum('debit');
            $dueAmount = max(0, $totalCredit - $totalDebit);

            if ($request->debit > $dueAmount && $dueAmount > 0) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['debit' => "Payment amount (৳" . number_format($request->debit, 2) . ") exceeds the outstanding due (৳" . number_format($dueAmount, 2) . ")."]);
            }

            $refInvoiceNo = $supplierLedger->ref_invoice_no ?? ('SP-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -4)));

            $memoNote = 'Payment ref: ' . $refInvoiceNo . ' [' . $request->payment_method . ']';
            if ($request->supplier_memo_no) {
                $memoNote .= ' | Supplier Memo: ' . $request->supplier_memo_no;
            }
            if ($request->transaction_no) {
                $memoNote .= ' Txn/Chq: ' . $request->transaction_no;
            }
            if ($request->bank_name) {
                $memoNote .= ' (' . $request->bank_name . ')';
            }
            $fullDetails = $request->details ? $request->details . ' | ' . $memoNote : $memoNote;

            $supplierLedger->supplier_id = $request->supplier_id;
            $supplierLedger->date = $request->date;
            $supplierLedger->details = $fullDetails;
            $supplierLedger->debit = $request->debit;
            $supplierLedger->supplier_memo_no = $request->supplier_memo_no;
            $supplierLedger->payment_method = $request->payment_method;
            $supplierLedger->transaction_no = $request->transaction_no;
            $supplierLedger->bank_name = $request->bank_name;
            if ($request->has('linked_purchase_id')) {
                $supplierLedger->linked_purchase_id = $request->linked_purchase_id ?: null;
            }
            $supplierLedger->save();

            // Update corresponding Ledger outflow entry
            $accLedger = Ledger::where('ref_invoice_no', $refInvoiceNo)->where('type', 'Supplier Payment')->first();
            if ($accLedger) {
                $accLedger->date = $request->date;
                $accLedger->details = "Supplier Payment: {$supplier->supplier_name} via {$request->payment_method}" . ($request->transaction_no ? " (Ref: {$request->transaction_no})" : '') . ($request->details ? " - {$request->details}" : '');
                $accLedger->expense_amount = $request->debit;
                $accLedger->save();
            } else {
                $branchId = auth()->check() ? auth()->user()->branchId() : null;
                $accLedger = new Ledger();
                $accLedger->invoice_no = 'EX-' . date('ymdhis');
                $accLedger->vendor_id = $branchId;
                $accLedger->date = $request->date;
                $accLedger->details = "Supplier Payment: {$supplier->supplier_name} via {$request->payment_method}" . ($request->transaction_no ? " (Ref: {$request->transaction_no})" : '') . ($request->details ? " - {$request->details}" : '');
                $accLedger->expense_amount = $request->debit;
                $accLedger->income_amount = 0;
                $accLedger->amount_type = Helper::$expense;
                $accLedger->type = 'Supplier Payment';
                $accLedger->ref_invoice_no = $refInvoiceNo;
                $accLedger->save();
            }

            DB::commit();
            return redirect()->route('suppliers.payment.slip', $supplierLedger->id)
                ->with('success', 'Payment Updated Successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('warning', 'An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified supplier payment from storage.
     *
     * @param int $id
     * @return RedirectResponse
     */
    public function supplierPaymentDestroy(Request $request, int $id): RedirectResponse
    {
        // Cancelling a payment: Super Admin only, with a reason; ledger rows are reversed, not deleted
        $reason = LedgerReversal::reasonFrom($request);
        $ledger = SupplierLedger::where('id', $id)->where('type', 'Payment')->firstOrFail();

        DB::beginTransaction();
        try {
            $reversal = app(LedgerReversal::class);
            $what = 'Supplier payment ' . ($ledger->ref_invoice_no ?: "#{$ledger->id}");
            $reversal->reverse('supplier_ledgers', fn ($q) => $q->where('id', $ledger->id), $reason, $what);
            if ($ledger->ref_invoice_no) {
                // The cash/bank outflow in the company ledger
                $reversal->reverse('ledgers', fn ($q) => $q->where('ref_invoice_no', $ledger->ref_invoice_no)->where('type', 'Supplier Payment'), $reason, $what);
            }
            $reversal->log('supplier_payment_cancelled', $ledger, [
                'supplier_id'    => $ledger->supplier_id,
                'amount'         => $ledger->debit,
                'date'           => $ledger->date,
                'ref_invoice_no' => $ledger->ref_invoice_no,
            ], $reason);

            DB::commit();
            return redirect()->back()->with('success', 'Payment cancelled. Its ledger entries were reversed, not deleted.');
        } catch (\Throwable $ex) {
            DB::rollBack();
            Log::error("Supplier payment cancel failed for #{$id}: " . $ex->getMessage());
            return redirect()->back()->with('warning', 'The payment could not be cancelled. Nothing was changed.');
        }
    }

    /**
     * Get the supplier's due balance amount.
     *
     * @param int $supplierId
     * @return array
     */
    public function getSupplierDue(int $supplierId): array
    {
        $ledgers = SupplierLedger::where('supplier_id', $supplierId)->get();

        $totalCredit = $ledgers->sum('credit');
        $totalDebit = $ledgers->sum('debit');   

        $due = $totalCredit - $totalDebit;

        return [
            'due' => max(0, $due),
            'total_credit' => $totalCredit,
            'total_debit' => $totalDebit,
        ];
    }
}
