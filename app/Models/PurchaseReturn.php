<?php

namespace App\Models;

use App\Classes\Helper;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturn extends Model
{
    use HasFactory, HelperTrait;

    protected $fillable = [
        'return_no',
        'purchase_master_id',
        'supplier_id',
        'vendor_id',
        'date',
        'total_amount',
        'accepted_amount',
        'loss_amount',
        'supplier_memo_no',
        'supplier_doc_no',
        'supplier_doc_date',
        'reason',
        'status',
        'attachment',
        'remarks',
        'confirmed_by',
        'confirmed_at',
        'unconfirm_reason',
        'unconfirmed_by',
        'unconfirmed_at',
        'delete_reason',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'date' => 'date',
        'supplier_doc_date' => 'date',
        'total_amount' => 'decimal:2',
        'accepted_amount' => 'decimal:2',
        'loss_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'unconfirmed_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseMaster()
    {
        return $this->belongsTo(PurchaseMaster::class, 'purchase_master_id');
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function branch()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function details()
    {
        return $this->hasMany(PurchaseReturnDetail::class, 'purchase_return_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function unconfirmedBy()
    {
        return $this->belongsTo(User::class, 'unconfirmed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function getDaysPendingAttribute(): int
    {
        if (!$this->isPending() || !$this->date) {
            return 0;
        }
        return (int) max(0, $this->date->diffInDays(now()->startOfDay()));
    }

    /**
     * Get array of supplier doc numbers
     */
    public function getSupplierDocNosArrayAttribute(): array
    {
        if (empty($this->supplier_doc_no)) {
            return [];
        }
        $parts = preg_split('/[\r\n,]+/', $this->supplier_doc_no);
        return array_values(array_filter(array_map('trim', $parts)));
    }

    /**
     * Get array of supplier memo numbers
     */
    public function getSupplierMemoNosArrayAttribute(): array
    {
        if (empty($this->supplier_memo_no)) {
            return [];
        }
        $parts = preg_split('/[\r\n,]+/', $this->supplier_memo_no);
        return array_values(array_filter(array_map('trim', $parts)));
    }

    /**
     * Get all unique purchase masters referenced across details or header
     */
    public function getLinkedPurchasesAttribute()
    {
        $ids = $this->details->pluck('purchase_master_id')->filter();
        if ($this->purchase_master_id) {
            $ids->push($this->purchase_master_id);
        }
        $uniqueIds = $ids->unique()->values();
        if ($uniqueIds->isEmpty()) {
            return collect();
        }
        return PurchaseMaster::withoutGlobalScopes()->whereIn('id', $uniqueIds)->get();
    }

    protected static function booted(): void
    {
        static::addGlobalScope('vendor_filter', function ($builder) {
            Helper::vendorScope($builder);
        });
    }

    /**
     * Check if accessible to the authenticated user
     */
    public function isAccessibleByAuthUser(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->canViewAllBranches()) return true;

        $branchId = $user->branchId();
        if ($branchId) {
            return (int) $this->vendor_id === $branchId;
        }

        return $this->vendor_id === null;
    }
}
