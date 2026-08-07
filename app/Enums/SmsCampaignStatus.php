<?php

namespace App\Enums;

enum SmsCampaignStatus: string
{
    case SENDING = 'sending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function label(): string
    {
        return match($this) {
            self::SENDING => 'Sending',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::SENDING => 'blue',
            self::COMPLETED => 'green',
            self::FAILED => 'red',
        };
    }
}
