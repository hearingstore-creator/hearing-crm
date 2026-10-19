<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipMilestone extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'threshold',
        'bonus_points',
        'is_active',
    ];

    protected $casts = [
        'threshold' => 'decimal:2',
        'bonus_points' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Awards granted for this milestone
     */
    public function awards(): HasMany
    {
        return $this->hasMany(MembershipMilestoneAward::class, 'membership_milestone_id');
    }
}
