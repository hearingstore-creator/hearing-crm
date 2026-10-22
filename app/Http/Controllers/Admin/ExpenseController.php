<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\IncomeExpenseHead;
use App\Models\Ledger;
use App\Models\User;
use App\Services\LedgerReversal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Debit Voucher. The voucher number is typed by the user (from the voucher book), never generated.
 * A voucher has one or more lines (one ledger row per expense head) that share the voucher number.
 */
class ExpenseController extends Controller
{
    /**
     * Configure middleware to handle authorization for different expense operations
     * 
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:expense.index')->only(['index', 'show', 'attachment']);
        $this->middleware('permission:expense.create')->only(['create', 'store']);
        $this->middleware('permission:expense.edit')->only(['edit', 'update']);
        $this->middleware('permission:expense.approve')->only(['approve']);
        $this->middleware('permission:expense.delete')->only(['destroy']);
    }

    /** Payment modes offered on a Debit Voucher. */
    public const PAYMENT_METHODS = ['Cash', 'Bank', 'Cheque', 'bKash', 'Nagad', 'Rocket', 'Card'];

    /**
     * Display a listing of the expense.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(Request $request): View
    {
        $query = Ledger::with('head')->where('amount_type', Helper::$expense);

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

        $expenses = $query->orderBy('id', 'desc')->get();
        $total = (float) $expenses->sum('expense_amount');
        $creators = User::whereIn('id', $expenses->pluck('created_by')->filter()->unique())->pluck('name', 'id');
        $heads = IncomeExpenseHead::where('head_type', Helper::$expense)->orderBy('head_name')->get();
        $methods = self::PAYMENT_METHODS;

        return view('admin.expense.index', compact('expenses', 'total', 'creators', 'heads', 'methods'));
    }

    /**
     * Show the form for creating a new expense (Debit Voucher).
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function create(): View
    {
        $data['heads'] = IncomeExpenseHead::where('head_type', Helper::$expense)->orderBy('head_name')->get();
        $data['methods'] = self::PAYMENT_METHODS;

        return view('admin.expense.create', $data);
    }

    /**
     * Voucher lines from the request. Older single-head posts (head + paid) become one line.
     * Completely empty rows are dropped. Returns [lines, usedLinesFormat].
     */
    protected function linesFrom(Request $request): array
    {
        $given = $request->input('lines');
        if (!is_array($given)) {
            return [[[
                'id'                     => null,
                'income_expense_head_id' => $request->input('income_expense_head_id'),
                'amount'                 => $request->input('paid'),
                'details'                => null,
            ]], false];
        }

        $lines = array_values(array_filter($given, fn ($l) => is_array($l)
            && (filled($l['income_expense_head_id'] ?? null) || filled($l['amount'] ?? null))));

        return [$lines, true];
    }

    /** Validation shared by store and update (voucher number rules are added by the caller). */
    protected function validateVoucher(Request $request, bool $usesLines, array $extraRules = []): array
    {
        $headRule = [
            'required',
            Rule::exists('income_expense_heads', 'id')->where('head_type', Helper::$expense)->whereNull('deleted_at'),
        ];

        $rules = [
            'date' => 'required|date',
            'payee_name' => 'nullable|string|max:150',
            'payment_method' => ['nullable', Rule::in(self::PAYMENT_METHODS)],
            'bank_name' => 'nullable|string|max:100',
            'transaction_no' => 'nullable|string|max:100',
            'details' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ] + $extraRules;

        if ($usesLines) {
            $rules += [
                'lines' => 'required|array|min:1|max:30',
                'lines.*.id' => 'nullable|integer',
                'lines.*.income_expense_head_id' => $headRule,
                'lines.*.amount' => 'required|numeric|gt:0|max:99999999',
                'lines.*.details' => 'nullable|string|max:500',
            ];
        } else {
            $rules += [
                'income_expense_head_id' => $headRule,
                'paid' => 'required|numeric|gt:0|max:99999999',
            ];
        }

        return $this->validate($request, $rules, [
            'income_expense_head_id.exists' => 'Choose an expense head from the list.',
            'lines.*.income_expense_head_id.exists' => 'Choose an expense head from the list.',
            'lines.*.income_expense_head_id.required' => 'Choose an expense head for every line.',
            'lines.*.amount.required' => 'Enter an amount for every line.',
            'lines.*.amount.gt' => 'Each line amount must be more than 0.',
            'lines.required' => 'Add at least one line with a head and an amount.',
            'paid.gt' => 'The amount must be more than 0.',
            'voucher_no.required' => 'Type the voucher number from your voucher book.',
        ]);
    }

    /**
     * Store a newly created expense (Debit Voucher) in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        [$lines, $usesLines] = $this->linesFrom($request);
        if ($usesLines) {
            $request->merge(['lines' => $lines]);
        }

        $this->validateVoucher($request, $usesLines, [
            'voucher_no' => ['required', 'string', 'max:40', function ($attr, $value, $fail) {
                if (Ledger::withoutGlobalScopes()->where('voucher_no', $value)->exists()) {
                    $fail('This voucher number is already used.');
                }
            }],
        ]);

        $voucherNo = trim($request->input('voucher_no'));
        $paymentMethod = $request->input('payment_method') ?: 'Cash';
        $attachment = $request->hasFile('attachment')
            ? $request->file('attachment')->store('expense-vouchers')
            : null;

        DB::transaction(function () use ($request, $lines, $voucherNo, $paymentMethod, $attachment) {
            foreach ($lines as $i => $line) {
                $row = (object) [
                    'income_expense_head_id' => $line['income_expense_head_id'],
                    'date'    => $request->input('date'),
                    'details' => filled($line['details'] ?? null) ? $line['details'] : $request->input('details'),
                    'paid'    => $line['amount'],
                ];
                Helper::expenseLedger($row, $i === 0 ? $voucherNo : $voucherNo . '-L' . ($i + 1), null, null, [
                    'voucher_no'      => $voucherNo,
                    'payee_name'      => $request->input('payee_name'),
                    'payment_method'  => $paymentMethod,
                    'bank_name'       => $request->input('bank_name'),
                    'transaction_no'  => $request->input('transaction_no'),
                    'attachment_path' => $attachment,
                    'status'          => 'Pending',
                ]);
            }
        });

        $first = Ledger::where('voucher_no', $voucherNo)->orderBy('id')->first();

        return redirect('admin/expense/' . $first->id)->with('success', 'Debit Voucher ' . $voucherNo . ' saved.');
    }

    /** Every line (ledger row) of a voucher; a row without a voucher number is its own voucher. */
    protected function voucherLines(Ledger $row)
    {
        if (!$row->voucher_no) {
            return collect([$row]);
        }

        return Ledger::with('head')->where('voucher_no', $row->voucher_no)
            ->where('amount_type', Helper::$expense)->orderBy('id')->get();
    }

    /**
     * Printable Debit Voucher.
     */
    public function show(int $id): View
    {
        $row = Ledger::where('amount_type', Helper::$expense)->findOrFail($id);
        $lines = $this->voucherLines($row);
        $voucher = $lines->first();
        $voucher->loadMissing('head');
        $kind = 'debit';
        $total = (float) $lines->sum('expense_amount');
        $preparedBy = $voucher->created_by ? User::find($voucher->created_by) : null;
        $approvedBy = $voucher->approved_by ? User::find($voucher->approved_by) : null;
        $amountInWords = Helper::numberToWords(abs($total));

        return view('admin.expense.voucher', compact('voucher', 'lines', 'total', 'kind', 'preparedBy', 'approvedBy', 'amountInWords'));
    }

    /**
     * Download the bill / receipt attached to a voucher.
     */
    public function attachment(int $id)
    {
        $voucher = Ledger::where('amount_type', Helper::$expense)->findOrFail($id);
        abort_unless($voucher->attachment_path && Storage::exists($voucher->attachment_path), 404);

        return Storage::response($voucher->attachment_path);
    }

    /**
     * A voucher can be changed only while it is a manual, un-approved Debit Voucher.
     */
    protected function editableVoucher(int $id): ?Ledger
    {
        return Ledger::where('id', $id)
            ->where('amount_type', Helper::$expense)
            ->whereNotNull('voucher_no')
            ->whereNull('type')
            ->whereNull('ref_invoice_no')
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'Approved'))
            ->first();
    }

    /**
     * Show the edit form for an un-approved Debit Voucher.
     */
    public function edit(int $id)
    {
        $voucher = $this->editableVoucher($id);
        if (!$voucher) {
            return redirect('admin/expense')->with('warning', 'Only un-approved vouchers entered on this screen can be edited.');
        }

        $lines = $this->voucherLines($voucher);
        $heads = IncomeExpenseHead::where('head_type', Helper::$expense)->orderBy('head_name')->get();
        $methods = self::PAYMENT_METHODS;

        return view('admin.expense.edit', compact('voucher', 'lines', 'heads', 'methods'));
    }

    /**
     * Update an un-approved Debit Voucher in place and log what changed.
     * Lines with an id are updated, new lines are added, and lines left out are removed.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $voucher = $this->editableVoucher($id);
        if (!$voucher) {
            return redirect('admin/expense')->with('warning', 'Only un-approved vouchers entered on this screen can be edited.');
        }

        [$lines, $usesLines] = $this->linesFrom($request);
        if ($usesLines) {
            $request->merge(['lines' => $lines]);
        } else {
            // Older single-head post: change only this one line, never drop the others
            $lines[0]['id'] = $voucher->id;
        }
        $data = $this->validateVoucher($request, $usesLines);

        $group = $this->voucherLines($voucher)->keyBy('id');
        $before = $group->map(fn ($r) => $r->only(['income_expense_head_id', 'expense_amount', 'details']))->all();
        $header = [
            'date'           => $data['date'],
            'payee_name'     => $data['payee_name'] ?? null,
            'payment_method' => ($data['payment_method'] ?? null) ?: 'Cash',
            'bank_name'      => $data['bank_name'] ?? null,
            'transaction_no' => $data['transaction_no'] ?? null,
        ];
        if ($request->hasFile('attachment')) {
            if ($voucher->attachment_path) {
                Storage::delete($voucher->attachment_path);
            }
            $header['attachment_path'] = $request->file('attachment')->store('expense-vouchers');
        }

        $removed = [];
        DB::transaction(function () use ($request, $lines, $usesLines, $group, $voucher, $header, &$removed) {
            $keptIds = [];
            foreach ($lines as $i => $line) {
                $lineDetails = filled($line['details'] ?? null) ? $line['details'] : ($request->input('details'));
                $existing = $group->get((int) ($line['id'] ?? 0));

                if ($existing) {
                    $existing->update($header + [
                        'income_expense_head_id' => $line['income_expense_head_id'],
                        'expense_amount'         => $line['amount'],
                        'details'                => $lineDetails,
                        'updated_by'             => auth()->id(),
                    ]);
                    $keptIds[] = $existing->id;
                } else {
                    Helper::expenseLedger((object) [
                        'income_expense_head_id' => $line['income_expense_head_id'],
                        'date'    => $header['date'],
                        'details' => $lineDetails,
                        'paid'    => $line['amount'],
                    ], $voucher->voucher_no . '-L' . ($group->count() + $i + 1), null, null, [
                        'voucher_no'      => $voucher->voucher_no,
                        'payee_name'      => $header['payee_name'],
                        'payment_method'  => $header['payment_method'],
                        'bank_name'       => $header['bank_name'],
                        'transaction_no'  => $header['transaction_no'],
                        'attachment_path' => $header['attachment_path'] ?? $voucher->attachment_path,
                        'status'          => 'Pending',
                    ]);
                }
            }

            if ($usesLines) {
                $removed = $group->keys()->diff($keptIds)->values()->all();
                if ($removed) {
                    Ledger::whereIn('id', $removed)->delete();
                }
                // Other lines of the voucher follow the shared header fields
                if ($keptIds) {
                    Ledger::whereIn('id', $keptIds)->update(collect($header)->except('date')->all() + ['date' => $header['date']]);
                }
            }

            app(LedgerReversal::class)->log('expense_edited', $voucher, [
                'voucher_no'    => $voucher->voucher_no,
                'removed_lines' => $removed,
                'header'        => $header,
            ], 'Voucher edited');
        });

        return redirect('admin/expense/' . $voucher->id)->with('success', 'Voucher ' . $voucher->voucher_no . ' updated.');
    }

    /**
     * Approve a Debit Voucher (somebody other than the person who made it, unless Super Admin).
     * Every line of the voucher is approved together.
     */
    public function approve(int $id): RedirectResponse
    {
        $voucher = Ledger::where('amount_type', Helper::$expense)->whereNotNull('voucher_no')->whereNull('type')->findOrFail($id);

        if ($voucher->status === 'Approved') {
            return back()->with('warning', 'This voucher is already approved.');
        }
        if ($voucher->created_by == auth()->id() && !auth()->user()->isSuperAdmin()) {
            return back()->with('warning', 'A voucher must be approved by someone other than the person who made it.');
        }

        Ledger::where('voucher_no', $voucher->voucher_no)->where('amount_type', Helper::$expense)->whereNull('type')
            ->update(['status' => 'Approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);

        return back()->with('success', 'Voucher ' . $voucher->voucher_no . ' approved.');
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
            // A voucher is cancelled as a whole: all of its lines are reversed together
            $filter = $ledger->voucher_no
                ? fn ($q) => $q->where('voucher_no', $ledger->voucher_no)->where('amount_type', Helper::$expense)
                : fn ($q) => $q->where('id', $ledger->id);
            $total = $ledger->voucher_no
                ? (float) Ledger::where('voucher_no', $ledger->voucher_no)->where('amount_type', Helper::$expense)->whereNull('type')->sum('expense_amount')
                : (float) $ledger->expense_amount;

            $reversal->reverse('ledgers', $filter, $reason, 'Expense ' . ($ledger->voucher_no ?: $ledger->invoice_no ?: "#{$ledger->id}"));
            $reversal->log('expense_cancelled', $ledger, [
                'amount'  => $total,
                'date'    => $ledger->date,
                'head_id' => $ledger->income_expense_head_id,
                'details' => $ledger->details,
            ], $reason);
        });

        return redirect('/admin/expense')->with('success', 'Expense cancelled. Its ledger entry was reversed, not deleted.');
    }
}
