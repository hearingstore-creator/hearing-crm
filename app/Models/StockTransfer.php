<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One posted stock transfer. Never edited or deleted: cancelled by a reversal (see App\Services\StockTransfer). */
class StockTransfer extends Model
{
    protected $fillable = [
        'transfer_no', 'from_vendor_id', 'to_vendor_id', 'date', 'note', 'status', 'cancelled_at', 'cancel_reason', 'cancelled_by', 'created_by', 'updated_by',
    ];

    protected $casts = ['date' => 'date', 'cancelled_at' => 'datetime'];

    public function details(): HasMany
    {
        return $this->hasMany(StockTransferDetail::class);
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_vendor_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_vendor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getFromNameAttribute(): string
    {
        return $this->from_vendor_id ? ($this->fromBranch->name ?? 'Branch #' . $this->from_vendor_id) : 'Head office';
    }

    public function getToNameAttribute(): string
    {
        return $this->to_vendor_id ? ($this->toBranch->name ?? 'Branch #' . $this->to_vendor_id) : 'Head office';
    }
}
