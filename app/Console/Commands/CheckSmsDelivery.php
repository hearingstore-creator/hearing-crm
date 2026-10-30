<?php

namespace App\Console\Commands;

use App\Services\SmsGateway;
use Illuminate\Console\Command;

/**
 * Asks the SMS gateway whether recent messages were delivered (hourly, routes/console.php).
 * Messages are checked for up to 3 days; each is asked about at most every 30 minutes.
 */
class CheckSmsDelivery extends Command
{
    protected $signature = 'sms:check-delivery {--limit=200 : most messages to ask about in one run}';
    protected $description = 'Update the delivery status of recent SMS from the gateway';

    public function handle(SmsGateway $gateway): int
    {
        $r = $gateway->refreshDeliveries((int) $this->option('limit'));
        $this->info("Checked {$r['checked']}: {$r['delivered']} delivered, {$r['failed']} failed, {$r['pending']} pending, {$r['unknown']} unclear.");
        if ($r['error']) {
            $this->warn('Stopped: ' . $r['error']);
        }

        return self::SUCCESS;
    }
}
