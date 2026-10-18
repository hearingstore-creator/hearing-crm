<?php

namespace App\Models;

use App\Enums\SmsStatus;
use App\Enums\SmsType;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    use HasFactory, HelperTrait;

    protected $fillable = [
        'sms_campaign_id',
        'recipient_type',
        'recipient_id',
        'phone_number',
        'message',
        'type',
        'status',
        'gateway_response',
        'error_message',
        'sent_at',
        'follow_up_created',
        'read_by_agent',
        'campaign_name',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function getTypeLabelAttribute(): string
    {
        $type = $this->type instanceof SmsType
            ? $this->type
            : SmsType::tryFrom($this->type);

        return $type?->label() ?? ucfirst($this->type);
    }

    public function getTypeColorAttribute(): string
    {
        $type = $this->type instanceof SmsType
            ? $this->type
            : SmsType::tryFrom($this->type);

        return $type?->color() ?? 'gray';
    }

    public function getStatusLabelAttribute(): ?string
    {
        $status = $this->status instanceof SmsStatus
            ? $this->status
            : SmsStatus::tryFrom($this->status);

        return $status?->label() ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): ?string
    {
        $status = $this->status instanceof SmsStatus
            ? $this->status
            : SmsStatus::tryFrom($this->status);

        return $status?->color();
    }

    public function campaign()
    {
        return $this->belongsTo(SmsCampaign::class, 'sms_campaign_id', 'id');
    }
}
