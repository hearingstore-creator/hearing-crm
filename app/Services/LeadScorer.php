<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\Pipeline;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lead score 0–100 for an open deal, with the reasons behind it, so the team calls the most promising people first.
 * Weights: config/lead_scoring.php, changed per pipeline in Pipeline Settings → Lead score.
 */
class LeadScorer
{
    private ?array $weights = null;
    private array $stageOrder = [];   // per pipeline id

    public static function band(?int $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return $score >= (int) config('lead_scoring.hot_from', 70) ? 'hot' : ($score >= (int) config('lead_scoring.warm_from', 40) ? 'warm' : 'cold');
    }

    /** Weights in use: defaults merged with the pipeline's own numbers. @return array<string, array{points: int, label: string}> */
    public function weights(): array
    {
        if ($this->weights !== null) {
            return $this->weights;
        }
        $custom = (array) (Pipeline::default()?->scoring ?? []);
        $weights = config('lead_scoring.weights', []);
        foreach ($weights as $key => $w) {
            if (isset($custom[$key]) && is_numeric($custom[$key])) {
                $weights[$key]['points'] = (int) $custom[$key];
            }
        }

        return $this->weights = $weights;
    }

    /** Score and save. Closed deals keep their last score. */
    public function refresh(Deal $deal): ?int
    {
        if ($deal->status !== Deal::OPEN) {
            return $deal->score;
        }
        [$score, $reasons] = $this->score($deal);
        DB::table('deals')->where('id', $deal->id)->update(['score' => $score, 'score_reasons' => json_encode($reasons), 'scored_at' => now()]);
        $deal->score = $score;
        $deal->score_reasons = $reasons;

        return $score;
    }

    /** @return array{0: int, 1: array<int, array{label: string, points: int}>} */
    public function score(Deal $deal): array
    {
        $w = $this->weights();
        $lead = DB::table('leads')->where('id', $deal->lead_id)->first();
        $reasons = [];
        $add = function (string $key) use (&$reasons, $w) {
            if (isset($w[$key]) && (int) $w[$key]['points'] !== 0) {
                $reasons[] = ['label' => $w[$key]['label'], 'points' => (int) $w[$key]['points']];
            }
        };

        // what they want
        $interest = array_merge((array) ($deal->inquiry_for ?? []), (array) json_decode((string) ($lead->inquiry_for ?? '[]'), true));
        if (array_intersect($interest, ['hearing_aid', 'cochlear_implant'])) {
            $add('interest_device');
        } elseif (in_array('hearing_test', $interest, true)) {
            $add('interest_test');
        } elseif (array_intersect($interest, ['ear_mold', 'repair', 'equipment'])) {
            $add('interest_service');
        }

        // money
        $value = (float) ($deal->value ?: ($lead->approx_budget ?? 0));
        if ($value >= 50000) {
            $add('budget_high');
        } elseif ($value > 0) {
            $add('budget_some');
        }

        // hearing loss
        $loss = (array) json_decode((string) ($lead->hearing_loss ?? '[]'), true);
        $degrees = collect(['right', 'left'])->map(fn ($s) => $loss[$s]['degree'] ?? null)->filter();
        if ($degrees->intersect(['moderate', 'moderately_severe', 'severe', 'profound', 'total_loss'])->isNotEmpty()) {
            $add('loss_moderate');
        } elseif ($degrees->isNotEmpty() || collect($loss)->contains(fn ($v) => is_string($v) && $v !== '')) {
            $add('loss_any');
        }

        // age
        $age = $lead->age ?? ($lead->date_of_birth ? Carbon::parse($lead->date_of_birth)->age : null);
        if ($age && $age >= 55) {
            $add('age_55');
        }

        // how they came
        $source = $deal->source ?: ($lead->source_of_entry ?? null);
        if ($source === 'referral' || !empty($lead->referrer_customer_id) || !empty($lead->ref_by)) {
            $add('source_referral');
        } elseif (in_array($source, ['phone_call', 'campaign_event'], true)) {
            $add('source_direct');
        }

        // bought before
        $customerId = DB::table('customers')->where('lead_id', $deal->lead_id)->value('id');
        if ($customerId && DB::table('sale_masters')->where('customer_id', $customerId)->whereNull('deleted_at')->exists()) {
            $add('returning');
        }

        // how far they got
        $order = $this->stageOrder((int) $deal->pipeline_id);   // stages of other pipelines (deal moved) count as 0
        $reached = max(
            (int) ($order['ids'][$deal->stage_id] ?? 0),
            (int) DB::table('lead_status_histories')->where('deal_id', $deal->id)->whereNotNull('to_stage_id')->pluck('to_stage_id')
                ->map(fn ($id) => $order['ids'][$id] ?? 0)->max()
        );
        if ($order['trial'] && $reached >= $order['trial']) {
            $add('stage_trial');
        } elseif ($order['assessment'] && $reached >= $order['assessment']) {
            $add('stage_assessment');
        }

        // activity
        $contact = ['call', 'visit', 'sms', 'whatsapp', 'email'];
        $lastContact = DB::table('lead_follow_ups')->where('deal_id', $deal->id)->where('status', 'completed')->whereIn('activity_type', $contact)
            ->max(DB::raw('COALESCE(completed_at, updated_at)'));
        if ($lastContact && Carbon::parse($lastContact)->gte(now()->subDays(7))) {
            $add('recent_contact');
        } elseif ((!$lastContact && Carbon::parse($deal->created_at)->lte(now()->subDays(14))) || ($lastContact && Carbon::parse($lastContact)->lt(now()->subDays(14)))) {
            $add('quiet_14');
        }
        if (DB::table('lead_follow_ups')->where('deal_id', $deal->id)->where('status', 'pending')->where('follow_up_date', '<', now()->subHours(2))->exists()) {
            $add('overdue_activity');
        }
        $rotting = DB::table('pipeline_stages')->where('id', $deal->stage_id)->value('rotting_days');
        if ($rotting && $deal->stage_entered_at && Carbon::parse($deal->stage_entered_at)->diffInDays(now()) >= $rotting) {
            $add('stuck');
        }

        $score = (int) config('lead_scoring.base', 20) + array_sum(array_column($reasons, 'points'));

        return [max(0, min(100, $score)), $reasons];
    }

    /** Stage order of one pipeline. Only stages linked to a lead status (default pipeline) give assessment / trial points. */
    private function stageOrder(int $pipelineId): array
    {
        if (isset($this->stageOrder[$pipelineId])) {
            return $this->stageOrder[$pipelineId];
        }
        $stages = DB::table('pipeline_stages')->where('pipeline_id', $pipelineId)->get(['id', 'sort_order', 'status_key']);
        $byKey = $stages->whereNotNull('status_key')->pluck('sort_order', 'status_key');

        return $this->stageOrder[$pipelineId] = [
            'ids' => $stages->pluck('sort_order', 'id')->all(),
            'assessment' => $byKey['counsel'] ?? $byKey['store_appointment'] ?? null,
            'trial' => $byKey['trial'] ?? null,
        ];
    }
}
