<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Template extends Model
{
    use HasFactory, HelperTrait;

    protected $fillable = [
        'title',
        'content',
        'status',
    ];
}
