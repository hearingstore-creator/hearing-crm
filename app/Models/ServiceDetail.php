<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceDetail extends Model
{
    use HasFactory, HelperTrait;

    public const STATUS_NOT_BOOKED = 'not_booked';
    public const STATUS_BOOKED     = 'booked';
    public const STATUS_DONE       = 'done';
    public const STATUS_CANCELLED  = 'cancelled';
    public const STATUS_REFUNDED   = 'refunded';

    protected $fillable = [
        'sale_master_id',
        'service_type_id',
        'qty',
        'service_price',
        'discount',
        'fulfillment_status',
        'appointment_id',
        'done_by',
        'done_at',
        'notes',
    ];

    protected $casts = [
        'done_at' => 'datetime',
    ];

    public function saleMaster()
    {
        return $this->belongsTo(SaleMaster::class, 'sale_master_id');
    }

    public function serviceType()
    {
        return $this->belongsTo(ServiceType::class, 'service_type_id', 'id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function doneByUser()
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    public function statusLabel(): string
    {
        return match ($this->fulfillment_status) {
            self::STATUS_BOOKED    => 'Booked',
            self::STATUS_DONE      => 'Done',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REFUNDED  => 'Refunded',
            default                => 'Not booked',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->fulfillment_status) {
            self::STATUS_BOOKED    => 'badge badge-info',
            self::STATUS_DONE      => 'badge badge-success',
            self::STATUS_CANCELLED => 'badge badge-danger',
            self::STATUS_REFUNDED  => 'badge badge-secondary',
            default                => 'badge badge-warning',
        };
    }

    public function daysWaiting(): int
    {
        $saleDate = $this->saleMaster?->date ?? $this->created_at;
        if (!$saleDate) {
            return 0;
        }
        $carbonDate = $saleDate instanceof \Carbon\CarbonInterface ? $saleDate : \Carbon\Carbon::parse($saleDate);
        return (int) $carbonDate->diffInDays(now());
    }

    public function markDone(?int $doneBy = null, mixed $doneAt = null, ?string $notes = null): bool
    {
        $this->fulfillment_status = self::STATUS_DONE;
        $this->done_by = $doneBy ?? auth()->id();
        $this->done_at = $doneAt ? (\Carbon\Carbon::parse($doneAt)) : now();
        if ($notes !== null) {
            $this->notes = $notes;
        }
        return $this->save();
    }

    public function linkAppointment(int $appointmentId): bool
    {
        $this->fulfillment_status = self::STATUS_BOOKED;
        $this->appointment_id = $appointmentId;
        return $this->save();
    }
}
