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

    public function advances()
    {
        return $this->hasMany(CustomerAdvance::class, 'customer_id');
    }

    public function activeAdvances()
    {
        return $this->hasMany(CustomerAdvance::class, 'customer_id')
            ->where('status', CustomerAdvance::STATUS_ACTIVE)
            ->where('balance', '>', 0);
    }

    public function activeAdvanceBalance(): float
    {
        return (float) $this->activeAdvances()->sum('balance');
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
            if (Helper::isAllBranchesMode() || (auth()->check() && auth()->user()->isSuperAdmin())) {
                return;
            }

            $branchId = Helper::currentBranchId();
            if ($branchId) {
                $builder->whereHas('lead', function ($query) use ($branchId) {
                    $query->where('vendor_id', $branchId);
                });
            }
        });

        static::creating(function ($customer) {
            if (empty($customer->customer_uid)) {
                $customer->customer_uid = static::generateCustomerUID();
            }
        });

        // SMS Center "Membership Upgrade" automation (read-only listener)
        static::updated(function ($customer) {
            rescue(fn () => app(\App\Services\SmsAutomationService::class)->onCustomerUpdated($customer));
        });
    }

    /**
     * Generate a guaranteed-unique customer UID: C-YYYYMMDDXXXX
     * Bypasses all global scopes, includes soft-deleted rows, and uses numeric max to prevent duplicate collisions.
     *
     * @return string
     */
    public static function generateCustomerUID(): string
    {
        $prefix = 'C-' . date('Ymd');
        $prefixLen = strlen($prefix);

        // Raw table query to strictly bypass any global scope (like branch) and check all soft-deleted records
        $maxNum = \Illuminate\Support\Facades\DB::table('customers')
            ->where('customer_uid', 'LIKE', $prefix . '%')
            ->selectRaw("MAX(CAST(SUBSTRING(customer_uid, {$prefixLen} + 1) AS UNSIGNED)) as max_num")
            ->value('max_num');

        $nextNumber = $maxNum ? ((int) $maxNum + 1) : 1;
        $candidate = $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);

        // Safety check loop to ensure candidate does not exist in the database
        while (\Illuminate\Support\Facades\DB::table('customers')->where('customer_uid', $candidate)->exists()) {
            $nextNumber++;
            $candidate = $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return $candidate;
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

    /**
     * Display name accessor (from linked lead)
     */
    public function getNameAttribute(): string
    {
        return $this->lead?->full_name ?? $this->customer_uid ?? ('Customer #' . $this->id);
    }
}
