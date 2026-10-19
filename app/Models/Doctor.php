<?php

namespace App\Models;

use App\Classes\Helper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HelperTrait;

class Doctor extends Model
{
    use HasFactory, HelperTrait;

    protected $fillable = [
        'name',
        'vendor_id',
        'contact_number',
        'email',
        'address',
        'qualification',
        'product_commission_percent',
        'product_commission_amount',
        'service_commission_percent',
        'service_commission_amount',
        'referrer_type',
        'status',
    ];

    public const TYPE_DOCTOR = 'doctor';
    public const TYPE_QUACK  = 'quack';
    public const TYPE_AGENT  = 'agent';

    public static function referrerTypeLabels(): array
    {
        return [
            self::TYPE_DOCTOR => 'Qualified Doctor / ENT',
            self::TYPE_QUACK  => 'Village Doctor / Quack / RMP',
            self::TYPE_AGENT  => 'RF / Referral Agent',
        ];
    }

    public function getReferrerTypeLabelAttribute(): string
    {
        return self::referrerTypeLabels()[$this->referrer_type ?? self::TYPE_DOCTOR] ?? 'Doctor';
    }

    public function getReferrerBadgeClassAttribute(): string
    {
        return match ($this->referrer_type ?? self::TYPE_DOCTOR) {
            self::TYPE_QUACK => 'badge-info',
            self::TYPE_AGENT => 'badge-warning',
            default => 'badge-primary',
        };
    }

    /**
     * Get the chambers for the doctor.
     */
    public function chambers()
    {
        return $this->hasMany(DoctorChamber::class)->orderByDesc('is_primary')->orderBy('id');
    }

    /**
     * Get the primary chamber for the doctor.
     */
    public function primaryChamber()
    {
        return $this->hasOne(DoctorChamber::class)->where('is_primary', true);
    }

    /**
     * Get the category-wise product commissions for the doctor.
     */
    public function categoryCommissions()
    {
        return $this->hasMany(DoctorCategoryCommission::class);
    }

    /**
     * Get the per-service commissions for the doctor.
     */
    public function serviceCommissions()
    {
        return $this->hasMany(DoctorServiceCommission::class);
    }

    /**
     * Get display address for the doctor (primary chamber or fallback).
     */
    public function getDisplayAddressAttribute(): string
    {
        $primary = $this->primaryChamber ?? $this->chambers->first();
        if ($primary) {
            return ($primary->chamber_name ? $primary->chamber_name . ' — ' : '') . $primary->address;
        }

        return $this->address ?? 'No chamber specified';
    }

    /**
     * Return structured commission configuration for frontend calculation.
     */
    public function getCommissionRulesArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'product_default' => [
                'percent' => (float) ($this->product_commission_percent ?? 0),
                'amount'  => (float) ($this->product_commission_amount ?? 0),
            ],
            'service_default' => [
                'percent' => (float) ($this->service_commission_percent ?? 0),
                'amount'  => (float) ($this->service_commission_amount ?? 0),
            ],
            'category_overrides' => $this->categoryCommissions->mapWithKeys(function ($item) {
                return [$item->category_id => [
                    'type'  => $item->commission_type,
                    'value' => (float) $item->commission_value,
                ]];
            })->all(),
            'service_overrides' => $this->serviceCommissions->mapWithKeys(function ($item) {
                return [$item->service_type_id => [
                    'type'  => $item->commission_type,
                    'value' => (float) $item->commission_value,
                ]];
            })->all(),
            'chambers' => $this->chambers->map(function ($c) {
                return [
                    'id'            => $c->id,
                    'chamber_name'  => $c->chamber_name,
                    'address'       => $c->address,
                    'phone'         => $c->phone,
                    'visiting_days' => $c->visiting_days,
                    'visiting_time' => $c->visiting_time,
                    'is_primary'    => (bool) $c->is_primary,
                ];
            })->all(),
        ];
    }

    /**
     * Get the branch/vendor the doctor is affiliated with.
     */
    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    /**
     * Get the ledger entries for the doctor.
     */
    public function ledgers()
    {
        return $this->hasMany(DoctorLedger::class);
    }

    /**
     * Get the doctor's due balance attributes.
     *
     * @return array
     */
    public function getDueAmountAttribute(): array
    {
        $totalCredit = $this->ledgers->sum('credit'); // Amount owed to doctor (from sales)
        $totalDebit = $this->ledgers->sum('debit');   // Amount paid to doctor

        $due = $totalCredit - $totalDebit;

        return [
            'due' => max(0, $due),
            'total_credit' => $totalCredit,
            'total_debit' => $totalDebit,
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('vendor_filter', function ($builder) {
            Helper::vendorScope($builder);
        });
    }
}

