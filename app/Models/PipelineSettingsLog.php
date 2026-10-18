<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PipelineSettingsLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'pipeline_id', 'stage_id', 'action', 'old_values', 'new_values'];

    protected $casts = ['old_values' => 'array', 'new_values' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function stage()
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    public static function record(string $action, ?PipelineStage $stage = null, array $old = [], array $new = [], ?int $pipelineId = null): void
    {
        static::create([
            'user_id'     => auth()->id(),
            'pipeline_id' => $pipelineId ?? $stage?->pipeline_id,
            'stage_id'    => $stage?->id,
            'action'      => $action,
            'old_values'  => $old ?: null,
            'new_values'  => $new ?: null,
        ]);
    }
}
