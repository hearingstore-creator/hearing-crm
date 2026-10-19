<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DashboardTodayService;
use App\Support\DataScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Nightly: compute the heaviest dashboard widgets (patient segments, dormant patients, upgrade and
 * second-ear lists, dead stock, branch stock) once per distinct view, so the first person in the
 * morning does not wait for them. The table it prints doubles as the timing check on live data.
 */
class DashboardSnapshots extends Command
{
    protected $signature = 'crm:dashboard-snapshots';
    protected $description = 'Warm the dashboard snapshots and print how long each heavy widget takes';

    public function handle(): int
    {
        $done = [];
        $rows = [];
        $original = Auth::user();

        User::where('status', 1)->orderBy('id')->get()->each(function (User $user) use (&$done, &$rows) {
            Auth::setUser($user);
            DataScope::reset();

            if (!$user->hasAccess('dashboard.index')) {
                return;
            }
            $service = app(DashboardTodayService::class)->forBranch(null)->forPeriod(null);
            if (!array_intersect(['care', 'branches'], array_keys($service->visibleTabs()))) {
                return;
            }

            $signature = $service->scopeSignature() . '|' . $service->branchKey();
            if (isset($done[$signature])) {
                return;
            }
            $done[$signature] = true;

            foreach ($service->warm() as $key => $ms) {
                $rows[] = [$user->name, $signature, $key, $ms];
            }
        });

        $original ? Auth::setUser($original) : Auth::forgetGuards();
        DataScope::reset();

        $this->table(['Warmed as', 'View', 'Widget', 'ms'], $rows);
        $this->info(count($done) . ' view(s) warmed.');

        return self::SUCCESS;
    }
}
