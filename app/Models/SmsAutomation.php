<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsAutomation extends Model
{
    protected $fillable = [
        'name',
        'trigger_type',
        'description',
        'message_body',
        'sms_template_id',
        'is_active',
        'sent_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function smsTemplate()
    {
        return $this->belongsTo(SmsTemplate::class);
    }
}
