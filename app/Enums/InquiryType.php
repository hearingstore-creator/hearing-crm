<?php

namespace App\Enums;

enum InquiryType: string
{
    case HEARING_AID = 'hearing_aid';
    case HEARING_TEST = 'hearing_test';
    case EAR_MOLD = 'ear_mold';
    case REPAIR = 'repair';
    case BATTERY = 'battery';
    case COCHLEAR_IMPLANT = 'cochlear_implant';
    case EQUIPMENT = 'equipment';
    case ACCESSORIES = 'accessories';
    case OTHER = 'other';

    public function label(): string
    {
        return match($this) {
            self::HEARING_AID => 'Hearing Aid',
            self::HEARING_TEST => 'Hearing Test',
            self::EAR_MOLD => 'Ear Mold',
            self::REPAIR => 'Repair',
            self::BATTERY => 'Battery',
            self::COCHLEAR_IMPLANT => 'Cochlear Implant',
            self::EQUIPMENT => 'Equipment',
            self::ACCESSORIES => 'Accessories',
            self::OTHER => 'Other',
        };
    }
}
