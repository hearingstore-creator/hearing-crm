<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffBreak extends Model
{
    use HasFactory;

    protected $table = 'staff_breaks';

    protected $fillable = [
        'user_id',
        'vendor_id',
        'day_of_week',
        'name',
        'start_time',
        'end_time',
        'is_active',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_active'   => 'boolean',
    ];

    public function dayName(): string
    {
        if (!$this->day_of_week) {
            return 'All Working Days (Consistent)';
        }

        return BranchSchedule::isoDayName($this->day_of_week);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function branch()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }
}
