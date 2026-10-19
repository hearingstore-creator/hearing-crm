<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Profit statement per branch for a period, built from the records themselves rather than from the
 * payment ledger, so nothing is counted twice:
 *
 *  - Income and product cost come from sale lines (by invoice date), less sale returns.
 *  - Repairs come from jobs delivered in the period (customer charge, supplier cost).
 *  - Referral commission is what the sale owes (reference_amount), less commission taken back on returns.
 *  - Operating expenses and other income come from manual company-ledger entries by their head.
 *  - Supplier payments, doctor payments, refunds, sale receipts and advances are cash movements of things
 *    counted above, so they are left out and listed in the reconciliation.
 *
 * Branch keys: 'hq' = head office (vendor_id NULL), otherwise the branch's user id.
 */
class ProfitStatement
{
    /** Ledger types (after removing "(Reversed)" / "Reversal") that belong in the statement. */
    private const EXPENSE_BASES = ['', 'Expense', 'SalaryPayment', 'Purchase Return Loss'];
    private const INCOME_BASES = ['', 'Income', 'Cancellation Fee', 'RMA Credit'];

    /** @var array<string, string> branch key => name */
    private array $columns = [];

    public function __construct(private Carbon $from, private Carbon $to, private string $branchFilter = 'all')
    {
        $this->columns = $this->resolveColumns();
    }

    /** The branch scope as a budget key: all | hq | a branch id. */
    public function scopeKey(): string
    {
        if ($forced = \App\Classes\Helper::reportBranchId()) {
            return (string) $forced;
        }
        $keys = array_keys($this->columns);

        return $this->branchFilter === 'all' || count($keys) !== 1 ? 'all' : (string) $keys[0];
    }

    /** @return array<string, string> */
    public function columns(): array
    {
        return $this->columns;
    }

    public function build(): array
    {
        $rows = [];
        $add = function (string $kind, string $label, array $vals = [], int $indent = 0, ?array $drill = null) use (&$rows) {
            $vals = array_replace(array_fill_keys(array_keys($this->columns), 0.0), $vals);
            $rows[] = ['kind' => $kind, 'label' => $label, 'vals' => $vals, 'total' => array_sum($vals), 'indent' => $indent, 'drill' => $drill];

            return $vals;
        };
        $sum = fn (array $sets) => collect($sets)->reduce(fn ($c, $s) => $this->plus($c, $s), []);

        // ---- Income
        $add('head', 'Income');
        $productIncome = $this->productLines('revenue');
        $serviceIncome = $this->serviceIncome();
        $repairIncome = $this->repairs('rj.total_customer_cost');
        $returns = $this->returnsValue();
        $adjustment = $this->invoiceAdjustment($productIncome, $serviceIncome);

        foreach ($productIncome as $label => $vals) {
            $add('line', $label, $vals, 1, ['kind' => 'product', 'label' => $label]);
        }
        foreach ($serviceIncome as $label => $vals) {
            $add('line', $label . ' (service)', $vals, 1, ['kind' => 'service', 'label' => $label]);
        }
        if (array_sum($repairIncome)) {
            $add('line', 'Repair jobs delivered', $repairIncome, 1);
        }
        if (array_sum($returns)) {
            $add('line', 'Less: sales returned', $this->neg($returns), 1);
        }
        if (abs(array_sum($adjustment)) > 0.004) {
            $add('line', 'Invoice-level discount / adjustment', $adjustment, 1);
        }
        $netSales = $add('total', 'Net sales', $sum(array_merge(array_values($productIncome), array_values($serviceIncome), [$repairIncome, $this->neg($returns), $adjustment])));

        // ---- Cost of goods
        $add('head', 'Cost of goods');
        $productCost = $this->productLines('cost');
        $returnedCost = $this->returnedCost();
        $repairCost = $this->repairs('rj.total_supplier_cost');
        foreach ($productCost as $label => $vals) {
            $add('line', $label, $vals, 1, ['kind' => 'product', 'label' => $label]);
        }
        if (array_sum($returnedCost)) {
            $add('line', 'Less: cost of goods returned', $this->neg($returnedCost), 1);
        }
        if (array_sum($repairCost)) {
            $add('line', 'Repair supplier / lab cost', $repairCost, 1);
        }
        $totalCost = $add('total', 'Total cost of goods', $sum(array_merge(array_values($productCost), [$this->neg($returnedCost), $repairCost])));

        $gross = $add('grand', 'Gross profit', $this->minus($netSales, $totalCost));

        // ---- Referral commission
        $add('head', 'Referral (doctors)');
        $referral = $this->referral();
        $referralBack = $this->referralTakenBack();
        $add('line', 'Commission owed on sales', $referral, 1);
        if (array_sum($referralBack)) {
            $add('line', 'Less: taken back on returns', $this->neg($referralBack), 1);
        }
        $netReferral = $add('total', 'Referral commission', $this->minus($referral, $referralBack));

        // ---- Operating expenses
        $add('head', 'Operating expenses');
        $expenses = $this->ledgerByLabel('Expense');
        foreach ($expenses as $label => $vals) {
            $add('line', $label, $vals, 1, ['kind' => 'expense', 'label' => $label]);
        }
        $totalExpenses = $add('total', 'Total operating expenses', $sum(array_values($expenses)));

        // ---- Other income
        $add('head', 'Other income');
        $other = $this->ledgerByLabel('Income');
        foreach ($other as $label => $vals) {
            $add('line', $label, $vals, 1, ['kind' => 'income', 'label' => $label]);
        }
        $totalOther = $add('total', 'Total other income', $sum(array_values($other)));

        $net = $add('grand', 'Net profit', $this->plus($this->minus($this->minus($gross, $netReferral), $totalExpenses), $totalOther));

        return [
            'columns' => $this->columns,
            'rows'    => $rows,
            'summary' => [
                'net_sales'          => array_sum($netSales),
                'cost'               => array_sum($totalCost),
                'gross_profit'       => array_sum($gross),
                'referral'           => array_sum($netReferral),
                'operating_expenses' => array_sum($totalExpenses),
                'other_income'       => array_sum($totalOther),
                'net_profit'         => array_sum($net),
                'margin'       => array_sum($netSales) > 0 ? round(array_sum($net) / array_sum($netSales) * 100, 1) : null,
            ],
            'checks' => $this->checks(),
            'memo'   => $this->memo(),
        ];
    }

    // ------------------------------------------------------------------ income and cost

    /** Line discounts are stored as percentages. @return array<string, array<string, float>> category name => branch => amount */
    private function productLines(string $what): array
    {
        $expr = $what === 'cost' ? 'SUM(sd.qty * sd.purchase_price)' : 'SUM(sd.qty * sd.mrp_price * (1 - COALESCE(sd.discount,0) / 100))';
        $q = DB::table('sale_details as sd')
            ->join('sale_masters as sm', 'sm.id', '=', 'sd.sale_master_id')
            ->leftJoin('products as p', 'p.id', '=', 'sd.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->whereNull('sd.deleted_at')->whereNull('sm.deleted_at')
            ->whereBetween('sm.date', $this->dates());

        return $this->grouped($q, 'sm.vendor_id', "COALESCE(c.category_name, 'Uncategorised products')", $expr);
    }

    private function serviceIncome(): array
    {
        $q = DB::table('service_details as sv')
            ->join('sale_masters as sm', 'sm.id', '=', 'sv.sale_master_id')
            ->leftJoin('service_types as st', 'st.id', '=', 'sv.service_type_id')
            ->whereNull('sv.deleted_at')->whereNull('sm.deleted_at')
            ->whereBetween('sm.date', $this->dates());

        return $this->grouped($q, 'sm.vendor_id', "COALESCE(st.service_type_name, 'Other service')", 'SUM(sv.qty * sv.service_price * (1 - COALESCE(sv.discount,0) / 100))');
    }

    /** What the invoice totals say beyond the lines (an invoice-wide discount, rounding); VAT is not income. */
    private function invoiceAdjustment(array $products, array $services): array
    {
        $q = DB::table('sale_masters as sm')->whereNull('sm.deleted_at')->whereBetween('sm.date', $this->dates());
        $totals = $this->byBranch($q, 'sm.vendor_id', 'SUM(sm.total_amount - COALESCE(sm.vat,0))');
        $lines = $this->plus(
            collect($products)->reduce(fn ($c, $s) => $this->plus($c, $s), []),
            collect($services)->reduce(fn ($c, $s) => $this->plus($c, $s), [])
        );

        return $this->minus($totals, $lines);
    }

    private function returnsValue(): array
    {
        return $this->byBranch($this->returnsQuery(), 'sr.vendor_id', 'SUM(rd.net_return_amount)');
    }

    private function returnedCost(): array
    {
        $q = $this->returnsQuery()->join('sale_details as sd', 'sd.id', '=', 'rd.sale_detail_id');

        return $this->byBranch($q, 'sr.vendor_id', 'SUM(rd.return_qty * sd.purchase_price)');
    }

    private function returnsQuery(): Builder
    {
        return DB::table('sale_return_details as rd')
            ->join('sale_returns as sr', 'sr.id', '=', 'rd.sale_return_id')
            ->whereNull('rd.deleted_at')->whereNull('sr.deleted_at')
            ->where('sr.status', 'completed')
            ->whereBetween('sr.date', $this->dates());
    }

    private function repairs(string $column): array
    {
        $q = DB::table('repair_jobs as rj')->whereNull('rj.deleted_at')
            ->where('rj.current_status', 'delivered')
            ->whereBetween(DB::raw('DATE(rj.delivered_at)'), $this->dates());

        return $this->byBranch($q, 'rj.vendor_id', "SUM(COALESCE($column,0))");
    }

    // ------------------------------------------------------------------ referral

    private function referral(): array
    {
        $q = DB::table('sale_masters as sm')->whereNull('sm.deleted_at')->whereBetween('sm.date', $this->dates());

        return $this->byBranch($q, 'sm.vendor_id', 'SUM(COALESCE(sm.reference_amount,0))');
    }

    private function referralTakenBack(): array
    {
        $q = DB::table('sale_returns as sr')->whereNull('sr.deleted_at')->where('sr.status', 'completed')->whereBetween('sr.date', $this->dates());

        return $this->byBranch($q, 'sr.vendor_id', 'SUM(COALESCE(sr.doctor_commission_adjusted,0))');
    }

    // ------------------------------------------------------------------ company ledger

    /** Manual expenses / other income, grouped by their head (reversals net out because both rows are kept). */
    private function ledgerByLabel(string $amountType): array
    {
        $col = $amountType === 'Expense' ? 'lg.expense_amount' : 'lg.income_amount';

        return $this->grouped($this->ledgerQuery($amountType), 'lg.vendor_id', $this->ledgerLabelSql($amountType), "SUM($col)");
    }

    private function ledgerQuery(string $amountType): Builder
    {
        $bases = $amountType === 'Expense' ? self::EXPENSE_BASES : self::INCOME_BASES;

        return DB::table('ledgers as lg')->leftJoin('income_expense_heads as h', 'h.id', '=', 'lg.income_expense_head_id')
            ->whereNull('lg.deleted_at')->where('lg.amount_type', $amountType)
            ->whereBetween('lg.date', $this->dates())
            ->whereRaw("{$this->baseType()} IN (" . implode(',', array_map(fn ($b) => DB::getPdo()->quote($b), $bases)) . ')');
    }

    private function ledgerLabelSql(string $amountType): string
    {
        return "CASE WHEN {$this->baseType()} IN ('', '$amountType') THEN COALESCE(h.head_name, 'No category')
                       WHEN {$this->baseType()} = 'SalaryPayment' THEN 'Salaries'
                       WHEN {$this->baseType()} = 'Purchase Return Loss' THEN 'Purchase return loss'
                       WHEN {$this->baseType()} = 'RMA Credit' THEN 'Supplier RMA credits'
                       WHEN {$this->baseType()} = 'Cancellation Fee' THEN 'Cancellation fees'
                       ELSE {$this->baseType()} END";
    }

    private function baseType(): string
    {
        return self::typeBase('lg.type');
    }

    /** A ledger type with the "(Reversed)" / "Reversal" marks removed, so a reversal and its original are the same kind. */
    public static function typeBase(string $column): string
    {
        return "REPLACE(REPLACE(COALESCE($column,''),' (Reversed)',''),' Reversal','')";
    }

    /**
     * Keep only ledger rows that are operating expenses (manual expenses, salaries, purchase-return loss).
     * Dashboard widgets use this so they agree with the statement and do not count supplier or doctor payments again.
     */
    public static function onlyOperatingExpenses(Builder $query, string $typeColumn = 'lg.type'): Builder
    {
        $bases = implode(',', array_map(fn ($b) => DB::getPdo()->quote($b), self::EXPENSE_BASES));

        return $query->whereRaw(self::typeBase($typeColumn) . " IN ($bases)");
    }

    // ------------------------------------------------------------------ drill-down and trend

    public const DETAIL_LIMIT = 500;

    /**
     * The records behind one line of the statement, for the same period and branches.
     *
     * @return array{columns: string[], rows: array<int, array<string, mixed>>, total: float, truncated: bool}|null
     */
    public function detail(string $kind, string $label): ?array
    {
        $limit = self::DETAIL_LIMIT + 1;

        if ($kind === 'product') {
            $q = DB::table('sale_details as sd')
                ->join('sale_masters as sm', 'sm.id', '=', 'sd.sale_master_id')
                ->leftJoin('products as p', 'p.id', '=', 'sd.product_id')
                ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
                ->whereNull('sd.deleted_at')->whereNull('sm.deleted_at')->whereBetween('sm.date', $this->dates())
                ->whereRaw("COALESCE(c.category_name, 'Uncategorised products') = ?", [$label]);
            $this->scope($q, 'sm.vendor_id');
            $rows = $q->orderBy('sm.date')->orderBy('sm.id')->limit($limit)
                ->selectRaw('sm.id as sale_id, sm.date, sm.sale_invoice_no as ref, p.model as what, sd.qty, sd.qty * sd.mrp_price * (1 - COALESCE(sd.discount,0) / 100) as amount, sd.qty * sd.purchase_price as cost')->get();

            return $this->detailResult(['Date', 'Invoice', 'Product', 'Qty', 'Income', 'Cost'], $rows, fn ($r) => [
                'date' => $r->date, 'ref' => $r->ref, 'link' => 'admin/sale/' . $r->sale_id, 'what' => $r->what, 'qty' => (float) $r->qty, 'amount' => round((float) $r->amount, 2), 'cost' => round((float) $r->cost, 2),
            ]);
        }

        if ($kind === 'service') {
            $q = DB::table('service_details as sv')
                ->join('sale_masters as sm', 'sm.id', '=', 'sv.sale_master_id')
                ->leftJoin('service_types as st', 'st.id', '=', 'sv.service_type_id')
                ->whereNull('sv.deleted_at')->whereNull('sm.deleted_at')->whereBetween('sm.date', $this->dates())
                ->whereRaw("COALESCE(st.service_type_name, 'Other service') = ?", [$label]);
            $this->scope($q, 'sm.vendor_id');
            $rows = $q->orderBy('sm.date')->orderBy('sm.id')->limit($limit)
                ->selectRaw('sm.id as sale_id, sm.date, sm.sale_invoice_no as ref, st.service_type_name as what, sv.qty, sv.qty * sv.service_price * (1 - COALESCE(sv.discount,0) / 100) as amount')->get();

            return $this->detailResult(['Date', 'Invoice', 'Service', 'Qty', 'Income'], $rows, fn ($r) => [
                'date' => $r->date, 'ref' => $r->ref, 'link' => 'admin/sale/' . $r->sale_id, 'what' => $r->what ?? 'Other service', 'qty' => (float) $r->qty, 'amount' => round((float) $r->amount, 2),
            ]);
        }

        if (in_array($kind, ['expense', 'income'], true)) {
            $type = $kind === 'expense' ? 'Expense' : 'Income';
            $q = $this->ledgerQuery($type)->whereRaw('(' . $this->ledgerLabelSql($type) . ') = ?', [$label]);
            $this->scope($q, 'lg.vendor_id');
            $col = $kind === 'expense' ? 'lg.expense_amount' : 'lg.income_amount';
            $rows = $q->orderBy('lg.date')->orderBy('lg.id')->limit($limit)
                ->selectRaw("lg.date, lg.invoice_no as ref, lg.details as what, $col as amount, lg.vendor_id")->get();

            return $this->detailResult(['Date', 'Entry', 'Details', 'Amount'], $rows, fn ($r) => [
                'date' => $r->date, 'ref' => $r->ref, 'link' => null, 'what' => $r->what, 'amount' => round((float) $r->amount, 2),
            ]);
        }

        return null;
    }

    private function detailResult(array $columns, $rows, callable $map): array
    {
        $truncated = $rows->count() > self::DETAIL_LIMIT;
        $mapped = $rows->take(self::DETAIL_LIMIT)->map($map)->values()->all();

        return [
            'columns'   => $columns,
            'rows'      => $mapped,
            'total'     => round(array_sum(array_column($mapped, 'amount')), 2),
            'truncated' => $truncated,
        ];
    }

    /**
     * Month-by-month summary ending in the month of $end (oldest first).
     *
     * @return array<int, array{month: string, key: string, summary: array}>
     */
    public static function trend(Carbon $end, string $branchFilter, int $months = 12): array
    {
        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = $end->copy()->startOfMonth()->subMonthsNoOverflow($i);
            $summary = (new self($start, $start->copy()->endOfMonth(), $branchFilter))->build()['summary'];
            $out[] = ['month' => $start->format('M Y'), 'key' => $start->format('Y-m'), 'summary' => $summary];
        }

        return $out;
    }

    // ------------------------------------------------------------------ audit trail

    /** Ledger money that is deliberately not in the statement, by what it is. */
    private function checks(): array
    {
        $excluded = DB::table('ledgers as lg')->whereNull('lg.deleted_at')->whereBetween('lg.date', $this->dates())
            ->tap(fn ($q) => $this->scope($q, 'lg.vendor_id'))
            ->selectRaw("{$this->baseType()} as base, lg.amount_type, SUM(lg.income_amount) as inc, SUM(lg.expense_amount) as exp")
            ->groupByRaw("{$this->baseType()}, lg.amount_type")->get()
            ->filter(function ($r) {
                $in = $r->amount_type === 'Income' ? self::INCOME_BASES : self::EXPENSE_BASES;

                return ! in_array($r->base, $in, true);
            })
            ->map(fn ($r) => [
                'what'   => $r->base === '' ? 'Untyped' : $r->base,
                'side'   => $r->amount_type,
                'amount' => (float) ($r->amount_type === 'Income' ? $r->inc : $r->exp),
            ])->filter(fn ($r) => abs($r['amount']) > 0.004)->values()->all();

        $noCost = $this->scope(
            DB::table('sale_details as sd')->join('sale_masters as sm', 'sm.id', '=', 'sd.sale_master_id')
                ->whereNull('sd.deleted_at')->whereNull('sm.deleted_at')->whereBetween('sm.date', $this->dates())
                ->where(fn ($q) => $q->whereNull('sd.purchase_price')->orWhere('sd.purchase_price', '<=', 0)),
            'sm.vendor_id'
        )->count();

        return ['excluded' => $excluded, 'lines_without_cost' => $noCost];
    }

    /** Facts shown under the statement that are not part of it. */
    private function memo(): array
    {
        $paid = $this->byBranch(
            DB::table('ledgers as lg')->whereNull('lg.deleted_at')->where('lg.amount_type', 'Expense')->whereBetween('lg.date', $this->dates())
                ->whereRaw("{$this->baseType()} = 'Doctor Payment'"),
            'lg.vendor_id', 'SUM(lg.expense_amount)'
        );

        return ['commission_paid_in_period' => array_sum($paid)];
    }

    // ------------------------------------------------------------------ plumbing

    private function dates(): array
    {
        return [$this->from->toDateString(), $this->to->toDateString()];
    }

    private function resolveColumns(): array
    {
        $branches = User::where('type', 'vendor')->orderBy('name')->pluck('name', 'id');
        $forced = \App\Classes\Helper::reportBranchId();

        if ($forced) {
            return [(string) $forced => $branches[$forced] ?? 'Branch'];
        }
        if ($this->branchFilter === 'hq') {
            return ['hq' => 'Head Office'];
        }
        if ($this->branchFilter !== 'all' && $branches->has((int) $this->branchFilter)) {
            return [(string) (int) $this->branchFilter => $branches[(int) $this->branchFilter]];
        }

        $cols = ['hq' => 'Head Office'];
        foreach ($branches as $id => $name) {
            $cols[(string) $id] = $name;
        }

        return $cols;
    }

    /** Keep only the branches shown. */
    private function scope(Builder $q, string $col): Builder
    {
        $keys = array_keys($this->columns);
        $ids = array_values(array_filter($keys, fn ($k) => $k !== 'hq'));

        return $q->where(function ($w) use ($col, $keys, $ids) {
            if (in_array('hq', $keys, true)) {
                $w->orWhereNull($col);
            }
            if ($ids) {
                $w->orWhereIn($col, $ids);
            }
        });
    }

    private function byBranch(Builder $q, string $col, string $valueExpr): array
    {
        $this->scope($q, $col);
        $key = "COALESCE(CAST($col AS CHAR), 'hq')";

        return $q->groupByRaw($key)->selectRaw("$key as bkey, $valueExpr as val")->pluck('val', 'bkey')
            ->map(fn ($v) => round((float) $v, 2))->all();
    }

    /** @return array<string, array<string, float>> label => branch => value */
    private function grouped(Builder $q, string $col, string $labelExpr, string $valueExpr): array
    {
        $this->scope($q, $col);
        $key = "COALESCE(CAST($col AS CHAR), 'hq')";
        $out = [];
        foreach ($q->groupByRaw("$key, $labelExpr")->selectRaw("$key as bkey, $labelExpr as lbl, $valueExpr as val")->orderBy('lbl')->get() as $r) {
            $out[$r->lbl][$r->bkey] = round((float) $r->val, 2);
        }

        return array_filter($out, fn ($vals) => abs(array_sum($vals)) > 0.004);
    }

    private function plus(array $a, array $b): array
    {
        foreach ($b as $k => $v) {
            $a[$k] = round(($a[$k] ?? 0) + $v, 2);
        }

        return $a;
    }

    private function minus(array $a, array $b): array
    {
        return $this->plus($a, $this->neg($b));
    }

    private function neg(array $a): array
    {
        return array_map(fn ($v) => -1 * $v, $a);
    }
}
