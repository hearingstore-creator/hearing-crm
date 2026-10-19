<?php

namespace App\Services;

use App\Exceptions\StockGuardException;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockTransfer as Transfer;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Moves stock between head office and branches (or branch to branch).
 *
 * A transfer is never edited or deleted. It writes ordinary stock rows (out at the source, in at the destination),
 * moves batch quantities through batches.adjust_qty (the destination gets its own batch row that points back to
 * the original purchase through origin_batch_id), and for serial items is read by StockGuard so the unit leaves
 * the source and appears at the destination. A cancel writes the opposite rows ("... Reversal").
 *
 * Warranty clocks do not change: supplier warranty keeps counting from the original purchase, customer warranty
 * from the sale. No ledger or accounting entries are made: no money moves.
 */
class StockTransfer
{
    public function __construct(protected StockGuard $guard)
    {
    }

    /**
     * @param array $data from_vendor_id, to_vendor_id (null = head office), date, note, lines[]
     *   each line: product_id, qty, serials (text|array), batch_id (the source batch)
     * @throws StockGuardException with one message per problem
     */
    public function post(array $data, User $user): Transfer
    {
        $from = !empty($data['from_vendor_id']) ? (int) $data['from_vendor_id'] : null;
        $to = !empty($data['to_vendor_id']) ? (int) $data['to_vendor_id'] : null;

        $problems = [];
        if ($from === $to) {
            $problems[] = 'Choose two different locations.';
        }
        foreach ([$from, $to] as $vendorId) {
            if ($vendorId && !User::where('type', 'vendor')->whereKey($vendorId)->exists()) {
                $problems[] = 'The selected location does not exist.';
                break;
            }
        }
        try {
            if (empty($data['date'])) {
                throw new \Exception('no date');
            }
            Carbon::parse($data['date']);
        } catch (\Throwable $e) {
            $problems[] = 'Enter a valid date.';
        }
        $lines = array_values(array_filter($data['lines'] ?? [], fn ($l) => !empty($l['product_id'])));
        if (!$lines) {
            $problems[] = 'Add at least one product line.';
        }
        if ($problems) {
            throw new StockGuardException(array_unique($problems));
        }

        return DB::transaction(function () use ($data, $from, $to, $lines, $user) {
            $this->guard->lockProducts(array_column($lines, 'product_id'));
            $prepared = $this->prepareLines($lines, $from);

            $transfer = Transfer::create([
                'transfer_no'    => 'TMP-' . uniqid(),
                'from_vendor_id' => $from,
                'to_vendor_id'   => $to,
                'date'           => Carbon::parse($data['date'])->toDateString(),
                'note'           => trim((string) ($data['note'] ?? '')) ?: null,
                'status'         => 'completed',
                'created_by'     => $user->id,
            ]);
            $transfer->update(['transfer_no' => 'TR-' . str_pad((string) $transfer->id, 5, '0', STR_PAD_LEFT)]);
            $no = $transfer->transfer_no;
            $date = $transfer->date->toDateString();
            $fromName = $from ? (User::find($from)?->name ?? 'branch') : 'Head office';
            $toName = $to ? (User::find($to)?->name ?? 'branch') : 'Head office';

            foreach ($prepared as $line) {
                $destBatchId = null;

                if (!$line['serials']) {
                    $source = DB::table('batches')->where('id', $line['batch_id'])->first();
                    DB::table('batches')->where('id', $source->id)->decrement('adjust_qty', $line['qty']);
                    $destBatchId = $this->destinationBatch($source, $to, $line['qty'])->id;
                }

                $transfer->details()->create([
                    'product_id'    => $line['product_id'],
                    'qty'           => $line['qty'],
                    'serial_no'     => $line['serials'] ?: null,
                    'batch_id'      => $line['batch_id'],
                    'dest_batch_id' => $destBatchId,
                ]);

                $this->stockRow($line['product_id'], $from, $date, 0, $line['qty'], "Transfer to {$toName}: {$no}", 'Transfer Out', $no);
                $this->stockRow($line['product_id'], $to, $date, $line['qty'], 0, "Transfer from {$fromName}: {$no}", 'Transfer In', $no);
            }

            return $transfer->load('details');
        });
    }

    /**
     * Cancel by reversal. Super Admin only, reason required, and only when everything is still where the
     * transfer put it (serials still in stock at the destination, batch quantity still there).
     *
     * @throws StockGuardException
     */
    public function cancel(Transfer $transfer, User $user, string $reason): Transfer
    {
        $problems = [];
        if (!$user->isSuperAdmin()) {
            $problems[] = 'Only a Super Admin can cancel a stock transfer.';
        }
        if (trim($reason) === '') {
            $problems[] = 'A reason is required to cancel a transfer.';
        }
        if ($transfer->status !== 'completed') {
            $problems[] = 'This transfer is already cancelled.';
        }
        if ($problems) {
            throw new StockGuardException($problems);
        }

        return DB::transaction(function () use ($transfer, $user, $reason) {
            $transfer = Transfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if ($transfer->status !== 'completed') {
                throw new StockGuardException(['This transfer is already cancelled.']);
            }
            $details = $transfer->details()->get();
            $this->guard->lockProducts($details->pluck('product_id')->all());
            $toName = $transfer->to_name;

            $problems = [];
            $balances = [];
            foreach ($details as $d) {
                $name = Product::find($d->product_id)?->model ?? ('Product #' . $d->product_id);
                $serials = StockGuard::serialList($d->serial_no);

                if ($serials) {
                    $balances[$d->product_id] ??= $this->guard->serialBalances((int) $d->product_id, $transfer->to_vendor_id)['balance'];
                    foreach ($serials as $serial) {
                        if (($balances[$d->product_id][StockGuard::key($serial)] ?? 0) < 1) {
                            $problems[] = "{$name}: serial {$serial} is no longer in stock at {$toName} (sold or moved), so the transfer cannot be cancelled.";
                        }
                    }
                } elseif ($d->dest_batch_id) {
                    $batch = DB::table('batches')->where('id', $d->dest_batch_id)->lockForUpdate()->first();
                    $available = $batch ? $this->batchAvailable($batch) : 0;
                    if ($available + 0.0001 < (float) $d->qty) {
                        $problems[] = "{$name}: only " . $this->fmt($available) . " of the transferred quantity is still in stock at {$toName}, so the transfer cannot be cancelled.";
                    }
                }
            }
            if ($problems) {
                throw new StockGuardException(array_unique($problems));
            }

            $no = $transfer->transfer_no;
            $today = today()->toDateString();
            foreach ($details as $d) {
                if (!StockGuard::serialList($d->serial_no)) {
                    DB::table('batches')->where('id', $d->batch_id)->increment('adjust_qty', (float) $d->qty);
                    DB::table('batches')->where('id', $d->dest_batch_id)->decrement('adjust_qty', (float) $d->qty);
                }
                $this->stockRow((int) $d->product_id, $transfer->to_vendor_id, $today, 0, (float) $d->qty, "Cancelled {$no}: {$reason}", 'Transfer In Reversal', $no);
                $this->stockRow((int) $d->product_id, $transfer->from_vendor_id, $today, (float) $d->qty, 0, "Cancelled {$no}: {$reason}", 'Transfer Out Reversal', $no);
            }

            $transfer->update([
                'status'        => 'cancelled',
                'cancelled_at'  => now(),
                'cancelled_by'  => $user->id,
                'cancel_reason' => trim($reason),
            ]);

            return $transfer->load('details');
        });
    }

    // ───────────────────────── helpers ─────────────────────────

    /** Validate every line against the stock at the source (inside the transaction, after locking). */
    protected function prepareLines(array $lines, ?int $from): array
    {
        $problems = [];
        $out = [];
        $seenSerials = [];
        $batchNeed = [];
        $balances = [];
        $where = $from ? (User::find($from)?->name ?? 'this branch') : 'head office';

        foreach ($lines as $i => $line) {
            $n = $i + 1;
            $productId = (int) $line['product_id'];
            $product = Product::find($productId);
            if (!$product) {
                $problems[] = "Line {$n}: the product does not exist.";
                continue;
            }
            $name = $product->model ?: ('Product #' . $productId);
            $serials = $this->serialsOf($line['serials'] ?? null);
            $qty = $serials ? count($serials) : (float) ($line['qty'] ?? 0);

            if (!$serials && $qty <= 0) {
                $problems[] = "{$name}: enter a quantity.";
                continue;
            }
            if ($serials && isset($line['qty']) && $line['qty'] !== '' && abs((float) $line['qty'] - $qty) > 0.001) {
                $problems[] = "{$name}: quantity is {$line['qty']} but " . count($serials) . ' serial number(s) are selected.';
                continue;
            }

            $batchId = null;
            if ($serials) {
                $balances[$productId] ??= $this->guard->serialBalances($productId, $from);
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
                        $problems[] = "{$name}: serial {$serial} is not in stock at {$where} (sold, returned, moved or adjusted).";
                    }
                }
            } elseif (!empty($line['batch_id'])) {
                $batchId = (int) $line['batch_id'];
                $batchNeed[$batchId] = ['product_id' => $productId, 'name' => $name, 'qty' => ($batchNeed[$batchId]['qty'] ?? 0) + $qty];
            } else {
                $problems[] = "{$name}: pick the serial numbers or a batch to move.";
            }

            $out[] = ['product_id' => $productId, 'qty' => (float) $qty, 'serials' => $serials, 'batch_id' => $batchId];
        }

        foreach ($batchNeed as $batchId => $need) {
            $batch = DB::table('batches')->where('id', $batchId)->where('product_id', $need['product_id'])->whereNull('deleted_at')
                ->when($from, fn ($q) => $q->where('vendor_id', $from), fn ($q) => $q->whereNull('vendor_id'))
                ->lockForUpdate()->first();
            if (!$batch) {
                $problems[] = "{$need['name']}: the selected batch does not belong to this product at {$where}.";
                continue;
            }
            $available = $this->batchAvailable($batch);
            if ($need['qty'] > $available + 0.0001) {
                $problems[] = "{$need['name']} batch {$batch->batch_no}: only " . $this->fmt($available) . " in stock, " . $this->fmt($need['qty']) . ' to move.';
            }
        }

        if ($problems) {
            throw new StockGuardException(array_unique($problems));
        }

        return $out;
    }

    /**
     * The batch row at the destination for this source batch: the original batch itself when the stock goes back to
     * the location that bought it, otherwise one batch per original batch and location (created once, topped up later).
     * Batch numbers are unique in the CRM, so a destination batch gets the original number plus a location suffix.
     */
    protected function destinationBatch(object $source, ?int $to, float $qty): Batch
    {
        $originId = $source->origin_batch_id ?: $source->id;
        $origin = Batch::withoutGlobalScopes()->findOrFail($originId);

        if (($origin->vendor_id ? (int) $origin->vendor_id : null) === $to) {
            DB::table('batches')->where('id', $origin->id)->increment('adjust_qty', $qty);

            return $origin;
        }

        $batch = Batch::withoutGlobalScopes()->where('origin_batch_id', $originId)
            ->when($to, fn ($q) => $q->where('vendor_id', $to), fn ($q) => $q->whereNull('vendor_id'))
            ->lockForUpdate()->first();

        if (!$batch) {
            $number = $origin->batch_no . '-' . ($to ? 'B' . $to : 'HQ');
            if (Batch::withoutGlobalScopes()->where('batch_no', $number)->exists()) {
                $number .= '-' . $originId;
            }

            return Batch::create([
                'product_id'      => $source->product_id,
                'vendor_id'       => $to,
                'batch_no'        => $number,
                'ref_invoice_no'  => $origin->ref_invoice_no,
                'type'            => 'Transfer',
                'purchase_price'  => $origin->purchase_price,
                'purchase_qty'    => 0,
                'adjust_qty'      => $qty,
                'origin_batch_id' => $originId,
            ]);
        }

        DB::table('batches')->where('id', $batch->id)->increment('adjust_qty', $qty);

        return $batch;
    }

    protected function batchAvailable(object $batch): float
    {
        return (float) $batch->purchase_qty + (float) $batch->sale_return_qty + (float) $batch->adjust_qty
            - (float) $batch->sale_qty - (float) $batch->purchase_return_qty;
    }

    protected function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') ?: '0';
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
