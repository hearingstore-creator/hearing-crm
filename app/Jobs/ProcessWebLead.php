<?php

namespace App\Jobs;

use App\Enums\ActivityType;
use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Enums\SourceOfEntry;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\LeadSubmission;
use App\Services\DealService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Turns a website submission into Person + Deal (stage "New", default owner) with a "call within SLA" activity.
 * Same phone already in the CRM: never a second person –
 *   - an open deal exists  → the enquiry is added to it (note + call), submission marked "duplicate";
 *   - no open deal         → a new deal is opened for that person.
 */
class ProcessWebLead implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public array $backoff = [10, 60];

    public function __construct(public int $submissionId)
    {
    }

    public function handle(DealService $deals): void
    {
        DB::transaction(function () use ($deals) {
            $s = LeadSubmission::with('form')->lockForUpdate()->find($this->submissionId);
            if (!$s || $s->status !== LeadSubmission::PENDING) {
                return;
            }

            $form = $s->form;
            $sla = $form->sla_minutes ?? 15;
            $inquiry = $s->inquiry_for ?: ($form->default_inquiry ?? null);
            $vendorId = $form->vendor_id ?? null;
            $dealAttributes = array_filter([
                'source'       => SourceOfEntry::WEBSITE->value,
                'utm_source'   => $s->utm_source,
                'utm_medium'   => $s->utm_medium,
                'utm_campaign' => $s->utm_campaign,
                'inquiry_for'  => $inquiry,
            ], fn ($v) => $v !== null && $v !== []);

            $lead = Lead::withoutGlobalScopes()->whereNull('deleted_at')
                ->where('normalized_phone', $s->normalized_phone)->oldest('id')->first();
            $status = LeadSubmission::PROCESSED;

            if ($lead) {
                $deal = Deal::withoutGlobalScopes()->whereNull('deleted_at')->open()
                    ->where('lead_id', $lead->id)->latest('id')->first();
                if ($deal) {
                    $status = LeadSubmission::DUPLICATE;
                    $deals->addActivity($deal, ActivityType::NOTE, null, $this->noteText($s, 'Website enquiry again'));
                } else {
                    $deal = $deals->openNewDeal($lead, $dealAttributes + ['vendor_id' => $vendorId ?? $lead->vendor_id]);
                    $deals->addActivity($deal, ActivityType::NOTE, null, $this->noteText($s, 'Website enquiry'));
                }
            } else {
                $lead = Lead::create([
                    'lead_uid'        => Lead::generateUid(),
                    'full_name'       => $s->name,
                    'phone_number'    => $s->phone,
                    'email'           => $this->freeEmail($s->email),
                    'vendor_id'       => $vendorId,
                    'inquiry_for'     => $inquiry,
                    'notes'           => $s->message,
                    'source_of_entry' => SourceOfEntry::WEBSITE->value,
                    'lead_status'     => LeadStatus::NEW->value,
                    'lead_type'       => LeadType::NEW_CUSTOMER->value,
                ]);
                $deal = $deals->openInitialDeal($lead, $dealAttributes);
                app(\App\Services\SmsAutomationService::class)->fireAfterCommit(\App\Enums\SmsAutomationTrigger::LEAD_CREATED,
                    $lead->phone_number, 'lead', $lead->id, ['name' => $lead->full_name, 'customer_name' => $lead->full_name], 'lead-welcome:' . $lead->id);
            }

            $deals->addActivity($deal, ActivityType::CALL, DealService::dueInOfficeHours($sla)->toDateTimeString(),
                "Website lead – call within {$sla} min" . ($s->page_url ? ' (' . $s->page_url . ')' : ''));

            $s->update(['status' => $status, 'lead_id' => $lead->id, 'deal_id' => $deal->id, 'error' => null, 'processed_at' => now()]);
        });
    }

    public function failed(Throwable $e): void
    {
        LeadSubmission::whereKey($this->submissionId)->update(['status' => LeadSubmission::FAILED, 'error' => mb_substr($e->getMessage(), 0, 1000)]);
    }

    private function noteText(LeadSubmission $s, string $title): string
    {
        return trim($title . ($s->message ? ': ' . $s->message : '') . ($s->utm_campaign ? ' [campaign: ' . $s->utm_campaign . ']' : ''));
    }

    /** Lead emails are unique in the lead form; skip an email that already belongs to another person. */
    private function freeEmail(?string $email): ?string
    {
        if (!$email) {
            return null;
        }

        return Lead::withoutGlobalScopes()->whereNull('deleted_at')->where('email', $email)->exists() ? null : $email;
    }
}
