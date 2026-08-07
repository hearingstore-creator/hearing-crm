<?php

namespace App\Enums;

enum HearingDegree: string
{
    case MILD = 'mild';
    case MODERATE = 'moderate';
    case MODERATELY_SEVERE = 'moderately_severe';
    case SEVERE = 'severe';
    case PROFOUND = 'profound';
    case TOTAL_LOSS = 'total_loss';

    public function label(): string
    {
        return match($this) {
            self::MILD => 'Mild',
            self::MODERATE => 'Moderate',
            self::MODERATELY_SEVERE => 'Moderately Severe',
            self::SEVERE => 'Severe',
            self::PROFOUND => 'Profound',
            self::TOTAL_LOSS => 'Total Loss',
        };
    }
}
