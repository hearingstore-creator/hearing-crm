<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SerialChain extends Model
{
    use HasFactory;

    protected $table = 'serial_chains';

    protected $fillable = [
        'serial_no',
        'product_id',
        'customer_id',
        'original_sale_id',
        'parent_serial_no',
        'replacement_job_id',
        'status',
        'warranty_start_date',
        'warranty_end_date',
        'is_void',
        'void_reason',
    ];

    protected $casts = [
        'warranty_start_date' => 'date',
        'warranty_end_date'   => 'date',
        'is_void'             => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function originalSale()
    {
        return $this->belongsTo(SaleMaster::class, 'original_sale_id');
    }

    public function replacementJob()
    {
        return $this->belongsTo(RepairJob::class, 'replacement_job_id');
    }

    public function parentChain()
    {
        return $this->belongsTo(SerialChain::class, 'parent_serial_no', 'serial_no');
    }

    public function childChains()
    {
        return $this->hasMany(SerialChain::class, 'parent_serial_no', 'serial_no');
    }
}
