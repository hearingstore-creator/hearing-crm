<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    use HasFactory, HelperTrait;
    protected $fillable = ['brand_name', 'status'];

    public function getManufacturerNameAttribute(): ?string
    {
        return $this->brand_name;
    }
}
