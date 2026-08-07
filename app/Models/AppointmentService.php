<?php

namespace App\Models;

use App\Traits\HelperTrait;
use App\Enums\ServiceCategory;
use Illuminate\Database\Eloquent\Model;

class AppointmentService extends Model
{
    use HelperTrait;

    protected $fillable = [
        'name',
        'description',
        'category',
        'max_per_day',
        'status',
    ];

    protected $casts = [
        'category' => ServiceCategory::class,
        'status' => 'boolean',
    ];

    public function professionals()
    {
        return $this->belongsToMany(User::class, 'professional_services');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'service_id');
    }
}
