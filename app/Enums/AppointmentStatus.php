<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case SCHEDULED = 'scheduled';
    case BOOKED = 'booked';
    case CONFIRMED = 'confirmed';
    case ARRIVED = 'arrived';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case NO_SHOW = 'no_show';
    case CANCELLED = 'cancelled';
    case CANCELLED_REFUNDED = 'cancelled_refunded';
    case CANCELLED_CREDIT_KEPT = 'cancelled_credit_kept';
    case RESCHEDULED = 'rescheduled';

    public function label(): string
    {
        return match ($this) {
            self::SCHEDULED => 'Scheduled',
            self::BOOKED => 'Booked',
            self::CONFIRMED => 'Confirmed',
            self::ARRIVED => 'Arrived / Waiting',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::NO_SHOW => 'No Show',
            self::CANCELLED => 'Cancelled',
            self::CANCELLED_REFUNDED => 'Cancelled – Refunded',
            self::CANCELLED_CREDIT_KEPT => 'Cancelled – Credit Kept',
            self::RESCHEDULED => 'Rescheduled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SCHEDULED, self::BOOKED => 'blue',
            self::CONFIRMED => 'teal',
            self::ARRIVED => 'amber',
            self::IN_PROGRESS => 'indigo',
            self::COMPLETED => 'green',
            self::NO_SHOW => 'gray',
            self::CANCELLED => 'red',
            self::CANCELLED_REFUNDED => 'orange',
            self::CANCELLED_CREDIT_KEPT => 'purple',
            self::RESCHEDULED => 'sky',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SCHEDULED, self::BOOKED => 'fas fa-clock',
            self::CONFIRMED => 'fas fa-check-circle',
            self::ARRIVED => 'fas fa-user-clock',
            self::IN_PROGRESS => 'fas fa-stethoscope',
            self::COMPLETED => 'fas fa-check-double',
            self::NO_SHOW => 'fas fa-user-slash',
            self::CANCELLED => 'fas fa-times-circle',
            self::CANCELLED_REFUNDED => 'fas fa-undo-alt',
            self::CANCELLED_CREDIT_KEPT => 'fas fa-wallet',
            self::RESCHEDULED => 'fas fa-history',
        };
    }

    public function colorHex(): string
    {
        return match ($this) {
            self::SCHEDULED, self::BOOKED => '#2563eb',
            self::CONFIRMED => '#0d9488',
            self::ARRIVED => '#d97706',
            self::IN_PROGRESS => '#4f46e5',
            self::COMPLETED => '#16a34a',
            self::NO_SHOW => '#64748b',
            self::CANCELLED => '#dc2626',
            self::CANCELLED_REFUNDED => '#ea580c',
            self::CANCELLED_CREDIT_KEPT => '#7c3aed',
            self::RESCHEDULED => '#0284c7',
        };
    }

    public function bgColor(): string
    {
        return match ($this) {
            self::SCHEDULED, self::BOOKED => '#eff6ff',
            self::CONFIRMED => '#ccfbf1',
            self::ARRIVED => '#fef3c7',
            self::IN_PROGRESS => '#e0e7ff',
            self::COMPLETED => '#dcfce7',
            self::NO_SHOW => '#f1f5f9',
            self::CANCELLED => '#fee2e2',
            self::CANCELLED_REFUNDED => '#ffedd5',
            self::CANCELLED_CREDIT_KEPT => '#ede9fe',
            self::RESCHEDULED => '#e0f2fe',
        };
    }

    public function canBeEdited(): bool
    {
        return in_array($this, [
            self::SCHEDULED,
            self::BOOKED,
            self::CONFIRMED,
            self::ARRIVED,
            self::IN_PROGRESS,
        ], true);
    }
}
