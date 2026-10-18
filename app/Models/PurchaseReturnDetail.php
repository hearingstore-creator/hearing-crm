<?php

namespace App\Models;

use App\Classes\Helper;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturnDetail extends Model
{
    use HasFactory, HelperTrait;

    protected $fillable = [
        'purchase_return_id',
        'purchase_master_id',
        'product_id',
        'batch_id',
        'serial_no',
        'qty',
        'purchase_price',
        'total_price',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'serial_no' => 'array',
        'qty' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function purchaseReturn()
    {
        return $this->belongsTo(PurchaseReturn::class, 'purchase_return_id');
    }

    public function purchaseMaster()
    {
        return $this->belongsTo(PurchaseMaster::class, 'purchase_master_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('vendor_relation_filter', function ($builder) {
            Helper::vendorRelationScope($builder, 'purchaseReturn');
        });
    }
}
