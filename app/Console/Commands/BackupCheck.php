<?php

namespace App\Console\Commands;

use App\Notifications\CrmAlert;
use App\Services\CrmNotifier;
use Illuminate\Console\Command;

/**
 * Tells the owner when the nightly backup failed or has stopped running.
 *
 * deploy/backup-offsite.sh writes a small status file at the end of every run (and on any failure). This reads it.
 * Problems: no status file at all, a run that failed, or a last success older than crm.backup_max_age_hours.
 * Each problem alerts the Super Admins once a day. It also prints the result, so it can be run by hand.
 */
class BackupCheck extends Command
{
    protected $signature = 'crm:backup-check';
    protected $description = 'Alert the owner when the nightly backup failed or stopped running';

    public function handle(CrmNotifier $notifier): int
    {
        $problems = $this->problems();

        if (! $problems) {
            $this->info('Backup OK: ' . $this->describe());

            return self::SUCCESS;
        }

        foreach ($problems as $key => $text) {
            $this->error($text);
            if ($notifier->once("backup-check:{$key}:" . now()->toDateString(), 'backup_check')) {
                $notifier->send($notifier->superAdmins(), new CrmAlert('Backup problem', $text, null, 'fa-database', 'danger', 'backup_alert'));
            }
        }

        return self::FAILURE;
    }

    /** @return array<string, string> key => message for the person */
    public function problems(): array
    {
        $path = (string) config('crm.backup_status_file');
        $raw = is_readable($path) ? @file_get_contents($path) : false;
        if ($raw === false) {
            return ['missing' => "No backup record was found ({$path}). The nightly backup may not be installed or has never run. Check docs/BACKUP_AND_CRON.md."];
        }

        $status = json_decode($raw, true);
        if (! is_array($status) || empty($status['at'])) {
            return ['unreadable' => 'The backup record is damaged. Run the backup by hand and check its output.'];
        }

        if (($status['status'] ?? '') !== 'ok') {
            return ['failed' => 'The last backup failed at ' . $status['at'] . ': ' . ($status['detail'] ?? 'unknown reason') . '. Your data is not being protected until this is fixed.'];
        }

        $age = now()->diffInHours(\Illuminate\Support\Carbon::parse($status['at']), true);
        if ($age > (int) config('crm.backup_max_age_hours', 30)) {
            return ['stale' => 'The last successful backup was ' . $age . ' hours ago (' . $status['at'] . '). The nightly backup has stopped running.'];
        }

        return [];
    }

    private function describe(): string
    {
        $status = json_decode((string) @file_get_contents((string) config('crm.backup_status_file')), true) ?: [];

        return sprintf('last run %s, database %s KB, files %s, env %s', $status['at'] ?? '?', round(($status['db_bytes'] ?? 0) / 1024), $status['files'] ?? '?', $status['env'] ?? '?');
    }
}
