<?php

namespace App\Console\Commands;

use App\Models\AppointmentHold;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PruneAppointmentHolds extends Command
{
    protected $signature = 'appointment:prune-holds';
    protected $description = 'Delete expired temporary website appointment holds';

    public function handle(): int
    {
        $now = Carbon::now('Asia/Dhaka');
        $deleted = AppointmentHold::where('expires_at', '<', $now)->delete();

        $this->info("Pruned {$deleted} expired appointment hold(s).");
        return self::SUCCESS;
    }
}
