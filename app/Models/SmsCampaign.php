<?php

namespace App\Models;

use App\Enums\SmsCampaignStatus;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsCampaign extends Model
{
    use HasFactory, HelperTrait;

    protected $fillable = [
        'name',
        'message_body',
        'sms_template_id',
        'recipient_type',
        'filter_criteria',
        'total_recipients',
        'sent_count',
        'failed_count',
        'status',
        'sent_at',
        'create_follow_up',
        'follow_up_days',
        'follow_up_assigned_user',
        'follow_up_mode',
        'scheduled_at',
        'approved_by',
        'approval_note',
    ];

    protected $casts = [
        'filter_criteria' => 'array',
        'total_recipients' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'sent_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    public function getStatusLabelAttribute(): ?string
    {
        $status = $this->status instanceof SmsCampaignStatus
            ? $this->status
            : SmsCampaignStatus::tryFrom($this->status);

        return $status?->label() ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): ?string
    {
        $status = $this->status instanceof SmsCampaignStatus
            ? $this->status
            : SmsCampaignStatus::tryFrom($this->status);

        return $status?->color();
    }

    public function smsTemplate()
    {
        return $this->belongsTo(SmsTemplate::class, 'sms_template_id', 'id');
    }

    public function logs()
    {
        return $this->hasMany(SmsLog::class, 'sms_campaign_id', 'id');
    }
}
