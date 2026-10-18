<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepairStatusLog extends Model
{
    use HasFactory;

    protected $table = 'repair_status_logs';

    public $timestamps = false;

    protected $fillable = [
        'repair_job_id',
        'from_status',
        'to_status',
        'remarks',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function repairJob()
    {
        return $this->belongsTo(RepairJob::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
