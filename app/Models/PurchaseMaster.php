<?php

namespace App\Models;

use App\Classes\Helper;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseMaster extends Model
{
    use HasFactory, HelperTrait;
    
    protected $fillable = [
        'purchase_invoice_no',
        'supplier_memo_no',
        'supplier_id',
        'vendor_id',
        'date',
        'details',
        'payment_id',
        'total_amount',
        'vat',
        'discount',
        'paid',
    ];

    protected $casts = [
       'date' => 'date',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function manufacturer()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
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
        return $this->hasMany(PurchaseDetail::class, 'purchase_master_id');
    }

    public function purchaseDetails()
    {
        return $this->hasMany(PurchaseDetail::class, 'purchase_master_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseDetail::class, 'purchase_master_id');
    }

    public function returns()
    {
        return $this->hasMany(PurchaseReturn::class, 'purchase_master_id');
    }

    protected static function booted(): void
    {
        static::addGlobalScope('vendor_filter', function ($builder) {
            Helper::vendorScope($builder);
        });
    }

    /**
     * Check if the purchase is accessible to the authenticated user
     *
     * @return bool
     */
    public function isAccessibleByAuthUser(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if ($user->canViewAllBranches()) {
            return true;
        }

        $branchId = $user->branchId();
        if ($branchId) {
            // Branch login / branch staff can only access their branch's purchases
            return (int) $this->vendor_id === $branchId;
        }

        // General users can only access global purchases
        return $this->vendor_id === null;
    }
}
