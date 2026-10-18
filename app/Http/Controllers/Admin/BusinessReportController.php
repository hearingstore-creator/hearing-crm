<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\Doctor;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only business reports:
 *  - Supplier Statement (bank-statement style with opening / running / closing balance)
 *  - Purchase Report (invoice / supplier / day / month wise)
 *  - Vendor Monthly Report (per-branch KPIs for a month + 12-month trend)
 *
 * Uses query-builder reads only; no data is modified.
 * Vendors are always restricted to their own data; admins can see Head Office + every vendor.
 */
class BusinessReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:supplier_statement.index')->only(['supplierStatement']);
        $this->middleware('permission:purchase_report.index')->only(['purchaseReport']);
        $this->middleware('permission:vendor_monthly_report.index')->only(['vendorMonthly']);
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                           */
    /* ------------------------------------------------------------------ */

    public const PERIODS = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'this_week' => 'This Week',
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'this_quarter' => 'This Quarter',
        'this_year' => 'This Year',
        'last_year' => 'Last Year',
        'custom' => 'Custom Range',
    ];

    /** Resolve the period preset / custom dates into [start, end, periodKey]. */
    private function resolvePeriod(Request $request, string $default = 'this_month'): array
    {
        $period = $request->input('period', $default);
        if ($request->filled('start_date') && $request->filled('end_date') && !$request->filled('period')) {
            $period = 'custom';
        }
        $now = now();
        [$start, $end] = match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year' => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'custom' => [
                Carbon::parse($request->input('start_date', $now->copy()->startOfMonth()))->startOfDay(),
                Carbon::parse($request->input('end_date', $now))->endOfDay(),
            ],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }
        if (!array_key_exists($period, self::PERIODS)) {
            $period = $default;
        }

        return [$start, $end, $period];
    }

    private function isVendor(): bool
    {
        return auth()->user()->isVendor();
    }

    /** Restrict a query on `$column` (vendor_id) to what the current user may see. */
    private function scopeVendor(Builder $q, string $column, $vendorFilter = null): Builder
    {
        if ($this->isVendor()) {
            return $q->where($column, auth()->id());
        }
        if ($vendorFilter === 'hq') {
            return $q->whereNull($column);
        }
        if ($vendorFilter !== null && $vendorFilter !== '' && $vendorFilter !== 'all') {
            return $q->where($column, (int) $vendorFilter);
        }

        return $q;
    }

    /** Suppliers visible to the current user (with branch name). */
    private function visibleSuppliers(): Collection
    {
        $q = DB::table('suppliers as s')
            ->leftJoin('users as v', 'v.id', '=', 's.vendor_id')
            ->whereNull('s.deleted_at')
            ->select('s.id', 's.supplier_name', 's.mobile_no', 's.phone', 's.address1', 's.email1', 's.vendor_id', 's.status', 'v.name as vendor_name')
            ->orderBy('s.supplier_name');

        return $this->scopeVendor($q, 's.vendor_id')->get();
    }

    /** Doctors visible to the current user (with branch name). */
    private function visibleDoctors(): Collection
    {
        $q = DB::table('doctors as d')
            ->leftJoin('users as v', 'v.id', '=', 'd.vendor_id')
            ->whereNull('d.deleted_at')
            ->select('d.id', 'd.name as doctor_name', 'd.qualification', 'd.contact_number', 'd.email', 'd.address', 'd.vendor_id', 'd.status', 'v.name as vendor_name')
            ->orderBy('d.name');

        return $this->scopeVendor($q, 'd.vendor_id')->get();
    }

    private function vendorOptions(): Collection
    {
        if ($this->isVendor()) {
            return collect([(object) ['id' => auth()->id(), 'name' => auth()->user()->name]]);
        }

        return DB::table('users')->where('type', 'vendor')->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);
    }

    private function companyInfo(): array
    {
        $s = DB::table('settings')->first();

        return [
            'name' => $s->company_name ?? config('app.name'),
            'address' => $s->address ?? null,
            'phone' => $s->phone ?? $s->mobile ?? null,
            'email' => $s->email ?? null,
            'logo' => !empty($s->logo) ? asset($s->logo) : null,
        ];
    }

    private function csv(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, $header);
            foreach ($rows as $r) {
                fputcsv($out, $r);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* ------------------------------------------------------------------ */
    /*  1. Supplier Statement                                             */
    /* ------------------------------------------------------------------ */

    public function supplierStatement(Request $request)
    {
        [$start, $end, $period] = $this->resolvePeriod($request);
        $suppliers = $this->visibleSuppliers();
        $supplierId = $request->integer('supplier_id') ?: null;
        $supplier = $supplierId ? $suppliers->firstWhere('id', $supplierId) : null;
        $s = $start->toDateString();
        $e = $end->toDateString();

        $format = $request->input('format', 'standard');
        if (!in_array($format, ['standard', 'itemized', 'aging'])) {
            $format = 'standard';
        }

        $data = compact('suppliers', 'supplier', 'period', 'start', 'end', 'format') + [
            'periods' => self::PERIODS,
            'company' => $this->companyInfo(),
        ];

        if ($supplier) {
            $base = fn () => DB::table('supplier_ledgers')->where('supplier_id', $supplier->id)->whereNull('deleted_at');

            $opening = (float) $base()->where('date', '<', $s)->sum(DB::raw('credit - debit'));
            $rows = $base()->whereBetween('date', [$s, $e])->orderBy('date')->orderBy('id')
                ->get(['id', 'date', 'details', 'debit', 'credit', 'type', 'ref_invoice_no', 'supplier_memo_no', 'payment_method', 'transaction_no', 'bank_name']);

            // Link purchase invoices
            $purchases = DB::table('purchase_masters')
                ->whereIn('purchase_invoice_no', $rows->pluck('ref_invoice_no')->filter()->unique())
                ->get(['id', 'purchase_invoice_no', 'supplier_memo_no'])
                ->keyBy('purchase_invoice_no');

            $balance = $opening;
            $rows = $rows->map(function ($r) use (&$balance, $purchases) {
                $balance += (float) $r->credit - (float) $r->debit;
                $r->balance = $balance;
                $p = $purchases->get($r->ref_invoice_no);
                $r->purchase_id = $p->id ?? null;
                $r->supplier_memo_no = $r->supplier_memo_no ?: ($p->supplier_memo_no ?? null);

                // Regex fallback for existing rows where memo was stored in details text
                if (empty($r->supplier_memo_no) && preg_match('/(?:Supplier Memo|Ref Memo|Memo|Challan):\s*([^|\-\]\n]+)/i', (string) $r->details, $m)) {
                    $r->supplier_memo_no = trim($m[1]);
                }

                return $r;
            });

            $totalCredit = (float) $rows->sum('credit');
            $totalDebit = (float) $rows->sum('debit');
            $data += [
                'opening' => $opening,
                'rows' => $rows,
                'totalPurchased' => $totalCredit,
                'totalPaid' => $totalDebit,
                'closing' => $opening + $totalCredit - $totalDebit,
                'invoiceCount' => DB::table('purchase_masters')->where('supplier_id', $supplier->id)
                    ->whereNull('deleted_at')->whereBetween('date', [$s, $e])->count(),
            ];

            // ── FORMAT 2: ITEMIZED (Products & Serial Numbers) ─────────────
            if ($format === 'itemized') {
                $purchaseIds = $purchases->pluck('id')->filter()->unique();
                $purchaseDetails = collect();
                if ($purchaseIds->isNotEmpty()) {
                    $purchaseDetails = DB::table('purchase_details')
                        ->join('products', 'purchase_details.product_id', '=', 'products.id')
                        ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
                        ->whereIn('purchase_details.purchase_master_id', $purchaseIds)
                        ->whereNull('purchase_details.deleted_at')
                        ->select([
                            'purchase_details.id',
                            'purchase_details.purchase_master_id',
                            'purchase_details.product_id',
                            'products.model as model_name',
                            'brands.brand_name',
                            DB::raw("COALESCE(CONCAT(brands.brand_name, ' - ', products.model), products.model, 'Product') as product_name"),
                            'purchase_details.qty',
                            'purchase_details.purchase_price',
                            'purchase_details.mrp_price',
                            'purchase_details.serial_no',
                            'purchase_details.batch_id',
                        ])
                        ->get()
                        ->groupBy('purchase_master_id');
                }

                $returnNos = $rows->where('type', 'Debit Note')->pluck('ref_invoice_no')->filter()->unique();
                $returns = collect();
                $returnDetails = collect();
                if ($returnNos->isNotEmpty()) {
                    $returns = DB::table('purchase_returns')
                        ->whereIn('return_no', $returnNos)
                        ->whereNull('deleted_at')
                        ->get(['id', 'return_no', 'reason', 'supplier_memo_no'])
                        ->keyBy('return_no');

                    $returnIds = $returns->pluck('id');
                    if ($returnIds->isNotEmpty()) {
                        $returnDetails = DB::table('purchase_return_details')
                            ->join('products', 'purchase_return_details.product_id', '=', 'products.id')
                            ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
                            ->whereIn('purchase_return_details.purchase_return_id', $returnIds)
                            ->whereNull('purchase_return_details.deleted_at')
                            ->select([
                                'purchase_return_details.id',
                                'purchase_return_details.purchase_return_id',
                                'purchase_return_details.product_id',
                                'products.model as model_name',
                                'brands.brand_name',
                                DB::raw("COALESCE(CONCAT(brands.brand_name, ' - ', products.model), products.model, 'Product') as product_name"),
                                'purchase_return_details.qty',
                                'purchase_return_details.purchase_price',
                                'purchase_return_details.total_price',
                                'purchase_return_details.serial_no',
                            ])
                            ->get()
                            ->groupBy('purchase_return_id');
                    }
                }

                $rows->each(function ($r) use ($purchaseDetails, $returns, $returnDetails) {
                    if ($r->purchase_id) {
                        $r->items = $purchaseDetails->get($r->purchase_id, collect());
                    } elseif (strtolower((string) $r->type) === 'debit note' || strpos(strtolower((string) $r->details), 'debit note') !== false) {
                        $ret = $returns->get($r->ref_invoice_no);
                        $r->return_id = $ret->id ?? null;
                        $r->items = $ret ? $returnDetails->get($ret->id, collect()) : collect();
                    } else {
                        $r->items = collect();
                    }

                    $r->items->transform(function ($item) {
                        $s = $item->serial_no;
                        if (is_array($s)) {
                            $item->serials = $s;
                        } elseif (!empty($s)) {
                            $decoded = json_decode($s, true);
                            $item->serials = is_array($decoded) ? $decoded : array_map('trim', explode(',', (string) $s));
                        } else {
                            $item->serials = [];
                        }
                        $item->serials = array_values(array_filter($item->serials));
                        return $item;
                    });
                });

                if ($request->input('export') === 'csv') {
                    $lines = [];
                    foreach ($rows as $r) {
                        if ($r->items->isNotEmpty()) {
                            foreach ($r->items as $it) {
                                $lines[] = [
                                    Carbon::parse($r->date)->format('d-m-Y'),
                                    $r->type ?: 'Transaction',
                                    $r->ref_invoice_no,
                                    $r->supplier_memo_no ?: '—',
                                    $it->product_name,
                                    $it->brand_name ?? '—',
                                    $it->qty,
                                    number_format($it->purchase_price, 2, '.', ''),
                                    number_format($it->qty * $it->purchase_price, 2, '.', ''),
                                    implode(', ', $it->serials),
                                ];
                            }
                        } else {
                            $lines[] = [
                                Carbon::parse($r->date)->format('d-m-Y'),
                                $r->type ?: 'Transaction',
                                $r->ref_invoice_no,
                                $r->supplier_memo_no ?: '—',
                                $r->details,
                                '—',
                                '1',
                                number_format($r->debit ?: $r->credit, 2, '.', ''),
                                number_format($r->debit ?: $r->credit, 2, '.', ''),
                                '—',
                            ];
                        }
                    }

                    return $this->csv(
                        'supplier-itemized-statement-' . str($supplier->supplier_name)->slug() . "-{$s}-to-{$e}.csv",
                        ['Date', 'Type', 'Reference #', 'Supplier Memo', 'Product', 'Brand', 'Qty', 'Unit Rate (৳)', 'Total Amount (৳)', 'Serial Numbers'],
                        $lines
                    );
                }
            }

            // ── FORMAT 3: AGING & BILL-BY-BILL ─────────────────────────────
            if ($format === 'aging') {
                $bills = DB::table('purchase_masters')
                    ->where('supplier_id', $supplier->id)
                    ->whereNull('deleted_at')
                    ->where('date', '<=', $e)
                    ->orderBy('date', 'desc')
                    ->get(['id', 'date', 'purchase_invoice_no', 'supplier_memo_no', 'total_amount', 'paid', 'payment_id']);

                $billIds = $bills->pluck('id');

                $returnsPerBill = DB::table('purchase_return_details')
                    ->whereIn('purchase_master_id', $billIds)
                    ->whereNull('deleted_at')
                    ->groupBy('purchase_master_id')
                    ->selectRaw('purchase_master_id, SUM(total_price) as total_returned')
                    ->pluck('total_returned', 'purchase_master_id');

                $agingBills = $bills->map(function ($b) use ($returnsPerBill) {
                    $total = (float) $b->total_amount;
                    $paid = (float) $b->paid;
                    $returned = (float) ($returnsPerBill[$b->id] ?? 0);
                    $netBill = max(0, $total - $returned);
                    $due = max(0, $netBill - $paid);

                    $billDate = Carbon::parse($b->date);
                    $ageDays = (int) $billDate->diffInDays(now());

                    if ($ageDays <= 30) {
                        $bracket = '0-30 Days';
                        $bracketClass = 'success';
                    } elseif ($ageDays <= 60) {
                        $bracket = '31-60 Days';
                        $bracketClass = 'warning';
                    } elseif ($ageDays <= 90) {
                        $bracket = '61-90 Days';
                        $bracketClass = 'orange';
                    } else {
                        $bracket = '90+ Days';
                        $bracketClass = 'danger';
                    }

                    $b->returned = $returned;
                    $b->net_bill = $netBill;
                    $b->due = $due;
                    $b->age_days = $ageDays;
                    $b->bracket = $bracket;
                    $b->bracket_class = $bracketClass;
                    $b->status = ($due <= 0.01) ? 'Paid' : (($paid > 0) ? 'Partial' : 'Unpaid');

                    return $b;
                });

                $unpaidOnly = $request->boolean('unpaid_only', false);
                if ($unpaidOnly) {
                    $agingBills = $agingBills->filter(fn ($b) => $b->due > 0.01)->values();
                }

                $agingSummary = [
                    'totalInvoiced' => $agingBills->sum('total_amount'),
                    'totalPaid' => $agingBills->sum('paid'),
                    'totalReturned' => $agingBills->sum('returned'),
                    'totalDue' => $agingBills->sum('due'),
                    'bracket_0_30' => $agingBills->where('bracket', '0-30 Days')->sum('due'),
                    'bracket_31_60' => $agingBills->where('bracket', '31-60 Days')->sum('due'),
                    'bracket_61_90' => $agingBills->where('bracket', '61-90 Days')->sum('due'),
                    'bracket_90_plus' => $agingBills->where('bracket', '90+ Days')->sum('due'),
                ];

                $data += [
                    'agingBills' => $agingBills,
                    'agingSummary' => $agingSummary,
                    'unpaidOnly' => $unpaidOnly,
                ];

                if ($request->input('export') === 'csv') {
                    return $this->csv(
                        'supplier-aging-report-' . str($supplier->supplier_name)->slug() . "-{$s}-to-{$e}.csv",
                        ['Bill Date', 'Invoice No', 'Supplier Memo', 'Total Bill (৳)', 'Paid (৳)', 'Returns (৳)', 'Net Due (৳)', 'Age (Days)', 'Aging Bracket', 'Status'],
                        $agingBills->map(fn ($b) => [
                            Carbon::parse($b->date)->format('d-m-Y'),
                            $b->purchase_invoice_no,
                            $b->supplier_memo_no ?: '—',
                            round($b->total_amount, 2),
                            round($b->paid, 2),
                            round($b->returned, 2),
                            round($b->due, 2),
                            $b->age_days,
                            $b->bracket,
                            $b->status,
                        ])
                    );
                }
            }

            // ── FORMAT 1: STANDARD LEDGER CSV ──────────────────────────────
            if ($format === 'standard' && $request->input('export') === 'csv') {
                $lines = [['', 'Opening Balance', '', '', '', '', number_format($opening, 2, '.', '')]];
                foreach ($rows as $r) {
                    $lines[] = [
                        Carbon::parse($r->date)->format('d-m-Y'),
                        $r->details,
                        $r->ref_invoice_no,
                        $r->supplier_memo_no ?: '—',
                        (float) $r->debit,
                        (float) $r->credit,
                        round($r->balance, 2)
                    ];
                }
                $lines[] = ['', 'Closing Balance', '', '', $totalDebit, $totalCredit, round($data['closing'], 2)];

                return $this->csv(
                    'supplier-statement-' . str($supplier->supplier_name)->slug() . "-{$s}-to-{$e}.csv",
                    ['Date', 'Particulars', 'Reference', 'Supplier Memo', 'Paid (Debit)', 'Purchased (Credit)', 'Balance Payable'],
                    $lines
                );
            }
        } else {
            // Summary of every supplier for the period
            $ids = $suppliers->pluck('id');
            $agg = DB::table('supplier_ledgers')->whereNull('deleted_at')->whereIn('supplier_id', $ids)
                ->groupBy('supplier_id')
                ->selectRaw('supplier_id,
                    SUM(CASE WHEN date < ? THEN credit - debit ELSE 0 END) as opening,
                    SUM(CASE WHEN date BETWEEN ? AND ? THEN credit ELSE 0 END) as purchased,
                    SUM(CASE WHEN date BETWEEN ? AND ? THEN debit ELSE 0 END) as paid', [$s, $s, $e, $s, $e])
                ->get()->keyBy('supplier_id');

            $summary = $suppliers->map(function ($sup) use ($agg) {
                $a = $agg[$sup->id] ?? null;
                $sup->opening = (float) ($a->opening ?? 0);
                $sup->purchased = (float) ($a->purchased ?? 0);
                $sup->paid = (float) ($a->paid ?? 0);
                $sup->closing = $sup->opening + $sup->purchased - $sup->paid;

                return $sup;
            })->filter(fn ($x) => $request->boolean('show_all') || abs($x->opening) > 0.009 || $x->purchased > 0 || $x->paid > 0 || abs($x->closing) > 0.009)
              ->values();

            $data += [
                'summary' => $summary,
                'sumOpening' => $summary->sum('opening'),
                'sumPurchased' => $summary->sum('purchased'),
                'sumPaid' => $summary->sum('paid'),
                'sumClosing' => $summary->sum('closing'),
            ];

            if ($request->input('export') === 'csv') {
                return $this->csv(
                    "supplier-summary-{$s}-to-{$e}.csv",
                    ['Supplier', 'Branch', 'Opening', 'Purchased', 'Paid', 'Closing Payable'],
                    $summary->map(fn ($x) => [$x->supplier_name, $x->vendor_name ?? 'Head Office', round($x->opening, 2), round($x->purchased, 2), round($x->paid, 2), round($x->closing, 2)])
                );
            }
        }

        return view('admin.report.supplier-statement', $data);
    }

    /* ------------------------------------------------------------------ */
    /*  1B. Doctor & Referral Statement                                   */
    /* ------------------------------------------------------------------ */

    public function doctorStatement(Request $request)
    {
        [$start, $end, $period] = $this->resolvePeriod($request);
        $doctors = $this->visibleDoctors();
        $doctorId = $request->integer('doctor_id') ?: null;
        $doctor = $doctorId ? $doctors->firstWhere('id', $doctorId) : null;
        $s = $start->toDateString();
        $e = $end->toDateString();

        $format = $request->input('format', 'standard');
        if (!in_array($format, ['standard', 'itemized', 'monthly'], true)) {
            $format = 'standard';
        }

        $itemType = $request->input('item_type', 'all');
        if (!in_array($itemType, ['all', 'products', 'services'], true)) {
            $itemType = 'all';
        }

        $data = compact('doctors', 'doctor', 'period', 'start', 'end', 'format', 'itemType') + [
            'periods' => self::PERIODS,
            'company' => $this->companyInfo(),
        ];

        if ($doctor) {
            $base = fn () => DB::table('doctor_ledgers')->where('doctor_id', $doctor->id)->whereNull('deleted_at');

            $opening = (float) $base()->where('date', '<', $s)->sum(DB::raw('credit - debit'));
            $allRows = $base()->whereBetween('date', [$s, $e])->orderBy('date')->orderBy('id')
                ->get(['id', 'date', 'details', 'debit', 'credit', 'type', 'ref_invoice_no', 'payment_method', 'bank_name', 'transaction_no', 'settlement_type', 'settlement_period_start', 'settlement_period_end']);

            $balance = $opening;
            $allRows = $allRows->map(function ($r) use (&$balance) {
                $balance += (float) $r->credit - (float) $r->debit;
                $r->balance = $balance;
                return $r;
            });

            // ── Load Referral Invoices with Products AND Clinical Services ──
            $refNos = $allRows->pluck('ref_invoice_no')->filter()->unique();
            $sales = collect();
            $saleProducts = collect();
            $saleServices = collect();

            if ($refNos->isNotEmpty()) {
                $sales = DB::table('sale_masters')
                    ->leftJoin('customers', 'sale_masters.customer_id', '=', 'customers.id')
                    ->leftJoin('leads', 'customers.lead_id', '=', 'leads.id')
                    ->whereIn('sale_masters.sale_invoice_no', $refNos)
                    ->whereNull('sale_masters.deleted_at')
                    ->select([
                        'sale_masters.id',
                        'sale_masters.date',
                        'sale_masters.sale_invoice_no',
                        'sale_masters.total_amount',
                        'sale_masters.reference_amount as commission_amount',
                        DB::raw("COALESCE(leads.full_name, 'Patient') as patient_name"),
                        'leads.phone_number as patient_phone',
                    ])
                    ->get()
                    ->keyBy('sale_invoice_no');

                $saleIds = $sales->pluck('id')->filter()->unique();

                if ($saleIds->isNotEmpty()) {
                    // 1. Hearing Aids / Products
                    $saleProducts = DB::table('sale_details')
                        ->join('products', 'sale_details.product_id', '=', 'products.id')
                        ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
                        ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                        ->whereIn('sale_details.sale_master_id', $saleIds)
                        ->whereNull('sale_details.deleted_at')
                        ->select([
                            'sale_details.sale_master_id',
                            'sale_details.product_id',
                            'products.category_id',
                            'categories.category_name',
                            'products.model as model_name',
                            'brands.brand_name',
                            DB::raw("COALESCE(CONCAT(brands.brand_name, ' - ', products.model), products.model, 'Product') as product_name"),
                            'sale_details.qty',
                            'sale_details.mrp_price',
                            'sale_details.discount',
                            'sale_details.serial_no',
                            DB::raw('(sale_details.qty * sale_details.mrp_price - COALESCE(sale_details.discount, 0)) as line_total')
                        ])
                        ->get()
                        ->groupBy('sale_master_id');

                    // 2. Clinical Services & Diagnostic Tests
                    $saleServices = DB::table('service_details')
                        ->join('service_types', 'service_details.service_type_id', '=', 'service_types.id')
                        ->whereIn('service_details.sale_master_id', $saleIds)
                        ->whereNull('service_details.deleted_at')
                        ->select([
                            'service_details.sale_master_id',
                            'service_details.service_type_id',
                            'service_types.service_type_name',
                            'service_details.qty',
                            'service_details.service_price',
                            'service_details.discount',
                            DB::raw('(service_details.qty * service_details.service_price - COALESCE(service_details.discount, 0)) as line_total')
                        ])
                        ->get()
                        ->groupBy('sale_master_id');
                }
            }

            // Doctor Commission Rules for accurate product vs service attribution
            $doctorModel = Doctor::with(['categoryCommissions', 'serviceCommissions'])->find($doctor->id);
            $categoryRules = $doctorModel ? $doctorModel->categoryCommissions->keyBy('category_id') : collect();
            $serviceRules = $doctorModel ? $doctorModel->serviceCommissions->keyBy('service_type_id') : collect();
            $defaultProdPercent = (float) ($doctorModel->product_commission_percent ?? 0);
            $defaultProdFixed   = (float) ($doctorModel->product_commission_amount ?? 0);
            $defaultServPercent = (float) ($doctorModel->service_commission_percent ?? 0);
            $defaultServFixed   = (float) ($doctorModel->service_commission_amount ?? 0);

            $allRows->each(function ($r) use ($sales, $saleProducts, $saleServices, $categoryRules, $serviceRules, $defaultProdPercent, $defaultProdFixed, $defaultServPercent, $defaultServFixed) {
                $s = $sales->get($r->ref_invoice_no);
                $r->sale = $s;
                $r->products = $s ? $saleProducts->get($s->id, collect()) : collect();
                $r->services = $s ? $saleServices->get($s->id, collect()) : collect();

                // Decode serial numbers for products
                $r->products->transform(function ($item) {
                    $sn = $item->serial_no;
                    if (is_array($sn)) {
                        $item->serials = $sn;
                    } elseif (!empty($sn)) {
                        $decoded = json_decode($sn, true);
                        $item->serials = is_array($decoded) ? $decoded : array_map('trim', explode(',', (string) $sn));
                    } else {
                        $item->serials = [];
                    }
                    $item->serials = array_values(array_filter($item->serials));
                    return $item;
                });

                $r->has_products = $r->products->isNotEmpty();
                $r->has_services = $r->services->isNotEmpty();

                $totalComm = (float) ($s ? $s->commission_amount : $r->credit);

                if ($r->has_products && !$r->has_services) {
                    $r->product_commission = $totalComm;
                    $r->service_commission = 0.0;
                } elseif (!$r->has_products && $r->has_services) {
                    $r->product_commission = 0.0;
                    $r->service_commission = $totalComm;
                } elseif ($r->has_products && $r->has_services) {
                    // Both exist: compute expected commissions from doctor rules
                    $calcProd = 0.0;
                    foreach ($r->products as $p) {
                        $cRule = $categoryRules->get($p->category_id);
                        if ($cRule) {
                            $calcProd += ($cRule->commission_type === 'percent') ? ($p->line_total * $cRule->commission_value / 100) : ($p->qty * $cRule->commission_value);
                        } elseif ($defaultProdPercent > 0) {
                            $calcProd += ($p->line_total * $defaultProdPercent / 100);
                        } elseif ($defaultProdFixed > 0) {
                            $calcProd += ($p->qty * $defaultProdFixed);
                        }
                    }

                    $calcServ = 0.0;
                    foreach ($r->services as $srv) {
                        $sRule = $serviceRules->get($srv->service_type_id);
                        if ($sRule) {
                            $calcServ += ($sRule->commission_type === 'percent') ? ($srv->line_total * $sRule->commission_value / 100) : ($srv->qty * $sRule->commission_value);
                        } elseif ($defaultServPercent > 0) {
                            $calcServ += ($srv->line_total * $defaultServPercent / 100);
                        } elseif ($defaultServFixed > 0) {
                            $calcServ += ($srv->qty * $defaultServFixed);
                        }
                    }

                    if ($calcProd + $calcServ > 0) {
                        $prodRatio = $calcProd / ($calcProd + $calcServ);
                        $r->product_commission = round($totalComm * $prodRatio, 2);
                        $r->service_commission = round($totalComm - $r->product_commission, 2);
                    } else {
                        $prodTotal = (float) $r->products->sum('line_total');
                        $servTotal = (float) $r->services->sum('line_total');
                        $combined = $prodTotal + $servTotal;
                        $prodRatio = $combined > 0 ? ($prodTotal / $combined) : 0.5;
                        $r->product_commission = round($totalComm * $prodRatio, 2);
                        $r->service_commission = round($totalComm - $r->product_commission, 2);
                    }
                } else {
                    $r->product_commission = 0.0;
                    $r->service_commission = 0.0;
                }
            });

            // Sub-filter for itemized statement:
            $rows = $allRows;
            if ($format === 'itemized') {
                if ($itemType === 'products') {
                    $rows = $allRows->filter(fn ($r) => $r->has_products || $r->debit > 0 || str_contains(strtolower($r->type ?? ''), 'payment'))->values();
                } elseif ($itemType === 'services') {
                    $rows = $allRows->filter(fn ($r) => $r->has_services || $r->debit > 0 || str_contains(strtolower($r->type ?? ''), 'payment'))->values();
                }
            }

            $totalCredit = (float) $allRows->sum('credit');
            $totalDebit = (float) $allRows->sum('debit');
            $totalProductItems = (int) $allRows->sum(fn ($r) => $r->products ? $r->products->sum('qty') : 0);
            $totalServiceItems = (int) $allRows->sum(fn ($r) => $r->services ? $r->services->sum('qty') : 0);
            $totalProductCommission = (float) $allRows->sum('product_commission');
            $totalServiceCommission = (float) $allRows->sum('service_commission');

            $data += [
                'opening' => $opening,
                'rows' => $rows,
                'allRows' => $allRows,
                'totalCommission' => $totalCredit,
                'totalPaid' => $totalDebit,
                'closing' => $opening + $totalCredit - $totalDebit,
                'referredCount' => $allRows->where('credit', '>', 0)->count(),
                'totalProductItems' => $totalProductItems,
                'totalServiceItems' => $totalServiceItems,
                'totalProductCommission' => $totalProductCommission,
                'totalServiceCommission' => $totalServiceCommission,
            ];

            // ── FORMAT 2: ITEMIZED CSV EXPORT ──────────────────────────────
            if ($format === 'itemized' && $request->input('export') === 'csv') {
                $lines = [];
                foreach ($rows as $r) {
                    $pName = $r->sale ? $r->sale->patient_name : '—';
                    $prodDesc = $r->products->isNotEmpty() ? $r->products->map(fn ($p) => "{$p->product_name} ({$p->qty} pc)" . ($p->serials ? ' [SN: ' . implode(',', $p->serials) . ']' : ''))->implode('; ') : '—';
                    $servDesc = $r->services->isNotEmpty() ? $r->services->map(fn ($s) => "{$s->service_type_name} ({$s->qty} test)")->implode('; ') : '—';
                    $lines[] = [
                        Carbon::parse($r->date)->format('d-m-Y'),
                        $r->type ?: 'Transaction',
                        $r->ref_invoice_no,
                        $pName,
                        $prodDesc,
                        $servDesc,
                        number_format($r->product_commission, 2, '.', ''),
                        number_format($r->service_commission, 2, '.', ''),
                        number_format($r->credit, 2, '.', ''),
                        number_format($r->debit, 2, '.', ''),
                        number_format($r->balance, 2, '.', ''),
                    ];
                }
                return $this->csv(
                    'doctor-itemized-statement-' . str($doctor->doctor_name)->slug() . "-{$itemType}-{$s}-to-{$e}.csv",
                    ['Date', 'Type', 'Reference #', 'Patient Name', 'Products (Hearing Aids)', 'Clinical Services', 'Product Comm (৳)', 'Service Comm (৳)', 'Total Comm (৳)', 'Paid Dr (৳)', 'Balance Payable (৳)'],
                    $lines
                );
            }

            // ── FORMAT 3: MONTHLY PERFORMANCE SUMMARY ──────────────────────
            if ($format === 'monthly') {
                $monthlySummary = $allRows->groupBy(fn ($r) => Carbon::parse($r->date)->format('Y-m'))
                    ->map(function ($mRows, $ym) {
                        $prodCount = (int) $mRows->sum(fn ($r) => $r->products ? $r->products->sum('qty') : 0);
                        $servCount = (int) $mRows->sum(fn ($r) => $r->services ? $r->services->sum('qty') : 0);
                        $prodComm = (float) $mRows->sum('product_commission');
                        $servComm = (float) $mRows->sum('service_commission');
                        $totComm = (float) $mRows->sum('credit');
                        $paid = (float) $mRows->sum('debit');

                        return (object) [
                            'ym' => $ym,
                            'month_label' => Carbon::createFromFormat('Y-m', $ym)->format('F Y'),
                            'referred_count' => $mRows->where('credit', '>', 0)->count(),
                            'product_count' => $prodCount,
                            'product_commission' => $prodComm,
                            'service_count' => $servCount,
                            'service_commission' => $servComm,
                            'commission_earned' => $totComm,
                            'paid_amount' => $paid,
                            'net_change' => $totComm - $paid,
                        ];
                    })->values()->sortByDesc('ym');

                $data['monthlySummary'] = $monthlySummary;

                if ($request->input('export') === 'csv') {
                    return $this->csv(
                        'doctor-monthly-summary-' . str($doctor->doctor_name)->slug() . "-{$s}-to-{$e}.csv",
                        ['Month', 'Referred Sales', 'Products (Pcs)', 'Product Comm (৳)', 'Services (Tests)', 'Service Comm (৳)', 'Total Comm (৳)', 'Paid (৳)', 'Net Change (৳)'],
                        $monthlySummary->map(fn ($m) => [
                            $m->month_label,
                            $m->referred_count,
                            $m->product_count,
                            round($m->product_commission, 2),
                            $m->service_count,
                            round($m->service_commission, 2),
                            round($m->commission_earned, 2),
                            round($m->paid_amount, 2),
                            round($m->net_change, 2)
                        ])
                    );
                }
            }

            // ── FORMAT 1: STANDARD LEDGER CSV ──────────────────────────────
            if ($format === 'standard' && $request->input('export') === 'csv') {
                $lines = [['', 'Opening Balance', '', '', '', number_format($opening, 2, '.', '')]];
                foreach ($allRows as $r) {
                    $lines[] = [
                        Carbon::parse($r->date)->format('d-m-Y'),
                        $r->details,
                        $r->ref_invoice_no,
                        (float) $r->debit,
                        (float) $r->credit,
                        round($r->balance, 2)
                    ];
                }
                $lines[] = ['', 'Closing Balance', '', $totalDebit, $totalCredit, round($data['closing'], 2)];

                return $this->csv(
                    'doctor-statement-' . str($doctor->doctor_name)->slug() . "-{$s}-to-{$e}.csv",
                    ['Date', 'Particulars', 'Reference', 'Paid (Debit)', 'Commission (Credit)', 'Balance Payable'],
                    $lines
                );
            }
        } else {
            // ── ALL DOCTORS REFERRAL & COMMISSION SUMMARY ──────────────────
            $allDoctors = $doctors;
            $summary = $allDoctors->map(function ($d) use ($s, $e) {
                $base = fn () => DB::table('doctor_ledgers')->where('doctor_id', $d->id)->whereNull('deleted_at');
                $op = (float) $base()->where('date', '<', $s)->sum(DB::raw('credit - debit'));
                $cr = (float) $base()->whereBetween('date', [$s, $e])->sum('credit');
                $dr = (float) $base()->whereBetween('date', [$s, $e])->sum('debit');
                $cl = $op + $cr - $dr;
                $d->opening = $op;
                $d->commission = $cr;
                $d->paid = $dr;
                $d->closing = $cl;
                $d->sales_value = (float) DB::table('sale_masters')->where('reference_by', $d->id)->whereNull('deleted_at')->whereBetween('date', [$s, $e])->sum('total_amount');
                $d->has_activity = ($op != 0 || $cr != 0 || $dr != 0 || $cl != 0 || $d->sales_value != 0);
                return $d;
            });

            if (!request()->boolean('show_all', false)) {
                $summary = $summary->filter(fn ($d) => $d->has_activity)->values();
            }

            $data += [
                'summary' => $summary,
                'sumOpening' => $summary->sum('opening'),
                'sumSales' => $summary->sum('sales_value'),
                'sumCommission' => $summary->sum('commission'),
                'sumPaid' => $summary->sum('paid'),
                'sumClosing' => $summary->sum('closing'),
            ];

            if ($request->input('export') === 'csv') {
                return $this->csv(
                    "doctor-referral-summary-{$s}-to-{$e}.csv",
                    ['Doctor', 'Branch', 'Referred Sales (৳)', 'Opening Due (৳)', 'Commission Earned (৳)', 'Paid (৳)', 'Closing Payable (৳)'],
                    $summary->map(fn ($x) => [$x->doctor_name, $x->vendor_name ?? 'Head Office', round($x->sales_value, 2), round($x->opening, 2), round($x->commission, 2), round($x->paid, 2), round($x->closing, 2)])
                );
            }
        }

        return view('admin.report.doctor-statement', $data);
    }

    /* ------------------------------------------------------------------ */
    /*  2. Purchase Report                                                */
    /* ------------------------------------------------------------------ */

    public function purchaseReport(Request $request)
    {
        [$start, $end, $period] = $this->resolvePeriod($request);
        $groupBy = in_array($request->input('group_by'), ['invoice', 'supplier', 'day', 'month'], true) ? $request->input('group_by') : 'invoice';
        $vendorFilter = $request->input('vendor_id');
        $supplierId = $request->integer('supplier_id') ?: null;
        $s = $start->toDateString();
        $e = $end->toDateString();

        $qty = DB::table('purchase_details')->whereNull('deleted_at')
            ->groupBy('purchase_master_id')
            ->selectRaw('purchase_master_id, SUM(qty) as qty, COUNT(*) as line_count');

        $base = DB::table('purchase_masters as pm')
            ->leftJoin('suppliers as s', 's.id', '=', 'pm.supplier_id')
            ->leftJoin('users as v', 'v.id', '=', 'pm.vendor_id')
            ->leftJoinSub($qty, 'pd', 'pd.purchase_master_id', '=', 'pm.id')
            ->whereNull('pm.deleted_at')
            ->whereBetween('pm.date', [$s, $e])
            ->when($supplierId, fn ($q) => $q->where('pm.supplier_id', $supplierId));
        $this->scopeVendor($base, 'pm.vendor_id', $vendorFilter);

        $totals = (clone $base)->selectRaw('COUNT(*) as invoices, COALESCE(SUM(pm.total_amount),0) as total,
                COALESCE(SUM(pm.discount),0) as discount, COALESCE(SUM(pm.vat),0) as vat,
                COALESCE(SUM(pm.paid),0) as paid, COALESCE(SUM(pd.qty),0) as qty,
                COUNT(DISTINCT pm.supplier_id) as suppliers')->first();

        $rows = match ($groupBy) {
            'supplier' => (clone $base)->groupBy('pm.supplier_id', 's.supplier_name')
                ->selectRaw('pm.supplier_id, s.supplier_name as label, COUNT(*) as invoices, SUM(pm.total_amount) as total,
                    SUM(pm.discount) as discount, SUM(pm.vat) as vat, SUM(pm.paid) as paid, COALESCE(SUM(pd.qty),0) as qty,
                    MAX(pm.date) as last_date')
                ->orderByDesc('total')->get(),
            'day' => (clone $base)->groupBy('pm.date')
                ->selectRaw('pm.date as label, COUNT(*) as invoices, SUM(pm.total_amount) as total, SUM(pm.discount) as discount,
                    SUM(pm.vat) as vat, SUM(pm.paid) as paid, COALESCE(SUM(pd.qty),0) as qty')
                ->orderBy('pm.date')->get(),
            'month' => (clone $base)->groupByRaw("DATE_FORMAT(pm.date, '%Y-%m')")
                ->selectRaw("DATE_FORMAT(pm.date, '%Y-%m') as label, COUNT(*) as invoices, SUM(pm.total_amount) as total,
                    SUM(pm.discount) as discount, SUM(pm.vat) as vat, SUM(pm.paid) as paid, COALESCE(SUM(pd.qty),0) as qty")
                ->orderBy('label')->get(),
            default => (clone $base)
                ->select('pm.id', 'pm.purchase_invoice_no', 'pm.date', 'pm.total_amount as total', 'pm.discount', 'pm.vat', 'pm.paid',
                    'pm.supplier_id', 's.supplier_name', 'v.name as vendor_name', DB::raw('COALESCE(pd.qty,0) as qty'), DB::raw('COALESCE(pd.line_count,0) as line_count'))
                ->orderBy('pm.date')->orderBy('pm.id')->get(),
        };

        if ($request->input('export') === 'csv') {
            $header = $groupBy === 'invoice'
                ? ['Date', 'Invoice', 'Supplier', 'Branch', 'Qty', 'Discount', 'VAT', 'Total', 'Paid', 'Due']
                : [ucfirst($groupBy), 'Invoices', 'Qty', 'Discount', 'VAT', 'Total', 'Paid', 'Due'];
            $lines = $rows->map(fn ($r) => $groupBy === 'invoice'
                ? [$r->date, $r->purchase_invoice_no, $r->supplier_name, $r->vendor_name ?? 'Head Office', (float) $r->qty, (float) $r->discount, (float) $r->vat, (float) $r->total, (float) $r->paid, round($r->total - $r->paid, 2)]
                : [$r->label ?? '—', (int) $r->invoices, (float) $r->qty, (float) $r->discount, (float) $r->vat, (float) $r->total, (float) $r->paid, round($r->total - $r->paid, 2)]);

            return $this->csv("purchase-report-{$groupBy}-{$s}-to-{$e}.csv", $header, $lines);
        }

        // Purchase Return Losses breakdown (per supplier and per month)
        $returnLossBase = DB::table('purchase_returns as pr')
            ->where('pr.status', 'confirmed')
            ->whereNull('pr.deleted_at')
            ->when($supplierId, fn ($q) => $q->where('pr.supplier_id', $supplierId));
        $this->scopeVendor($returnLossBase, 'pr.vendor_id', $vendorFilter);

        $totalReturnLoss = (float) (clone $returnLossBase)->whereBetween('pr.date', [$s, $e])->sum('pr.loss_amount');

        $returnLossBySupplier = (clone $returnLossBase)
            ->join('suppliers as s', 's.id', '=', 'pr.supplier_id')
            ->whereBetween('pr.date', [$s, $e])
            ->where('pr.loss_amount', '>', 0)
            ->groupBy('pr.supplier_id', 's.supplier_name')
            ->selectRaw('pr.supplier_id, s.supplier_name, COUNT(*) as return_count, SUM(pr.total_amount) as total_return_val, SUM(pr.accepted_amount) as total_accepted_val, SUM(pr.loss_amount) as total_loss')
            ->orderByDesc('total_loss')
            ->get();

        $returnLossByMonth = (clone $returnLossBase)
            ->where('pr.loss_amount', '>', 0)
            ->groupByRaw("DATE_FORMAT(pr.date, '%Y-%m')")
            ->selectRaw("DATE_FORMAT(pr.date, '%Y-%m') as month_label, COUNT(*) as return_count, SUM(pr.total_amount) as total_return_val, SUM(pr.accepted_amount) as total_accepted_val, SUM(pr.loss_amount) as total_loss")
            ->orderByDesc('month_label')
            ->take(12)
            ->get();

        return view('admin.report.purchase-summary', [
            'rows' => $rows,
            'totals' => $totals,
            'groupBy' => $groupBy,
            'suppliers' => $this->visibleSuppliers(),
            'vendors' => $this->vendorOptions(),
            'vendorFilter' => $vendorFilter,
            'supplierId' => $supplierId,
            'periods' => self::PERIODS,
            'period' => $period,
            'start' => $start,
            'end' => $end,
            'company' => $this->companyInfo(),
            'isVendor' => $this->isVendor(),
            'totalReturnLoss' => $totalReturnLoss,
            'returnLossBySupplier' => $returnLossBySupplier,
            'returnLossByMonth' => $returnLossByMonth,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  3. Vendor Monthly Report                                          */
    /* ------------------------------------------------------------------ */

    public function vendorMonthly(Request $request)
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->input('month')) ? $request->input('month') : now()->format('Y-m');
        $mStart = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();
        $mEnd = $mStart->copy()->endOfMonth();
        $vendorFilter = $this->isVendor() ? (string) auth()->id() : $request->input('vendor_id', 'all');

        // Which branches to show
        $branches = collect();
        if (!$this->isVendor() && in_array($vendorFilter, ['all', 'hq', '', null], true)) {
            $branches->push((object) ['key' => 'hq', 'id' => null, 'name' => 'Head Office']);
        }
        foreach ($this->vendorOptions() as $v) {
            if ($this->isVendor() || in_array($vendorFilter, ['all', '', null], true) || (string) $vendorFilter === (string) $v->id) {
                $branches->push((object) ['key' => (string) $v->id, 'id' => $v->id, 'name' => $v->name]);
            }
        }

        $metrics = $this->branchMetrics($mStart, $mEnd, $vendorFilter);
        $rows = $branches->map(function ($b) use ($metrics) {
            $m = [];
            foreach ($metrics as $name => $set) {
                $m[$name] = (float) ($set[$b->key] ?? 0);
            }
            $m['sales_due'] = $m['sales_amount'] - $m['sales_paid'];
            $m['gross_profit'] = $m['sales_amount'] - $m['cogs'];
            $m['net'] = $m['gross_profit'] - $m['expenses'];

            return (object) (['branch' => $b] + $m);
        });

        $sumKeys = ['leads', 'customers', 'sales_count', 'sales_amount', 'sales_paid', 'sales_due', 'discount', 'cogs', 'gross_profit',
            'purchase_count', 'purchase_amount', 'purchase_paid', 'expenses', 'other_income', 'net', 'appointments'];
        $totals = [];
        foreach ($sumKeys as $k) {
            $totals[$k] = $rows->sum($k);
        }

        // Previous month for comparison
        $pStart = $mStart->copy()->subMonthNoOverflow()->startOfMonth();
        $prev = $this->branchMetrics($pStart, $pStart->copy()->endOfMonth(), $vendorFilter);
        $prevTotals = [
            'sales_amount' => array_sum($prev['sales_amount']),
            'purchase_amount' => array_sum($prev['purchase_amount']),
            'leads' => array_sum($prev['leads']),
            'customers' => array_sum($prev['customers']),
        ];

        // 12-month trend for the selected scope (Jan..Dec of selected year)
        $trend = [];
        $yStart = $mStart->copy()->startOfYear();
        for ($i = 0; $i < 12; $i++) {
            $ms = $yStart->copy()->addMonths($i);
            if ($ms->gt(now()->endOfMonth())) {
                break;
            }
            $mm = $this->branchMetrics($ms, $ms->copy()->endOfMonth(), $vendorFilter, true);
            $trend[] = [
                'month' => $ms->format('M Y'),
                'key' => $ms->format('Y-m'),
                'leads' => array_sum($mm['leads']),
                'customers' => array_sum($mm['customers']),
                'sales_count' => array_sum($mm['sales_count']),
                'sales_amount' => array_sum($mm['sales_amount']),
                'sales_paid' => array_sum($mm['sales_paid']),
                'purchase_amount' => array_sum($mm['purchase_amount']),
                'expenses' => array_sum($mm['expenses']),
                'gross_profit' => array_sum($mm['sales_amount']) - array_sum($mm['cogs']),
            ];
        }

        if ($request->input('export') === 'csv') {
            return $this->csv("vendor-monthly-{$month}.csv",
                ['Branch', 'New Leads', 'New Customers', 'Appointments', 'Invoices', 'Sales', 'Collected', 'Due', 'Discount', 'Est. COGS', 'Gross Profit',
                    'Purchases', 'Purchase Paid', 'Expenses', 'Other Income', 'Net (GP - Exp)'],
                $rows->map(fn ($r) => [$r->branch->name, $r->leads, $r->customers, $r->appointments, $r->sales_count, $r->sales_amount, $r->sales_paid, $r->sales_due,
                    $r->discount, $r->cogs, $r->gross_profit, $r->purchase_amount, $r->purchase_paid, $r->expenses, $r->other_income, $r->net])
            );
        }

        return view('admin.report.vendor-monthly', [
            'rows' => $rows,
            'totals' => $totals,
            'prevTotals' => $prevTotals,
            'trend' => $trend,
            'month' => $month,
            'mStart' => $mStart,
            'vendorFilter' => $vendorFilter,
            'vendors' => $this->vendorOptions(),
            'isVendor' => $this->isVendor(),
            'company' => $this->companyInfo(),
        ]);
    }

    /**
     * Per-branch metric sets keyed by branch key ('hq' or vendor id) for a date window.
     *
     * @return array<string, array<string, float>>
     */
    private function branchMetrics(Carbon $from, Carbon $to, $vendorFilter, bool $light = false): array
    {
        $s = $from->toDateString();
        $e = $to->toDateString();
        $sdt = $from->copy()->startOfDay()->toDateTimeString();
        $edt = $to->copy()->endOfDay()->toDateTimeString();
        $key = "COALESCE(CAST(%s AS CHAR), 'hq')";

        $pluck = function (Builder $q, string $col, string $vendorCol) use ($key, $vendorFilter) {
            $this->scopeVendor($q, $vendorCol, $vendorFilter === 'all' ? null : $vendorFilter);
            $k = sprintf($key, $vendorCol);

            return $q->groupByRaw($k)->selectRaw("$k as bkey, $col as val")->pluck('val', 'bkey')->map(fn ($v) => (float) $v)->all();
        };

        $sales = fn (string $col) => $pluck(
            DB::table('sale_masters as sm')->whereNull('sm.deleted_at')->whereBetween('sm.date', [$s, $e]), $col, 'sm.vendor_id');
        $purch = fn (string $col) => $pluck(
            DB::table('purchase_masters as pm')->whereNull('pm.deleted_at')->whereBetween('pm.date', [$s, $e]), $col, 'pm.vendor_id');

        $out = [
            'leads' => $pluck(DB::table('leads as l')->whereNull('l.deleted_at')->whereBetween('l.created_at', [$sdt, $edt]), 'COUNT(*)', 'l.vendor_id'),
            'customers' => $pluck(DB::table('customers as c')->join('leads as l', 'l.id', '=', 'c.lead_id')
                ->whereNull('c.deleted_at')->whereBetween('c.created_at', [$sdt, $edt]), 'COUNT(*)', 'l.vendor_id'),
            'sales_count' => $sales('COUNT(*)'),
            'sales_amount' => $sales('COALESCE(SUM(sm.total_amount),0)'),
            'sales_paid' => $sales('COALESCE(SUM(sm.paid),0)'),
            'purchase_amount' => $purch('COALESCE(SUM(pm.total_amount),0)'),
            'cogs' => $pluck(DB::table('sale_details as sd')->join('sale_masters as sm', 'sm.id', '=', 'sd.sale_master_id')
                ->whereNull('sd.deleted_at')->whereNull('sm.deleted_at')->whereBetween('sm.date', [$s, $e]),
                'COALESCE(SUM(sd.qty * sd.purchase_price),0)', 'sm.vendor_id'),
            'expenses' => $pluck(DB::table('ledgers as lg')->whereNull('lg.deleted_at')->whereBetween('lg.date', [$s, $e])
                ->where('lg.amount_type', 'Expense')->where(fn ($q) => $q->whereNull('lg.type')->orWhereNotIn('lg.type', ['Purchase'])),
                'COALESCE(SUM(lg.expense_amount),0)', 'lg.vendor_id'),
        ];

        if ($light) {
            return $out;
        }

        return $out + [
            'discount' => $sales('COALESCE(SUM(sm.total_discount),0)'),
            'purchase_count' => $purch('COUNT(*)'),
            'purchase_paid' => $purch('COALESCE(SUM(pm.paid),0)'),
            'other_income' => $pluck(DB::table('ledgers as lg')->whereNull('lg.deleted_at')->whereBetween('lg.date', [$s, $e])
                ->where('lg.amount_type', 'Income')->where(fn ($q) => $q->whereNull('lg.type')->orWhereNotIn('lg.type', ['Sales'])),
                'COALESCE(SUM(lg.income_amount),0)', 'lg.vendor_id'),
            'appointments' => $pluck(DB::table('appointments as a')->leftJoin('leads as l', 'l.id', '=', 'a.lead_id')
                ->whereNull('a.deleted_at')->whereRaw('DATE(a.appointment_date) BETWEEN ? AND ?', [$s, $e]), 'COUNT(*)', 'l.vendor_id'),
        ];
    }
}
