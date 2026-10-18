<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Resource extends Model
{
    protected $fillable = [
        'vendor_id',
        'name',
        'type',
        'capacity',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity'  => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'resource_id');
    }
}
