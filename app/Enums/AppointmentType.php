<?php

namespace App\Enums;

enum AppointmentType: string
{
    case TELE_COUNSEL = 'tele_counsel';
    case OFFICE_VISIT = 'office_visit';
    case SERVICE = 'service';

    /**
     * Get label for UI
     */
    public function label(): string
    {
        return match ($this) {
            self::TELE_COUNSEL => 'Tele Counsel',
            self::OFFICE_VISIT => 'Office Visit',
            self::SERVICE => 'Service',
        };
    }
}
