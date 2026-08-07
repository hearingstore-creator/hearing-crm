<?php

namespace App\Enums;

enum HearingLoss: string
{
    case RIGHT = 'right';
    case LEFT = 'left';

    public function label(): string
    {
        return match($this) {
            self::RIGHT => 'Right (Hearing Loss need to written or select)',
            self::LEFT => 'Left (Hearing Loss need to written or select)',
        };
    }
}
