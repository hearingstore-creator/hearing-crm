<?php

namespace App\Enums;

enum HearingType: string
{
    case CONDUCTIVE = 'conductive';
    case MIXED = 'mixed';
    case SENSORY_NEURAL = 'sensory_neural';

    public function label(): string
    {
        return match($this) {
            self::CONDUCTIVE => 'Conductive',
            self::MIXED => 'Mixed',
            self::SENSORY_NEURAL => 'Sensory Neural',
        };
    }
}
