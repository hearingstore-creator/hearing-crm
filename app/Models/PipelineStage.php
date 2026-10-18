<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PipelineStage extends Model
{
    /** Info that can be required when a deal moves INTO this stage (stage gates). */
    public const GATE_FIELDS = [
        'inquiry_for'           => 'Interest (what the patient needs)',
        'note'                  => 'Counselling note',
        'appointment_or_walkin' => 'Appointment booked or walk-in',
        'trial_product'         => 'Trial product',
        'trial_return_date'     => 'Trial return date',
        'value'                 => 'Quoted value',
    ];

    protected $fillable = [
        'pipeline_id', 'name', 'status_key', 'color', 'probability', 'rotting_days',
        'required_fields', 'sort_order', 'is_system', 'archived_at',
    ];

    protected $casts = [
        'required_fields' => 'array',
        'is_system'       => 'boolean',
        'archived_at'     => 'datetime',
    ];

    public function pipeline()
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function deals()
    {
        return $this->hasMany(Deal::class, 'stage_id');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /** Name with "(archived)" suffix for history and reports. */
    public function getDisplayNameAttribute(): string
    {
        return $this->name . ($this->isArchived() ? ' (archived)' : '');
    }
}
