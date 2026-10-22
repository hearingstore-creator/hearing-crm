<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Http\Controllers\Admin\SmsController;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Sequence;
use App\Models\SequenceEnrollment;
use App\Models\SequenceRun;
use App\Models\SequenceStep;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Follow-up sequences: start (by event or by hand), run due steps every few minutes, and stop when the patient
 * replies, books, moves on, buys or is lost. SMS follow the SMS Center rules (mode, daily cap, opt-out, quiet hours);
 * a sequence message never counts as the first contact of a new lead – a person still has to call.
 */
class SequenceService
{
    // ── starting ─────────────────────────────────────────────────────────

    public function start(Sequence $sequence, Deal $deal, ?int $by = null, bool $evenIfInactive = false): ?SequenceEnrollment
    {
        if ((!$sequence->is_active && !$evenIfInactive) || !$sequence->steps()->exists()) {
            return null;
        }
        if ($deal->status !== Deal::OPEN && $sequence->trigger !== 'deal_lost') {
            return null;
        }
        if (SequenceEnrollment::where('sequence_id', $sequence->id)->where('deal_id', $deal->id)->where('status', 'active')->exists()) {
            return null;
        }
        $first = $sequence->steps()->first();

        $enrollment = SequenceEnrollment::create([
            'sequence_id' => $sequence->id, 'deal_id' => $deal->id, 'lead_id' => $deal->lead_id, 'status' => 'active',
            'next_position' => $first->position, 'next_run_at' => now()->addMinutes((int) $first->delay_minutes),
            'start_stage_id' => $deal->stage_id, 'started_by' => $by, 'started_at' => now(),
        ]);
        $this->note($deal, 'Follow-up sequence "' . $sequence->name . '" started' . ($by ? '' : ' automatically'));

        return $enrollment;
    }

    public function onDealCreated(Deal $deal): void
    {
        // a person who comes back ends their "lost – check in later" follow-up
        $this->stopWhere(fn ($q) => $q->where('lead_id', $deal->lead_id)->where('deal_id', '!=', $deal->id)
            ->whereIn('deal_id', Deal::withoutGlobalScopes()->select('id')->where('pipeline_id', $deal->pipeline_id))
            ->whereHas('sequence', fn ($s) => $s->where('trigger', 'deal_lost')), 'New enquiry from the same person');

        $source = $deal->source;
        foreach (Sequence::where('is_active', true)->where('trigger', 'deal_created')->forPipeline($deal->pipeline_id)->get() as $seq) {
            if (!$seq->trigger_sources || in_array($source, $seq->trigger_sources, true)) {
                $this->start($seq, $deal);
            }
        }
    }

    public function onStageEntered(Deal $deal): void
    {
        foreach (Sequence::where('is_active', true)->where('trigger', 'stage_entered')->forPipeline($deal->pipeline_id)->where('trigger_stage_id', $deal->stage_id)->get() as $seq) {
            $this->start($seq, $deal);
        }
    }

    public function onDealLost(Deal $deal): void
    {
        $this->stopForDeal($deal, 'Deal lost', exceptLostSequences: true);
        foreach (Sequence::where('is_active', true)->where('trigger', 'deal_lost')->forPipeline($deal->pipeline_id)->get() as $seq) {
            if (!$seq->trigger_lost_reasons || in_array((int) $deal->lost_reason_id, array_map('intval', $seq->trigger_lost_reasons), true)) {
                $this->start($seq, $deal);
            }
        }
    }

    // ── stopping ─────────────────────────────────────────────────────────

    public function stopForDeal(Deal $deal, string $reason, bool $exceptLostSequences = false): int
    {
        return $this->stopWhere(function ($q) use ($deal, $exceptLostSequences) {
            $q->where('deal_id', $deal->id);
            if ($exceptLostSequences) {
                $q->whereHas('sequence', fn ($s) => $s->where('trigger', '!=', 'deal_lost'));
            }
        }, $reason);
    }

    /** A message came in from this number (WhatsApp / SMS reply). */
    public function stopForPhone(?string $phone, string $reason = 'Replied'): int
    {
        $digits = substr(preg_replace('/\D/', '', (string) $phone), -10);
        if (strlen($digits) < 10) {
            return 0;
        }
        $leadIds = Lead::withoutGlobalScopes()->where('phone_number', 'like', '%' . $digits)->pluck('id');
        if ($leadIds->isEmpty()) {
            return 0;
        }

        return $this->stopWhere(fn ($q) => $q->whereIn('lead_id', $leadIds)->whereHas('sequence', fn ($s) => $s->where('stop_on_reply', true)), $reason);
    }

    public function stopEnrollment(SequenceEnrollment $e, string $reason): void
    {
        if ($e->status !== 'active') {
            return;
        }
        $e->update(['status' => 'stopped', 'stop_reason' => mb_substr($reason, 0, 120), 'ended_at' => now(), 'next_run_at' => null]);
        if ($deal = Deal::withoutGlobalScopes()->find($e->deal_id)) {
            $this->note($deal, 'Follow-up sequence "' . ($e->sequence->name ?? '') . '" stopped: ' . $reason);
        }
    }

    private function stopWhere(callable $filter, string $reason): int
    {
        $list = SequenceEnrollment::with('sequence')->where('status', 'active')->where($filter)->get();
        foreach ($list as $e) {
            $this->stopEnrollment($e, $reason);
        }

        return $list->count();
    }

    // ── running ──────────────────────────────────────────────────────────

    /** Run the steps that are due. Returns how many steps ran (sent, skipped or failed). */
    public function runDue(int $limit = 200): int
    {
        $n = 0;
        $due = SequenceEnrollment::with('sequence')->where('status', 'active')->where('next_run_at', '<=', now())->orderBy('next_run_at')->limit($limit)->get();
        foreach ($due as $e) {
            try {
                $n += (int) $this->runOne($e);
            } catch (\Throwable $ex) {
                report($ex);
                $e->update(['next_run_at' => now()->addMinutes(30)]);
            }
        }

        return $n;
    }

    private function runOne(SequenceEnrollment $e): bool
    {
        $seq = $e->sequence;
        $deal = Deal::withoutGlobalScopes()->whereNull('deleted_at')->find($e->deal_id);
        $lead = $deal ? Lead::withoutGlobalScopes()->find($deal->lead_id) : null;

        if (!$seq || !$deal || !$lead) {
            $this->stopEnrollment($e, 'Deal or person deleted');
            return false;
        }
        if ($reason = $this->stopReason($e, $seq, $deal)) {
            $this->stopEnrollment($e, $reason);
            return false;
        }

        $step = SequenceStep::where('sequence_id', $seq->id)->where('position', '>=', $e->next_position)->orderBy('position')->first();
        if (!$step) {
            $this->finish($e, $deal, $seq);
            return false;
        }

        // Messages wait for the morning; call tasks are fine any time (they show as due)
        if ($step->channel !== 'call' && ($morning = SmsController::quietHoursSendAt())) {
            $e->update(['next_run_at' => $morning]);
            return false;
        }

        [$status, $detail] = $this->execute($step, $seq, $deal, $lead);
        SequenceRun::create(['enrollment_id' => $e->id, 'step_id' => $step->id, 'channel' => $step->channel, 'status' => $status, 'detail' => mb_substr((string) $detail, 0, 500)]);

        $next = SequenceStep::where('sequence_id', $seq->id)->where('position', '>', $step->position)->orderBy('position')->first();
        if ($next) {
            $e->update(['next_position' => $next->position, 'next_run_at' => now()->addMinutes((int) $next->delay_minutes)]);
        } else {
            $this->finish($e, $deal, $seq);
        }

        return true;
    }

    private function stopReason(SequenceEnrollment $e, Sequence $seq, Deal $deal): ?string
    {
        if ($seq->trigger === 'deal_lost') {
            if ($deal->status !== Deal::LOST) {
                return 'Deal reopened';
            }
        } elseif ($deal->status !== Deal::OPEN) {
            return $deal->status === Deal::WON ? 'Deal won' : 'Deal closed';
        }
        if ($seq->stop_on_stage_change && $e->start_stage_id && (int) $deal->stage_id !== (int) $e->start_stage_id) {
            return 'Deal moved to another stage';
        }
        if ($seq->stop_on_appointment && DB::table('appointments')->where('lead_id', $deal->lead_id)->whereNull('deleted_at')->where('created_at', '>=', $e->started_at)->exists()) {
            return 'Appointment booked';
        }

        return null;
    }

    private function finish(SequenceEnrollment $e, Deal $deal, Sequence $seq): void
    {
        $e->update(['status' => 'completed', 'ended_at' => now(), 'next_run_at' => null]);
        $this->note($deal, 'Follow-up sequence "' . $seq->name . '" finished');
    }

    /** @return array{0: string, 1: ?string} [sent|skipped|failed, detail] */
    private function execute(SequenceStep $step, Sequence $seq, Deal $deal, Lead $lead): array
    {
        $vars = [
            'name' => $lead->full_name ?? '',
            'company' => Setting::find(1)?->company_name ?? '',
            'company_phone' => Setting::find(1)?->phone ?? '',
            'owner' => DB::table('users')->where('id', $deal->owner_id)->value('name') ?? '',
        ];
        $text = preg_replace_callback('/\{\{(\w+)\}\}/', fn ($m) => $vars[$m[1]] ?? $m[0], (string) $step->body);

        return match ($step->channel) {
            'sms' => $this->sms($seq, $deal, $lead, $text),
            'email' => $this->email($seq, $deal, $lead, $step, $text, $vars),
            'whatsapp' => $this->whatsapp($seq, $deal, $lead, $step),
            'call' => $this->callTask($seq, $deal, $text),
            default => ['skipped', 'Unknown channel'],
        };
    }

    private function sms(Sequence $seq, Deal $deal, Lead $lead, string $text): array
    {
        $phone = $lead->phone_number;
        if (!$phone) {
            return ['skipped', 'No mobile number'];
        }
        $mode = SmsAutomationService::mode();
        if ($mode !== 'all') {
            return ['skipped', $mode === 'off' ? 'Automatic SMS are paused in the SMS Center' : 'SMS Center is on "Essential only"'];
        }
        if (SmsAutomationService::isOptedOut($phone)) {
            return ['skipped', 'Number opted out of SMS'];
        }
        if (!app(SmsAutomationService::class)->underDailyCap()) {
            return ['skipped', 'Daily automatic SMS limit reached'];
        }

        $before = (int) DB::table('sms_logs')->max('id');
        app(SmsService::class)->sendAndLogSms($phone, $text, 'lead', (int) $lead->id, null, \App\Enums\SmsType::AUTOMATED, 'sequence');
        $status = DB::table('sms_logs')->where('id', '>', $before)->latest('id')->value('status');
        $this->note($deal, 'Sequence "' . $seq->name . '" – SMS ' . ($status === 'sent' ? 'sent' : 'failed') . ': ' . $text);

        return $status === 'sent' ? ['sent', null] : ['failed', 'The SMS gateway did not accept it'];
    }

    private function email(Sequence $seq, Deal $deal, Lead $lead, SequenceStep $step, string $text, array $vars): array
    {
        if (!$lead->email || !filter_var($lead->email, FILTER_VALIDATE_EMAIL)) {
            return ['skipped', 'No email address'];
        }
        $subject = preg_replace_callback('/\{\{(\w+)\}\}/', fn ($m) => $vars[$m[1]] ?? $m[0], (string) ($step->subject ?: $seq->name));
        try {
            Mail::raw($text, fn ($m) => $m->to($lead->email, $lead->full_name)->subject($subject));
        } catch (\Throwable $ex) {
            return ['failed', 'Email not sent: ' . $ex->getMessage()];
        }
        $this->note($deal, 'Sequence "' . $seq->name . '" – email sent: ' . $subject);

        return ['sent', $lead->email];
    }

    /** WhatsApp Cloud API approved template; {{1}} of the body is the person's name. */
    private function whatsapp(Sequence $seq, Deal $deal, Lead $lead, SequenceStep $step): array
    {
        $phoneId = config('connectors.meta.wa_phone_number_id');
        $token = config('connectors.meta.wa_token');
        if (!$phoneId || !$token) {
            return ['skipped', 'WhatsApp sending is not set up (WHATSAPP_PHONE_NUMBER_ID, WHATSAPP_TOKEN)'];
        }
        if (!$step->wa_template) {
            return ['skipped', 'No template name on this step'];
        }
        $to = \App\Classes\Helper::normalizePhone((string) $lead->phone_number);
        if (!$to) {
            return ['skipped', 'No mobile number'];
        }

        $response = Http::withToken($token)->timeout(15)->post('https://graph.facebook.com/' . config('connectors.meta.graph_version') . '/' . $phoneId . '/messages', [
            'messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template',
            'template' => [
                'name' => $step->wa_template, 'language' => ['code' => $step->wa_language ?: 'en'],
                'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $lead->full_name ?: 'there']]]],
            ],
        ]);
        if (!$response->successful()) {
            return ['failed', 'WhatsApp: ' . ($response->json('error.message') ?? ('HTTP ' . $response->status()))];
        }
        $this->note($deal, 'Sequence "' . $seq->name . '" – WhatsApp template "' . $step->wa_template . '" sent');

        return ['sent', (string) ($response->json('messages.0.id') ?? '')];
    }

    private function callTask(Sequence $seq, Deal $deal, string $text): array
    {
        app(DealService::class)->addActivity($deal, ActivityType::CALL, DealService::dueInOfficeHours(0)->toDateTimeString(),
            'Sequence "' . $seq->name . '": ' . ($text ?: 'follow-up call'));

        return ['sent', 'Call task for the owner'];
    }

    private function note(Deal $deal, string $text): void
    {
        DB::table('lead_follow_ups')->insert([
            'lead_id' => $deal->lead_id, 'deal_id' => $deal->id, 'activity_type' => ActivityType::NOTE->value,
            'assigned_user' => $deal->owner_id, 'follow_up_date' => now(), 'follow_up_notes' => mb_substr($text, 0, 1000),
            'status' => 'completed', 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
