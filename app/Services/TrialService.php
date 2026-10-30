<?php

namespace App\Services;

use App\Classes\Helper;
use App\Enums\ActivityType;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerAdvance;
use App\Models\CustomerLedger;
use App\Models\Deal;
use App\Models\DealTrial;
use App\Models\Ledger;
use App\Models\Product;
use App\Notifications\CrmAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Hearing-aid trials.
 *  - In-session (the usual case): which model was tried and how it went; feeds the trial reports.
 *  - Take-home (rare): the serial leaves the centre against a deposit (kept as a customer advance), with a return date.
 *    The serial cannot be sold to anyone else while it is out. It ends as bought (found on a sale to the same patient)
 *    or returned (deposit refunded or kept as credit). Overdue returns alert the owner.
 */
class TrialService
{
    public function __construct(private DealService $deals)
    {
    }

    /** "Brand Model" of a product, or the text typed. */
    public static function label(?Product $product, ?string $typed = null): string
    {
        if ($product) {
            $brand = DB::table('brands')->where('id', $product->brand_id)->value('brand_name');

            return trim(($brand ? $brand . ' ' : '') . ($product->model ?? $product->product_name ?? 'Product #' . $product->id));
        }

        return trim((string) $typed) ?: 'Not recorded';
    }

    /** A typed trial product ("Phonak Audeo L70") matched to a product when the model is recognisable. */
    public static function matchProduct(?string $typed): ?Product
    {
        $typed = trim((string) $typed);
        if ($typed === '') {
            return null;
        }
        $candidates = Product::withoutGlobalScopes()->whereNotNull('model')->where('model', '!=', '')->get(['id', 'model', 'brand_id']);
        $best = null;
        foreach ($candidates as $p) {
            if (stripos($typed, (string) $p->model) !== false && (!$best || strlen($p->model) > strlen($best->model))) {
                $best = $p;
            }
        }

        return $best ? Product::withoutGlobalScopes()->find($best->id) : null;
    }

    public function recordInSession(Deal $deal, ?int $productId, ?string $typed, ?string $result, ?string $note = null): DealTrial
    {
        $product = $productId ? Product::withoutGlobalScopes()->find($productId) : self::matchProduct($typed);

        return DealTrial::create([
            'deal_id' => $deal->id, 'lead_id' => $deal->lead_id, 'product_id' => $product?->id,
            'product_label' => mb_substr($product ? self::label($product) : self::label(null, $typed), 0, 150),
            'kind' => 'in_session', 'result' => array_key_exists((string) $result, DealTrial::RESULTS) ? $result : null,
            'status' => 'done', 'vendor_id' => $deal->vendor_id, 'notes' => $note, 'user_id' => auth()->id(),
        ]);
    }

    /** Take-home trial: hold the serial, take the deposit, plan the return. */
    public function startTakeHome(Deal $deal, int $productId, string $serial, float $deposit, string $method, ?string $reference, string $returnDue, ?string $note = null): DealTrial
    {
        $product = Product::withoutGlobalScopes()->findOrFail($productId);
        $serial = trim($serial);
        $vendorId = $deal->vendor_id ?: (auth()->user()?->branchId());

        $balances = app(StockGuard::class)->serialBalances($product->id, $vendorId);
        if (($balances['balance'][StockGuard::key($serial)] ?? 0) < 1) {
            throw new \DomainException("Serial {$serial} is not in stock for " . self::label($product) . ' at this branch.');
        }
        if ($this->heldBy($serial)) {
            throw new \DomainException("Serial {$serial} is already out on a take-home trial.");
        }

        return DB::transaction(function () use ($deal, $product, $serial, $deposit, $method, $reference, $returnDue, $note, $vendorId) {
            $customer = Customer::withoutGlobalScopes()->firstOrCreate(['lead_id' => $deal->lead_id], [
                'referral_code' => Helper::generateReferralCode((string) DB::table('leads')->where('id', $deal->lead_id)->value('full_name')),
                'created_by' => auth()->id(),
            ]);

            $trial = DealTrial::create([
                'deal_id' => $deal->id, 'lead_id' => $deal->lead_id, 'product_id' => $product->id, 'product_label' => self::label($product),
                'kind' => 'take_home', 'serial_no' => $serial, 'vendor_id' => $vendorId, 'deposit' => $deposit,
                'return_due' => $returnDue, 'status' => 'out', 'notes' => $note, 'user_id' => auth()->id(),
            ]);
            if ($deposit > 0) {
                $trial->update(['advance_id' => $this->takeDeposit($customer, $trial, $deposit, $method, $reference, $vendorId)->id]);
            }

            $due = Carbon::parse($returnDue);
            $this->deals->addActivity($deal, ActivityType::CALL, $due->copy()->subDay()->setTime(11, 0)->toDateTimeString(),
                'Take-home trial: ' . $trial->product_label . ' (S/N ' . $serial . ') due back ' . $due->format('d M') . ' – remind the patient');
            $this->deals->addActivity($deal, ActivityType::NOTE, null, 'Take-home trial started: ' . $trial->product_label . ' S/N ' . $serial
                . ($deposit > 0 ? ', deposit ৳' . number_format($deposit, 2) : ', no deposit') . ', back by ' . $due->format('d M Y'));

            return $trial;
        });
    }

    /** The patient keeps the device: it must be on a sale to the same patient. */
    public function closeBought(DealTrial $trial, ?int $saleMasterId = null): DealTrial
    {
        if (!$trial->isOut()) {
            throw new \DomainException('This trial is already closed.');
        }
        $saleId = $saleMasterId ?: $this->saleWithSerial($trial);
        if (!$saleId) {
            throw new \DomainException('Make the sale first (pick serial ' . $trial->serial_no . ' and use the deposit as the advance), then mark the trial as bought.');
        }

        $trial->update(['status' => 'bought', 'sale_master_id' => $saleId, 'closed_at' => now()]);
        $advance = $trial->advance_id ? CustomerAdvance::find($trial->advance_id) : null;
        $this->deals->addActivity($trial->deal, ActivityType::NOTE, null, 'Take-home trial ended: patient bought ' . $trial->product_label
            . ($advance && $advance->balance > 0.009 ? '. Deposit ৳' . number_format((float) $advance->balance, 2) . ' is still unused – apply it or refund it under Advances.' : '.'));

        return $trial;
    }

    /** The device came back: release the serial; refund the deposit or keep it as the patient's credit. */
    public function closeReturned(DealTrial $trial, string $deposit = 'refund', string $method = 'cash', ?string $reference = null, ?string $note = null): DealTrial
    {
        if (!$trial->isOut()) {
            throw new \DomainException('This trial is already closed.');
        }

        return DB::transaction(function () use ($trial, $deposit, $method, $reference, $note) {
            $advance = $trial->advance_id ? CustomerAdvance::find($trial->advance_id) : null;
            if ($advance && $advance->balance > 0.009 && in_array($advance->status, [CustomerAdvance::STATUS_ACTIVE, CustomerAdvance::STATUS_PARTIALLY_APPLIED], true)) {
                app(CustomerAdvanceService::class)->processRefund($advance, $deposit === 'refund' ? (float) $advance->balance : 0, 0, $method, $reference,
                    'Take-home trial returned' . ($note ? ': ' . $note : ''), $deposit === 'refund' ? 'refund' : 'credit_kept');
            }
            $trial->update(['status' => 'returned', 'closed_at' => now(), 'notes' => trim($trial->notes . ($note ? "\nReturned: " . $note : ''))]);
            $this->deals->addActivity($trial->deal, ActivityType::NOTE, null, 'Take-home trial returned: ' . $trial->product_label . ' S/N ' . $trial->serial_no
                . ($advance ? ($deposit === 'refund' ? ', deposit refunded' : ', deposit kept as credit') : ''));
            DB::table('lead_follow_ups')->where('deal_id', $trial->deal_id)->where('status', 'pending')
                ->where('follow_up_notes', 'like', 'Take-home trial:%' . $trial->serial_no . '%')
                ->update(['status' => 'completed', 'completed_at' => now(), 'outcome_notes' => 'Device returned']);

            return $trial;
        });
    }

    /** The open take-home trial holding this serial, if any. */
    public function heldBy(string $serial, ?int $exceptCustomerId = null): ?DealTrial
    {
        $trial = DealTrial::where('kind', 'take_home')->where('status', 'out')->where('serial_no', trim($serial))->first();
        if ($trial && $exceptCustomerId && (int) DB::table('customers')->where('lead_id', $trial->lead_id)->value('id') === $exceptCustomerId) {
            return null;   // the patient who has it on trial may buy it
        }

        return $trial;
    }

    /** Scheduled (crm:automations trials): close trials that were bought, alert overdue returns. @return array{bought: int, overdue: int} */
    public function sweep(): array
    {
        $bought = 0;
        $overdue = 0;
        foreach (DealTrial::where('kind', 'take_home')->where('status', 'out')->get() as $trial) {
            if ($saleId = $this->saleWithSerial($trial)) {
                $this->closeBought($trial, $saleId);
                $bought++;
                continue;
            }
            if ($trial->return_due && $trial->return_due->lt(today()) && !$trial->overdue_alerted_at) {
                $deal = $trial->deal;
                $name = DB::table('leads')->where('id', $trial->lead_id)->value('full_name');
                $notifier = app(CrmNotifier::class);
                $notifier->send($notifier->superAdmins()->push($deal?->owner), new CrmAlert(
                    'Trial device not returned',
                    "{$name} has {$trial->product_label} (S/N {$trial->serial_no}) since " . $trial->created_at->format('d M') . '; it was due back ' . $trial->return_due->format('d M') . '.',
                    url('admin/deals?deal=' . $trial->deal_id), 'fa-assistive-listening-systems', 'danger', 'deal_assigned'
                ));
                $trial->update(['overdue_alerted_at' => now()]);
                $overdue++;
            }
        }

        return ['bought' => $bought, 'overdue' => $overdue];
    }

    private function saleWithSerial(DealTrial $trial): ?int
    {
        $customerId = DB::table('customers')->where('lead_id', $trial->lead_id)->value('id');
        if (!$customerId) {
            return null;
        }
        $rows = DB::table('sale_details as d')->join('sale_masters as m', 'm.id', '=', 'd.sale_master_id')
            ->where('m.customer_id', $customerId)->whereNull('m.deleted_at')->whereNull('d.deleted_at')
            ->where('m.created_at', '>=', $trial->created_at)->where('d.serial_no', 'like', '%' . $trial->serial_no . '%')
            ->get(['m.id', 'd.serial_no']);
        foreach ($rows as $r) {
            if (in_array(StockGuard::key($trial->serial_no), array_map([StockGuard::class, 'key'], StockGuard::serialList($r->serial_no)), true)) {
                return (int) $r->id;
            }
        }

        return null;
    }

    /** Deposit as a customer advance, booked like other advances (customer ledger + cash in). */
    private function takeDeposit(Customer $customer, DealTrial $trial, float $amount, string $method, ?string $reference, ?int $vendorId): CustomerAdvance
    {
        $receiptNo = CustomerAdvance::generateReceiptNo();
        $advance = CustomerAdvance::create([
            'receipt_no' => $receiptNo, 'customer_id' => $customer->id, 'lead_id' => $trial->lead_id, 'date' => now()->toDateString(),
            'amount' => $amount, 'applied_amount' => 0, 'refunded_amount' => 0, 'deduction_fee' => 0, 'balance' => $amount,
            'payment_method' => $method, 'reference' => $reference, 'status' => CustomerAdvance::STATUS_ACTIVE,
            'notes' => 'Deposit for take-home trial: ' . $trial->product_label . ' S/N ' . $trial->serial_no, 'vendor_id' => $vendorId, 'created_by' => auth()->id(),
        ]);

        $c = new CustomerLedger();
        $c->customer_id = $customer->id;
        $c->date = now()->toDateString();
        $c->details = "Trial deposit {$receiptNo} – {$trial->product_label} S/N {$trial->serial_no}" . ($reference ? " (Ref: {$reference})" : '');
        $c->debit = 0;
        $c->credit = $amount;
        $c->type = 'Receive';
        $c->ref_invoice_no = $receiptNo;
        $c->save();

        $g = new Ledger();
        $g->invoice_no = 'ADV-LED-' . $receiptNo;
        $g->date = now()->toDateString();
        $g->details = "Trial deposit {$receiptNo} – " . (DB::table('leads')->where('id', $trial->lead_id)->value('full_name') ?? 'patient');
        $g->expense_amount = 0;
        $g->income_amount = $amount;
        $g->amount_type = Helper::$income;
        $g->type = 'Advance Receive';
        $g->ref_invoice_no = $receiptNo;
        $g->vendor_id = $vendorId;
        $g->save();

        ActivityLog::create([
            'user_id' => auth()->id(), 'subject_id' => $advance->id, 'subject_type' => CustomerAdvance::class, 'action' => 'trial_deposit_received',
            'new_values' => ['receipt_no' => $receiptNo, 'amount' => $amount, 'trial_id' => $trial->id, 'serial' => $trial->serial_no],
            'ip_address' => request()->ip() ?? '127.0.0.1', 'user_agent' => mb_substr((string) request()->userAgent(), 0, 255),
        ]);

        return $advance;
    }
}
