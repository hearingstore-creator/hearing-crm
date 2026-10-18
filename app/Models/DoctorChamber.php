<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorChamber extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_id',
        'chamber_name',
        'address',
        'phone',
        'visiting_days',
        'visiting_time',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
}
