<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepairAttachment extends Model
{
    use HasFactory;

    protected $table = 'repair_attachments';

    protected $fillable = [
        'repair_job_id',
        'file_path',
        'attachment_type',
        'caption',
        'uploaded_by',
    ];

    public function repairJob()
    {
        return $this->belongsTo(RepairJob::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
