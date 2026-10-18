<?php

use Illuminate\Support\Facades\Schedule;

/*
| Lead automations. On the server one cron entry runs them all:
|   * * * * * cd /path/to/crm && php artisan schedule:run >> /dev/null 2>&1
| Locally: php artisan schedule:work
*/
Schedule::command('crm:automations sla')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('crm:automations digest')->dailyAt('08:00');
Schedule::command('crm:automations reengage')->dailyAt('09:00');
Schedule::command('crm:automations appointments')->dailyAt('09:00')->withoutOverlapping();
Schedule::command('crm:automations warranty')->dailyAt('10:30')->withoutOverlapping();
Schedule::command('crm:automations points')->dailyAt('11:00')->withoutOverlapping();
Schedule::command('appointment:prune-holds')->everyFiveMinutes()->withoutOverlapping();

// Activity log retention: keep the last N months (config crm.activity_log_keep_months, default 24)
Illuminate\Support\Facades\Artisan::command('crm:prune-activity-log', function () {
    $months = (int) config('crm.activity_log_keep_months', 24);
    $cutoff = now()->subMonths($months);
    $deleted = 0;
    do {
        $batch = \Illuminate\Support\Facades\DB::table('activity_logs')->where('created_at', '<', $cutoff)->limit(5000)->delete();
        $deleted += $batch;
    } while ($batch > 0);
    $this->info("Deleted {$deleted} activity log rows older than {$months} months.");
})->purpose('Delete activity log rows older than the retention period');
Schedule::command('crm:prune-activity-log')->monthlyOn(1, '03:00')->withoutOverlapping();
