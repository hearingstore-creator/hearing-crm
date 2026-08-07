<?php

namespace App\Enums;

enum SmsType: string
{
    case BULK = 'bulk';
    case AUTOMATED = 'automated';
    case MANUAL = 'manual';

    public function label(): string
    {
        return match($this) {
            self::BULK => 'Bulk',
            self::AUTOMATED => 'Automated',
            self::MANUAL => 'Manual',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::BULK => 'blue',
            self::AUTOMATED => 'purple',
            self::MANUAL => 'gray',
        };
    }
}
