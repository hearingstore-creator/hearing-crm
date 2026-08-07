<?php

namespace App\Models;

use App\Traits\HelperTrait;
use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HelperTrait;

    protected $fillable = [
        'lead_id',
        'customer_id',
        'user_id',
        'service_id',
        'professional_time_slot_id',
        'appointment_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'status' => AppointmentStatus::class,
    ];

    /**
     * Relations
     */

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function service()
    {
        return $this->belongsTo(AppointmentService::class, 'service_id');
    }

    public function professionalTimeSlot()
    {
        return $this->belongsTo(ProfessionalTimeSlot::class, 'professional_time_slot_id');
    }
}
