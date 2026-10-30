<?php

namespace App\Services;

use App\Classes\Helper;
use App\Models\IncomeExpenseHead;
use App\Models\Ledger;
use App\Models\MarketingCampaign;
use App\Models\MarketingSpend;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Monthly campaign spend. Each entry is also the advertising expense in the accounts (one expense row,
 * head "Advertising & Marketing"), so it is entered once and the Profit Statement stays right.
 * A correction reverses the old expense row and posts a new one; nothing is deleted.
 * An expense typed in Expenses earlier can be linked to a campaign instead (no second expense row).
 */
class MarketingSpendService
{
    public const HEAD_NAME = 'Advertising & Marketing';
    public const LEDGER_TYPE = 'Expense';

    public function headId(): int
    {
        return (int) IncomeExpenseHead::firstOrCreate(
            ['head_type' => Helper::$expense, 'head_name' => self::HEAD_NAME],
            ['status' => 1]
        )->id;
    }

    /** Add or change the spend of a campaign for a month and branch. Returns the saved row. */
    public function save(MarketingCampaign $campaign, string $month, float $amount, ?int $vendorId, string $source = 'manual', ?string $note = null): MarketingSpend
    {
        $monthStart = Carbon::parse($month . (strlen($month) === 7 ? '-01' : ''))->startOfMonth()->toDateString();

        return DB::transaction(function () use ($campaign, $monthStart, $amount, $vendorId, $source, $note) {
            $spend = MarketingSpend::where('campaign_id', $campaign->id)->whereDate('month', $monthStart)
                ->where(fn ($q) => $vendorId ? $q->where('vendor_id', $vendorId) : $q->whereNull('vendor_id'))
                ->lockForUpdate()->first();

            if ($spend && $spend->source === 'expense') {
                throw new \DomainException('This month is linked to an expense from the Expenses screen. Change that expense, or unlink it first.');
            }

            if ($spend && round((float) $spend->amount, 2) === round($amount, 2)) {
                $spend->update(['note' => $note ?? $spend->note, 'updated_by' => auth()->id()]);
                return $spend;
            }

            if ($spend) {
                $this->reverseLedger($spend, 'Marketing spend changed from ' . number_format((float) $spend->amount, 2) . ' to ' . number_format($amount, 2));
                $spend->fill(['amount' => $amount, 'source' => $source, 'note' => $note ?? $spend->note, 'updated_by' => auth()->id()]);
            } else {
                $spend = new MarketingSpend([
                    'campaign_id' => $campaign->id, 'month' => $monthStart, 'amount' => $amount, 'vendor_id' => $vendorId,
                    'source' => $source, 'note' => $note, 'created_by' => auth()->id(),
                ]);
            }
            $spend->save();
            $spend->ledger_id = $amount > 0 ? $this->postLedger($spend, $campaign) : null;
            $spend->save();

            return $spend;
        });
    }

    /** Remove a month's spend: its expense row is reversed (an expense linked from Expenses is only unlinked). */
    public function remove(MarketingSpend $spend, string $reason): void
    {
        DB::transaction(function () use ($spend, $reason) {
            if ($spend->source !== 'expense') {
                $this->reverseLedger($spend, $reason);
            }
            $spend->delete();
        });
    }

    /** Count an expense already typed in the Expenses screen as a campaign's spend for its month. */
    public function linkExpense(MarketingCampaign $campaign, Ledger $ledger): MarketingSpend
    {
        if (MarketingSpend::where('ledger_id', $ledger->id)->exists()) {
            throw new \DomainException('That expense is already counted for a campaign.');
        }

        return MarketingSpend::create([
            'campaign_id' => $campaign->id,
            'month'       => Carbon::parse($ledger->date)->startOfMonth()->toDateString(),
            'amount'      => (float) $ledger->expense_amount,
            'vendor_id'   => $ledger->vendor_id,
            'ledger_id'   => $ledger->id,
            'source'      => 'expense',
            'note'        => mb_substr((string) $ledger->details, 0, 250) ?: null,
            'created_by'  => auth()->id(),
        ]);
    }

    /**
     * Advertising-type expenses from the Expenses screen not yet counted for any campaign
     * (head names that mention advertising, marketing, Facebook, Google, boost or promotion).
     */
    public function unlinkedAdvertisingExpenses(int $limit = 100)
    {
        $linked = MarketingSpend::whereNotNull('ledger_id')->pluck('ledger_id');

        return Ledger::withoutGlobalScopes()->from('ledgers')
            ->join('income_expense_heads as h', 'h.id', '=', 'ledgers.income_expense_head_id')
            ->whereNull('ledgers.deleted_at')
            ->where('ledgers.amount_type', Helper::$expense)
            ->whereNull('ledgers.type')
            ->where('ledgers.expense_amount', '>', 0)
            ->where(fn ($q) => $q->where('h.head_name', 'like', '%advert%')->orWhere('h.head_name', 'like', '%marketing%')
                ->orWhere('h.head_name', 'like', '%facebook%')->orWhere('h.head_name', 'like', '%google%')
                ->orWhere('h.head_name', 'like', '%boost%')->orWhere('h.head_name', 'like', '%promotion%'))
            ->whereNotIn('ledgers.id', $linked)
            ->orderByDesc('ledgers.date')->limit($limit)
            ->get(['ledgers.*', 'h.head_name']);
    }

    private function postLedger(MarketingSpend $spend, MarketingCampaign $campaign): int
    {
        $ledger = new Ledger();
        $ledger->income_expense_head_id = $this->headId();
        $ledger->vendor_id = $spend->vendor_id;
        $ledger->invoice_no = Helper::freeLedgerInvoiceNo(date('ymdhis') . rand(10, 99));
        $ledger->date = Carbon::parse($spend->month)->toDateString();
        $ledger->details = 'Marketing: ' . $campaign->name . ' (' . Carbon::parse($spend->month)->format('M Y') . ')';
        $ledger->expense_amount = $spend->amount;
        $ledger->income_amount = 0;
        $ledger->amount_type = Helper::$expense;
        $ledger->type = self::LEDGER_TYPE;
        $ledger->ref_invoice_no = 'MKT-' . $spend->id;
        $ledger->save();

        return (int) $ledger->id;
    }

    private function reverseLedger(MarketingSpend $spend, string $reason): void
    {
        if (!$spend->ledger_id) {
            return;
        }
        app(LedgerReversal::class)->reverse('ledgers', fn ($q) => $q->where('id', $spend->ledger_id), $reason, 'Marketing spend MKT-' . $spend->id);
    }
}
