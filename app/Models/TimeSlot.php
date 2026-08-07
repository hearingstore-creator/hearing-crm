<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Model;

class TimeSlot extends Model
{
    use HelperTrait;

    protected $fillable = [
        'start_time',
        'end_time',
        'status',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'status' => 'boolean',
    ];

    public function professionals()
    {
        return $this->belongsToMany(User::class, 'professional_time_slots');
    }
}
