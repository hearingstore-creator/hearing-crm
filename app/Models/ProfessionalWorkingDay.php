<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Model;

class ProfessionalWorkingDay extends Model
{
    use HelperTrait;

    protected $fillable = [
        'user_id',
        'day_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function day()
    {
        return $this->belongsTo(Day::class);
    }
}
