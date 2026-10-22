<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A reply received from the SMS gateway. */
class SmsInbound extends Model
{
    protected $table = 'sms_inbound';

    protected $fillable = ['phone_number', 'message', 'action', 'sms_log_id', 'received_at'];

    protected $casts = ['received_at' => 'datetime'];
}
