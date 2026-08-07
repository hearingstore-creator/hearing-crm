<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

trait HasActivityLog
{
    /**
     * Boot the trait and register model events
     */
    protected static function bootHasActivityLog(): void
    {
        // Log when a model is created
        static::created(function ($model) {
            $model->logActivity('created', [], $model->getLoggableAttributes());
        });

        // Log when a model is updated
        static::updated(function ($model) {
            $model->logActivity('updated', $model->getOriginal(), $model->getChanges());
        });

        // Log when a model is deleted
        static::deleted(function ($model) {
            $model->logActivity('deleted', $model->getLoggableAttributes(), []);
        });

        // Log when a model is restored (if using SoftDeletes)
        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model) {
                $model->logActivity('restored', [], $model->getLoggableAttributes());
            });
        }
    }

    /**
     * Create an activity log entry
     */
    protected function logActivity(string $action, array $oldValues = [], array $newValues = []): void
    {
        // Skip if logging is disabled for this action
        if ($this->shouldSkipLogging($action)) {
            return;
        }

        ActivityLog::create([
            'user_id' => $this->getActivityLogUserId(),
            'subject_type' => get_class($this),
            'subject_id' => $this->id,
            'action' => $action,
            'old_values' => $this->filterLoggableData($oldValues),
            'new_values' => $this->filterLoggableData($newValues),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Get the user ID for the activity log
     */
    protected function getActivityLogUserId(): ?int
    {
        return Auth::id();
    }

    /**
     * Get attributes that should be logged
     */
    protected function getLoggableAttributes(): array
    {
        $attributes = $this->getAttributes();
        
        // Remove attributes that shouldn't be logged
        return $this->filterLoggableData($attributes);
    }

    /**
     * Filter data to only include loggable attributes
     */
    protected function filterLoggableData(array $data): array
    {
        // Get attributes to exclude
        $excluded = array_merge(
            $this->getHiddenAttributes(),
            $this->getUnloggableAttributes()
        );

        // Remove excluded attributes
        $filtered = array_diff_key($data, array_flip($excluded));

        // Only include specific attributes if defined
        if (!empty($this->getOnlyLoggableAttributes())) {
            $filtered = array_intersect_key($filtered, array_flip($this->getOnlyLoggableAttributes()));
        }

        return $filtered;
    }

    /**
     * Get hidden attributes (passwords, tokens, etc.)
     */
    protected function getHiddenAttributes(): array
    {
        return property_exists($this, 'hidden') ? $this->hidden : [];
    }

    /**
     * Get attributes that should NOT be logged
     * Override this in your model
     */
    protected function getUnloggableAttributes(): array
    {
        return property_exists($this, 'unloggable') 
            ? $this->unloggable 
            : ['created_at', 'updated_at'];
    }

    /**
     * Get ONLY attributes that should be logged (whitelist approach)
     * Override this in your model
     */
    protected function getOnlyLoggableAttributes(): array
    {
        return property_exists($this, 'loggable') ? $this->loggable : [];
    }

    /**
     * Check if logging should be skipped for this action
     */
    protected function shouldSkipLogging(string $action): bool
    {
        // Skip if actions are defined and this action isn't in the list
        if (property_exists($this, 'loggableActions') && !empty($this->loggableActions)) {
            return !in_array($action, $this->loggableActions);
        }

        return false;
    }

    /**
     * Disable activity logging temporarily
     */
    public static function withoutActivityLogging(callable $callback)
    {
        static::$loggingDisabled = true;
        
        try {
            return $callback();
        } finally {
            static::$loggingDisabled = false;
        }
    }

    /**
     * Relationship to activity logs
     */
    public function activityLogs()
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }
}
