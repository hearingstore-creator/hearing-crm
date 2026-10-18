<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'appointment_id',
        'from_status',
        'to_status',
        'old_date',
        'new_date',
        'old_start_time',
        'new_start_time',
        'old_user_id',
        'new_user_id',
        'reason',
        'changed_by',
        'created_at',
    ];

    protected $casts = [
        'old_date'   => 'date',
        'new_date'   => 'date',
        'created_at' => 'datetime',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
