<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Salary extends Model
{
    
    use HasFactory, HelperTrait;
    protected $guarded = ['id'];

    public function employee(){
        return $this->belongsTo(Employee::class);
    }
}
