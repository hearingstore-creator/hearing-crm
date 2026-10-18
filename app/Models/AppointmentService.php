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
        'duration_minutes',
        'buffer_after_minutes',
        'requires_resource',
        'resource_type',
        'is_online_booking_enabled',
        'color_hex',
        'max_per_day',
        'status',
    ];

    protected $casts = [
        'category'                  => ServiceCategory::class,
        'duration_minutes'          => 'integer',
        'buffer_after_minutes'      => 'integer',
        'requires_resource'         => 'boolean',
        'is_online_booking_enabled' => 'boolean',
        'status'                    => 'boolean',
    ];

    public function serviceType()
    {
        return $this->hasOne(ServiceType::class, 'appointment_service_id');
    }

    public function professionals()
    {
        return $this->belongsToMany(User::class, 'professional_services');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'service_id');
    }
}
