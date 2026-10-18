<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffScheduleOverride extends Model
{
    protected $fillable = [
        'user_id',
        'vendor_id',
        'override_date',
        'is_day_off',
        'start_time',
        'end_time',
        'notes',
    ];

    protected $casts = [
        'override_date' => 'date',
        'is_day_off'    => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }
}
