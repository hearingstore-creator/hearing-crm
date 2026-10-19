<?php

namespace App\Models;

use App\Classes\Helper;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Batch extends Model
{
    use HasFactory, HelperTrait;

    protected $fillable = [
        'product_id',
        'vendor_id',
        'batch_no',
        'ref_invoice_no',
        'type',
        'purchase_price',
        'purchase_qty',
        'sale_qty',
        'purchase_return_qty',
        'sale_return_qty',
        'adjust_qty',
    ];
    protected $appends = ['stock'];

    public function product()
    {
       return $this->belongsTo(Product::class);
    }

    public function medicine()
    {
       return $this->belongsTo(Product::class, 'product_id');
    }

    public function getStockAttribute()
    {
        $query = Batch::where('id', $this->id);
        
        return $query->value(DB::raw("coalesce(SUM(purchase_qty),0) + coalesce(SUM(sale_return_qty),0) + coalesce(SUM(adjust_qty),0) - coalesce(SUM(sale_qty),0) - coalesce(SUM(purchase_return_qty),0)"));
    }

    protected static function booted(): void
    {
        static::addGlobalScope('vendor_filter', function ($builder) {
            Helper::vendorScope($builder);
        });
    }
}
