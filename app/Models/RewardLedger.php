<?php

namespace App\Models;

use App\Enums\RewardLedgerType;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RewardLedger extends Model
{
    use HasFactory, HelperTrait;

    protected $fillable = [
        'customer_id',
        'sale_master_id',
        'referral_id',
        'type',
        'points',
        'batch_reference',
        'expiry_date',
    ];

    protected $casts = [
        'type' => RewardLedgerType::class,
        'points' => 'integer',
        'expiry_date' => 'date',
    ];

    /**
     * Get the customer
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the sale master
     */
    public function saleMaster()
    {
        return $this->belongsTo(SaleMaster::class, 'sale_master_id');
    }

    /**
     * Get the referral
     */
    public function referral()
    {
        return $this->belongsTo(Referral::class, 'referral_id');
    }
}
