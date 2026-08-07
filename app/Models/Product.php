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
        'warranty_sales_expiry_date'
    ];

    protected $casts = [
        'warranty_inventory_months' => 'integer',
        'warranty_sales_months' => 'integer',
        'usage_months' => 'integer',
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
}
