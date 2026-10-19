<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The single row of support rules (SLA hours by priority, reminder and auto-close days). */
class SupportSetting extends Model
{
    protected $fillable = ['sla_critical_hours', 'sla_high_hours', 'sla_medium_hours', 'sla_low_hours', 'stale_days', 'auto_close_days'];

    protected $casts = [
        'sla_critical_hours' => 'integer', 'sla_high_hours' => 'integer', 'sla_medium_hours' => 'integer', 'sla_low_hours' => 'integer',
        'stale_days' => 'integer', 'auto_close_days' => 'integer',
    ];

    /** The settings row, read once per request (kept in the container so a saved change is seen at once). */
    public static function current(): self
    {
        if (app()->bound('support.settings')) {
            return app('support.settings');
        }

        $defaults = config('crm.ticket_sla_hours', []);
        $row = static::firstOrCreate([], [
            'sla_critical_hours' => $defaults['Critical'] ?? 4, 'sla_high_hours' => $defaults['High'] ?? 24,
            'sla_medium_hours' => $defaults['Medium'] ?? 72, 'sla_low_hours' => $defaults['Low'] ?? 168,
        ]);
        app()->instance('support.settings', $row);

        return $row;
    }

    public static function forget(): void
    {
        app()->forgetInstance('support.settings');
    }

    /** Hours allowed for a priority name (Critical, High, Medium, Low). */
    public function hoursFor(string $priority): int
    {
        return (int) ($this->{'sla_' . strtolower($priority) . '_hours'} ?? $this->sla_medium_hours);
    }

    /** [Critical => hours, ...] in priority order. */
    public function slaHours(): array
    {
        return ['Critical' => $this->sla_critical_hours, 'High' => $this->sla_high_hours, 'Medium' => $this->sla_medium_hours, 'Low' => $this->sla_low_hours];
    }
}
