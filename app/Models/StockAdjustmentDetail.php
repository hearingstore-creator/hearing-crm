<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentDetail extends Model
{
    protected $fillable = [
        'stock_adjustment_id', 'product_id', 'direction', 'qty', 'serial_no', 'batch_id', 'unit_cost', 'mrp_price', 'warranty_start_date',
    ];

    protected $casts = ['serial_no' => 'array', 'qty' => 'float', 'unit_cost' => 'float', 'mrp_price' => 'float', 'warranty_start_date' => 'date'];

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
