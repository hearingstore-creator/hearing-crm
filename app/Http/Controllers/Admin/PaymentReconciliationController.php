<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Models\PaymentReconciliation;
use App\Models\SaleMaster;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PaymentReconciliationController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:payment_reconciliation.index')->only(['index', 'showSlip']);
        $this->middleware('permission:payment_reconciliation.create')->only(['settleSingle', 'settleBatch']);
        $this->middleware('permission:payment_reconciliation.delete')->only(['destroy']);
        $this->middleware(fn ($request, $next) => Helper::forceReportBranch($request, $next));
    }

    /**
     * Display the Payment Reconciliation Hub.
     */
    public function index(Request $request): View
    {
        $tab = $request->input('tab', 'pending');
        $branchId = Helper::reportBranchId();
        $vendorFilter = $branchId ?: ($request->input('vendor_id') ?: $request->input('branch'));

        // Channels
        $channels = PaymentReconciliation::channelOptions();

        // ══════════════════════════════════════════════════════════════
        // TAB 1: PENDING SETTLEMENTS (Unsettled Digital Payments)
        // ══════════════════════════════════════════════════════════════
        $pendingQuery = SaleMaster::with(['customer.lead', 'vendor'])
            ->where('paid', '>', 0)
            ->where(function ($q) {
                $q->where('gateway_settlement_status', 'pending')
                  ->orWhere(function ($sub) {
                      $sub->whereIn('payment_id', [3, 5, 6, 7, 8])
                          ->where('gateway_settlement_status', '!=', 'reconciled');
                  });
            });

        if ($vendorFilter && $vendorFilter !== 'all') {
            if ($vendorFilter === 'hq') {
                $pendingQuery->whereNull('vendor_id');
            } else {
                $pendingQuery->where('vendor_id', (int) $vendorFilter);
            }
        }

        if ($request->filled('channel')) {
            $ch = $request->channel;
            $pendingQuery->where(function ($q) use ($ch) {
                $q->where('payment_method', $ch)
                  ->orWhere(function ($sub) use ($ch) {
                      $pId = match($ch) {
                          'card' => 5,
                          'bkash' => 6,
                          'nagad' => 7,
                          'rocket' => 8,
                          'bank_transfer' => 2,
                          default => 3,
                      };
                      $sub->where('payment_id', $pId);
                  });
            });
        }

        if ($request->filled('start_date')) {
            $pendingQuery->whereDate('date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $pendingQuery->whereDate('date', '<=', $request->end_date);
        }

        if ($request->filled('q')) {
            $term = trim($request->q);
            $pendingQuery->where(function ($q) use ($term) {
                $q->where('sale_invoice_no', 'like', "%{$term}%")
                  ->orWhere('payment_trx_no', 'like', "%{$term}%")
                  ->orWhere('pos_terminal_name', 'like', "%{$term}%")
                  ->orWhereHas('customer.lead', function ($lq) use ($term) {
                      $lq->where('full_name', 'like', "%{$term}%")
                         ->orWhere('phone_number', 'like', "%{$term}%");
                  });
            });
        }

        // Summary counters for Pending
        $pendingBaseQuery = clone $pendingQuery;
        $allPendingItems = $pendingBaseQuery->get(['id', 'paid', 'payment_id', 'payment_method']);
        $pendingCount = $allPendingItems->count();
        $pendingGross = (float) $allPendingItems->sum('paid');
        $cardPendingGross = (float) $allPendingItems->filter(fn ($s) => $s->payment_id == 5 || $s->payment_method === 'card')->sum('paid');
        $mfsPendingGross = (float) $allPendingItems->filter(fn ($s) => in_array($s->payment_id, [6, 7, 8]) || in_array($s->payment_method, ['bkash', 'nagad', 'rocket']))->sum('paid');

        $pendingSales = $pendingQuery->orderBy('date', 'desc')->orderBy('id', 'desc')->paginate(25, ['*'], 'pending_page');

        // ══════════════════════════════════════════════════════════════
        // TAB 2: RECONCILIATION HISTORY & BATCHES
        // ══════════════════════════════════════════════════════════════
        $historyQuery = PaymentReconciliation::with(['vendor', 'reconciledBy', 'ledger', 'sales.customer.lead']);

        if ($vendorFilter && $vendorFilter !== 'all') {
            if ($vendorFilter === 'hq') {
                $historyQuery->whereNull('vendor_id');
            } else {
                $historyQuery->where('vendor_id', (int) $vendorFilter);
            }
        }

        if ($request->filled('history_channel')) {
            $historyQuery->where('channel', $request->history_channel);
        }
        if ($request->filled('history_start')) {
            $historyQuery->whereDate('settlement_date', '>=', $request->history_start);
        }
        if ($request->filled('history_end')) {
            $historyQuery->whereDate('settlement_date', '<=', $request->history_end);
        }
        if ($request->filled('history_q')) {
            $term = trim($request->history_q);
            $historyQuery->where(function ($q) use ($term) {
                $q->where('reconciliation_no', 'like', "%{$term}%")
                  ->orWhere('batch_no', 'like', "%{$term}%")
                  ->orWhere('pos_terminal_name', 'like', "%{$term}%")
                  ->orWhere('notes', 'like', "%{$term}%");
            });
        }

        $historyBase = clone $historyQuery;
        $historySummary = $historyBase->get(['id', 'gross_amount', 'fee_amount', 'net_amount']);
        $historyCount = $historySummary->count();
        $historyTotalFees = (float) $historySummary->sum('fee_amount');
        $historyTotalNet = (float) $historySummary->sum('net_amount');

        $reconciliations = $historyQuery->orderBy('settlement_date', 'desc')->orderBy('id', 'desc')->paginate(20, ['*'], 'history_page');

        // Branch options for Super Admin
        $vendors = User::where('type', 'vendor')->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);

        return view('admin.payment_reconciliation.index', compact(
            'tab',
            'channels',
            'pendingSales',
            'pendingCount',
            'pendingGross',
            'cardPendingGross',
            'mfsPendingGross',
            'reconciliations',
            'historyCount',
            'historyTotalFees',
            'historyTotalNet',
            'vendors',
            'vendorFilter'
        ));
    }

    /**
     * Settle a single sale's digital payment with exact fee deduction.
     */
    public function settleSingle(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'settlement_date' => ['required', 'date'],
            'fee_amount'      => ['required', 'numeric', 'min:0'],
            'fee_rate'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'batch_no'        => ['nullable', 'string', 'max:100'],
            'pos_terminal_name' => ['nullable', 'string', 'max:100'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ]);

        $sale = SaleMaster::findOrFail($id);

        if ($sale->gateway_settlement_status === 'reconciled') {
            return redirect()->back()->with('warning', 'This transaction is already reconciled.');
        }

        $gross = (float) $sale->paid;
        $fee = (float) $request->fee_amount;
        if ($fee > $gross) {
            return redirect()->back()->with('warning', 'Charge amount cannot exceed total paid amount.')->withInput();
        }

        $net = max(0, round($gross - $fee, 2));
        $channel = $sale->payment_method ?: Helper::channelKeyFromPaymentId($sale->payment_id);
        $terminal = $request->pos_terminal_name ?: ($sale->pos_terminal_name ?: ($channel === 'card' ? 'Card POS Terminal' : 'bKash/MFS'));

        DB::beginTransaction();
        try {
            // 1. Create Reconciliation Record
            $rec = PaymentReconciliation::create([
                'reconciliation_no'   => PaymentReconciliation::nextReconciliationNo(),
                'batch_no'            => $request->batch_no ?: $sale->payment_trx_no,
                'vendor_id'           => $sale->vendor_id,
                'channel'             => $channel,
                'pos_terminal_name'   => $terminal,
                'settlement_date'     => $request->settlement_date,
                'gross_amount'        => $gross,
                'fee_amount'          => $fee,
                'net_amount'          => $net,
                'fee_rate'            => $request->fee_rate ?: ($gross > 0 ? round(($fee / $gross) * 100, 2) : 0),
                'total_transactions'  => 1,
                'notes'               => $request->notes,
                'reconciled_by'       => auth()->id(),
            ]);

            // 2. Post Gateway Charge Expense Entry to General Ledger if fee > 0
            if ($fee > 0) {
                $head = Helper::getOrCreateGatewayChargeHead();
                $invoiceNo = Helper::freeLedgerInvoiceNo(date('ymdhis'));

                $ledger = new Ledger();
                $ledger->income_expense_head_id = $head->id;
                $ledger->vendor_id = $sale->vendor_id;
                $ledger->invoice_no = $invoiceNo;
                $ledger->date = $request->settlement_date;
                $ledger->details = "Payment gateway charge for {$sale->sale_invoice_no} [{$rec->channel_label}] (Gross: ৳" . number_format($gross, 2) . ", Fee: ৳" . number_format($fee, 2) . ", Net: ৳" . number_format($net, 2) . ")";
                $ledger->expense_amount = $fee;
                $ledger->income_amount = 0;
                $ledger->amount_type = Helper::$expense;
                $ledger->type = 'Expense';
                $ledger->ref_invoice_no = $sale->sale_invoice_no;
                $ledger->save();

                $rec->ledger_id = $ledger->id;
                $rec->save();
            }

            // 3. Mark Sale as Reconciled
            $sale->gateway_settlement_status = 'reconciled';
            $sale->gateway_charge_amount = $fee;
            $sale->gateway_net_amount = $net;
            $sale->gateway_settled_at = now();
            $sale->gateway_settlement_id = $rec->id;
            if ($request->filled('pos_terminal_name')) {
                $sale->pos_terminal_name = $request->pos_terminal_name;
            }
            if ($request->filled('batch_no')) {
                $sale->payment_trx_no = $request->batch_no;
            }
            $sale->save();

            DB::commit();

            return redirect()->route('payment-reconciliation.index', ['tab' => 'history'])
                ->with('success', "Transaction {$sale->sale_invoice_no} reconciled successfully (Fee: ৳" . number_format($fee, 2) . ", Net: ৳" . number_format($net, 2) . ").");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('warning', 'Failed to reconcile payment: ' . Helper::userMessage($e))->withInput();
        }
    }

    /**
     * Settle multiple transactions from a daily POS batch summary slip.
     */
    public function settleBatch(Request $request): RedirectResponse
    {
        $request->validate([
            'sale_ids'          => ['required', 'array', 'min:1'],
            'sale_ids.*'        => ['integer', 'exists:sale_masters,id'],
            'settlement_date'   => ['required', 'date'],
            'batch_no'          => ['required', 'string', 'max:100'],
            'pos_terminal_name' => ['nullable', 'string', 'max:100'],
            'channel'           => ['required', 'string'],
            'batch_fee'         => ['required', 'numeric', 'min:0'],
            'notes'             => ['nullable', 'string', 'max:500'],
        ]);

        $sales = SaleMaster::whereIn('id', $request->sale_ids)->get();

        if ($sales->isEmpty()) {
            return redirect()->back()->with('warning', 'No valid transactions selected for settlement.');
        }

        // Check if any sale is already reconciled
        foreach ($sales as $sale) {
            if ($sale->gateway_settlement_status === 'reconciled') {
                return redirect()->back()->with('warning', "Sale #{$sale->sale_invoice_no} is already reconciled.");
            }
        }

        $totalGross = (float) $sales->sum('paid');
        $batchFee = (float) $request->batch_fee;

        if ($batchFee > $totalGross) {
            return redirect()->back()->with('warning', 'Batch fee cannot exceed total gross amount.')->withInput();
        }

        $batchNet = max(0, round($totalGross - $batchFee, 2));
        $firstSale = $sales->first();

        DB::beginTransaction();
        try {
            // 1. Create Batch Reconciliation Record
            $rec = PaymentReconciliation::create([
                'reconciliation_no'   => PaymentReconciliation::nextReconciliationNo(),
                'batch_no'            => $request->batch_no,
                'vendor_id'           => $firstSale->vendor_id,
                'channel'             => $request->channel,
                'pos_terminal_name'   => $request->pos_terminal_name ?: 'Card POS Terminal',
                'settlement_date'     => $request->settlement_date,
                'gross_amount'        => $totalGross,
                'fee_amount'          => $batchFee,
                'net_amount'          => $batchNet,
                'fee_rate'            => $totalGross > 0 ? round(($batchFee / $totalGross) * 100, 2) : 0,
                'total_transactions'  => $sales->count(),
                'notes'               => $request->notes,
                'reconciled_by'       => auth()->id(),
            ]);

            // 2. Post Batch Fee to General Ledger
            if ($batchFee > 0) {
                $head = Helper::getOrCreateGatewayChargeHead();
                $invoiceNo = Helper::freeLedgerInvoiceNo(date('ymdhis'));

                $ledger = new Ledger();
                $ledger->income_expense_head_id = $head->id;
                $ledger->vendor_id = $firstSale->vendor_id;
                $ledger->invoice_no = $invoiceNo;
                $ledger->date = $request->settlement_date;
                $ledger->details = "POS batch #{$request->batch_no} charge ({$sales->count()} sales, Gross: ৳" . number_format($totalGross, 2) . ", Fee: ৳" . number_format($batchFee, 2) . ", Net: ৳" . number_format($batchNet, 2) . ")";
                $ledger->expense_amount = $batchFee;
                $ledger->income_amount = 0;
                $ledger->amount_type = Helper::$expense;
                $ledger->type = 'Expense';
                $ledger->ref_invoice_no = $rec->reconciliation_no;
                $ledger->save();

                $rec->ledger_id = $ledger->id;
                $rec->save();
            }

            // 3. Pro-rate Fee and Reconcile Each Sale
            $remainingFeeToDistribute = $batchFee;
            $count = $sales->count();
            foreach ($sales as $idx => $sale) {
                $isLast = ($idx === $count - 1);
                if ($isLast) {
                    $saleFee = round($remainingFeeToDistribute, 2);
                } else {
                    $share = $totalGross > 0 ? ($sale->paid / $totalGross) : (1 / $count);
                    $saleFee = round($batchFee * $share, 2);
                    $remainingFeeToDistribute -= $saleFee;
                }

                $saleNet = max(0, round((float) $sale->paid - $saleFee, 2));

                $sale->gateway_settlement_status = 'reconciled';
                $sale->gateway_charge_amount = $saleFee;
                $sale->gateway_net_amount = $saleNet;
                $sale->gateway_settled_at = now();
                $sale->gateway_settlement_id = $rec->id;
                if ($request->filled('pos_terminal_name')) {
                    $sale->pos_terminal_name = $request->pos_terminal_name;
                }
                $sale->save();
            }

            DB::commit();

            return redirect()->route('payment-reconciliation.index', ['tab' => 'history'])
                ->with('success', "Batch #{$request->batch_no} ({$sales->count()} sales) reconciled successfully. Total fee: ৳" . number_format($batchFee, 2) . " debited to Bank & Gateway Charges.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('warning', 'Batch reconciliation failed: ' . Helper::userMessage($e))->withInput();
        }
    }

    /**
     * Show reconciliation voucher / slip modal JSON.
     */
    public function showSlip(int $id): JsonResponse
    {
        $rec = PaymentReconciliation::with(['vendor', 'reconciledBy', 'ledger.head', 'sales.customer.lead'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'rec' => [
                'id' => $rec->id,
                'reconciliation_no' => $rec->reconciliation_no,
                'batch_no' => $rec->batch_no ?: '—',
                'channel_label' => $rec->channel_label,
                'pos_terminal_name' => $rec->pos_terminal_name ?: '—',
                'settlement_date' => $rec->settlement_date ? $rec->settlement_date->format('d M Y') : '—',
                'gross_amount' => number_format($rec->gross_amount, 2),
                'fee_amount' => number_format($rec->fee_amount, 2),
                'net_amount' => number_format($rec->net_amount, 2),
                'fee_rate' => $rec->fee_rate ? number_format($rec->fee_rate, 2) . '%' : '—',
                'total_transactions' => $rec->total_transactions,
                'branch_name' => $rec->vendor->name ?? 'Head Office',
                'reconciled_by' => $rec->reconciledBy->name ?? 'System',
                'created_at' => $rec->created_at ? $rec->created_at->format('d M Y, h:i A') : '—',
                'notes' => $rec->notes ?: 'None',
                'ledger_invoice' => $rec->ledger->invoice_no ?? null,
                'sales' => $rec->sales->map(function ($s) {
                    return [
                        'invoice_no' => $s->sale_invoice_no,
                        'date' => $s->date ? $s->date->format('d M Y') : '—',
                        'patient' => $s->customer->lead->full_name ?? ($s->customer->name ?? 'Patient'),
                        'gross' => number_format((float) $s->paid, 2),
                        'fee' => number_format((float) $s->gateway_charge_amount, 2),
                        'net' => number_format((float) $s->gateway_net_amount, 2),
                    ];
                }),
            ]
        ]);
    }

    /**
     * Rollback a reconciliation (Super Admin only).
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $rec = PaymentReconciliation::with('sales')->findOrFail($id);

        DB::beginTransaction();
        try {
            // 1. Reset all linked sales to pending
            foreach ($rec->sales as $sale) {
                $sale->gateway_settlement_status = 'pending';
                $sale->gateway_charge_amount = 0;
                $sale->gateway_net_amount = 0;
                $sale->gateway_settled_at = null;
                $sale->gateway_settlement_id = null;
                $sale->save();
            }

            // 2. Delete or reverse General Ledger entry if created
            if ($rec->ledger_id) {
                $ledgerEntry = Ledger::withoutGlobalScopes()->find($rec->ledger_id);
                if ($ledgerEntry) {
                    $ledgerEntry->forceDelete();
                }
            }

            // 3. Delete reconciliation record
            $recNo = $rec->reconciliation_no;
            $rec->forceDelete();

            DB::commit();

            return redirect()->back()->with('success', "Reconciliation {$recNo} has been rolled back. Linked sales are now pending.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('warning', 'Rollback failed: ' . Helper::userMessage($e));
        }
    }
}
