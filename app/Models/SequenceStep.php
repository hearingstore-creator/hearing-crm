<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SequenceStep extends Model
{
    protected $fillable = ['sequence_id', 'position', 'delay_minutes', 'channel', 'subject', 'body', 'wa_template', 'wa_language'];

    /** "after 2 days", "after 3 h", "straight away" */
    public function delayLabel(): string
    {
        $m = (int) $this->delay_minutes;

        return match (true) {
            $m === 0 => 'straight away',
            $m % 1440 === 0 => 'after ' . ($m / 1440) . ' day' . ($m === 1440 ? '' : 's'),
            $m % 60 === 0 => 'after ' . ($m / 60) . ' h',
            default => 'after ' . $m . ' min',
        };
    }
}
