<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One posted stock adjustment. Never edited or deleted: a mistake is cancelled by a reversal (see App\Services\StockAdjustment). */
class StockAdjustment extends Model
{
    public const REASONS = [
        'damaged'          => 'Damaged',
        'lost'             => 'Lost',
        'found'            => 'Found',
        'count_correction' => 'Stock count correction',
        'opening_stock'    => 'Opening stock',
        'other'            => 'Other',
    ];

    protected $fillable = [
        'adjustment_no', 'vendor_id', 'date', 'reason', 'note', 'status', 'cancelled_at', 'cancel_reason', 'cancelled_by', 'created_by', 'updated_by',
    ];

    protected $casts = ['date' => 'date', 'cancelled_at' => 'datetime'];

    public function details(): HasMany
    {
        return $this->hasMany(StockAdjustmentDetail::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getReasonLabelAttribute(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst($this->reason);
    }

    public function getLocationAttribute(): string
    {
        return $this->vendor_id ? ($this->branch->name ?? 'Branch #' . $this->vendor_id) : 'Head office';
    }
}
