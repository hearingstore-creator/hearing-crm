<?php

namespace App\Models;

use App\Classes\Helper;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory, HelperTrait;
    protected $fillable = [
        'supplier_name',
        'vendor_id',
        'phone',
        'mobile_no',
        'email1',
        'email2',
        'address1',
        'address2',
        'fax',
        'state',
        'city',
        'country',
        'zip',
        'status'
    ];

    /**
     * Get the ledger entries for the supplier.
     */
    public function ledgers()
    {
        return $this->hasMany(SupplierLedger::class);
    }

    /**
     * Get the supplier's due balance attributes.
     *
     * @return array
     */
    public function getDueAmountAttribute(): array
    {
        $totalCredit = $this->ledgers->sum('credit'); 
        $totalDebit = $this->ledgers->sum('debit');   

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
