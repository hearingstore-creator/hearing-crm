<?php

namespace App\Models;

use App\Enums\FollowUpMode;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadFollowUp extends Model
{
    use HasFactory, HelperTrait;
    
    protected $fillable = [
        'lead_id',
        'assigned_user',
        'follow_up_date',
        'follow_up_mode',
        'follow_up_notes',

        // Tracking users
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'follow_up_mode' => 'array'
    ];

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

    // The user assigned to handle this follow-up
    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user');
    }
}
