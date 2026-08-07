<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerReturnDetail extends Model
{
    use HasFactory, HelperTrait;
    protected $guarded = ['id'];

    public function medicine()
    {
       return $this->belongsTo(Medicine::class);
    }
}
