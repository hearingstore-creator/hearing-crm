<?php

namespace App\Models;

use App\Enums\ActivityType;
use App\Enums\FollowUpMode;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadFollowUp extends Model
{
    use HasFactory, HelperTrait;
    
    protected $fillable = [
        'lead_id',
        'deal_id',
        'activity_type',
        'assigned_user',
        'follow_up_date',
        'follow_up_mode',
        'follow_up_notes',
        'status',
        'completed_at',
        'outcome_notes',
        'outcome',

        // Tracking users
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'follow_up_mode' => 'array',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (LeadFollowUp $fu) {
            // Keep the new activity type and the old follow_up_mode in step (old screens still read the mode)
            if ($fu->activity_type && $fu->isDirty('activity_type')) {
                $fu->follow_up_mode = [ActivityType::from($fu->activity_type)->legacyMode()];
            } elseif (!$fu->activity_type || $fu->isDirty('follow_up_mode')) {
                $fu->activity_type = ActivityType::fromLegacyMode(($fu->follow_up_mode ?? [])[0] ?? null)->value;
            }

            // Added from the lead page: attach to the person's open deal (else the latest one)
            if (!$fu->deal_id && $fu->lead_id) {
                $fu->deal_id = Deal::withoutGlobalScopes()->where('lead_id', $fu->lead_id)
                    ->orderByRaw("status = 'open' DESC")->latest('id')->value('id');
            }
        });
    }

    public function getTypeAttribute(): ActivityType
    {
        return ActivityType::tryFrom((string) $this->activity_type) ?? ActivityType::TASK;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /** Pending activities due by the end of today for a user, on open deals they can see (My Day badge). */
    public static function dueCountFor(int $userId): int
    {
        return static::where('status', 'pending')->where('assigned_user', $userId)
            ->where('follow_up_date', '<=', now()->endOfDay())
            ->whereIn('deal_id', Deal::open()->select('deals.id'))
            ->count();
    }

     public function getFollowUpModeLabelAttribute()
    {
        $items = $this->follow_up_mode ?? [];

        if (!is_array($items)) {
            $items = json_decode($items, true) ?? [];
        }

        $labels = collect($items)->map(function ($item) {
            return FollowUpMode::tryFrom($item)?->label() ?? ucfirst(str_replace('_', ' ', $item));
        });

        return $labels->implode(', ');
    }

    /**
     * Relationships
     */

    // Each follow-up belongs to one lead
    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    // The user assigned to handle this follow-up
    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user');
    }
}
