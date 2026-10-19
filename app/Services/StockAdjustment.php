<?php

namespace App\Services;

use App\Exceptions\StockGuardException;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockAdjustment as Adjustment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Posts and cancels stock adjustments (damaged, lost, found, count correction, opening stock).
 *
 * An adjustment is never edited or deleted. It writes ordinary stock rows (type "Adjustment In" / "Adjustment Out"),
 * moves batch quantities through batches.adjust_qty, and for serial items is read by StockGuard so the unit
 * appears in or leaves the stock of that location. A cancel writes the opposite rows ("... Reversal").
 * No ledger or accounting entries are made: no money moves.
 */
class StockAdjustment
{
    public function __construct(protected StockGuard $guard)
    {
    }

    /**
     * @param array $data vendor_id (null = head office), date, reason, note, lines[]
     *   each line: product_id, direction (in|out), qty, serials (text|array), batch_id (out), batch_no (in),
     *   unit_cost, mrp_price, warranty_start_date
     * @throws StockGuardException with one message per problem
     */
    public function post(array $data, User $user): Adjustment
    {
        $vendorId = !empty($data['vendor_id']) ? (int) $data['vendor_id'] : null;
        $problems = $this->headerProblems($data, $vendorId);
        $lines = array_values(array_filter($data['lines'] ?? [], fn ($l) => !empty($l['product_id'])));
        if (!$lines) {
            $problems[] = 'Add at least one product line.';
        }
        if ($problems) {
            throw new StockGuardException($problems);
        }

        return DB::transaction(function () use ($data, $vendorId, $lines, $user) {
            $this->guard->lockProducts(array_column($lines, 'product_id'));
            $prepared = $this->prepareLines($lines, $vendorId);

            $adjustment = Adjustment::create([
                'adjustment_no' => 'TMP-' . uniqid(),
                'vendor_id'     => $vendorId,
                'date'          => Carbon::parse($data['date'])->toDateString(),
                'reason'        => $data['reason'],
                'note'          => trim((string) ($data['note'] ?? '')) ?: null,
                'status'        => 'completed',
                'created_by'    => $user->id,
            ]);
            $adjustment->update(['adjustment_no' => 'ADJ-' . str_pad((string) $adjustment->id, 5, '0', STR_PAD_LEFT)]);
            $no = $adjustment->adjustment_no;
            $label = Adjustment::REASONS[$data['reason']] ?? $data['reason'];

            foreach ($prepared as $line) {
                $batchId = $line['batch_id'];

                if ($line['direction'] === 'in' && !$line['serials']) {
                    $batch = Batch::create([
                        'product_id'     => $line['product_id'],
                        'vendor_id'      => $vendorId,
                        'batch_no'       => $line['batch_no'] ?: 'ADJ' . $adjustment->id . '-' . $line['product_id'],
                        'ref_invoice_no' => $no,
                        'type'           => 'Adjustment',
                        'purchase_price' => $line['unit_cost'],
                        'purchase_qty'   => 0,
                        'adjust_qty'     => $line['qty'],
                    ]);
                    $batchId = $batch->id;
                } elseif ($line['direction'] === 'out' && $batchId) {
                    DB::table('batches')->where('id', $batchId)->decrement('adjust_qty', $line['qty']);
                }

                $adjustment->details()->create([
                    'product_id'          => $line['product_id'],
                    'direction'           => $line['direction'],
                    'qty'                 => $line['qty'],
                    'serial_no'           => $line['serials'] ?: null,
                    'batch_id'            => $batchId,
                    'unit_cost'           => $line['unit_cost'],
                    'mrp_price'           => $line['mrp_price'],
                    'warranty_start_date' => $line['warranty_start_date'],
                ]);

                $this->stockRow($line['product_id'], $vendorId, $adjustment->date->toDateString(), $line['direction'] === 'in' ? $line['qty'] : 0,
                    $line['direction'] === 'out' ? $line['qty'] : 0, "{$label}: {$no}", $line['direction'] === 'in' ? 'Adjustment In' : 'Adjustment Out', $no);
            }

            return $adjustment->load('details');
        });
    }

    /**
     * Cancel by reversal. Super Admin only, reason required, and only when everything the adjustment
     * did is still as it left it (an added unit is still in stock, a written-off serial is not back in stock).
     *
     * @throws StockGuardException
     */
    public function cancel(Adjustment $adjustment, User $user, string $reason): Adjustment
    {
        $problems = [];
        if (!$user->isSuperAdmin()) {
            $problems[] = 'Only a Super Admin can cancel a stock adjustment.';
        }
        if (trim($reason) === '') {
            $problems[] = 'A reason is required to cancel an adjustment.';
        }
        if ($adjustment->status !== 'completed') {
            $problems[] = 'This adjustment is already cancelled.';
        }
        if ($problems) {
            throw new StockGuardException($problems);
        }

        return DB::transaction(function () use ($adjustment, $user, $reason) {
            $adjustment = Adjustment::whereKey($adjustment->id)->lockForUpdate()->firstOrFail();
            if ($adjustment->status !== 'completed') {
                throw new StockGuardException(['This adjustment is already cancelled.']);
            }
            $details = $adjustment->details()->get();
            $this->guard->lockProducts($details->pluck('product_id')->all());

            $problems = [];
            $inStockAnywhere = null;
            $balances = [];
            foreach ($details as $d) {
                $name = Product::find($d->product_id)?->model ?? ('Product #' . $d->product_id);
                $serials = StockGuard::serialList($d->serial_no);

                if ($d->direction === 'out' && $serials) {
                    $inStockAnywhere ??= $this->guard->serialsInStockEverywhere(null);
                    foreach ($serials as $serial) {
                        if (isset($inStockAnywhere[StockGuard::key($serial)])) {
                            $problems[] = "{$name}: serial {$serial} is in stock again, so the write-off cannot be cancelled.";
                        }
                    }
                } elseif ($d->direction === 'in' && $serials) {
                    $balances[$d->product_id] ??= $this->guard->serialBalances((int) $d->product_id, $adjustment->vendor_id)['balance'];
                    foreach ($serials as $serial) {
                        if (($balances[$d->product_id][StockGuard::key($serial)] ?? 0) < 1) {
                            $problems[] = "{$name}: serial {$serial} is no longer in stock here (sold or moved), so the adjustment cannot be cancelled.";
                        }
                    }
                } elseif ($d->direction === 'in' && $d->batch_id) {
                    $batch = DB::table('batches')->where('id', $d->batch_id)->lockForUpdate()->first();
                    $available = $batch ? (float) $batch->purchase_qty + (float) $batch->sale_return_qty + (float) $batch->adjust_qty
                        - (float) $batch->sale_qty - (float) $batch->purchase_return_qty : 0;
                    if ($available + 0.0001 < (float) $d->qty) {
                        $problems[] = "{$name}: only " . rtrim(rtrim(number_format($available, 2, '.', ''), '0'), '.') . ' of the added batch quantity is still in stock, so the adjustment cannot be cancelled.';
                    }
                }
            }
            if ($problems) {
                throw new StockGuardException(array_unique($problems));
            }

            $no = $adjustment->adjustment_no;
            foreach ($details as $d) {
                if ($d->batch_id) {
                    DB::table('batches')->where('id', $d->batch_id)->{$d->direction === 'in' ? 'decrement' : 'increment'}('adjust_qty', (float) $d->qty);
                }
                $this->stockRow((int) $d->product_id, $adjustment->vendor_id, today()->toDateString(), $d->direction === 'out' ? (float) $d->qty : 0,
                    $d->direction === 'in' ? (float) $d->qty : 0, "Cancelled {$no}: {$reason}", $d->direction === 'in' ? 'Adjustment In Reversal' : 'Adjustment Out Reversal', $no);
            }

            $adjustment->update([
                'status'        => 'cancelled',
                'cancelled_at'  => now(),
                'cancelled_by'  => $user->id,
                'cancel_reason' => trim($reason),
            ]);

            return $adjustment->load('details');
        });
    }

    // ───────────────────────── helpers ─────────────────────────

    protected function headerProblems(array $data, ?int $vendorId): array
    {
        $problems = [];
        $reason = $data['reason'] ?? null;
        if (!isset(Adjustment::REASONS[$reason])) {
            $problems[] = 'Choose a reason.';
        } elseif (in_array($reason, ['other', 'count_correction'], true) && trim((string) ($data['note'] ?? '')) === '') {
            $problems[] = 'A note is required for this reason.';
        }
        try {
            Carbon::parse($data['date'] ?? null);
            if (empty($data['date'])) {
                throw new \Exception('no date');
            }
        } catch (\Throwable $e) {
            $problems[] = 'Enter a valid date.';
        }
        if ($vendorId && !User::where('type', 'vendor')->whereKey($vendorId)->exists()) {
            $problems[] = 'The selected location does not exist.';
        }

        return $problems;
    }

    /** Validate every line against the current stock (inside the transaction, after locking) and return normalised lines. */
    protected function prepareLines(array $lines, ?int $vendorId): array
    {
        $problems = [];
        $out = [];
        $seenSerials = [];
        $batchNeed = [];
        $balances = [];
        $inStockAnywhere = null;
        $where = $vendorId ? (User::find($vendorId)?->name ?? 'this branch') : 'head office';

        foreach ($lines as $i => $line) {
            $n = $i + 1;
            $productId = (int) $line['product_id'];
            $product = Product::find($productId);
            if (!$product) {
                $problems[] = "Line {$n}: the product does not exist.";
                continue;
            }
            $name = $product->model ?: ('Product #' . $productId);
            $direction = $line['direction'] ?? null;
            if (!in_array($direction, ['in', 'out'], true)) {
                $problems[] = "{$name}: choose Add or Remove.";
                continue;
            }

            $serials = $this->serialsOf($line['serials'] ?? null);
            $qty = $serials ? count($serials) : (float) ($line['qty'] ?? 0);
            if (!$serials && $qty <= 0) {
                $problems[] = "{$name}: enter a quantity.";
                continue;
            }
            if ($serials && isset($line['qty']) && $line['qty'] !== '' && abs((float) $line['qty'] - $qty) > 0.001) {
                $problems[] = "{$name}: quantity is {$line['qty']} but " . count($serials) . ' serial number(s) are entered.';
                continue;
            }

            $unitCost = isset($line['unit_cost']) && $line['unit_cost'] !== '' ? (float) $line['unit_cost'] : null;
            $mrp = isset($line['mrp_price']) && $line['mrp_price'] !== '' ? (float) $line['mrp_price'] : null;
            $warrantyStart = null;
            if (!empty($line['warranty_start_date'])) {
                try {
                    $warrantyStart = Carbon::parse($line['warranty_start_date'])->toDateString();
                } catch (\Throwable $e) {
                    $problems[] = "{$name}: the warranty start date is not valid.";
                }
            }
            if (($unitCost !== null && $unitCost < 0) || ($mrp !== null && $mrp < 0)) {
                $problems[] = "{$name}: cost and price cannot be negative.";
            }

            $batchId = null;
            if ($direction === 'out') {
                if ($serials) {
                    $balances[$productId] ??= $this->guard->serialBalances($productId, $vendorId);
                    foreach ($serials as $serial) {
                        $key = StockGuard::key($serial);
                        if (isset($seenSerials[$key])) {
                            $problems[] = "Serial {$serial} is listed more than once.";
                            continue;
                        }
                        $seenSerials[$key] = true;
                        if (!isset($balances[$productId]['purchased'][$key])) {
                            $problems[] = "{$name}: serial {$serial} was never in stock at {$where}.";
                        } elseif (($balances[$productId]['balance'][$key] ?? 0) < 1) {
                            $problems[] = "{$name}: serial {$serial} is not in stock at {$where} (sold, returned or already adjusted).";
                        }
                    }
                } elseif (!empty($line['batch_id'])) {
                    $batchId = (int) $line['batch_id'];
                    $batchNeed[$batchId] = ['product_id' => $productId, 'name' => $name, 'qty' => ($batchNeed[$batchId]['qty'] ?? 0) + $qty];
                } else {
                    $problems[] = "{$name}: pick the serial numbers or a batch to remove.";
                }
            } else {
                if ($unitCost === null) {
                    $problems[] = "{$name}: enter the unit cost of the stock being added.";
                }
                if ($serials) {
                    $inStockAnywhere ??= $this->guard->serialsInStockEverywhere(null);
                    foreach ($serials as $serial) {
                        $key = StockGuard::key($serial);
                        if (isset($seenSerials[$key])) {
                            $problems[] = "Serial {$serial} is listed more than once.";
                            continue;
                        }
                        $seenSerials[$key] = true;
                        if (isset($inStockAnywhere[$key])) {
                            $problems[] = "{$name}: serial {$serial} is already in stock ({$inStockAnywhere[$key]}).";
                        }
                    }
                }
            }

            $out[] = [
                'product_id' => $productId, 'direction' => $direction, 'qty' => (float) $qty, 'serials' => $serials,
                'batch_id' => $batchId, 'batch_no' => trim((string) ($line['batch_no'] ?? '')),
                'unit_cost' => $unitCost, 'mrp_price' => $mrp, 'warranty_start_date' => $warrantyStart,
            ];
        }

        foreach ($batchNeed as $batchId => $need) {
            $batch = DB::table('batches')->where('id', $batchId)->where('product_id', $need['product_id'])->whereNull('deleted_at')
                ->when($vendorId, fn ($q) => $q->where('vendor_id', $vendorId), fn ($q) => $q->whereNull('vendor_id'))
                ->lockForUpdate()->first();
            if (!$batch) {
                $problems[] = "{$need['name']}: the selected batch does not belong to this product at {$where}.";
                continue;
            }
            $available = (float) $batch->purchase_qty + (float) $batch->sale_return_qty + (float) $batch->adjust_qty
                - (float) $batch->sale_qty - (float) $batch->purchase_return_qty;
            if ($need['qty'] > $available + 0.0001) {
                $problems[] = "{$need['name']} batch {$batch->batch_no}: only " . rtrim(rtrim(number_format(max(0, $available), 2, '.', ''), '0'), '.') . " in stock, {$need['qty']} to remove.";
            }
        }

        if ($problems) {
            throw new StockGuardException(array_unique($problems));
        }

        return $out;
    }

    /** Serial numbers typed one per line, or separated by commas or semicolons. */
    protected function serialsOf($raw): array
    {
        if (is_string($raw)) {
            $raw = preg_replace('/[\r\n;]+/', ',', $raw);
        }

        return StockGuard::serialList($raw);
    }

    protected function stockRow(int $productId, ?int $vendorId, string $date, float $in, float $out, string $details, string $type, string $ref): void
    {
        Stock::create([
            'product_id'     => $productId,
            'vendor_id'      => $vendorId,
            'date'           => $date,
            'in_qty'         => $in,
            'out_qty'        => $out,
            'details'        => $details,
            'type'           => $type,
            'ref_invoice_no' => $ref,
        ]);
    }
}
