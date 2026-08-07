<?php

namespace App\Enums;

enum LeadStatus: string
{
    case NEW = 'new';
    case VERIFIED = 'verified';
    case TELE_COUNSEL_APPOINTMENT = 'tele_counsel_appointment';
    case TELE_COUNSEL = 'tele_counsel';
    case STORE_APPOINTMENT = 'store_appointment';
    case COUNSEL = 'counsel';
    case TRIAL = 'trial';
    case WON = 'won';
    case LOST = 'lost';

    public function label(): string
    {
        return match($this) {
            self::NEW => 'New',
            self::VERIFIED => 'Verified',
            self::TELE_COUNSEL_APPOINTMENT => 'Tele-counsel Appointment',
            self::TELE_COUNSEL => 'Tele-counsel',
            self::STORE_APPOINTMENT => 'Store Appointment',
            self::COUNSEL => 'Counsel',
            self::TRIAL => 'Trial',
            self::WON => 'Won',
            self::LOST => 'Loss (Need to note reason and from whom we lost)',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::NEW => 'gray',
            self::VERIFIED => 'blue',
            self::TELE_COUNSEL_APPOINTMENT => 'yellow',
            self::TELE_COUNSEL => 'purple',
            self::STORE_APPOINTMENT => 'indigo',
            self::COUNSEL => 'pink',
            self::TRIAL => 'orange',
            self::WON => 'green',
            self::LOST => 'red',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::NEW => 'fas fa-plus',
            self::VERIFIED => 'fas fa-check-circle',
            self::TELE_COUNSEL_APPOINTMENT => 'fas fa-calendar',
            self::TELE_COUNSEL => 'fas fa-phone',
            self::STORE_APPOINTMENT => 'fas fa-hospital',
            self::COUNSEL => 'fas fa-user-md',
            self::TRIAL => 'fas fa-headphones',
            self::WON => 'fas fa-trophy',
            self::LOST => 'fas fa-times-circle',
        };
    }
}
