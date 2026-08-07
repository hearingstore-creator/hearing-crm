<?php

namespace App\Models;

use App\Classes\Helper;
use App\Enums\RewardLedgerType;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory, HelperTrait;

    protected $fillable = [
        'lead_id',
        'customer_uid',
        'membership_tier_id',
        'referral_code',
        'referred_by_customer_id',
        'membership_id',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function membershipTier()
    {
        return $this->belongsTo(MembershipTier::class, 'membership_tier_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'customer_id');
    }

    public function saleMasters()
    {
        return $this->hasMany(SaleMaster::class, 'customer_id');
    }

    public function rewardLedgers()
    {
        return $this->hasMany(RewardLedger::class, 'customer_id');
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function referredBy()
    {
        return $this->belongsTo(Customer::class, 'referred_by_customer_id');
    }

    public function membershipUpgradeHistories()
    {
        return $this->hasMany(MembershipUpgradeHistory::class, 'customer_id');
    } 
    
    protected static function booted(): void
    {
        static::addGlobalScope('vendor_relation_filter', function ($builder) {
            Helper::vendorRelationScope($builder, 'lead');
        });

        static::creating(function ($customer) {
            if (empty($customer->customer_uid)) {
                $customer->customer_uid = $customer->generateCustomerUID();
            }
        });
    }

    /**
     * Generate a unique customer UID
     * Format: C-YYYYMMDD-XXXX (where XXXX is a 4-digit padded number)
     *
     * @return string
     */
    private function generateCustomerUID(): string
    {
        $prefix = 'C-' . date('Ymd');
        $lastCustomer = Customer::withTrashed()
            ->where('customer_uid', 'LIKE', $prefix . '%')
            ->orderByDesc('customer_uid')
            ->value('customer_uid');

        if ($lastCustomer) {
            $lastNumber = (int) substr($lastCustomer, strlen($prefix));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get available redeem points for the customer
     * Calculates real available balance from reward ledgers (non-expired points minus redeemed)
     *
     * @return float
     */
    public function getAvailableRedeemPoints(): float
    {
        // Get total earned and referral points (non-expired, positive)
        $earnedPoints = RewardLedger::where('customer_id', $this->id)
            ->whereIn('type', [RewardLedgerType::EARN->value, RewardLedgerType::REFERRAL->value])
            ->where('points', '>', 0)
            ->where(function ($query) {
                $query->whereNull('expiry_date')
                      ->orWhere('expiry_date', '>', now());
            })
            ->sum('points');

        // Get total redeemed points (absolute value of negative points)
        $redeemedPoints = abs(RewardLedger::where('customer_id', $this->id)
            ->where('type', RewardLedgerType::REDEEM->value)
            ->where('points', '<', 0)
            ->sum('points'));

        // Available balance = earned - redeemed
        return max(0, $earnedPoints - $redeemedPoints);
    }
}
