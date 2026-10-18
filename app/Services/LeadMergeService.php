<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\DealNote;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

/**
 * Merge two person records (leads) that were created before the duplicate check existed.
 *
 * Everything that belongs to the duplicate moves to the kept person: deals, activities, notes, appointments,
 * stage history, website submissions and SMS history. Empty profile fields on the kept person are filled
 * from the duplicate. The duplicate is soft-deleted, so nothing is lost.
 *
 * Customer records (sales, membership, points, ledger) are never merged: the duplicate may not be a customer.
 */
class LeadMergeService
{
    /** Tables whose lead_id moves to the kept person. */
    private const TABLES = ['deals', 'lead_follow_ups', 'deal_notes', 'appointments', 'lead_status_histories', 'lead_submissions'];

    /** Profile fields copied from the duplicate when the kept person has them empty. */
    private const FILL = ['email', 'age', 'date_of_birth', 'gender', 'address', 'district_id', 'contact_person_name', 'relation',
        'contact_person_phone', 'interest_brand_product', 'hearing_loss', 'approx_budget', 'ref_by', 'source_of_entry', 'lead_type'];

    /** Why these two cannot be merged, or null when they can. */
    public function blocker(Lead $keep, Lead $merge): ?string
    {
        if ($keep->id === $merge->id) {
            return 'Choose two different people.';
        }
        if ($this->customerOf($merge)) {
            return $this->customerOf($keep)
                ? 'Both people are customers. Customer records (sales, membership, points) cannot be merged.'
                : 'The duplicate is a customer. Keep the customer record and merge the other one into it.';
        }

        return null;
    }

    /** Counts of what will move, for the preview. */
    public function summary(Lead $lead): array
    {
        $counts = [];
        foreach (self::TABLES as $table) {
            $q = DB::table($table)->where('lead_id', $lead->id);
            if (in_array($table, ['deals', 'lead_follow_ups', 'deal_notes'], true)) {
                $q->whereNull('deleted_at');
            }
            $counts[$table] = $q->count();
        }
        $counts['sms_logs'] = DB::table('sms_logs')->where('recipient_type', 'lead')->where('recipient_id', $lead->id)->count();

        return $counts;
    }

    public function merge(Lead $keep, Lead $merge): void
    {
        if ($reason = $this->blocker($keep, $merge)) {
            throw new \DomainException($reason);
        }

        DB::transaction(function () use ($keep, $merge) {
            foreach (self::TABLES as $table) {
                DB::table($table)->where('lead_id', $merge->id)->update(['lead_id' => $keep->id]);
            }
            DB::table('sms_logs')->where('recipient_type', 'lead')->where('recipient_id', $merge->id)->update(['recipient_id' => $keep->id]);

            $fill = [];
            foreach (self::FILL as $field) {
                if (blank($keep->{$field}) && filled($merge->{$field})) {
                    $fill[$field] = $merge->{$field};
                }
            }
            $fill['inquiry_for'] = array_values(array_unique(array_merge((array) $keep->inquiry_for, (array) $merge->inquiry_for)));
            if (filled($merge->notes) && $merge->notes !== $keep->notes) {
                $fill['notes'] = trim(($keep->notes ? $keep->notes . "\n" : '') . $merge->notes);
            }
            $keep->forceFill($fill)->saveQuietly();

            DealNote::create([
                'lead_id'    => $keep->id,
                'body'       => "Merged duplicate: {$merge->full_name} · {$merge->phone_number} · {$merge->lead_uid}",
                'is_pinned'  => false,
                'created_by' => auth()->id(),
            ]);

            $merge->delete();

            app(DealService::class)->syncLead($keep->fresh());
        });
    }

    private function customerOf(Lead $lead): ?Customer
    {
        return Customer::withoutGlobalScopes()->where('lead_id', $lead->id)->whereNull('deleted_at')->first();
    }
}
