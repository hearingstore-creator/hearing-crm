<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\CrmAlert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/** Who to tell, and "do it only once" for scheduled automations. */
class CrmNotifier
{
    /** Active Super Admins (the owner). */
    public function superAdmins(): Collection
    {
        return once(fn () => User::where('status', 1)->get()->filter->isSuperAdmin()->values());
    }

    /** The branch login (Branch Admin) of a branch, if active. */
    public function branchAdmin(?int $vendorId): ?User
    {
        return $vendorId ? User::where('status', 1)->where('type', 'vendor')->find($vendorId) : null;
    }

    /** Send one alert to several users (nulls, duplicates and users who muted this category are skipped). */
    public function send(iterable $users, CrmAlert $alert): void
    {
        $list = collect($users)->filter()->unique('id')
            ->reject(fn (User $u) => in_array($alert->category, (array) $u->notification_mutes, true))->values();
        if ($list->isNotEmpty()) {
            Notification::send($list, $alert);
        }
    }

    /** True the first time a key is seen, false afterwards (idempotent automations). */
    public function once(string $key, string $automation): bool
    {
        return DB::table('automation_logs')->insertOrIgnore(['key' => mb_substr($key, 0, 150), 'automation' => $automation, 'created_at' => now()]) === 1;
    }
}
