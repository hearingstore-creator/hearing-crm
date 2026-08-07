<?php

namespace App\Models;

use App\Classes\Helper;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    use HasFactory, HelperTrait;
    
    protected $fillable = [
        'product_id',
        'vendor_id',
        'date',
        'in_qty',
        'out_qty',
        'details',
        'type',
        'ref_invoice_no',
        'status',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('vendor_filter', function ($builder) {
            Helper::vendorScope($builder);
        });
    }

    /**
     * Get the product that owns the stock.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
