<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipMilestoneAward extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'membership_milestone_id',
        'reward_ledger_id',
        'awarded_at',
    ];

    protected $casts = [
        'awarded_at' => 'datetime',
    ];

    /**
     * Get the customer that received the award
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the milestone that was awarded
     */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(MembershipMilestone::class, 'membership_milestone_id');
    }

    /**
     * Get the reward ledger row created for the bonus points
     */
    public function rewardLedger(): BelongsTo
    {
        return $this->belongsTo(RewardLedger::class, 'reward_ledger_id');
    }
}
