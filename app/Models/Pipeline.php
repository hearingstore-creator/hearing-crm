<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pipeline extends Model
{
    protected $fillable = ['name', 'is_default', 'default_owner_id', 'is_active', 'office_open', 'office_close', 'weekly_holidays', 'scoring'];

    protected $casts = ['is_default' => 'boolean', 'is_active' => 'boolean', 'weekly_holidays' => 'array', 'scoring' => 'array'];

    public function stages()
    {
        return $this->hasMany(PipelineStage::class)->orderBy('sort_order');
    }

    /** Active (not archived) stages, board order. */
    public function activeStages()
    {
        return $this->stages()->whereNull('archived_at');
    }

    public function defaultOwner()
    {
        return $this->belongsTo(User::class, 'default_owner_id');
    }

    public static function default(): self
    {
        return static::where('is_default', true)->firstOrFail();
    }
}
