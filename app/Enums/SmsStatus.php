<?php

namespace App\Enums;

enum SmsStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case FAILED = 'failed';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::SENT => 'Sent',
            self::FAILED => 'Failed',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => 'yellow',
            self::SENT => 'green',
            self::FAILED => 'red',
        };
    }
}
