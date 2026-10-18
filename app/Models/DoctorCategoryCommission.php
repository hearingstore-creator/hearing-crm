<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorCategoryCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_id',
        'category_id',
        'commission_type',
        'commission_value',
    ];

    protected $casts = [
        'commission_value' => 'float',
    ];

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
