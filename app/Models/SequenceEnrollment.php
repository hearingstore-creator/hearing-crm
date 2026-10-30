<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One deal going through a sequence. */
class SequenceEnrollment extends Model
{
    protected $fillable = ['sequence_id', 'deal_id', 'lead_id', 'status', 'next_position', 'next_run_at', 'start_stage_id',
        'stop_reason', 'started_by', 'started_at', 'ended_at'];

    protected $casts = [
        'next_run_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function sequence()
    {
        return $this->belongsTo(Sequence::class);
    }

    public function runs()
    {
        return $this->hasMany(SequenceRun::class, 'enrollment_id');
    }
}
