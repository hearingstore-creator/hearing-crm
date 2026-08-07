<?php

namespace App\Enums;

enum ServiceCategory: string
{
    case DIAGNOSTIC = 'diagnostic';
    case CONSULTATION = 'consultation';
    case TREATMENT = 'treatment';
    case REMOTE = 'remote';

    public function label(): string
    {
        return match ($this) {
            self::DIAGNOSTIC => 'Diagnostic',
            self::CONSULTATION => 'Consultation',
            self::TREATMENT => 'Treatment',
            self::REMOTE => 'Remote',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DIAGNOSTIC => 'blue',
            self::CONSULTATION => 'green',
            self::TREATMENT => 'orange',
            self::REMOTE => 'purple',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DIAGNOSTIC => 'fas fa-stethoscope',
            self::CONSULTATION => 'fas fa-comments',
            self::TREATMENT => 'fas fa-procedures',
            self::REMOTE => 'fas fa-video',
        };
    }
}
