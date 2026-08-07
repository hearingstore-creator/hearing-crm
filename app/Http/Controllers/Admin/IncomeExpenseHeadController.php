<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\IncomeExpenseHead;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IncomeExpenseHeadController extends Controller
{
    /**
     * Configure middleware to handle authorization for different income/expense head operations
     * 
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:income_expense_head.index')->only(['index']);
        $this->middleware('permission:income_expense_head.create')->only(['create', 'store']);
        $this->middleware('permission:income_expense_head.edit')->only(['edit', 'update']);
        $this->middleware('permission:income_expense_head.delete')->only(['destroy']);
    }

    /**
     * Display a listing of the income/expense heads.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        $incomeExpenseHeads = IncomeExpenseHead::orderBy('id', 'desc')->get();

        return view('admin.income_expense_head.index', compact('incomeExpenseHeads'));
    }

    /**
     * Show the form for creating a new income/expense head.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function create(): View
    {
        $data['headTypes'] = [Helper::$income, Helper::$expense];

        return view('admin.income_expense_head.create', $data);
    }

    /**
     * Store a newly created income/expense head in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'head_type' => 'required|string',
            'head_name' => 'required|string|unique:income_expense_heads',
        ]);

        $incomeExpenseHead = new IncomeExpenseHead($request->all());

        if ($incomeExpenseHead->save()) {
            return redirect('admin/income-expense-head')->with('success', 'Saved Successfully');
        }

        return redirect()->back()->with('warning', 'Not Saved');
    }

    /**
     * Show the form for editing the specified income/expense head.
     *
     * @param  int  $id
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(int $id): View
    {
        $data['incomeExpenseHead'] = IncomeExpenseHead::findOrFail($id);
        $data['headTypes'] = [Helper::$income, Helper::$expense];

        return view('admin.income_expense_head.edit', $data);
    }

    /**
     * Update the specified income/expense head in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->validate($request, [
            'head_type' => 'required|string',
            'head_name' => 'required|unique:income_expense_heads,head_name,' . $id,
        ]);

        $incomeExpenseHead = IncomeExpenseHead::findOrFail($id);
        $incomeExpenseHead->fill($request->all());

        if ($incomeExpenseHead->save()) {
            return redirect('admin/income-expense-head')->with('success', 'Updated Successfully');
        }

        return redirect()->back()->with('warning', 'Not Updated');
    }

    /**
     * Remove the specified income/expense head from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        $incomeExpenseHead = IncomeExpenseHead::findOrFail($id);
        $incomeExpenseHead->delete();

        return redirect('/admin/income-expense-head')->with('success', 'Deleted Successfully');
    }
}
