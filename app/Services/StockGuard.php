<?php

namespace App\Services;

use App\Exceptions\StockGuardException;
use Illuminate\Support\Facades\DB;

/**
 * Server-side stock and serial checks for sales and purchases.
 *
 * Stock is kept per branch (vendor_id, NULL = head office), so sale checks use the
 * sale's own branch. Serial numbers are physical units, so purchase duplicate checks
 * look across all branches. Serials are compared trimmed and case-insensitive.
 *
 * Call these inside the save transaction: they lock the product / batch rows so two
 * counters cannot sell the same unit at the same time.
 */
class StockGuard
{
    /** @var array<int, string> */
    protected array $labels = [];

    // ───────────────────────────── Sales ─────────────────────────────

    /**
     * Check the final product lines of a sale.
     *
     * @param array $lines each: product_id, qty, batch_id (or null), serials (array/JSON/null)
     * @param int|null $vendorId branch of the sale (NULL = head office)
     * @param int|null $saleMasterId existing sale being edited; its own units count as available
     * @throws StockGuardException
     */
    public function assertSaleLines(array $lines, ?int $vendorId, ?int $saleMasterId = null): void
    {
        $problems = [];
        $this->lockProducts(array_column($lines, 'product_id'));

        // What this sale already holds (edit): those serials / batch quantities stay available to it
        $heldSerials = [];
        $heldBatch = [];
        if ($saleMasterId) {
            $current = DB::table('sale_details')
                ->where('sale_master_id', $saleMasterId)
                ->whereNull('deleted_at')
                ->get(['product_id', 'batch_id', 'serial_no', 'qty']);
            foreach ($current as $d) {
                if ($d->batch_id) {
                    $heldBatch[$d->batch_id] = ($heldBatch[$d->batch_id] ?? 0) + (float) $d->qty;
                }
                foreach (self::serialList($d->serial_no) as $s) {
                    $heldSerials[$d->product_id][self::key($s)] = true;
                }
            }
        }

        $seen = [];
        $batchNeed = [];
        foreach ($lines as $line) {
            $productId = (int) $line['product_id'];
            $label = $this->productLabel($productId);
            $qty = $line['qty'] ?? null;

            if (!is_numeric($qty) || (float) $qty <= 0) {
                $problems[] = "{$label}: quantity must be more than 0.";
                continue;
            }
            $qty = (float) $qty;
            $serials = self::serialList($line['serials'] ?? null);

            if ($serials) {
                if (abs(count($serials) - $qty) > 0.001) {
                    $problems[] = "{$label}: quantity is " . self::fmt($qty) . ' but ' . count($serials) . ' serial number(s) are selected.';
                }

                $newSerials = [];
                foreach ($serials as $serial) {
                    $key = self::key($serial);
                    if (isset($seen[$key])) {
                        $problems[] = "Serial {$serial} is selected more than once in this sale.";
                        continue;
                    }
                    $seen[$key] = true;
                    if (empty($heldSerials[$productId][$key])) {
                        $newSerials[$key] = $serial;
                    }
                }

                if ($newSerials) {
                    $stock = $this->serialBalances($productId, $vendorId, $saleMasterId);
                    foreach ($newSerials as $key => $serial) {
                        if (!isset($stock['purchased'][$key])) {
                            $problems[] = "Serial {$serial} was never purchased for {$label} in this branch.";
                        } elseif (($stock['balance'][$key] ?? 0) < 1) {
                            $problems[] = "Serial {$serial} ({$label}) is already sold or returned to the supplier.";
                        }
                    }
                }
            } elseif (!empty($line['batch_id'])) {
                $batchId = (int) $line['batch_id'];
                $batchNeed[$batchId]['product_id'] = $productId;
                $batchNeed[$batchId]['qty'] = ($batchNeed[$batchId]['qty'] ?? 0) + $qty;
            } else {
                $problems[] = "{$label}: select a batch or serial numbers.";
            }
        }

        foreach ($batchNeed as $batchId => $need) {
            $label = $this->productLabel($need['product_id']);
            $batch = $this->vendorWhere(
                DB::table('batches')->where('id', $batchId)->where('product_id', $need['product_id'])->whereNull('deleted_at'),
                'vendor_id',
                $vendorId
            )->lockForUpdate()->first();

            if (!$batch) {
                $problems[] = "{$label}: the selected batch does not belong to this product or branch.";
                continue;
            }

            $held = $heldBatch[$batchId] ?? 0;
            if ($need['qty'] <= $held + 0.0001) {
                continue; // edit that does not take more from this batch than before
            }

            $available = (float) $batch->purchase_qty - (float) $batch->sale_qty
                - (float) $batch->purchase_return_qty + (float) $batch->sale_return_qty + $held;
            if ($need['qty'] > $available + 0.0001) {
                $problems[] = "{$label} batch {$batch->batch_no}: only " . self::fmt(max(0, $available))
                    . ' in stock, ' . self::fmt($need['qty']) . ' requested.';
            }
        }

        if ($problems) {
            throw new StockGuardException(array_unique($problems));
        }
    }

    /**
     * Per-serial counts for one product in one branch.
     * balance = purchased - returned to supplier - sold + returned by patients.
     *
     * @return array{purchased: array<string,int>, balance: array<string,int>}
     */
    public function serialBalances(int $productId, ?int $vendorId, ?int $excludeSaleMasterId = null): array
    {
        $purchased = $this->countSerials($this->vendorWhere(
            DB::table('purchase_details as d')
                ->join('purchase_masters as m', 'm.id', '=', 'd.purchase_master_id')
                ->where('d.product_id', $productId)
                ->whereNotNull('d.serial_no')
                ->whereNull('d.deleted_at')
                ->whereNull('m.deleted_at'),
            'm.vendor_id',
            $vendorId
        )->pluck('d.serial_no'));

        $toSupplier = $this->countSerials($this->vendorWhere(
            DB::table('purchase_return_details as d')
                ->join('purchase_returns as r', 'r.id', '=', 'd.purchase_return_id')
                ->where('d.product_id', $productId)
                ->whereNotNull('d.serial_no')
                ->where('r.status', '!=', 'cancelled')
                ->whereNull('d.deleted_at')
                ->whereNull('r.deleted_at'),
            'r.vendor_id',
            $vendorId
        )->pluck('d.serial_no'));

        $soldQuery = $this->vendorWhere(
            DB::table('sale_details as d')
                ->join('sale_masters as m', 'm.id', '=', 'd.sale_master_id')
                ->where('d.product_id', $productId)
                ->whereNotNull('d.serial_no')
                ->whereNull('d.deleted_at')
                ->whereNull('m.deleted_at'),
            'm.vendor_id',
            $vendorId
        );
        if ($excludeSaleMasterId) {
            $soldQuery->where('m.id', '!=', $excludeSaleMasterId);
        }
        $sold = $this->countSerials($soldQuery->pluck('d.serial_no'));

        $fromPatients = $this->countSerials($this->vendorWhere(
            DB::table('sale_return_details as d')
                ->join('sale_returns as r', 'r.id', '=', 'd.sale_return_id')
                ->where('d.product_id', $productId)
                ->whereNotNull('d.serial_no')
                ->whereNotIn('r.status', ['cancelled', 'void'])
                ->whereNull('d.deleted_at')
                ->whereNull('r.deleted_at'),
            'r.vendor_id',
            $vendorId
        )->pluck('d.serial_no'));

        $balance = [];
        foreach ($purchased as $key => $n) {
            $balance[$key] = $n - ($toSupplier[$key] ?? 0) - ($sold[$key] ?? 0) + ($fromPatients[$key] ?? 0);
        }

        return ['purchased' => $purchased, 'balance' => $balance];
    }

    // ─────────────────────────── Purchases ───────────────────────────

    /**
     * Check serial lines of a new or edited purchase: no blanks, count = qty, no repeats,
     * and no serial that is already in stock anywhere (any branch, any product).
     * Serials this purchase already had are not re-checked, so old data can still be edited.
     *
     * @param array $lines each: product_id, qty, serials (comma text or array); batch lines are skipped
     * @throws StockGuardException
     */
    public function assertPurchaseSerials(array $lines, ?int $purchaseMasterId = null): void
    {
        $problems = [];
        $this->lockProducts(array_column($lines, 'product_id'));

        $ownKeys = [];
        if ($purchaseMasterId) {
            foreach (DB::table('purchase_details')->where('purchase_master_id', $purchaseMasterId)->whereNull('deleted_at')->pluck('serial_no') as $raw) {
                foreach (self::serialList($raw) as $s) {
                    $ownKeys[self::key($s)] = true;
                }
            }
        }

        $seen = [];
        $newSerials = [];
        foreach ($lines as $line) {
            if (!array_key_exists('serials', $line)) {
                continue; // batch line
            }
            $label = $this->productLabel((int) $line['product_id']);
            $serials = self::serialList($line['serials']);
            $qty = $line['qty'] ?? null;

            if (!$serials) {
                $problems[] = "{$label}: enter the serial numbers or a batch number.";
                continue;
            }
            if (!is_numeric($qty) || abs(count($serials) - (float) $qty) > 0.001) {
                $problems[] = "{$label}: quantity is " . (is_numeric($qty) ? self::fmt((float) $qty) : 'missing')
                    . ' but ' . count($serials) . ' serial number(s) are entered.';
            }
            foreach ($serials as $serial) {
                $key = self::key($serial);
                if (isset($seen[$key])) {
                    $problems[] = "Serial {$serial} is entered more than once in this purchase.";
                    continue;
                }
                $seen[$key] = true;
                if (!isset($ownKeys[$key])) {
                    $newSerials[$key] = $serial;
                }
            }
        }

        if ($newSerials) {
            $existing = $this->serialsInStockEverywhere($purchaseMasterId);
            foreach ($newSerials as $key => $serial) {
                if (isset($existing[$key])) {
                    $problems[] = "Serial {$serial} is already in stock (purchase {$existing[$key]}).";
                }
            }
        }

        if ($problems) {
            throw new StockGuardException(array_unique($problems));
        }
    }

    /**
     * Problems that stop an edit of an existing purchase: removing or changing lines whose
     * units were already sold or returned, or lowering a batch below what was used.
     *
     * @param array $keptLines detail id => [product_id, qty, serials (null for batch lines)]
     * @return string[]
     */
    public function purchaseEditProblems(int $purchaseMasterId, array $keptLines): array
    {
        $problems = [];
        foreach ($this->purchaseUsage($purchaseMasterId) as $detailId => $usage) {
            $label = $this->productLabel($usage['product_id']);
            $kept = $keptLines[$detailId] ?? null;
            $isUsed = $usage['used_serials'] || $usage['batch_used'] > 0.0001;

            if (!$kept) {
                if ($isUsed) {
                    $problems[] = "{$label}: this line cannot be removed because its stock was already sold or returned.";
                }
                continue;
            }
            if ($isUsed && (int) $kept['product_id'] !== $usage['product_id']) {
                $problems[] = "{$label}: the product cannot be changed because its stock was already sold or returned.";
                continue;
            }
            if ($usage['used_serials']) {
                $keptKeys = array_flip(array_map([self::class, 'key'], self::serialList($kept['serials'] ?? null)));
                foreach ($usage['used_serials'] as $key => $serial) {
                    if (!isset($keptKeys[$key])) {
                        $problems[] = "Serial {$serial} cannot be removed because it was already sold or returned.";
                    }
                }
            }
            if ($usage['batch_used'] > 0.0001 && is_numeric($kept['qty'] ?? null) && (float) $kept['qty'] < $usage['batch_used'] - 0.0001) {
                $problems[] = "{$label}: quantity cannot be lower than " . self::fmt($usage['batch_used']) . ' (already sold or returned).';
            }
        }

        return $problems;
    }

    /**
     * Reasons a purchase cannot be deleted (empty array = safe to delete).
     *
     * @return string[]
     */
    public function purchaseDeleteProblems(int $purchaseMasterId): array
    {
        $problems = [];

        $returnNos = DB::table('purchase_returns')
            ->where('purchase_master_id', $purchaseMasterId)
            ->where('status', '!=', 'cancelled')
            ->whereNull('deleted_at')
            ->pluck('return_no')
            ->merge(
                DB::table('purchase_return_details as d')
                    ->join('purchase_returns as r', 'r.id', '=', 'd.purchase_return_id')
                    ->where('d.purchase_master_id', $purchaseMasterId)
                    ->where('r.status', '!=', 'cancelled')
                    ->whereNull('d.deleted_at')
                    ->whereNull('r.deleted_at')
                    ->pluck('r.return_no')
            )
            ->unique()
            ->values();
        if ($returnNos->isNotEmpty()) {
            $problems[] = 'It has purchase returns (' . $returnNos->take(3)->implode(', ') . '). Cancel or delete those first.';
        }

        $usedSerials = [];
        $usedBatch = false;
        foreach ($this->purchaseUsage($purchaseMasterId) as $usage) {
            $usedSerials = array_merge($usedSerials, array_values($usage['used_serials']));
            $usedBatch = $usedBatch || $usage['batch_used'] > 0.0001;
        }
        if ($usedSerials) {
            $problems[] = 'Serial(s) ' . implode(', ', array_slice($usedSerials, 0, 3))
                . (count($usedSerials) > 3 ? ' and ' . (count($usedSerials) - 3) . ' more' : '')
                . ' from this purchase were already sold or returned.';
        }
        if ($usedBatch) {
            $problems[] = 'Stock from its batch(es) was already sold or returned.';
        }

        return $problems;
    }

    /**
     * For each line of a purchase: serials that were ever sold in that branch or returned to the
     * supplier, and how much of its batch was used (sold + returned to supplier - returned by patients).
     *
     * @return array<int, array{product_id: int, used_serials: array<string,string>, batch_used: float}>
     */
    protected function purchaseUsage(int $purchaseMasterId): array
    {
        $master = DB::table('purchase_masters')->where('id', $purchaseMasterId)->first(['id', 'vendor_id']);
        if (!$master) {
            return [];
        }

        $usage = [];
        $details = DB::table('purchase_details')->where('purchase_master_id', $purchaseMasterId)->whereNull('deleted_at')->get();
        foreach ($details as $d) {
            $row = ['product_id' => (int) $d->product_id, 'used_serials' => [], 'batch_used' => 0.0];

            $serials = self::serialList($d->serial_no);
            if ($serials) {
                $sold = $this->countSerials($this->vendorWhere(
                    DB::table('sale_details as sd')
                        ->join('sale_masters as sm', 'sm.id', '=', 'sd.sale_master_id')
                        ->where('sd.product_id', $d->product_id)
                        ->whereNotNull('sd.serial_no')
                        ->whereNull('sd.deleted_at')
                        ->whereNull('sm.deleted_at'),
                    'sm.vendor_id',
                    $master->vendor_id
                )->pluck('sd.serial_no'));
                $returned = $this->countSerials(
                    DB::table('purchase_return_details as rd')
                        ->join('purchase_returns as r', 'r.id', '=', 'rd.purchase_return_id')
                        ->where('rd.product_id', $d->product_id)
                        ->whereNotNull('rd.serial_no')
                        ->where('r.status', '!=', 'cancelled')
                        ->whereNull('rd.deleted_at')
                        ->whereNull('r.deleted_at')
                        ->pluck('rd.serial_no')
                );
                foreach ($serials as $serial) {
                    $key = self::key($serial);
                    if (isset($sold[$key]) || isset($returned[$key])) {
                        $row['used_serials'][$key] = $serial;
                    }
                }
            }

            if ($d->batch_id) {
                $batch = DB::table('batches')->where('id', $d->batch_id)->whereNull('deleted_at')->first();
                if ($batch) {
                    $row['batch_used'] = max(0, (float) $batch->sale_qty + (float) $batch->purchase_return_qty - (float) $batch->sale_return_qty);
                }
            }

            $usage[(int) $d->id] = $row;
        }

        return $usage;
    }

    /**
     * Serials still in stock in any branch (purchased more often than returned to the supplier),
     * keyed by serial key, value = purchase invoice no. Sold units also count: a sold serial
     * cannot be bought again either.
     */
    protected function serialsInStockEverywhere(?int $excludePurchaseMasterId): array
    {
        $query = DB::table('purchase_details as d')
            ->join('purchase_masters as m', 'm.id', '=', 'd.purchase_master_id')
            ->whereNotNull('d.serial_no')
            ->whereNull('d.deleted_at')
            ->whereNull('m.deleted_at');
        if ($excludePurchaseMasterId) {
            $query->where('m.id', '!=', $excludePurchaseMasterId);
        }

        $purchased = [];
        $invoice = [];
        foreach ($query->get(['d.serial_no', 'm.purchase_invoice_no']) as $row) {
            foreach (self::serialList($row->serial_no) as $s) {
                $key = self::key($s);
                $purchased[$key] = ($purchased[$key] ?? 0) + 1;
                $invoice[$key] = $row->purchase_invoice_no;
            }
        }

        $returned = $this->countSerials(
            DB::table('purchase_return_details as d')
                ->join('purchase_returns as r', 'r.id', '=', 'd.purchase_return_id')
                ->whereNotNull('d.serial_no')
                ->where('r.status', '!=', 'cancelled')
                ->whereNull('d.deleted_at')
                ->whereNull('r.deleted_at')
                ->pluck('d.serial_no')
        );

        $result = [];
        foreach ($purchased as $key => $n) {
            if ($n - ($returned[$key] ?? 0) > 0) {
                $result[$key] = $invoice[$key];
            }
        }

        return $result;
    }

    // ──────────────────────────── Helpers ────────────────────────────

    /** Decode a serial_no column / request value (JSON array, PHP array, or comma text) into clean strings. */
    public static function serialList($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : explode(',', $value);
        }
        if (!is_array($value)) {
            $value = [$value];
        }

        $out = [];
        array_walk_recursive($value, function ($s) use (&$out) {
            $s = trim((string) $s);
            if ($s !== '') {
                $out[] = $s;
            }
        });

        return $out;
    }

    public static function key(string $serial): string
    {
        return mb_strtoupper(trim($serial));
    }

    protected function countSerials(iterable $rawValues): array
    {
        $counts = [];
        foreach ($rawValues as $raw) {
            foreach (self::serialList($raw) as $s) {
                $key = self::key($s);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        return $counts;
    }

    protected function lockProducts(array $productIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($ids) {
            sort($ids); // same lock order everywhere avoids deadlocks
            DB::table('products')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get(['id']);
        }
    }

    protected function vendorWhere($query, string $column, ?int $vendorId)
    {
        return $vendorId ? $query->where($column, $vendorId) : $query->whereNull($column);
    }

    protected function productLabel(int $productId): string
    {
        if (!isset($this->labels[$productId])) {
            $model = DB::table('products')->where('id', $productId)->value('model');
            $this->labels[$productId] = $model ? "{$model}" : "Product #{$productId}";
        }

        return $this->labels[$productId];
    }

    protected static function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }
}
