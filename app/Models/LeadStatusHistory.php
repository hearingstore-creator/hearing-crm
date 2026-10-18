<?php

namespace App\Models;

use App\Enums\LeadStatus;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadStatusHistory extends Model
{
    use HasFactory, HelperTrait;

     /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

    protected $fillable = [
        'lead_id',
        'deal_id',
        'from_status',
        'to_status',
        'from_stage_id',
        'to_stage_id',
        'notes',
    ];

    /**
     * Get the lead that owns the status history.
     */
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function fromStage()
    {
        return $this->belongsTo(PipelineStage::class, 'from_stage_id');
    }

    public function toStage()
    {
        return $this->belongsTo(PipelineStage::class, 'to_stage_id');
    }

    /**
     * Get the from status label.
     */
    public function getFromStatusLabelAttribute(): ?string
    {
        return $this->from_status
            ? (LeadStatus::tryFrom($this->from_status)?->label() ?? ucfirst(str_replace('_', ' ', $this->from_status)))
            : null;
    }

    /**
     * Get the to status label.
     */
    public function getToStatusLabelAttribute(): string
    {
        return LeadStatus::tryFrom((string) $this->to_status)?->label() ?? ucfirst(str_replace('_', ' ', (string) $this->to_status));
    }
}
