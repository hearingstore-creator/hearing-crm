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
        'from_status',
        'to_status',
        'notes',
    ];

    /**
     * Get the lead that owns the status history.
     */
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the from status label.
     */
    public function getFromStatusLabelAttribute(): ?string
    {
        return $this->from_status ? LeadStatus::from($this->from_status)?->label() : null;
    }

    /**
     * Get the to status label.
     */
    public function getToStatusLabelAttribute(): string
    {
        return LeadStatus::from($this->to_status)?->label() ?? ucfirst($this->to_status);
    }
}
