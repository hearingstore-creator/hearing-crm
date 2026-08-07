<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeLedger extends Model
{
    use HasFactory, HelperTrait;
    protected $guarded = ['id'];
}
