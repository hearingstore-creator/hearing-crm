<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\IncomeExpenseHead;
use App\Models\Ledger;
use App\Services\LedgerReversal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    /**
     * Configure middleware to handle authorization for different expense operations
     * 
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:expense.index')->only(['index']);
        $this->middleware('permission:expense.create')->only(['create', 'store']);
        $this->middleware('permission:expense.delete')->only(['destroy']);
    }

    /**
     * Display a listing of the expense.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        $expenses = Ledger::where('amount_type', Helper::$expense)->orderBy('id', 'desc')->get();
        return view('admin.expense.index', compact('expenses'));
    }

    /**
     * Show the form for creating a new expense.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function create(): View
    {
        $data['heads'] = IncomeExpenseHead::where('head_type', Helper::$expense)->orderBy('id', 'desc')->get();

        return view('admin.expense.create', $data);
    }

    /**
     * Store a newly created expense in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'income_expense_head_id' => 'required',
            'date' => 'required|date',
            'paid' => 'required',
        ]);

        $expenseInvoiceNo = date('ymdhis');
        Helper::expenseLedger($request, $expenseInvoiceNo, null, null);

        return redirect()->back()->with('success', 'Saved Successfully');
    }

    /**
     * Remove the specified expense from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        // Super Admin only, with a reason; the row is reversed, not deleted
        $reason = LedgerReversal::reasonFrom($request);

        // Only expenses entered on this screen: sale income, purchase, supplier / doctor payments and
        // refunds are cancelled from their own screens so their linked records stay consistent.
        $ledger = Ledger::where('id', $id)
            ->where('amount_type', Helper::$expense)
            ->whereNull('type')
            ->whereNull('ref_invoice_no')
            ->first();
        if (!$ledger) {
            return redirect('/admin/expense')->with('warning', 'Only expenses entered on this screen can be cancelled here. Cancel payments and invoices from their own screens.');
        }

        DB::transaction(function () use ($ledger, $reason) {
            $reversal = app(LedgerReversal::class);
            $reversal->reverse('ledgers', fn ($q) => $q->where('id', $ledger->id), $reason, 'Expense ' . ($ledger->invoice_no ?: "#{$ledger->id}"));
            $reversal->log('expense_cancelled', $ledger, [
                'amount'  => $ledger->expense_amount,
                'date'    => $ledger->date,
                'head_id' => $ledger->income_expense_head_id,
                'details' => $ledger->details,
            ], $reason);
        });

        return redirect('/admin/expense')->with('success', 'Expense cancelled. Its ledger entry was reversed, not deleted.');
    }
}
