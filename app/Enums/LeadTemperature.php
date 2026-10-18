<?php

namespace App\Enums;

enum LeadTemperature: string
{
    case HOT = 'hot';
    case WARM = 'warm';
    case COLD = 'cold';

    public function label(): string
    {
        return match ($this) {
            self::HOT => 'Hot Lead',
            self::WARM => 'Warm Lead',
            self::COLD => 'Cold Lead',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::HOT => 'fas fa-fire',
            self::WARM => 'fas fa-sun',
            self::COLD => 'fas fa-snowflake',
        };
    }

    /** Theme colour family used for badges / cards. */
    public function color(): string
    {
        return match ($this) {
            self::HOT => 'danger',
            self::WARM => 'warning',
            self::COLD => 'info',
        };
    }

    public static function fromValueOrDefault(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::WARM;
    }
}
