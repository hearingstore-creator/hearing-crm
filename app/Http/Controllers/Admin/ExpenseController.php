<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\IncomeExpenseHead;
use App\Models\Ledger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
    public function destroy(int $id): RedirectResponse
    {
        $ledger = Ledger::findOrFail($id);
        $ledger->delete();

        return redirect('/admin/expense')->with('success', 'Deleted Successfully');
    }
}
