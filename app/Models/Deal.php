<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * One sales opportunity of a person (lead). A person can have many deals over the years.
 * Visibility: branch users see their branch's deals; Head Office sees all (same rule as leads).
 */
class Deal extends Model
{
    use HelperTrait;

    public const OPEN = 'open';
    public const WON = 'won';
    public const LOST = 'lost';

    protected $fillable = [
        'lead_id', 'pipeline_id', 'stage_id', 'status', 'vendor_id', 'owner_id', 'title', 'inquiry_for',
        'value', 'expected_close_date', 'temperature', 'position', 'stage_entered_at', 'won_at', 'lost_at',
        'lost_reason_id', 'lost_to', 'lost_note', 'sale_master_id', 'source', 'utm_source', 'utm_medium', 'utm_campaign',
        'campaign_id', 'utm_content', 'utm_term', 'gclid', 'fbclid', 'landing_page',
    ];

    protected $casts = [
        'inquiry_for'         => 'array',
        'value'               => 'decimal:2',
        'expected_close_date' => 'date',
        'stage_entered_at'    => 'datetime',
        'won_at'              => 'datetime',
        'lost_at'             => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('branch_filter', function ($builder) {
            $branchId = auth()->check() ? auth()->user()->branchId() : null;
            if ($branchId) {
                $builder->where('deals.vendor_id', $branchId);
            }
        });
    }

    /** The person. Deal visibility is the access rule, so the lead's own branch scope is not applied. */
    public function lead()
    {
        return $this->belongsTo(Lead::class)->withoutGlobalScope('vendor_filter');
    }

    public function pipeline()
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function stage()
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function lostReason()
    {
        return $this->belongsTo(LostReason::class);
    }

    public function saleMaster()
    {
        return $this->belongsTo(SaleMaster::class);
    }

    public function transfers()
    {
        return $this->hasMany(DealTransfer::class)->latest('id');
    }

    public function statusHistories()
    {
        return $this->hasMany(LeadStatusHistory::class)->orderBy('created_at');
    }

    /** Typed activities (calls, visits, tasks, notes) stored in lead_follow_ups. */
    public function activities()
    {
        return $this->hasMany(LeadFollowUp::class);
    }

    /** Calendar appointments booked from this deal. */
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    /** Earliest pending activity: the "next activity" chip on the card. */
    public function nextActivity()
    {
        return $this->hasOne(LeadFollowUp::class)
            ->ofMany(['follow_up_date' => 'min', 'id' => 'min'], fn ($q) => $q->where('status', 'pending'));
    }

    public function scopeOpen($query)
    {
        return $query->where('deals.status', self::OPEN);
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    /** Card / list title: explicit title, else the interest labels, else a generic label. */
    public function getDisplayTitleAttribute(): string
    {
        if ($this->title) {
            return $this->title;
        }
        $labels = collect($this->inquiry_for ?? [])->map(fn ($i) => ucwords(str_replace('_', ' ', (string) $i)))->filter();

        return $labels->isNotEmpty() ? $labels->join(', ') : 'Hearing care inquiry';
    }

    public function daysInStage(): int
    {
        return $this->stage_entered_at ? (int) $this->stage_entered_at->diffInDays(now()) : 0;
    }

    public function isRotting(): bool
    {
        $limit = $this->stage?->rotting_days;

        return $this->isOpen() && $limit && $this->daysInStage() >= $limit;
    }

    public function proposals()
    {
        return $this->hasMany(Proposal::class, 'deal_id');
    }
}
