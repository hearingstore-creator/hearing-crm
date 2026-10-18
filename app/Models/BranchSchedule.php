<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchSchedule extends Model
{
    protected $fillable = [
        'vendor_id',
        'day_of_week',
        'open_time',
        'close_time',
        'is_closed',
    ];

    protected $casts = [
        'is_closed'   => 'boolean',
        'day_of_week' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public static function isoDayName(int $isoDay): string
    {
        return match ($isoDay) {
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
            default => 'Unknown',
        };
    }
}
