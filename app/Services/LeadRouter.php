<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\LeadAssignmentRule;
use App\Models\User;
use App\Notifications\CrmAlert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Picks the owner of a new inbound lead from the assignment rules (Pipeline Settings → Lead assignment).
 * Rules are tried in order; the first whose conditions match and that has someone available wins.
 * People who are deactivated, on approved leave now, or at their open-deal limit are skipped.
 * No matching rule = the old behaviour (branch staff own what they create, else the pipeline's default owner).
 */
class LeadRouter
{
    /** Rules apply to leads that arrive by themselves (no one signed in), and to head-office entries when a rule says so. */
    public function shouldRoute(): bool
    {
        $user = auth()->user();

        return !$user || !$user->branchId();
    }

    /** @return array{0: ?LeadAssignmentRule, 1: ?int} the matching rule and the chosen user id */
    public function pick(Deal $deal, ?Lead $lead, array $excludeUserIds = []): array
    {
        $manual = (bool) auth()->user();
        $source = $deal->source ?: $lead?->source_of_entry;
        $inquiries = (array) ($deal->inquiry_for ?: $lead?->inquiry_for ?: []);
        $branch = (int) ($deal->vendor_id ?? 0);

        foreach (LeadAssignmentRule::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get() as $rule) {
            if ($manual && !$rule->include_manual) {
                continue;
            }
            if ($rule->sources && !in_array($source, $rule->sources, true)) {
                continue;
            }
            if ($rule->inquiries && !array_intersect($inquiries, $rule->inquiries)) {
                continue;
            }
            if ($rule->vendor_ids && !in_array($branch, array_map('intval', $rule->vendor_ids), true)) {
                continue;
            }
            if ($userId = $this->choose($rule, $excludeUserIds)) {
                return [$rule, $userId];
            }
        }

        return [null, null];
    }

    /** Route a deal that was just opened. Returns true when a rule assigned it. */
    public function assign(Deal $deal, ?Lead $lead): bool
    {
        [$rule, $userId] = $this->pick($deal, $lead);
        if (!$rule) {
            return false;
        }

        $this->apply($deal, $rule, $userId, 'Assigned by rule "' . $rule->name . '"');

        return true;
    }

    /** First contact missed: hand the deal to the next person of the same rule. */
    public function reassign(Deal $deal): ?int
    {
        $rule = $deal->assigned_rule_id ? LeadAssignmentRule::find($deal->assigned_rule_id) : null;
        if (!$rule || !$rule->is_active) {
            return null;
        }
        $userId = $this->choose($rule, array_filter([$deal->owner_id]));
        if (!$userId) {
            return null;
        }
        $previous = $deal->owner_id;
        $this->apply($deal, $rule, $userId, 'Not contacted in time – moved on by rule "' . $rule->name . '"');
        // the waiting call goes with the deal
        DB::table('lead_follow_ups')->where('deal_id', $deal->id)->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('assigned_user')->orWhere('assigned_user', $previous))
            ->update(['assigned_user' => $userId, 'updated_at' => now()]);

        return $userId;
    }

    private function apply(Deal $deal, LeadAssignmentRule $rule, int $userId, string $note): void
    {
        $deal->forceFill(array_filter([
            'owner_id' => $userId,
            'assigned_rule_id' => $rule->id,
            'vendor_id' => $rule->set_vendor_id ?: null,
        ], fn ($v) => $v !== null))->save();

        $rule->forceFill(['last_user_id' => $userId, 'assigned_count' => $rule->assigned_count + 1])->save();

        DB::table('lead_follow_ups')->insert([
            'lead_id' => $deal->lead_id, 'deal_id' => $deal->id, 'activity_type' => 'note', 'assigned_user' => $userId,
            'follow_up_date' => now(), 'follow_up_notes' => $note, 'status' => 'completed', 'completed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::afterCommit(function () use ($deal, $userId) {
            try {
                $lead = $deal->lead()->withoutGlobalScopes()->first();
                app(CrmNotifier::class)->send([User::find($userId)], new CrmAlert(
                    'New lead for you',
                    ($lead->full_name ?? 'A new lead') . ($lead?->phone_number ? ' · ' . $lead->phone_number : '') . ' – please make the first contact.',
                    url('admin/deals?deal=' . $deal->id), 'fa-user-check', 'info', 'deal_assigned'
                ));
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    /** The next person for this rule, or null when nobody is available. */
    public function choose(LeadAssignmentRule $rule, array $exclude = []): ?int
    {
        $ids = collect($rule->user_ids ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $available = $this->available($ids->diff($exclude)->values(), $rule->max_open);
        if ($available->isEmpty()) {
            return null;
        }

        return match ($rule->method) {
            'least_busy' => $available->sortBy(fn ($u) => [$u['open'], $ids->search($u['id'])])->first()['id'],
            'fixed' => $available->sortBy(fn ($u) => $ids->search($u['id']))->first()['id'],
            default => $this->nextInTurn($ids, $available->pluck('id'), (int) $rule->last_user_id),
        };
    }

    private function nextInTurn(Collection $order, Collection $available, int $last): int
    {
        $start = $last ? $order->search($last) : false;
        $count = $order->count();
        for ($i = 1; $i <= $count; $i++) {
            $candidate = $order[(($start === false ? -1 : $start) + $i) % $count];
            if ($available->contains($candidate)) {
                return $candidate;
            }
        }

        return $available->first();
    }

    /** @return Collection<int, array{id: int, open: int}> */
    private function available(Collection $ids, ?int $maxOpen): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }
        $active = User::whereIn('id', $ids)->where('status', 1)->pluck('id');
        $onLeave = DB::table('staff_leaves')->whereIn('user_id', $active)->where('status', 'approved')
            ->where('start_datetime', '<=', now())->where('end_datetime', '>=', now())->pluck('user_id');
        $open = DB::table('deals')->whereNull('deleted_at')->where('status', Deal::OPEN)->whereIn('owner_id', $active)
            ->selectRaw('owner_id, COUNT(*) as c')->groupBy('owner_id')->pluck('c', 'owner_id');

        return $active->diff($onLeave)
            ->map(fn ($id) => ['id' => (int) $id, 'open' => (int) ($open[$id] ?? 0)])
            ->filter(fn ($u) => !$maxOpen || $u['open'] < $maxOpen)
            ->values();
    }
}
