<?php

namespace App\Console\Commands;

use App\Jobs\SendSmsCampaign;
use App\Models\SmsCampaign;
use Illuminate\Console\Command;

/**
 * Starts SMS campaigns whose chosen send time has come (scheduled in SMS Center → Compose & Send).
 * Runs every minute from routes/console.php. A campaign is claimed with one atomic update, so two runs
 * can never send the same campaign twice.
 */
class SendScheduledSms extends Command
{
    protected $signature = 'sms:send-scheduled';
    protected $description = 'Start scheduled SMS campaigns that are due';

    public function handle(): int
    {
        $started = 0;

        SmsCampaign::where('status', 'scheduled')->where('scheduled_at', '<=', now())->orderBy('scheduled_at')->get()
            ->each(function (SmsCampaign $campaign) use (&$started) {
                $claimed = SmsCampaign::where('id', $campaign->id)->where('status', 'scheduled')->update(['status' => 'sending']);
                if ($claimed !== 1) {
                    return;
                }
                SendSmsCampaign::dispatch($campaign->id, $campaign->created_by);
                $started++;
            });

        $this->info("Scheduled SMS campaigns started: {$started}");

        return self::SUCCESS;
    }
}
