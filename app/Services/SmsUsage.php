<?php

namespace App\Services;

use App\Enums\SmsAutomationTrigger;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * What the SMS Center has sent and what it costs, so a manager can see which automations eat the credits
 * before switching them off. Cost = SMS parts sent x the per-SMS price in Settings (the gateway invoice is the final word).
 */
class SmsUsage
{
    /** SMS parts and cost for each of the last N months (oldest first). */
    public function monthly(int $months = 6): array
    {
        $rate = (float) (Setting::find(1)?->sms_cost_per_sms ?? 0.35);
        $rows = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = now()->startOfMonth()->subMonths($i);
            $q = DB::table('sms_logs')->whereNull('deleted_at')->where('status', 'sent')->whereBetween('created_at', [$start, $start->copy()->endOfMonth()]);
            $messages = (int) (clone $q)->count();
            $parts = (int) (clone $q)->sum('sms_parts');
            $rows[] = ['label' => $start->format('M Y'), 'messages' => $messages, 'parts' => $parts, 'cost' => round($parts * $rate, 2)];
        }

        return $rows;
    }

    /** @return array<int, array{messages: int, parts: int, cost: float}> keyed by campaign id */
    public function campaignCosts(array $campaignIds): array
    {
        if (!$campaignIds) {
            return [];
        }
        $rate = (float) (Setting::find(1)?->sms_cost_per_sms ?? 0.35);

        return DB::table('sms_logs')->whereNull('deleted_at')->where('status', 'sent')->whereIn('sms_campaign_id', $campaignIds)
            ->selectRaw('sms_campaign_id, count(*) as messages, sum(sms_parts) as parts')->groupBy('sms_campaign_id')->get()
            ->mapWithKeys(fn ($r) => [(int) $r->sms_campaign_id => ['messages' => (int) $r->messages, 'parts' => (int) $r->parts, 'cost' => round((int) $r->parts * $rate, 2)]])
            ->all();
    }

    /** Delivery outcome of messages the gateway accepted in the last 30 days (only those we could ask about). */
    public function delivery(): array
    {
        $base = fn () => DB::table('sms_logs')->whereNull('deleted_at')->where('status', 'sent')->whereNotNull('csms_id')->where('created_at', '>=', now()->subDays(30));
        $counts = $base()->selectRaw("coalesce(delivery_status, 'none') as d, count(*) as c")->groupBy('d')->pluck('c', 'd');
        $known = (int) ($counts['delivered'] ?? 0) + (int) ($counts['failed'] ?? 0);

        return [
            'delivered' => (int) ($counts['delivered'] ?? 0),
            'failed'    => (int) ($counts['failed'] ?? 0),
            'pending'   => (int) ($counts['pending'] ?? 0),
            'unchecked' => (int) ($counts['none'] ?? 0) + (int) ($counts['unknown'] ?? 0),
            'rate'      => $known > 0 ? round(((int) ($counts['delivered'] ?? 0)) / $known * 100, 1) : null,
        ];
    }

    public function summary(): array
    {
        $rate = (float) (Setting::find(1)?->sms_cost_per_sms ?? 0.35);
        $monthStart = now()->startOfMonth();

        $base = fn () => DB::table('sms_logs')->whereNull('deleted_at')->where('status', 'sent');
        $parts = fn ($q) => (int) $q->sum('sms_parts');

        $today = $parts($base()->whereDate('created_at', today()));
        $month = $parts($base()->where('created_at', '>=', $monthStart));

        $byTrigger = $base()->where('created_at', '>=', $monthStart)->whereNotNull('automation_trigger')
            ->selectRaw('automation_trigger, count(*) as messages, sum(sms_parts) as parts')
            ->groupBy('automation_trigger')->get()->keyBy('automation_trigger');

        $automations = DB::table('sms_automations')->orderBy('name')->get()->map(function ($a) use ($byTrigger, $rate) {
            $trigger = SmsAutomationTrigger::tryFrom($a->trigger_type);
            $row = $byTrigger->get($a->trigger_type);
            $partsUsed = (int) ($row->parts ?? 0);

            return [
                'id'        => $a->id,
                'name'      => $a->name,
                'trigger'   => $trigger?->label() ?? $a->trigger_type,
                'essential' => (bool) $trigger?->isEssential(),
                'active'    => (bool) $a->is_active,
                'messages'  => (int) ($row->messages ?? 0),
                'parts'     => $partsUsed,
                'cost'      => round($partsUsed * $rate, 2),
            ];
        })->sortByDesc('parts')->values()->all();

        // everything that is not an automation: campaigns and one-off messages
        $other = $base()->where('created_at', '>=', $monthStart)->whereNull('automation_trigger')
            ->selectRaw('type, count(*) as messages, sum(sms_parts) as parts')->groupBy('type')->get()
            ->map(fn ($r) => ['type' => ucfirst((string) $r->type), 'messages' => (int) $r->messages, 'parts' => (int) $r->parts, 'cost' => round((int) $r->parts * $rate, 2)])
            ->all();

        $cap = (int) config('crm.automation_sms_daily_cap');
        $autoToday = (int) DB::table('sms_logs')->whereNull('deleted_at')->where('type', 'automated')->whereDate('created_at', today())->count();

        return [
            'rate'            => $rate,
            'today_parts'     => $today,
            'today_cost'      => round($today * $rate, 2),
            'month_parts'     => $month,
            'month_cost'      => round($month * $rate, 2),
            'automations'     => $automations,
            'other'           => $other,
            'cap'             => $cap,
            'cap_used'        => $autoToday,
            'mode'            => SmsAutomationService::mode(),
        ];
    }
}
