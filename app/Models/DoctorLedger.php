<?php

namespace App\Models;

use App\Classes\Helper;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorLedger extends Model
{
    use HasFactory, HelperTrait;

    protected $fillable = [
        'doctor_id',
        'vendor_id',
        'date',
        'details',
        'debit',
        'credit',
        'type',
        'ref_invoice_no',
        'payment_method',
        'bank_name',
        'transaction_no',
        'settlement_type',
        'settlement_period_start',
        'settlement_period_end',
    ];

    protected $casts = [
       'date' => 'date',
       'settlement_period_start' => 'date',
       'settlement_period_end' => 'date',
    ];

    /**
     * Get the doctor that owns the ledger.
     */
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('vendor_relation_filter', function ($builder) {
            Helper::vendorRelationScope($builder, 'doctor');
        });
    }
}
