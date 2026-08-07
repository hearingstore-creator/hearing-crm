<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'min_purchase',
        'max_purchase',
        'earn_percentage',
        'referral_reward_points',
    ];

    protected $casts = [
        'min_purchase' => 'decimal:2',
        'max_purchase' => 'decimal:2',
        'earn_percentage' => 'decimal:2',
        'referral_reward_points' => 'integer',
    ];

    /**
     * Get all customers with this membership tier
     */
    public function customers()
    {
        return $this->hasMany(Customer::class, 'membership_tier_id');
    }
}
