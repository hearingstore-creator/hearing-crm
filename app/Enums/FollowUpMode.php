<?php

namespace App\Enums;

enum FollowUpMode: string
{
    case PHONE_CALL = 'phone_call';
    case SMS = 'sms';
    case EMAIL = 'email';
    case VISIT = 'visit';
    case OTHER = 'other';

    public function label(): string
    {
        return match($this) {
            self::PHONE_CALL => 'Phone call',
            self::SMS => 'SMS',
            self::EMAIL => 'Email',
            self::VISIT => 'Visit',
            self::OTHER => 'Other',
        };
    }
}
