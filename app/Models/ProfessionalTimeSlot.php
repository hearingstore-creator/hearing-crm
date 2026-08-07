<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProfessionalTimeSlot extends Pivot
{
    protected $table = 'professional_time_slots';

    protected $fillable = [
        'user_id',
        'time_slot_id',
        'day_id',
    ];

    public function timeSlot()
    {
        return $this->belongsTo(TimeSlot::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function day()
    {
        return $this->belongsTo(Day::class);
    }
}
