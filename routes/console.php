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
