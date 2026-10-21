<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory, HelperTrait;
    protected $guarded = ['id'];

    protected $casts = [
        'sms_balance_checked_at' => 'datetime',
    ];
}
