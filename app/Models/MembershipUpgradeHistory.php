<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipUpgradeHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'old_tier_id',
        'new_tier_id',
        'upgraded_at',
    ];

    protected $casts = [
        'upgraded_at' => 'datetime',
    ];

    /**
     * Get the customer who was upgraded
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the old membership tier
     */
    public function oldTier()
    {
        return $this->belongsTo(MembershipTier::class, 'old_tier_id');
    }

    /**
     * Get the new membership tier
     */
    public function newTier()
    {
        return $this->belongsTo(MembershipTier::class, 'new_tier_id');
    }

    /**
     * Get the upgrade duration in days
     */
    public function getUpgradeDurationAttribute(): ?int
    {
        return null; // Can be calculated if needed based on next upgrade
    }

    /**
     * Scope: Get upgrade history for a specific customer
     */
    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope: Get recent upgrades
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('upgraded_at', '>=', now()->subDays($days));
    }

    /**
     * Scope: Order by upgrade date descending
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderBy('upgraded_at', 'desc');
    }
}
