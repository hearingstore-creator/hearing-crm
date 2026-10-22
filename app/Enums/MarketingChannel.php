<?php

namespace App\Enums;

/** Where a lead or a sale came from, for marketing cost and return reports. */
enum MarketingChannel: string
{
    case FACEBOOK = 'facebook';
    case INSTAGRAM = 'instagram';
    case WHATSAPP = 'whatsapp';
    case GOOGLE = 'google';
    case YOUTUBE = 'youtube';
    case WEBSITE = 'website';
    case SMS = 'sms';
    case PRINT = 'print';
    case RADIO_TV = 'radio_tv';
    case EVENT = 'event';
    case DOCTOR = 'doctor';
    case REFERRAL = 'referral';
    case PHONE = 'phone';
    case WALK_IN = 'walk_in';
    case OTHER = 'other';
    case UNKNOWN = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::FACEBOOK => 'Facebook',
            self::INSTAGRAM => 'Instagram',
            self::WHATSAPP => 'WhatsApp',
            self::GOOGLE => 'Google',
            self::YOUTUBE => 'YouTube',
            self::WEBSITE => 'Website (organic)',
            self::SMS => 'SMS',
            self::PRINT => 'Print / leaflet',
            self::RADIO_TV => 'Radio / TV',
            self::EVENT => 'Hearing camp / event',
            self::DOCTOR => 'Doctor / referrer',
            self::REFERRAL => 'Patient referral',
            self::PHONE => 'Phone call',
            self::WALK_IN => 'Walk-in',
            self::OTHER => 'Other',
            self::UNKNOWN => 'Unknown / direct',
        };
    }

    /** Channels a campaign can be created for (Unknown is only a reporting bucket). */
    public static function forCampaigns(): array
    {
        return array_values(array_filter(self::cases(), fn ($c) => $c !== self::UNKNOWN));
    }

    /** The lead form's "Source of entry" as a channel. */
    public static function fromSource(?string $source): ?self
    {
        return match ($source) {
            'facebook' => self::FACEBOOK,
            'instagram' => self::INSTAGRAM,
            'whatsapp' => self::WHATSAPP,
            'google' => self::GOOGLE,
            'website' => self::WEBSITE,
            'phone_call' => self::PHONE,
            'campaign_event' => self::EVENT,
            'referral' => self::REFERRAL,
            'other' => self::OTHER,
            default => null,
        };
    }

    /** A utm_source value from an ad link as a channel. */
    public static function fromUtmSource(?string $utmSource): ?self
    {
        $s = mb_strtolower(trim((string) $utmSource));
        if ($s === '') {
            return null;
        }

        return match (true) {
            in_array($s, ['fb', 'facebook', 'facebook.com', 'm.facebook.com', 'meta', 'messenger'], true) => self::FACEBOOK,
            in_array($s, ['ig', 'instagram', 'instagram.com'], true) => self::INSTAGRAM,
            in_array($s, ['wa', 'whatsapp'], true) => self::WHATSAPP,
            in_array($s, ['google', 'adwords', 'googleads', 'google_ads', 'gads'], true) => self::GOOGLE,
            in_array($s, ['youtube', 'yt'], true) => self::YOUTUBE,
            in_array($s, ['sms', 'text'], true) => self::SMS,
            in_array($s, ['qr', 'print', 'leaflet', 'flyer', 'newspaper'], true) => self::PRINT,
            default => self::OTHER,
        };
    }
}
