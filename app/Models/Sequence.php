<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A follow-up sequence: steps (SMS, email, WhatsApp template, call task) spread over days. */
class Sequence extends Model
{
    public const TRIGGERS = [
        'manual'        => 'Started by hand from a deal',
        'deal_created'  => 'A new lead / deal is created',
        'stage_entered' => 'A deal reaches a stage',
        'deal_lost'     => 'A deal is lost',
    ];

    public const CHANNELS = [
        'sms'      => ['SMS', 'fas fa-sms'],
        'email'    => ['Email', 'fas fa-envelope'],
        'whatsapp' => ['WhatsApp template', 'fab fa-whatsapp'],
        'call'     => ['Call task for the owner', 'fas fa-phone'],
    ];

    protected $fillable = ['name', 'description', 'is_active', 'trigger', 'trigger_pipeline_id', 'trigger_sources', 'trigger_stage_id', 'trigger_lost_reasons',
        'stop_on_reply', 'stop_on_appointment', 'stop_on_stage_change', 'created_by', 'updated_by'];

    protected $casts = [
        'is_active' => 'boolean',
        'trigger_sources' => 'array',
        'trigger_lost_reasons' => 'array',
        'stop_on_reply' => 'boolean',
        'stop_on_appointment' => 'boolean',
        'stop_on_stage_change' => 'boolean',
    ];

    /** Sequences that apply to deals in this pipeline (no pipeline set = any pipeline). */
    public function scopeForPipeline($query, ?int $pipelineId)
    {
        return $query->where(fn ($q) => $q->whereNull('trigger_pipeline_id')->orWhere('trigger_pipeline_id', $pipelineId));
    }

    public function triggerPipeline()
    {
        return $this->belongsTo(Pipeline::class, 'trigger_pipeline_id');
    }

    public function steps()
    {
        return $this->hasMany(SequenceStep::class)->orderBy('position');
    }

    public function enrollments()
    {
        return $this->hasMany(SequenceEnrollment::class);
    }
}
