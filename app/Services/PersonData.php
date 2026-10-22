<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;

/**
 * A person's right to see and erase their data (GDPR-style requests).
 *  - export: everything the CRM holds about one person, as JSON
 *  - erase:  names, contact details, notes and messages are removed; invoices, payments and stock stay
 *            (accounts must keep them) but no longer say who the person was.
 */
class PersonData
{
    public function export(int $leadId): array
    {
        $lead = (array) DB::table('leads')->find($leadId);
        abort_if(!$lead, 404);
        $customer = DB::table('customers')->where('lead_id', $leadId)->first();
        $customerId = $customer->id ?? 0;
        $phone = (string) ($lead['phone_number'] ?? '');
        $digits = substr(preg_replace('/\D/', '', $phone), -10);

        return [
            'exported_at' => now()->toIso8601String(),
            'person' => $lead,
            'customer' => $customer,
            'deals' => DB::table('deals')->where('lead_id', $leadId)->get(['id', 'title', 'status', 'source', 'value', 'created_at', 'won_at', 'lost_at']),
            'activities' => DB::table('lead_follow_ups')->where('lead_id', $leadId)->get(['activity_type', 'follow_up_date', 'follow_up_notes', 'status', 'outcome_notes']),
            'notes' => DB::table('deal_notes')->where('lead_id', $leadId)->get(['body', 'created_at']),
            'appointments' => DB::table('appointments')->where('lead_id', $leadId)->orWhere(fn ($q) => $q->where('customer_id', $customerId)->where('customer_id', '>', 0))
                ->get(['appointment_date', 'status', 'notes', 'created_at']),
            'purchases' => DB::table('sale_masters')->where('customer_id', $customerId)->whereNull('deleted_at')->get(['sale_invoice_no', 'date', 'total_amount', 'paid']),
            'messages' => strlen($digits) === 10 ? DB::table('sms_logs')->where('phone_number', 'like', '%' . $digits)->get(['message', 'status', 'created_at']) : [],
            'web_enquiries' => DB::table('lead_submissions')->where('lead_id', $leadId)->get(['name', 'phone', 'email', 'message', 'utm_source', 'utm_campaign', 'created_at']),
            'surveys' => DB::table('nps_responses')->where('customer_id', $customerId)->get(['score', 'comment', 'answered_at']),
        ];
    }

    public function erase(int $leadId, string $reason): void
    {
        $lead = DB::table('leads')->find($leadId);
        abort_if(!$lead, 404);
        if ($lead->erased_at) {
            return;
        }
        $customerId = DB::table('customers')->where('lead_id', $leadId)->value('id');
        $digits = substr(preg_replace('/\D/', '', (string) $lead->phone_number), -10);

        DB::transaction(function () use ($lead, $leadId, $customerId, $digits, $reason) {
            DB::table('leads')->where('id', $leadId)->update([
                'full_name' => 'Erased person ' . ($lead->lead_uid ?? $leadId), 'phone_number' => 'erased-' . $leadId, 'email' => null,
                'address' => null, 'date_of_birth' => null, 'age' => null, 'contact_person_name' => null, 'contact_person_phone' => null,
                'notes' => null, 'hearing_loss' => null, 'ref_by' => null, 'interest_brand_product' => null, 'loss_reason' => null, 'loss_to_whom' => null,
                'utm_source' => null, 'utm_medium' => null, 'utm_campaign' => null, 'utm_content' => null, 'utm_term' => null,
                'gclid' => null, 'fbclid' => null, 'landing_page' => null, 'referrer_url' => null,
                'marketing_consent' => false, 'consent_at' => null, 'consent_source' => null, 'erased_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('lead_follow_ups')->where('lead_id', $leadId)->update(['follow_up_notes' => '[erased]', 'outcome_notes' => null]);
            DB::table('deal_notes')->where('lead_id', $leadId)->update(['body' => '[erased]']);
            DB::table('deals')->where('lead_id', $leadId)->update(['lost_note' => null, 'gclid' => null, 'fbclid' => null, 'landing_page' => null]);
            DB::table('lead_submissions')->where('lead_id', $leadId)->update(['name' => 'Erased', 'phone' => 'erased', 'normalized_phone' => 'erased-' . $leadId,
                'email' => null, 'message' => null, 'ip' => null, 'user_agent' => null, 'page_url' => null, 'referrer_url' => null]);
            DB::table('appointments')->where('lead_id', $leadId)->update(['notes' => null]);
            if ($customerId) {
                DB::table('appointments')->where('customer_id', $customerId)->update(['notes' => null]);
                DB::table('nps_responses')->where('customer_id', $customerId)->update(['comment' => null]);
            }
            if (strlen($digits) === 10) {
                DB::table('sms_logs')->where('phone_number', 'like', '%' . $digits)->update(['phone_number' => 'erased', 'message' => '[erased]']);
                DB::table('sms_inbound')->where('phone_number', 'like', '%' . $digits)->update(['phone_number' => 'erased', 'message' => '[erased]']);
            }

            ActivityLog::create([
                'user_id' => auth()->id(), 'subject_id' => $leadId, 'subject_type' => \App\Models\Lead::class, 'action' => 'person_erased',
                'new_values' => ['lead_uid' => $lead->lead_uid, 'reason' => $reason],
                'ip_address' => request()->ip() ?? '127.0.0.1', 'user_agent' => mb_substr((string) request()->userAgent(), 0, 255),
            ]);
        });
    }
}
