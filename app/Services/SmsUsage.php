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
