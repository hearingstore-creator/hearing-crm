<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory, HelperTrait;

    protected $fillable = [
        'category_id',
        'type_id',
        'brand_id',
        'model',
        'battery_size',
        'usage_months',
        'warranty_inventory_months',
        'warranty_inventory_alert_date',
        'warranty_sales_months',
        'warranty_sales_expiry_date',
        'reorder_level',
        'service_interval_months',
    ];

    protected $casts = [
        'warranty_inventory_months' => 'integer',
        'warranty_sales_months' => 'integer',
        'usage_months' => 'integer',
        'reorder_level' => 'integer',
        'service_interval_months' => 'integer',
        'warranty_inventory_alert_date' => 'date',
        'warranty_sales_expiry_date' => 'date',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function type()
    {
        return $this->belongsTo(Type::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function manufacturer()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function getMedicineNameAttribute(): string
    {
        return $this->product_name;
    }

    /**
     * Display name accessor (Brand - Model)
     */
    public function getProductNameAttribute(): string
    {
        $brand = $this->brand->brand_name ?? '';
        $model = $this->model ?? '';

        if ($brand && $model) {
            return $brand . ' - ' . $model;
        }

        return $model ?: ($brand ?: 'Product #' . $this->id);
    }

    /**
     * Get the latest MRP / Retail Selling Price for this product.
     */
    public function getLatestMrpAttribute(): float
    {
        $mrp = PurchaseDetail::where('product_id', $this->id)
            ->whereNotNull('mrp_price')
            ->where('mrp_price', '>', 0)
            ->latest('id')
            ->value('mrp_price');

        if (!$mrp) {
            $mrp = SaleDetail::where('product_id', $this->id)
                ->whereNotNull('mrp_price')
                ->where('mrp_price', '>', 0)
                ->latest('id')
                ->value('mrp_price');
        }

        return (float) ($mrp ?? 0);
    }
}
