<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentHold extends Model
{
    protected $fillable = [
        'token',
        'vendor_id',
        'user_id',
        'resource_id',
        'service_id',
        'hold_date',
        'start_time',
        'end_time',
        'expires_at',
        'ip_address',
    ];

    protected $casts = [
        'hold_date'  => 'date',
        'expires_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'resource_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(AppointmentService::class, 'service_id');
    }

    public static function pruneExpired(): int
    {
        return static::where('expires_at', '<', now())->delete();
    }
}
