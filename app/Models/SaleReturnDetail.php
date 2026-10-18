<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleReturnDetail extends Model
{
    use HasFactory, HelperTrait;

    protected $table = 'sale_return_details';

    protected $fillable = [
        'sale_return_id',
        'sale_detail_id',
        'item_type',
        'product_id',
        'service_type_id',
        'service_detail_id',
        'batch_id',
        'serial_no',
        'sold_qty',
        'return_qty',
        'unit_price',
        'deduction_rate',
        'deduction_amount',
        'net_return_amount',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'serial_no'         => 'array',
        'sold_qty'          => 'decimal:2',
        'return_qty'        => 'decimal:2',
        'unit_price'        => 'decimal:2',
        'deduction_rate'    => 'decimal:2',
        'deduction_amount'  => 'decimal:2',
        'net_return_amount' => 'decimal:2',
    ];

    public function saleReturn()
    {
        return $this->belongsTo(SaleReturn::class, 'sale_return_id');
    }

    public function saleDetail()
    {
        return $this->belongsTo(SaleDetail::class, 'sale_detail_id');
    }

    public function serviceDetail()
    {
        return $this->belongsTo(ServiceDetail::class, 'service_detail_id');
    }

    public function serviceType()
    {
        return $this->belongsTo(ServiceType::class, 'service_type_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isService(): bool
    {
        return $this->item_type === 'service';
    }

    public function isProduct(): bool
    {
        return $this->item_type !== 'service';
    }
}
