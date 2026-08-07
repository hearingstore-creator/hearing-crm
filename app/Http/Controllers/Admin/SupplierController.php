<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $this->middleware('permission:supplier_ledger.view')->only(['createSupplierLedger', 'supplierLedger']);
        $this->middleware('permission:supplier_payment.index')->only(['supplierPaymentIndex']);
        $this->middleware('permission:supplier_payment.create')->only(['supplierPaymentCreate', 'supplierPaymentStore']);
        $this->middleware('permission:supplier_payment.edit')->only(['supplierPaymentEdit', 'supplierPaymentUpdate']);
        $this->middleware('permission:supplier_payment.delete')->only(['supplierPaymentDestroy']);
    }

    /**
     * Display a listing of the suppliers.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        $data['suppliers'] = Supplier::orderBy('id', 'desc')->get();
        return view('admin.supplier.index', $data);
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

        if (auth()->user()->isVendor()) {
            $requestAll['vendor_id'] = auth()->id();
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
     * Show the form for creating a supplier ledger.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function createSupplierLedger(): View
    {
        $data['suppliers'] = Supplier::where('status', 1)->orderBy('id', 'desc')->get();

        return view('admin.supplier.ledger', $data);
    }

    /**
     * Display the supplier ledger within a date range.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\View\View
     */
    public function supplierLedger(Request $request): View
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $supplierId = $request->supplier_id;
        $suppliers = Supplier::where('status', 1)->orderBy('id', 'desc')->get();
        $supplierName = Supplier::where('id', $supplierId)->value('supplier_name');

        $ledgers = SupplierLedger::where('supplier_id', $request->supplier_id)
            ->whereBetween('date', [$startDate, $endDate])
            ->ForAuthVendor()
            ->orderBy('id', 'desc')
            ->get();

        return view('admin.supplier.ledger', compact('ledgers', 'startDate', 'endDate', 'suppliers', 'supplierId', 'supplierName'));
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

        if (auth()->user()->isVendor()) {
            $requestAll['vendor_id'] = auth()->id();
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
        $supplier->delete();

        return redirect('/admin/suppliers')->with('success', 'Deleted Successfully');
    }

    /**
     * Display a listing of the supplier payments.
     *
     * @return View
     */
    public function supplierPaymentIndex(): View
    {
        $data['payments'] = SupplierLedger::with('supplier')
            ->where('type', 'Payment')
            ->latest()
            ->paginate(20);

        return view('admin.supplier_payment.index', $data);
    }

    /**
     * Show the form for creating a new supplier payment.
     *
     * @return View
     */
    public function supplierPaymentCreate(): View
    {
        $data['suppliers'] = Supplier::where('status', 1)->get();
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
            'supplier_id' => ['required'],
            'date' => ['required'],
            'debit' => ['required', 'numeric', 'min:0'],
            'details' => ['nullable'],
        ]);

        // Calculate supplier's due amount
        $ledgers = SupplierLedger::where('supplier_id', $request->supplier_id)->get();
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
            $refInvoiceNo = 'SP-' . date('ymdhis');

            $supplierLedger = new SupplierLedger();
            $supplierLedger->supplier_id = $request->supplier_id;
            $supplierLedger->date = $request->date;
            $supplierLedger->details = $request->details ? $request->details . ' (Payment ref no - ' . $refInvoiceNo . ')' : 'Payment ref no - ' . $refInvoiceNo;
            $supplierLedger->debit = $request->debit;
            $supplierLedger->credit = 0;
            $supplierLedger->type = 'Payment';
            $supplierLedger->ref_invoice_no = $refInvoiceNo;

            if ($supplierLedger->save()) {
                DB::commit();
                return redirect('admin/suppliers-payment')->with('success', 'Payment Saved Successfully');
            }

            DB::rollBack();
            return redirect()->back()->with('warning', 'Payment Not Saved');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('warning', 'An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified supplier payment.
     *
     * @param int $id
     * @return View
     */
    public function supplierPaymentEdit(int $id): View
    {
        $data['suppliers'] = Supplier::where('status', 1)->get();
        $data['payment'] = SupplierLedger::where('id', $id)->where('type', 'Payment')->first();

        // Calculate current due amount for this supplier
        if ($data['payment']) {
            $dueResponse = $this->getSupplierDue($data['payment']->supplier_id);
            $data['due_amount'] = $dueResponse['due'] ?? 0.00;
        }

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
            'supplier_id' => ['required'],
            'date' => ['required'],
            'debit' => ['required', 'numeric', 'min:0'],
            'details' => ['nullable'],
        ]);

        DB::beginTransaction();
        try {
            $supplierLedger = SupplierLedger::where('id', $id)->where('type', 'Payment')->first();

            if (!$supplierLedger) {
                return redirect()->back()->with('warning', 'Payment not found');
            }

            // Calculate supplier's due amount (excluding current payment being edited)
            $ledgers = SupplierLedger::where('supplier_id', $request->supplier_id)
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

            $refInvoiceNo = $supplierLedger->ref_invoice_no ?? 'SP-' . date('ymdhis');

            $supplierLedger->supplier_id = $request->supplier_id;
            $supplierLedger->date = $request->date;
            $supplierLedger->details = $request->details ? $request->details . ' (Payment ref no - ' . $refInvoiceNo . ')' : 'Payment ref no - ' . $refInvoiceNo;
            $supplierLedger->debit = $request->debit;
            $supplierLedger->type = 'Payment';
            $supplierLedger->ref_invoice_no = $refInvoiceNo;

            if ($supplierLedger->save()) {
                DB::commit();
                return redirect('admin/suppliers-payment')->with('success', 'Payment Updated Successfully');
            }

            DB::rollBack();
            return redirect()->back()->with('warning', 'Payment Not Updated');
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
    public function supplierPaymentDestroy(int $id): RedirectResponse
    {
        try {
            $ledger = SupplierLedger::where('id', $id)->where('type', 'Payment')->first();

            if ($ledger) {
                $ledger->delete();
            }

            return redirect()->back()->with('success', 'Payment Deleted Successfully');
        } catch (\Exception $ex) {
            return redirect()->back()->with('warning', $ex->getMessage());
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
