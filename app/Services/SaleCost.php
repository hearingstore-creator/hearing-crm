<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Unit cost of a sale line, worked out from purchase records instead of trusting the browser.
 *
 * Mode (config crm.sale_cost_mode, env CRM_SALE_COST_MODE):
 *   shadow (default) - the sale still saves the posted cost; any difference from the database cost is logged
 *   server           - the database cost is saved; the posted value is only a fallback when nothing is known
 */
class SaleCost
{
    /** Cost to store on the sale line. */
    public static function resolve(int $productId, $batchId, $serials, float $posted): float
    {
        $server = self::lookup($productId, $batchId ? (int) $batchId : null, StockGuard::serialList($serials));

        if (config('crm.sale_cost_mode') === 'server') {
            return $server['source'] === 'none' ? $posted : $server['cost'];
        }

        if ($server['source'] !== 'none' && abs($server['cost'] - $posted) > 0.5) {
            Log::info('Sale cost differs from purchase records', [
                'product_id' => $productId, 'posted' => $posted, 'database' => $server['cost'], 'source' => $server['source'],
            ]);
        }

        return $posted;
    }

    /** @return array{cost: float, source: string} source is serial, batch, latest or none */
    public static function lookup(int $productId, ?int $batchId, array $serials): array
    {
        if ($serials) {
            $costs = self::serialCosts($productId);
            $found = [];
            foreach ($serials as $serial) {
                $key = StockGuard::key($serial);
                if (isset($costs[$key])) {
                    $found[] = $costs[$key];
                }
            }
            if ($found) {
                return ['cost' => round(array_sum($found) / count($found), 2), 'source' => 'serial'];
            }
        } elseif ($batchId) {
            $price = DB::table('batches')->where('id', $batchId)->where('product_id', $productId)->whereNull('deleted_at')->value('purchase_price');
            if ($price !== null && (float) $price > 0) {
                return ['cost' => (float) $price, 'source' => 'batch'];
            }
        }

        $latest = DB::table('purchase_details as d')->join('purchase_masters as m', 'm.id', '=', 'd.purchase_master_id')
            ->where('d.product_id', $productId)->whereNull('d.deleted_at')->whereNull('m.deleted_at')->where('d.purchase_price', '>', 0)
            ->orderByDesc('d.id')->value('d.purchase_price');

        return $latest !== null ? ['cost' => (float) $latest, 'source' => 'latest'] : ['cost' => 0.0, 'source' => 'none'];
    }

    /** [serial key => purchase price] from the purchase lines of one product (the newest line wins for a repeated serial). */
    protected static function serialCosts(int $productId): array
    {
        $out = [];
        $rows = DB::table('purchase_details as d')->join('purchase_masters as m', 'm.id', '=', 'd.purchase_master_id')
            ->where('d.product_id', $productId)->whereNotNull('d.serial_no')->whereNull('d.deleted_at')->whereNull('m.deleted_at')
            ->orderBy('d.id')->get(['d.serial_no', 'd.purchase_price']);

        foreach ($rows as $row) {
            foreach (StockGuard::serialList($row->serial_no) as $serial) {
                $out[StockGuard::key($serial)] = (float) $row->purchase_price;
            }
        }

        // Units added by a stock adjustment carry the cost entered on the adjustment line
        $adjusted = DB::table('stock_adjustment_details as d')->join('stock_adjustments as a', 'a.id', '=', 'd.stock_adjustment_id')
            ->where('d.product_id', $productId)->where('d.direction', 'in')->where('a.status', 'completed')
            ->whereNotNull('d.serial_no')->whereNull('d.deleted_at')->whereNull('a.deleted_at')
            ->orderBy('d.id')->get(['d.serial_no', 'd.unit_cost']);

        foreach ($adjusted as $row) {
            foreach (StockGuard::serialList($row->serial_no) as $serial) {
                $out[StockGuard::key($serial)] = (float) $row->unit_cost;
            }
        }

        return $out;
    }
}
