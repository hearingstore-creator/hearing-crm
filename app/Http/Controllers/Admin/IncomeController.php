<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\IncomeExpenseHead;
use App\Models\Ledger;
use App\Models\User;
use App\Services\LedgerReversal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;

/** Credit Voucher: money received, posted to the same ledger as before. */
class IncomeController extends Controller
{
    /**
     * Configure middleware to handle authorization for different income operations
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:income.index')->only(['index', 'incomeList', 'show', 'attachment']);
        $this->middleware('permission:income.create')->only(['create', 'store']);
        $this->middleware('permission:income.approve')->only(['approve']);
        $this->middleware('permission:income.delete')->only(['destroy']);
    }

    /**
     * Display a listing of the income vouchers, with filters and a total.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(Request $request): View
    {
        $query = Ledger::with('head')->where('amount_type', Helper::$income);

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->input('to'));
        }
        if ($request->filled('head')) {
            $query->where('income_expense_head_id', $request->input('head'));
        }
        if ($request->filled('method')) {
            $query->where('payment_method', $request->input('method'));
        }
        if ($request->filled('q')) {
            $term = '%' . trim($request->input('q')) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('voucher_no', 'like', $term)
                    ->orWhere('invoice_no', 'like', $term)
                    ->orWhere('payee_name', 'like', $term)
                    ->orWhere('details', 'like', $term)
                    ->orWhere('transaction_no', 'like', $term);
            });
        }

        $incomes = $query->orderBy('id', 'desc')->get();
        $total = (float) $incomes->sum('income_amount');
        $heads = IncomeExpenseHead::where('head_type', Helper::$income)->orderBy('head_name')->get();
        $methods = ExpenseController::PAYMENT_METHODS;

        return view('admin.income.index', compact('incomes', 'total', 'heads', 'methods'));
    }

    /**
     * Provide a list of incomes for DataTables (kept for older links).
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
            ->rawColumns(['details'])
            ->toJson();
    }

    /**
     * Show the form for creating a new Credit Voucher.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function create(): View
    {
        $data['heads'] = IncomeExpenseHead::where('head_type', Helper::$income)->orderBy('head_name')->get();
        $data['methods'] = ExpenseController::PAYMENT_METHODS;

        return view('admin.income.create', $data);
    }

    /**
     * Store a newly created Credit Voucher.
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
            'payee_name' => 'nullable|string|max:150',
            'payment_method' => ['nullable', \Illuminate\Validation\Rule::in(ExpenseController::PAYMENT_METHODS)],
            'bank_name' => 'nullable|string|max:100',
            'transaction_no' => 'nullable|string|max:100',
            'details' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ], [
            'income_expense_head_id.exists' => 'Choose an income head from the list.',
            'paid_amount.gt' => 'The amount must be more than 0.',
        ]);

        // The form posts the amount as paid_amount; the ledger helper reads it as paid.
        $request->merge(['paid' => $request->paid_amount]);

        $attachment = $request->hasFile('attachment')
            ? $request->file('attachment')->store('income-vouchers')
            : null;
        $paymentMethod = $request->input('payment_method') ?: 'Cash';

        $voucher = null;
        for ($attempt = 0; $attempt < 3 && !$voucher; $attempt++) {
            $voucherNo = Helper::nextVoucherNo('CV', $request->input('date'));
            try {
                DB::transaction(function () use ($request, $voucherNo, $attachment, $paymentMethod) {
                    Helper::incomeLedger($request, $voucherNo, null, null, [
                        'voucher_no'      => $voucherNo,
                        'payee_name'      => $request->input('payee_name'),
                        'payment_method'  => $paymentMethod,
                        'bank_name'       => $request->input('bank_name'),
                        'transaction_no'  => $request->input('transaction_no'),
                        'attachment_path' => $attachment,
                        'status'          => 'Pending',
                    ]);
                });
                $voucher = Ledger::where('voucher_no', $voucherNo)->first();
            } catch (\Illuminate\Database\QueryException $e) {
                if ($attempt === 2) {
                    throw $e;
                }
            }
        }

        return redirect('admin/income/' . $voucher->id)->with('success', 'Credit Voucher ' . $voucher->voucher_no . ' saved.');
    }

    /**
     * Printable Credit Voucher.
     */
    public function show(int $id): View
    {
        $voucher = Ledger::with('head')->where('amount_type', Helper::$income)->findOrFail($id);
        $kind = 'credit';
        $preparedBy = $voucher->created_by ? User::find($voucher->created_by) : null;
        $approvedBy = $voucher->approved_by ? User::find($voucher->approved_by) : null;
        $amountInWords = Helper::numberToWords(abs((float) $voucher->income_amount));

        return view('admin.expense.voucher', compact('voucher', 'kind', 'preparedBy', 'approvedBy', 'amountInWords'));
    }

    /**
     * Download the document attached to a voucher.
     */
    public function attachment(int $id)
    {
        $voucher = Ledger::where('amount_type', Helper::$income)->findOrFail($id);
        abort_unless($voucher->attachment_path && Storage::exists($voucher->attachment_path), 404);

        return Storage::response($voucher->attachment_path);
    }

    /**
     * Approve a Credit Voucher (somebody other than the person who made it, unless Super Admin).
     */
    public function approve(int $id): RedirectResponse
    {
        $voucher = Ledger::where('amount_type', Helper::$income)->whereNotNull('voucher_no')->whereNull('type')->findOrFail($id);

        if ($voucher->status === 'Approved') {
            return back()->with('warning', 'This voucher is already approved.');
        }
        if ($voucher->created_by == auth()->id() && !auth()->user()->isSuperAdmin()) {
            return back()->with('warning', 'A voucher must be approved by someone other than the person who made it.');
        }

        $voucher->update(['status' => 'Approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);

        return back()->with('success', 'Voucher ' . $voucher->voucher_no . ' approved.');
    }

    /**
     * Cancel a manual income: Super Admin only, with a reason; the ledger row is reversed, not deleted.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $reason = LedgerReversal::reasonFrom($request);

        // Only income entered on this screen; sales and other documents are cancelled from their own screens.
        $ledger = Ledger::where('id', $id)
            ->where('amount_type', Helper::$income)
            ->whereNull('type')
            ->whereNull('ref_invoice_no')
            ->whereNotNull('income_expense_head_id')
            ->first();
        if (!$ledger) {
            return redirect('/admin/income')->with('warning', 'Only income entered on this screen can be cancelled here. Cancel sales and payments from their own screens.');
        }

        DB::transaction(function () use ($ledger, $reason) {
            $reversal = app(LedgerReversal::class);
            $reversal->reverse('ledgers', fn ($q) => $q->where('id', $ledger->id), $reason, 'Income ' . ($ledger->voucher_no ?: $ledger->invoice_no ?: "#{$ledger->id}"));
            $reversal->log('income_cancelled', $ledger, [
                'amount'  => $ledger->income_amount,
                'date'    => $ledger->date,
                'head_id' => $ledger->income_expense_head_id,
                'details' => $ledger->details,
            ], $reason);
        });

        return redirect('/admin/income')->with('success', 'Income cancelled. Its ledger entry was reversed, not deleted.');
    }
}
