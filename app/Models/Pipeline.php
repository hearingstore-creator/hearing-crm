<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A sales pipeline. "Hearing Care Sales" is the default: it is the only one whose stages mirror to leads.lead_status,
 * and it also holds the company-wide settings (office hours, weekly holidays, lead score weights).
 */
class Pipeline extends Model
{
    protected $fillable = ['name', 'description', 'is_default', 'default_owner_id', 'is_active', 'sort_order', 'archived_at',
        'office_open', 'office_close', 'weekly_holidays', 'scoring'];

    protected $casts = ['is_default' => 'boolean', 'is_active' => 'boolean', 'weekly_holidays' => 'array', 'scoring' => 'array', 'archived_at' => 'datetime'];

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

    /** Switched on and not archived: new deals can be opened in it. The default pipeline is always usable. */
    public function scopeUsable($query)
    {
        return $query->whereNull('archived_at')->where(fn ($q) => $q->where('is_active', true)->orWhere('is_default', true))
            ->orderByDesc('is_default')->orderBy('sort_order')->orderBy('id');
    }

    public function isUsable(): bool
    {
        return $this->is_default || ($this->is_active && !$this->archived_at);
    }

    /** A usable pipeline by id, or the default one. */
    public static function usableOrDefault(?int $id): self
    {
        return ($id ? static::usable()->find($id) : null) ?? static::default();
    }
}
