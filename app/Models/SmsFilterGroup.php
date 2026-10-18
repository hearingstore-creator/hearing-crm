<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsFilterGroup extends Model
{
    protected $fillable = ['name', 'recipient_type', 'filter_criteria', 'created_by'];

    protected $casts = [
        'filter_criteria' => 'array',
    ];
}
