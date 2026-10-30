<?php

namespace App\Enums;

enum SmsCampaignStatus: string
{
    case SENDING = 'sending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case SCHEDULED = 'scheduled';
    case PENDING_APPROVAL = 'pending_approval';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::SENDING => 'Sending',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::SCHEDULED => 'Scheduled',
            self::PENDING_APPROVAL => 'Awaiting approval',
            self::REJECTED => 'Rejected',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::SENDING => 'blue',
            self::COMPLETED => 'green',
            self::FAILED => 'red',
            self::SCHEDULED => 'gray',
            self::PENDING_APPROVAL => 'gray',
            self::REJECTED => 'red',
        };
    }
}
