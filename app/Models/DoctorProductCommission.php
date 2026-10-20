<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** A doctor's commission for one product or model: percent of the line, or a fixed amount per unit. */
class DoctorProductCommission extends Model
{
    use HasFactory;

    protected $fillable = ['doctor_id', 'product_id', 'commission_type', 'commission_value'];

    protected $casts = ['commission_value' => 'float'];

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
