<?php

namespace App\Models;

use App\Classes\Helper;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleMaster extends Model
{
    use HasFactory, HelperTrait;
    
    protected $fillable = [
        'sale_invoice_no',
        'customer_id',
        'vendor_id',
        'date',
        'details',
        'payment_id',
        'reference_by',
        'reference_amount',
        'total_amount',
        'vat',
        'total_discount',
        'paid',
        'delivery_status',
        'is_percent_product_commission',
        'is_percent_service_commission'
    ];

    protected $casts = [
       'date' => 'date',
       'is_percent_product_commission' => 'boolean',
       'is_percent_service_commission' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'reference_by');
    }

    public function saleDetails()
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function serviceDetails()
    {
        return $this->hasMany(ServiceDetail::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('vendor_filter', function ($builder) {
            Helper::vendorScope($builder);
        });
    }

    /**
     * Check if the sale is accessible to the authenticated user
     *
     * @return bool
     */
    public function isAccessibleByAuthUser(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if ($user->isVendor()) {
            // Vendor can only access their own sales
            return $this->vendor_id === $user->id;
        }

        // General users can only access global sales
        return $this->vendor_id === null;
    }
}
