<?php

namespace App\Models;

use App\Enums\ReferralStatus;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    use HasFactory, HelperTrait;

    protected $fillable = [
        'referrer_id',
        'referred_customer_id',
        'referral_code_used',
        'status',
        'rewarded_at',
    ];

    protected $casts = [
        'rewarded_at' => 'datetime',
        'status' => ReferralStatus::class,
    ];

    /**
     * Get the customer who made the referral
     */
    public function referrer()
    {
        return $this->belongsTo(Customer::class, 'referrer_id');
    }

    /**
     * Get the customer who was referred
     */
    public function referredCustomer()
    {
        return $this->belongsTo(Customer::class, 'referred_customer_id');
    }

    /**
     * Get the reward ledger entries for this referral
     */
    public function rewardLedgers()
    {
        return $this->hasMany(RewardLedger::class, 'referral_id');
    }
}
