<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReferral extends Model
{
    use HasFactory, HelperTrait;

    protected $fillable = [
        'sale_master_id',
        'doctor_id',
        'doctor_chamber_id',
        'role',
        'commission_amount',
        'settlement_status',
        'doctor_payment_id',
        'settled_at',
        'notes',
    ];

    protected $casts = [
        'commission_amount' => 'float',
        'settled_at'        => 'datetime',
    ];

    public const ROLE_DOCTOR = 'doctor';
    public const ROLE_QUACK  = 'quack';
    public const ROLE_AGENT  = 'agent';

    public function saleMaster(): BelongsTo
    {
        return $this->belongsTo(SaleMaster::class, 'sale_master_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function doctorChamber(): BelongsTo
    {
        return $this->belongsTo(DoctorChamber::class, 'doctor_chamber_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(DoctorLedger::class, 'doctor_payment_id');
    }

    public function scopePending($query)
    {
        return $query->where('settlement_status', 'pending');
    }

    public function scopeSettled($query)
    {
        return $query->where('settlement_status', 'settled');
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            self::ROLE_QUACK => 'Accompanying Quack / RMP',
            self::ROLE_AGENT => 'RF / Referral Agent',
            default          => 'Referring Doctor / Specialist',
        };
    }

    public function getRoleBadgeClassAttribute(): string
    {
        return match ($this->role) {
            self::ROLE_QUACK => 'badge-info',
            self::ROLE_AGENT => 'badge-warning',
            default          => 'badge-primary',
        };
    }
}
