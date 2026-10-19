<?php

namespace App\Enums;

enum RewardLedgerType: string
{
    case EARN = 'earn';
    case REDEEM = 'redeem';
    case REFERRAL = 'referral';
    case MILESTONE = 'milestone';

    public function label(): string
    {
        return match($this) {
            self::EARN => 'Earn',
            self::REDEEM => 'Redeem',
            self::REFERRAL => 'Referral',
            self::MILESTONE => 'Milestone',
        };
    }
}
