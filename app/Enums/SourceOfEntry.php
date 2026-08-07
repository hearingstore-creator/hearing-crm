<?php

namespace App\Enums;

enum SourceOfEntry: string
{
    case FACEBOOK = 'facebook';
    case INSTAGRAM = 'instagram';
    case REFERRAL = 'referral';
    case WEBSITE = 'website';
    case PHONE_CALL = 'phone_call';
    case CAMPAIGN_EVENT = 'campaign_event';
    case GOOGLE = 'google';
    case WHATSAPP = 'whatsapp';
    case OTHER = 'other';

    public function label(): string
    {
        return match($this) {
            self::FACEBOOK => 'Facebook',
            self::INSTAGRAM => 'Instagram',
            self::REFERRAL => 'Referral',
            self::WEBSITE => 'Website',
            self::PHONE_CALL => 'Phone Call',
            self::CAMPAIGN_EVENT => 'Campaign / Event',
            self::GOOGLE => 'Google',
            self::WHATSAPP => 'Whatsapp',
            self::OTHER => 'Other',
        };
    }
}
