<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case SCHEDULED = 'scheduled';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    // case NO_SHOW = 'no_show';
    // case RESCHEDULED = 'rescheduled';

    public function label(): string
    {
        return match ($this) {
            self::SCHEDULED => 'Scheduled',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            // self::NO_SHOW => 'No Show',
            //self::RESCHEDULED => 'Rescheduled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SCHEDULED => 'blue',
            self::COMPLETED => 'green',
            self::CANCELLED => 'red',
           // self::NO_SHOW => 'yellow',
            //self::RESCHEDULED => 'orange',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SCHEDULED => 'fas fa-clock',
            self::COMPLETED => 'fas fa-check-circle',
            self::CANCELLED => 'fas fa-times-circle',
            // self::NO_SHOW => 'fas fa-user-slash',
            //self::RESCHEDULED => 'fas fa-calendar-alt',
        };
    }

    public function colorHex(): string
    {
        return match ($this) {
            self::SCHEDULED => '#007bff',
            self::COMPLETED => '#28a745',
            self::CANCELLED => '#dc3545',
           // self::NO_SHOW => '#ffc107',
            //self::RESCHEDULED => '#fd7e14',
        };
    }

    public function bgColor(): string
    {
        return match ($this) {
            self::SCHEDULED => '#e7f1ff',
            self::COMPLETED => '#e8f5e9',
            self::CANCELLED => '#fdecea',
            // self::NO_SHOW => '#fff8e1',
            //self::RESCHEDULED => '#fff3e0',
        };
    }

    public function canBeEdited(): bool
    {
        return $this === self::SCHEDULED;
    }

    // public function canBeCancelled(): bool
    // {
    //     return in_array($this, [self::SCHEDULED, self::RESCHEDULED]);
    // }
}
