<?php

namespace App\Models;

use App\Classes\Helper;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SaleDetail extends Model
{
    use HasFactory, HelperTrait;

    protected $fillable = [
        'sale_master_id',
        'product_id',
        'batch_id',
        'serial_no',
        'qty',
        'purchase_price',
        'mrp_price',
        'discount'
    ];

    protected $casts = [
        'serial_no' => 'array'
    ];

    protected $appends = ['stock'];

     public function salesMaster()
    {
        return $this->belongsTo(SaleMaster::class, 'sale_master_id');
    }

    public function saleMaster()
    {
        return $this->belongsTo(SaleMaster::class, 'sale_master_id');
    }

    public function product()
    {
       return $this->belongsTo(Product::class);
    }

    public function batch()
    {
       return $this->belongsTo(Batch::class);
    }

    public function getStockAttribute()
    {
        if (!empty($this->serial_no)) {
             return Stock::where('product_id', $this->product_id)
                    ->whereNull('vendor_id')
                    ->value(DB::raw("SUM(in_qty - out_qty)")) ?? 0;
        } else {
            $data = Batch::where('id', $this->batch_id)
            ->value(DB::raw("coalesce(SUM(purchase_qty),0) + coalesce(SUM(sale_return_qty),0) - coalesce(SUM(sale_qty),0) - coalesce(SUM(purchase_return_qty),0)"));
            return $data;
        }
    }

    protected static function booted(): void
    {
        static::addGlobalScope('vendor_relation_filter', function ($builder) {
            Helper::vendorRelationScope($builder, 'salesMaster');
        });
    }
}
