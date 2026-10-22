<?php

namespace App\Services;

use App\Classes\Helper;
use App\Enums\ActivityType;
use App\Enums\LeadStatus;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\DealTransfer;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\LeadStatusHistory;
use App\Models\LostReason;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\SaleMaster;
use Illuminate\Support\Facades\DB;

/**
 * All deal (opportunity) state changes go through here, so stage history, the customer conversion
 * and the backward-compatible mirror to leads.lead_status / leads.vendor_id always stay consistent.
 */
class DealService
{
    /** Open the first deal for a newly created lead (lead form, import, later website). */
    public function openInitialDeal(Lead $lead, array $attributes = []): Deal
    {
        $pipeline = Pipeline::default();
        [$stage, $status] = $this->stageForStatus($pipeline, $lead->lead_status);

        $deal = Deal::create(array_merge([
            'lead_id'          => $lead->id,
            'pipeline_id'      => $pipeline->id,
            'stage_id'         => $stage->id,
            'status'           => $status,
            'vendor_id'        => $lead->vendor_id,
            'owner_id'         => $this->defaultOwnerId($pipeline),
            'inquiry_for'      => $lead->inquiry_for,
            'value'            => $lead->approx_budget,
            'temperature'      => $lead->lead_temperature,
            'source'           => $lead->source_of_entry,
            'campaign_id'      => $lead->campaign_id,
            'position'         => 0,
            'stage_entered_at' => now(),
            'won_at'           => $status === Deal::WON ? now() : null,
            'lost_at'          => $status === Deal::LOST ? now() : null,
        ], $attributes));

        $this->history($deal, null, $stage, null, $this->statusKey($deal, $stage), 'Deal created');
        if ($status === Deal::WON) {
            $this->ensureCustomer($lead);
        }
        $this->route($deal, $lead, $attributes);
        $this->syncLead($lead);
        $this->rescore($deal);
        $this->sequences(fn (SequenceService $s) => $s->onDealCreated($deal));

        return $deal;
    }

    /** New opportunity for an existing person (returning customer, second ear, repair...). */
    public function openNewDeal(Lead $lead, array $attributes = []): Deal
    {
        $pipeline = Pipeline::default();
        $stage = $pipeline->activeStages()->first();

        return DB::transaction(function () use ($lead, $pipeline, $stage, $attributes) {
            $deal = Deal::create(array_merge([
                'lead_id'          => $lead->id,
                'pipeline_id'      => $pipeline->id,
                'stage_id'         => $stage->id,
                'status'           => Deal::OPEN,
                'vendor_id'        => auth()->user()?->branchId() ?? $lead->vendor_id,
                'owner_id'         => $this->defaultOwnerId($pipeline),
                'temperature'      => $lead->lead_temperature,
                'position'         => 0,
                'stage_entered_at' => now(),
            ], $attributes));

            $this->history($deal, null, $stage, null, $stage->status_key, 'New deal opened');
            $this->route($deal, $lead, $attributes);
            $this->syncLead($lead);
            $this->rescore($deal);
            $this->sequences(fn (SequenceService $s) => $s->onDealCreated($deal));

            return $deal;
        });
    }

    /**
     * Move an open deal to another stage. $gate holds the stage-gate answers (already validated).
     */
    public function move(Deal $deal, PipelineStage $to, array $gate = [], ?string $note = null): void
    {
        DB::transaction(function () use ($deal, $to, $gate, $note) {
            $from = $deal->stage;
            $notes = array_filter([$note, $gate['note'] ?? null]);

            if (!empty($gate['inquiry_for'])) {
                $deal->inquiry_for = array_values((array) $gate['inquiry_for']);
            }
            if (isset($gate['value']) && $gate['value'] !== '') {
                $deal->value = $gate['value'];
            }
            if (!empty($gate['walk_in'])) {
                $notes[] = 'Walk-in';
            }
            if (!empty($gate['appointment_at'])) {
                $notes[] = 'Appointment: ' . date('d M Y h:i A', strtotime($gate['appointment_at']));
                $this->addActivity($deal, ActivityType::VISIT, $gate['appointment_at'], 'Assessment appointment');
            }
            if (!empty($gate['trial_product']) || !empty($gate['trial_product_id'])) {
                $trial = app(TrialService::class)->recordInSession($deal, isset($gate['trial_product_id']) ? (int) $gate['trial_product_id'] : null,
                    $gate['trial_product'] ?? null, $gate['trial_result'] ?? null, $gate['note'] ?? null);
                $notes[] = 'Trial product: ' . $trial->product_label . ($trial->result ? ' – ' . \App\Models\DealTrial::RESULTS[$trial->result] : '');
            }
            if (!empty($gate['trial_return_date'])) {
                $notes[] = 'Trial return: ' . $gate['trial_return_date'];
                $this->addActivity($deal, ActivityType::CALL, $gate['trial_return_date'] . ' 11:00:00',
                    'Trial return' . (!empty($gate['trial_product']) ? ' – ' . $gate['trial_product'] : ''));
            }
            // Trial check-in calls (+3 and +7 days), before the return date
            if ($from?->id !== $to->id && $to->status_key === LeadStatus::TRIAL->value) {
                $returnAt = !empty($gate['trial_return_date']) ? strtotime($gate['trial_return_date']) : null;
                foreach ([3, 7] as $days) {
                    $at = now()->addDays($days)->setTime(11, 0);
                    if (!$returnAt || $at->timestamp < $returnAt) {
                        $this->addActivity($deal, ActivityType::CALL, $at->toDateTimeString(), "Trial check-in (day $days): comfort, sound, battery");
                    }
                }
            }

            $stageChanged = $from?->id !== $to->id;
            $deal->stage_id = $to->id;
            if ($stageChanged) {
                $deal->stage_entered_at = now();
            }
            $deal->save();

            if ($stageChanged || $notes) {
                $this->history($deal, $from, $to, $from?->status_key, $to->status_key, implode(' · ', $notes) ?: 'Moved to ' . $to->name);
            }
            $this->syncLead($deal->lead);
        });
        $this->rescore($deal->fresh() ?? $deal);
        $this->sequences(fn (SequenceService $s) => $s->onStageEntered($deal->fresh() ?? $deal));
    }

    public function markWon(Deal $deal, ?int $saleMasterId = null, ?string $note = null): Customer
    {
        return DB::transaction(function () use ($deal, $saleMasterId, $note) {
            $deal->fill([
                'status' => Deal::WON, 'won_at' => now(), 'sale_master_id' => $saleMasterId,
                'lost_at' => null, 'lost_reason_id' => null, 'lost_to' => null, 'lost_note' => null,
            ])->save();

            $this->history($deal, $deal->stage, $deal->stage, $deal->stage?->status_key, LeadStatus::WON->value, $note ?: 'Deal won');
            $customer = $this->ensureCustomer($deal->lead);
            $this->syncLead($deal->lead);
            $this->sequences(fn (SequenceService $s) => $s->stopForDeal($deal, 'Deal won'));

            return $customer;
        });
    }

    public function markLost(Deal $deal, int $reasonId, ?string $lostTo = null, ?string $note = null): void
    {
        DB::transaction(function () use ($deal, $reasonId, $lostTo, $note) {
            $deal->fill([
                'status' => Deal::LOST, 'lost_at' => now(), 'lost_reason_id' => $reasonId,
                'lost_to' => $lostTo, 'lost_note' => $note, 'won_at' => null,
            ])->save();

            $reason = LostReason::find($reasonId)?->name;
            $this->history($deal, $deal->stage, $deal->stage, $deal->stage?->status_key, LeadStatus::LOST->value,
                trim('Lost: ' . $reason . ($lostTo ? " (to $lostTo)" : '') . ($note ? " – $note" : '')));

            // Keep the old lead fields filled for existing reports
            $deal->lead->forceFill([
                'loss_reason'  => trim($reason . ($note ? ": $note" : '')),
                'loss_to_whom' => $lostTo,
            ])->saveQuietly();
            $this->syncLead($deal->lead);
            $this->sequences(fn (SequenceService $s) => $s->onDealLost($deal));
        });
    }

    public function reopen(Deal $deal): void
    {
        DB::transaction(function () use ($deal) {
            $previous = $deal->status;
            $deal->fill([
                'status' => Deal::OPEN, 'won_at' => null, 'lost_at' => null, 'lost_reason_id' => null,
                'lost_to' => null, 'lost_note' => null, 'stage_entered_at' => now(),
            ])->save();

            $this->history($deal, $deal->stage, $deal->stage, $previous, $deal->stage?->status_key, 'Deal reopened');
            $this->syncLead($deal->lead);
        });
    }

    /** HQ → branch (or back). HQ keeps visibility; source / campaign attribution stays on the deal. */
    public function transfer(Deal $deal, ?int $vendorId, ?int $ownerId, ?string $note = null): void
    {
        DB::transaction(function () use ($deal, $vendorId, $ownerId, $note) {
            DealTransfer::create([
                'deal_id' => $deal->id, 'from_vendor_id' => $deal->vendor_id, 'to_vendor_id' => $vendorId,
                'from_owner_id' => $deal->owner_id, 'to_owner_id' => $ownerId, 'note' => $note,
                'transferred_by' => auth()->id(),
            ]);

            $deal->fill(['vendor_id' => $vendorId, 'owner_id' => $ownerId])->save();
            $this->syncLead($deal->lead);

            // Tell the branch and the new owner
            $notifier = app(CrmNotifier::class);
            $notifier->send([$notifier->branchAdmin($vendorId), $ownerId ? \App\Models\User::find($ownerId) : null], new \App\Notifications\CrmAlert(
                'Deal transferred to you',
                ($deal->lead->full_name ?? 'A deal') . ' · ' . $deal->display_title . ($note ? ' – ' . $note : ''),
                url('admin/deals?deal=' . $deal->id),
                'fa-share-square', 'info', 'deal_assigned',
            ));
        });
    }

    /** New owner, same branch. Logged like a transfer (timeline + audit); only the new owner is told. */
    public function reassign(Deal $deal, int $ownerId, ?string $note = null): void
    {
        DB::transaction(function () use ($deal, $ownerId, $note) {
            DealTransfer::create([
                'deal_id' => $deal->id, 'from_vendor_id' => $deal->vendor_id, 'to_vendor_id' => $deal->vendor_id,
                'from_owner_id' => $deal->owner_id, 'to_owner_id' => $ownerId, 'note' => $note,
                'transferred_by' => auth()->id(),
            ]);
            $deal->fill(['owner_id' => $ownerId])->save();

            app(CrmNotifier::class)->send([\App\Models\User::find($ownerId)], new \App\Notifications\CrmAlert(
                'Deal assigned to you',
                ($deal->lead->full_name ?? 'A deal') . ' · ' . $deal->display_title . ($note ? ' – ' . $note : ''),
                url('admin/deals?deal=' . $deal->id),
                'fa-user-check', 'info', 'deal_assigned',
            ));
        });
    }

    /**
     * Keep the legacy lead edit form working: its status / branch fields drive the person's open deal.
     */
    public function syncFromLeadForm(Lead $lead, ?string $oldStatus, ?int $oldVendorId): void
    {
        $deal = $lead->deals()->open()->first();
        $statusChanged = $oldStatus !== $lead->lead_status;

        if (!$deal) {
            if ($statusChanged && !in_array($lead->lead_status, [LeadStatus::WON->value, LeadStatus::LOST->value], true)) {
                $this->openNewDeal($lead, ['inquiry_for' => $lead->inquiry_for, 'value' => $lead->approx_budget]);
            }
            return;
        }

        if ((int) $oldVendorId !== (int) $lead->vendor_id) {
            $this->transfer($deal, $lead->vendor_id, $lead->vendor_id ? null : $deal->owner_id, 'Branch changed from lead form');
            $deal->refresh();
        }

        if (!$statusChanged) {
            return;
        }

        match ($lead->lead_status) {
            LeadStatus::WON->value  => $this->markWon($deal, null, 'Marked won from lead form'),
            LeadStatus::LOST->value => $this->markLost($deal, (int) LostReason::where('name', 'Other')->value('id'), $lead->loss_to_whom, $lead->loss_reason),
            default                 => $this->move($deal, $this->stageForStatus($deal->pipeline, $lead->lead_status)[0], [], 'Status changed from lead form'),
        };
    }

    /** Mirror the person's current deal to leads.lead_status / leads.vendor_id (dashboards, SMS filters). */
    public function syncLead(Lead $lead): void
    {
        $deals = Deal::withoutGlobalScopes()->with('stage')->where('lead_id', $lead->id)->whereNull('deleted_at')->latest('updated_at')->get();
        $open = $deals->firstWhere('status', Deal::OPEN);
        $current = $open ?? $deals->first();
        if (!$current) {
            return;
        }

        $status = $open ? $open->stage?->status_key : $current->status;
        $changes = [];
        if ($status && LeadStatus::tryFrom($status) && $lead->lead_status !== $status) {
            $changes['lead_status'] = $status;
        }
        if ($open && (int) $lead->vendor_id !== (int) $open->vendor_id) {
            $changes['vendor_id'] = $open->vendor_id;
        }
        if ($changes) {
            $lead->forceFill($changes)->saveQuietly();
        }
    }

    /** @return array{0: PipelineStage, 1: string} stage + deal status for a legacy lead_status value */
    public function stageForStatus(Pipeline $pipeline, ?string $leadStatus): array
    {
        $stages = $pipeline->activeStages()->get();
        $byKey = fn (string $key) => $stages->firstWhere('status_key', $key);

        return match ($leadStatus) {
            LeadStatus::WON->value  => [$stages->last(), Deal::WON],
            LeadStatus::LOST->value => [$stages->first(), Deal::LOST],
            LeadStatus::VERIFIED->value, LeadStatus::TELE_COUNSEL_APPOINTMENT->value => [$stages->first(), Deal::OPEN],
            LeadStatus::STORE_APPOINTMENT->value => [$byKey(LeadStatus::TELE_COUNSEL->value) ?? $stages->first(), Deal::OPEN],
            default => [($leadStatus ? $byKey($leadStatus) : null) ?? $stages->first(), Deal::OPEN],
        };
    }

    /** Default owner: branch users own what they create; otherwise the pipeline's default owner (HQ). */
    private function defaultOwnerId(Pipeline $pipeline): ?int
    {
        $user = auth()->user();
        if ($user && $user->branchId()) {
            return $user->id;
        }

        return $pipeline->default_owner_id ?? $user?->id;
    }

    private function ensureCustomer(Lead $lead): Customer
    {
        // Bypass the branch scope so an existing customer is always found (never duplicated)
        return Customer::withoutGlobalScopes()->firstOrCreate(
            ['lead_id' => $lead->id],
            ['referral_code' => Helper::generateReferralCode($lead->full_name ?? 'Customer')]
        );
    }

    /**
     * Called when an invoice is created: attach it to the customer's most recent won deal that has no
     * invoice yet (won within 30 days). Sales logic itself is untouched; this only fills deals.sale_master_id.
     */
    public function linkSale(SaleMaster $sale): void
    {
        $leadId = Customer::withoutGlobalScopes()->whereKey($sale->customer_id)->value('lead_id');
        if (!$leadId) {
            return;
        }

        Deal::withoutGlobalScopes()
            ->where('lead_id', $leadId)
            ->where('status', Deal::WON)
            ->whereNull('sale_master_id')
            ->whereNull('deleted_at')
            ->where('won_at', '>=', now()->subDays(30))
            ->latest('won_at')
            ->limit(1)
            ->update(['sale_master_id' => $sale->id]);
    }

    /**
     * "Call within N minutes", counted only inside office hours on working days (Pipeline Settings).
     * Inside hours: now + N (if that passes closing, it rolls to the next working day's opening + N).
     * Before opening: opening + N. After closing, on a weekly or public holiday: next working day's opening + N.
     */
    public static function dueInOfficeHours(int $minutes, ?\Carbon\Carbon $from = null): \Carbon\Carbon
    {
        $pipeline = Pipeline::default();
        $weekly = array_map('intval', $pipeline->weekly_holidays ?? []);
        $now = ($from ?? now())->copy();
        $public = \App\Models\OfficeHoliday::whereBetween('date', [$now->toDateString(), $now->copy()->addDays(60)->toDateString()])
            ->pluck('date')->map(fn ($d) => $d->toDateString())->all();
        $closed = fn ($day) => in_array($day->dayOfWeekIso, $weekly, true) || in_array($day->toDateString(), $public, true);
        $openAt = fn ($day) => $day->copy()->setTimeFromTimeString($pipeline->office_open ?: '10:00');
        $closeAt = $now->copy()->setTimeFromTimeString($pipeline->office_close ?: '21:00');

        if (!$closed($now) && $now->gte($openAt($now))
            && $now->copy()->addMinutes($minutes)->lte($closeAt)) {
            return $now->addMinutes($minutes);
        }

        // Next opening: today if before opening on a working day, otherwise the next working day
        $day = $now->lt($openAt($now)) ? $now->copy() : $now->copy()->addDay();
        for ($i = 0; $i < 60 && $closed($day); $i++) {
            $day->addDay();
        }

        return $openAt($day)->addMinutes($minutes);
    }

    /** Warning text when a chosen time is on a holiday or outside office hours (saving is still allowed). */
    public static function offHoursWarning(\Carbon\Carbon $at): ?string
    {
        $pipeline = Pipeline::default();
        $holiday = \App\Models\OfficeHoliday::whereDate('date', $at->toDateString())->first();
        if ($holiday) {
            return 'Note: ' . $at->format('D d M') . ' is a public holiday' . (!empty($holiday->name) ? ' (' . $holiday->name . ')' : '') . '.';
        }
        if (in_array($at->dayOfWeekIso, array_map('intval', $pipeline->weekly_holidays ?? []), true)) {
            return 'Note: ' . $at->format('l') . ' is the weekly holiday.';
        }
        $t = $at->format('H:i');
        if ($t < substr($pipeline->office_open ?: '10:00', 0, 5) || $t > substr($pipeline->office_close ?: '21:00', 0, 5)) {
            return 'Note: this is outside office hours.';
        }

        return null;
    }

    /** Schedule an activity, or log it as already done ($done) – notes are always logged as done. */
    public function addActivity(Deal $deal, ActivityType $type, ?string $when, ?string $note, ?int $assignee = null, bool $done = false): LeadFollowUp
    {
        $done = $done || $type === ActivityType::NOTE;
        if ($done) {
            $this->contacted($deal, $type->value);
        }

        $activity = LeadFollowUp::create([
            'lead_id'         => $deal->lead_id,
            'deal_id'         => $deal->id,
            'activity_type'   => $type->value,
            'assigned_user'   => $assignee ?? $deal->owner_id,
            'follow_up_date'  => $when ? date('Y-m-d H:i:s', strtotime($when)) : now(),
            'follow_up_notes' => $note,
            'status'          => $done ? 'completed' : 'pending',
            'completed_at'    => $done ? now() : null,
        ]);
        $this->rescore($deal);

        return $activity;
    }

    public function completeActivity(LeadFollowUp $activity, ?string $outcome = null): void
    {
        $activity->update(['status' => 'completed', 'completed_at' => now(), 'outcome_notes' => $outcome]);
        if ($deal = Deal::withoutGlobalScopes()->find($activity->deal_id)) {
            $this->contacted($deal, (string) ($activity->activity_type instanceof ActivityType ? $activity->activity_type->value : $activity->activity_type));
            $this->rescore($deal);
        }
    }

    /** A call, message or visit was done: the first-contact clock of an inbound lead stops. */
    private function contacted(Deal $deal, string $type): void
    {
        if (in_array($type, ['call', 'visit', 'sms', 'whatsapp', 'email'], true) && !$deal->first_contact_at) {
            DB::table('deals')->where('id', $deal->id)->whereNull('first_contact_at')->update(['first_contact_at' => now()]);
            $deal->first_contact_at = now();
        }
    }

    /** Assignment rules decide the owner of a new deal, unless the caller chose one. */
    private function route(Deal $deal, Lead $lead, array $attributes): void
    {
        if (array_key_exists('owner_id', $attributes)) {
            return;
        }
        $router = app(LeadRouter::class);
        if ($router->shouldRoute()) {
            $router->assign($deal, $lead);
        }
    }

    /** Follow-up sequences react to deal events; a sequence problem never blocks the work. */
    private function sequences(callable $fn): void
    {
        try {
            $fn(app(SequenceService::class));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Keep the lead score current; a scoring problem never blocks the work. */
    public function rescore(Deal $deal): void
    {
        try {
            app(LeadScorer::class)->refresh($deal);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * One-tap call result: completes the activity and plans the next step.
     * Returns [message, suggestLost, threeMissed] – suggestLost after "not interested", "wrong number" or 3 unanswered calls in a row.
     */
    public function recordOutcome(LeadFollowUp $activity, \App\Enums\CallOutcome $result, ?string $note = null, ?string $callbackAt = null): array
    {
        $activity->update([
            'status'        => 'completed',
            'completed_at'  => now(),
            'outcome'       => $result->value,
            'outcome_notes' => trim($result->label() . ($note ? ' – ' . $note : '')),
        ]);

        $deal = Deal::withoutGlobalScopes()->findOrFail($activity->deal_id);
        $this->contacted($deal, 'call');
        $message = $result->label() . ' logged.';
        $next = null;

        if ($result === \App\Enums\CallOutcome::CALL_BACK) {
            $next = $callbackAt ? \Carbon\Carbon::parse($callbackAt) : self::dueInOfficeHours(60, now()->addDay()->startOfDay());
        } elseif ($minutes = $result->retryMinutes()) {
            $next = self::dueInOfficeHours($minutes);
        }

        if ($next && $deal->status === Deal::OPEN) {
            $this->addActivity($deal, ActivityType::CALL, $next->toDateTimeString(),
                $result === \App\Enums\CallOutcome::INTERESTED ? 'Follow up: interested' : $result->label() . ' – call again',
                $activity->assigned_user);
            $message .= ' Next call ' . ($next->isToday() ? 'today ' . $next->format('h:i A') : $next->format('D d M, h:i A')) . '.';
            if ($callbackAt && ($warn = self::offHoursWarning($next))) {
                $message .= ' ' . $warn;
            }
        }

        // Three unanswered calls in a row (latest results only)
        $recent = LeadFollowUp::where('deal_id', $deal->id)->whereNotNull('outcome')
            ->latest('completed_at')->latest('id')->limit(3)->pluck('outcome');
        $threeMissed = $recent->count() === 3 && $recent->every(fn ($o) => $o === \App\Enums\CallOutcome::NO_ANSWER->value);

        return [$message, $result->suggestsLost() || $threeMissed, $threeMissed];
    }

    private function statusKey(Deal $deal, PipelineStage $stage): ?string
    {
        return $deal->status === Deal::OPEN ? $stage->status_key : $deal->status;
    }

    private function history(Deal $deal, ?PipelineStage $from, ?PipelineStage $to, ?string $fromStatus, ?string $toStatus, ?string $notes): void
    {
        $valid = fn (?string $s) => ($s && LeadStatus::tryFrom($s)) ? $s : null;

        LeadStatusHistory::create([
            'lead_id'       => $deal->lead_id,
            'deal_id'       => $deal->id,
            'from_status'   => $valid($fromStatus),
            'to_status'     => $valid($toStatus) ?? LeadStatus::NEW->value,
            'from_stage_id' => $from?->id,
            'to_stage_id'   => $to?->id,
            'notes'         => $notes,
        ]);
    }
}
