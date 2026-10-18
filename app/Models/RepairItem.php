<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepairItem extends Model
{
    use HasFactory;

    protected $table = 'repair_items';

    protected $fillable = [
        'repair_job_id',
        'item_type',
        'product_id',
        'item_name',
        'qty',
        'customer_unit_price',
        'customer_total',
        'supplier_unit_cost',
        'supplier_total',
        'is_warranty_covered',
    ];

    protected $casts = [
        'qty'                 => 'decimal:2',
        'customer_unit_price' => 'decimal:2',
        'customer_total'      => 'decimal:2',
        'supplier_unit_cost'  => 'decimal:2',
        'supplier_total'      => 'decimal:2',
        'is_warranty_covered' => 'boolean',
    ];

    public function repairJob()
    {
        return $this->belongsTo(RepairJob::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
