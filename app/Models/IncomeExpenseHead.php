<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncomeExpenseHead extends Model
{
    use HasFactory, HelperTrait;

    protected $fillable = [
        'head_type',
        'head_name',
        'status',
    ];
}
