<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceType extends Model
{
    use HasFactory, HelperTrait;

    public const BOOKING_WALK_IN = 'walk_in';
    public const BOOKING_APPOINTMENT_REQUIRED = 'appointment_required';
    public const BOOKING_EITHER = 'either';

    protected $fillable = [
        'service_type_name',
        'booking_type',
        'appointment_service_id',
        'price',
        'status',
    ];

    public static function bookingTypeOptions(): array
    {
        return [
            self::BOOKING_EITHER                => 'Either',
            self::BOOKING_WALK_IN               => 'Walk-in',
            self::BOOKING_APPOINTMENT_REQUIRED  => 'Appointment required',
        ];
    }

    public function bookingTypeLabel(): string
    {
        return match ($this->booking_type) {
            self::BOOKING_WALK_IN              => 'Walk-in',
            self::BOOKING_APPOINTMENT_REQUIRED => 'Appointment required',
            default                            => 'Either',
        };
    }

    public function bookingTypeBadgeClass(): string
    {
        return match ($this->booking_type) {
            self::BOOKING_WALK_IN              => 'badge badge-success',
            self::BOOKING_APPOINTMENT_REQUIRED => 'badge badge-warning',
            default                            => 'badge badge-info',
        };
    }

    public function appointmentService()
    {
        return $this->belongsTo(AppointmentService::class, 'appointment_service_id');
    }

    public function isWalkIn(): bool
    {
        return $this->booking_type === self::BOOKING_WALK_IN;
    }

    public function isAppointmentRequired(): bool
    {
        return $this->booking_type === self::BOOKING_APPOINTMENT_REQUIRED;
    }

    public function isEither(): bool
    {
        return $this->booking_type === self::BOOKING_EITHER || empty($this->booking_type);
    }
}
