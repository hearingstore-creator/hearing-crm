<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;

/**
 * Cancels money records by reversal instead of deleting their ledger rows.
 *
 * Each original row is kept and its type is marked "(Reversed)", so type-based lists and
 * totals (payments, sales) no longer count it. A mirror row is added on the same date:
 * debit/credit swapped for party ledgers, negative amounts for the company ledger.
 * Statements therefore keep the full history and still net to zero.
 *
 * Works on the tables directly so branch scopes never hide rows of another branch.
 * Call inside the caller's transaction.
 */
class LedgerReversal
{
    /** Tables with debit/credit columns that are swapped in the mirror row. */
    protected const PARTY_LEDGERS = ['customer_ledgers', 'supplier_ledgers', 'doctor_ledgers'];

    /**
     * Reverse every live, not yet reversed row the query matches.
     *
     * @param string $table customer_ledgers | supplier_ledgers | doctor_ledgers | ledgers
     * @param callable $filter fn (Builder $q) adding where clauses (ref_invoice_no, type, id ...)
     * @param string $what short label for the cancelled document, e.g. "Sale INV123"
     * @return int number of rows reversed
     */
    public function reverse(string $table, callable $filter, string $reason, string $what): int
    {
        $query = DB::table($table)
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('type')->orWhere(fn ($q) => $q
                ->where('type', 'not like', '%(Reversed)')
                ->where('type', 'not like', '% Reversal')));
        $filter($query);
        $rows = $query->lockForUpdate()->get();

        $user = auth()->user();
        $note = "Reversal: {$what} cancelled on " . now()->format('d M Y') . ' by ' . ($user->name ?? 'system') . ". Reason: {$reason}";
        $now = now();

        foreach ($rows as $row) {
            $original = (array) $row;
            // Manual company-ledger rows (e.g. expenses) have no type; name them by their kind
            $baseType = $row->type ?: ($row->amount_type ?? 'Entry');

            DB::table($table)->where('id', $row->id)->update([
                'type'       => mb_substr($baseType . ' (Reversed)', 0, 191),
                'updated_at' => $now,
                'updated_by' => $user->id ?? null,
            ]);

            $mirror = $original;
            unset($mirror['id'], $mirror['deleted_at'], $mirror['deleted_by'], $mirror['updated_by'], $mirror['voucher_no']);
            $mirror['type'] = mb_substr($baseType . ' Reversal', 0, 191);
            $mirror['details'] = $note . ($row->details ? " | Original: {$row->details}" : '');
            $mirror['created_at'] = $now;
            $mirror['updated_at'] = $now;
            $mirror['created_by'] = $user->id ?? null;

            if (in_array($table, self::PARTY_LEDGERS, true)) {
                $mirror['debit'] = $row->credit;
                $mirror['credit'] = $row->debit;
            } else {
                $mirror['income_amount'] = -1 * (float) $row->income_amount;
                $mirror['expense_amount'] = -1 * (float) $row->expense_amount;
                $mirror['invoice_no'] = mb_substr(($row->invoice_no ?? '') . '-R', 0, 191);
            }

            DB::table($table)->insert($mirror);
        }

        return $rows->count();
    }

    /**
     * Audit entry for a cancelled document.
     */
    public function log(string $action, $subject, array $values, string $reason): void
    {
        ActivityLog::create([
            'user_id'      => auth()->id(),
            'subject_id'   => is_object($subject) ? $subject->id : $subject,
            'subject_type' => is_object($subject) ? get_class($subject) : null,
            'action'       => $action,
            'new_values'   => array_merge($values, ['reason' => $reason]),
            'ip_address'   => request()->ip() ?? '127.0.0.1',
            'user_agent'   => mb_substr((string) request()->userAgent(), 0, 255),
        ]);
    }

    /**
     * Validate the cancellation reason and the Super Admin rule shared by all money deletes.
     */
    public static function reasonFrom(\Illuminate\Http\Request $request): string
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403, 'Only Super Admin can cancel invoices and payments.');

        $data = $request->validate(
            ['reason' => ['required', 'string', 'min:5', 'max:500']],
            ['reason.required' => 'Please give a reason for cancelling.', 'reason.min' => 'Please give a clearer reason (at least 5 characters).']
        );

        return trim($data['reason']);
    }
}
