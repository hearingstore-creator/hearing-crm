<?php

namespace App\Console\Commands;

use App\Services\StockGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only stock health check. It changes nothing.
 *
 * Stock lives in three places that code keeps in step: the `stocks` ledger, the batch counters and the
 * serial lists on purchase / sale lines. For every product and location this compares them and also looks
 * for lines whose serial count does not match the quantity. Run it on live before and after any stock change.
 */
class InventoryCheck extends Command
{
    protected $signature = 'inventory:check {--product= : Only this product id} {--limit=200 : Most rows to print per section} {--strict : Exit with code 1 when a problem is found}';
    protected $description = 'Read-only check that the stock ledger, batches and serials agree';

    public function handle(StockGuard $guard): int
    {
        $only = $this->option('product') ? (int) $this->option('product') : null;
        $limit = max(1, (int) $this->option('limit'));
        $problems = 0;

        $names = DB::table('products')->pluck('model', 'id');
        $places = DB::table('users')->where('type', 'vendor')->pluck('name', 'id')->all();
        $where = fn (string $v) => $v === '' ? 'Head office' : ($places[(int) $v] ?? "Branch #{$v}");
        $key = fn ($vendor) => $vendor === null ? '' : (string) $vendor;

        // 1. Ledger vs batches vs serials, per product and location
        $ledger = DB::table('stocks')->whereNull('deleted_at')
            ->when($only, fn ($q) => $q->where('product_id', $only))
            ->groupBy('vendor_id', 'product_id')
            ->selectRaw('vendor_id, product_id, SUM(COALESCE(in_qty,0) - COALESCE(out_qty,0)) as qty')->get();

        $batches = DB::table('batches')->whereNull('deleted_at')
            ->when($only, fn ($q) => $q->where('product_id', $only))
            ->groupBy('vendor_id', 'product_id')
            ->selectRaw('vendor_id, product_id, SUM(COALESCE(purchase_qty,0) + COALESCE(sale_return_qty,0) + COALESCE(adjust_qty,0) - COALESCE(sale_qty,0) - COALESCE(purchase_return_qty,0)) as qty')->get();

        $cells = [];
        foreach ($ledger as $r) {
            $cells[$key($r->vendor_id) . '|' . $r->product_id]['ledger'] = (float) $r->qty;
        }
        foreach ($batches as $r) {
            $cells[$key($r->vendor_id) . '|' . $r->product_id]['batch'] = (float) $r->qty;
        }

        $serialProducts = DB::table('purchase_details')->whereNull('deleted_at')->whereNotNull('serial_no')
            ->when($only, fn ($q) => $q->where('product_id', $only))->distinct()->pluck('product_id')
            // Products whose serial units entered stock through an adjustment (opening stock, found) have no purchase line
            ->merge(DB::table('stock_adjustment_details')->whereNull('deleted_at')->whereNotNull('serial_no')
                ->when($only, fn ($q) => $q->where('product_id', $only))->distinct()->pluck('product_id'))
            ->unique()->values();
        $serialPlaces = collect([null])->merge(array_keys($places));
        $negativeSerials = [];

        foreach ($serialProducts as $productId) {
            foreach ($serialPlaces as $vendorId) {
                $balances = $guard->serialBalances((int) $productId, $vendorId === null ? null : (int) $vendorId)['balance'];
                $inStock = 0;
                foreach ($balances as $serial => $n) {
                    if ($n > 0) {
                        $inStock += $n;
                    }
                    if ($n < 0 || $n > 1) {
                        $negativeSerials[] = [$names[$productId] ?? "#{$productId}", $where($key($vendorId)), $serial, $n];
                    }
                }
                if ($inStock > 0 || isset($cells[$key($vendorId) . '|' . $productId])) {
                    $cells[$key($vendorId) . '|' . $productId]['serial'] = (float) $inStock;
                }
            }
        }

        $mismatch = [];
        $negative = [];
        foreach ($cells as $cell => $v) {
            [$vendor, $productId] = explode('|', $cell);
            $ledgerQty = $v['ledger'] ?? 0.0;
            $parts = ($v['batch'] ?? 0.0) + ($v['serial'] ?? 0.0);
            $label = [$names[(int) $productId] ?? "#{$productId}", $where($vendor)];
            if (abs($ledgerQty - $parts) > 0.001) {
                $mismatch[] = array_merge($label, [$this->n($ledgerQty), $this->n($v['batch'] ?? 0), $this->n($v['serial'] ?? 0), $this->n($ledgerQty - $parts)]);
            }
            if ($ledgerQty < -0.001) {
                $negative[] = array_merge($label, [$this->n($ledgerQty)]);
            }
        }

        $problems += $this->section('Ledger does not match batches + serials', ['Product', 'Location', 'Ledger', 'Batches', 'Serials in stock', 'Difference'], $mismatch, $limit);
        $problems += $this->section('Negative stock in the ledger', ['Product', 'Location', 'Ledger'], $negative, $limit);
        $problems += $this->section('Serials sold or returned more often than bought (or in stock twice)', ['Product', 'Location', 'Serial', 'Balance'], $negativeSerials, $limit);

        // 2. Negative batches
        $negBatches = DB::table('batches')->whereNull('deleted_at')
            ->when($only, fn ($q) => $q->where('product_id', $only))
            ->whereRaw('(COALESCE(purchase_qty,0) + COALESCE(sale_return_qty,0) + COALESCE(adjust_qty,0) - COALESCE(sale_qty,0) - COALESCE(purchase_return_qty,0)) < -0.001')
            ->get(['id', 'product_id', 'vendor_id', 'batch_no', 'purchase_qty', 'sale_qty'])
            ->map(fn ($b) => [$names[$b->product_id] ?? "#{$b->product_id}", $where($key($b->vendor_id)), $b->batch_no, $this->n($b->purchase_qty), $this->n($b->sale_qty)])->all();
        $problems += $this->section('Batches sold below zero', ['Product', 'Location', 'Batch', 'Bought', 'Sold'], $negBatches, $limit);

        // 3. Lines whose serial count does not match the quantity, and sale lines with neither batch nor serial
        $badLines = [];
        foreach (['purchase_details' => 'Purchase line', 'sale_details' => 'Sale line'] as $table => $label) {
            DB::table($table)->whereNull('deleted_at')->whereNotNull('serial_no')
                ->when($only, fn ($q) => $q->where('product_id', $only))
                ->orderBy('id')->chunkById(500, function ($rows) use (&$badLines, $label, $names) {
                    foreach ($rows as $row) {
                        $count = count(StockGuard::serialList($row->serial_no));
                        if ($count > 0 && abs($count - (float) $row->qty) > 0.001) {
                            $badLines[] = [$label . ' #' . $row->id, $names[$row->product_id] ?? "#{$row->product_id}", $this->n($row->qty), $count];
                        }
                    }
                });
        }
        $problems += $this->section('Serial count differs from the quantity on a line', ['Line', 'Product', 'Qty', 'Serials'], $badLines, $limit);

        $blind = DB::table('sale_details')->whereNull('deleted_at')->whereNull('batch_id')
            ->where(fn ($q) => $q->whereNull('serial_no')->orWhere('serial_no', '[]')->orWhere('serial_no', ''))
            ->when($only, fn ($q) => $q->where('product_id', $only))
            ->get(['id', 'product_id', 'qty'])
            ->map(fn ($r) => ['Sale line #' . $r->id, $names[$r->product_id] ?? "#{$r->product_id}", $this->n($r->qty)])->all();
        $problems += $this->section('Sale lines with neither a batch nor serial numbers (stock cannot be traced)', ['Line', 'Product', 'Qty'], $blind, $limit);

        $this->newLine();
        $this->line($problems === 0 ? '<info>All checks passed. Stock ledger, batches and serials agree.</info>' : "<comment>{$problems} problem row(s) found. Nothing was changed.</comment>");

        return $problems > 0 && $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }

    protected function section(string $title, array $headers, array $rows, int $limit): int
    {
        $this->newLine();
        $this->line('<options=bold>' . $title . '</>: ' . count($rows));
        if ($rows) {
            $this->table($headers, array_slice($rows, 0, $limit));
            if (count($rows) > $limit) {
                $this->line('  ... ' . (count($rows) - $limit) . ' more (raise --limit to see them).');
            }
        }

        return count($rows);
    }

    protected function n($v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') ?: '0';
    }
}
