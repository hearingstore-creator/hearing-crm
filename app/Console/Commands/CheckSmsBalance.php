<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Notifications\CrmAlert;
use App\Services\CrmNotifier;
use App\Services\SmsGateway;
use Illuminate\Console\Command;

/**
 * Reads the SMS credit left at the gateway (every 3 hours, routes/console.php) and warns the owner
 * once a day while it is at or below the level set in SMS Center → Automation.
 */
class CheckSmsBalance extends Command
{
    protected $signature = 'sms:check-balance';
    protected $description = 'Check the SMS gateway balance and warn when it is low';

    public function handle(SmsGateway $gateway, CrmNotifier $notifier): int
    {
        $r = $gateway->checkAndRecord();
        if (!$r['ok']) {
            $this->warn('Balance not read: ' . $r['error']);

            return self::SUCCESS;
        }

        $this->info('SMS balance: ' . number_format($r['balance'], 2));

        $threshold = Setting::find(1)?->sms_low_balance;
        if ($threshold !== null && $r['balance'] <= (float) $threshold && $notifier->once('sms-low-balance:' . now()->toDateString(), 'sms_balance')) {
            $notifier->send($notifier->superAdmins(), new CrmAlert(
                'SMS balance is low',
                'The SMS gateway balance is ' . number_format($r['balance'], 2) . ' (warning level ' . number_format((float) $threshold, 2) . '). Recharge to keep SMS going.',
                url('admin/sms#automation-tab'), 'fa-battery-quarter', 'warning', 'sms_balance'
            ));
            $this->warn('Low balance alert sent.');
        }

        return self::SUCCESS;
    }
}
