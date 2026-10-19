<?php

namespace App\Models;

use App\Classes\Helper;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PurchaseDetail extends Model
{
    use HasFactory, HelperTrait;
    protected $fillable = [
        'purchase_master_id',
        'product_id',
        'batch_id',
        'serial_no',
        'qty',
        'purchase_price',
        'mrp_price',
    ];

    protected $casts = [
        'serial_no' => 'array'
    ];
    
    protected $appends = ['batch_stock'];

    public function product()
    {
       return $this->belongsTo(Product::class);
    }

    public function batch()
    {
       return $this->belongsTo(Batch::class);
    }

    public function purchaseMaster()
    {
        return $this->belongsTo(PurchaseMaster::class, 'purchase_master_id');
    }

    public function getBatchStockAttribute()
    {
        // $data = Stock::where('medicine_id', $this->medicine_id)
        // ->value(DB::raw("SUM(in_qty - out_qty)"));
        // return $data;
        $data = Batch::where('id', $this->batch_id)
        ->value(DB::raw("coalesce(SUM(purchase_qty),0) + coalesce(SUM(sale_return_qty),0) + coalesce(SUM(adjust_qty),0) - coalesce(SUM(sale_qty),0) - coalesce(SUM(purchase_return_qty),0)"));
        return $data;

    }

    protected static function booted(): void
    {
        static::addGlobalScope('vendor_relation_filter', function ($builder) {
            Helper::vendorRelationScope($builder, 'purchaseMaster');
        });
    }
}
