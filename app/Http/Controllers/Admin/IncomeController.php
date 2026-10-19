<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\IncomeExpenseHead;
use App\Models\Ledger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class IncomeController extends Controller
{
    /**
     * Configure middleware to handle authorization for different income operations
     * 
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:income.index')->only(['index', 'incomeList']);
        $this->middleware('permission:income.create')->only(['create', 'store']);
        $this->middleware('permission:income.delete')->only(['destroy']);
    }

    /**
     * Display a listing of the resource.
     *
     * This method retrieves and displays a list of income transactions.
     * It corresponds to the 'admin.income.index' view.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        return view('admin.income.index');
    }

    /**
     * Provide a list of incomes for DataTables.
     *
     * This method is called via AJAX to populate the DataTables on the income index page.
     * It fetches all ledger entries marked as income, orders them by ID, and formats them for the table.
     * The 'details' column is enhanced with invoice references if available.
     * An action button for deletion is added for entries linked to an income/expense head.
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function incomeList(): JsonResponse
    {
        $data = Ledger::where('amount_type', Helper::$income)->orderBy('id', 'desc')->get();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('details', function ($row) {
                if ($row->ref_invoice_no !== null) {
                    $ref = '(' . $row->type . '- invoice no-' . $row->ref_invoice_no . ')';

                    return $row->details . $ref;
                }

                return $row->details;
            })
            ->addColumn('action', function ($row) {
                if (empty($row->income_expense_head_id)) {
                    return '';
                }

                return '<div class="row">
                    <div class="col-6">
                    <form action="' . route('income.destroy', $row->id) . '" method="POST">
                    <input type="hidden" name="_token" value="' . csrf_token() . '" />
                    <input type="hidden" name="_method" value="delete" />
                    <button type="submit" class="btn btn-danger btn-sm" onclick="confirmDelete(event)"><i class="far fa-trash-alt"> </i></button>
                    </form>
                    </div>
                    </div>';
            })
            ->rawColumns(['details', 'action'])
            ->toJson();
    }

    /**
     * Show the form for creating a new resource.
     *
     * This method displays the form for recording a new income.
     * It fetches all active income heads to populate a dropdown in the 'admin.income.create' view.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function create(): View
    {
        $data['heads'] = IncomeExpenseHead::where('head_type', Helper::$income)->orderBy('id', 'desc')->get();

        return view('admin.income.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * This method validates and saves a new income transaction.
     * It uses the Helper::incomeLedger() method to create the corresponding ledger entry.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'income_expense_head_id' => [
                'required',
                \Illuminate\Validation\Rule::exists('income_expense_heads', 'id')->where('head_type', Helper::$income)->whereNull('deleted_at'),
            ],
            'date' => 'required|date',
            'paid_amount' => 'required|numeric|gt:0|max:99999999',
        ], [
            'income_expense_head_id.exists' => 'Choose an income head from the list.',
            'paid_amount.gt' => 'The amount must be more than 0.',
        ]);

        // The form posts the amount as paid_amount; the ledger helper reads it as paid.
        // Without this the entry was saved with no amount at all.
        $request->merge(['paid' => $request->paid_amount]);

        $incomeInvoiceNo = date('ymdhis');
        Helper::incomeLedger($request, $incomeInvoiceNo, null, null);

        return redirect()->back()->with('success', 'Saved Successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * This method deletes a specific income record from the ledger.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        $ledger = Ledger::findOrFail($id);
        $ledger->delete();

        return redirect('/admin/income')->with('success', 'Deleted Successfully');
    }
}
