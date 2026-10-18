<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepairTask extends Model
{
    use HasFactory;

    protected $table = 'repair_tasks';

    protected $fillable = [
        'repair_job_id',
        'task_type',
        'title',
        'due_date',
        'is_completed',
        'completed_at',
        'completed_by',
    ];

    protected $casts = [
        'due_date'     => 'datetime',
        'completed_at' => 'datetime',
        'is_completed' => 'boolean',
    ];

    public function repairJob()
    {
        return $this->belongsTo(RepairJob::class, 'repair_job_id');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
