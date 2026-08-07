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
        'status',
    ];

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
