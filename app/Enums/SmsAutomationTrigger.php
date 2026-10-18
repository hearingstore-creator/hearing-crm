<?php

namespace App\Enums;

enum SmsAutomationTrigger: string
{
    case LEAD_CREATED = 'lead_created';
    case CUSTOMER_CREATED = 'customer_created';
    case CUSTOMER_FIRST_SALE = 'customer_first_sale';
    case APPOINTMENT_BOOKED = 'appointment_booked';
    case APPOINTMENT_RESCHEDULED = 'appointment_rescheduled';
    case APPOINTMENT_REMINDER = 'appointment_reminder';
    case TICKET_CREATED = 'ticket_created';
    case WARRANTY_EXPIRING = 'warranty_expiring';
    case MEMBERSHIP_UPGRADE = 'membership_upgrade';
    case POINTS_EXPIRING = 'points_expiring';
    /** Legacy: never fired, hidden from the picker; kept so old rows still show a label. */
    case POINTS_MILESTONE = 'points_milestone';

    public function label(): string
    {
        return match ($this) {
            self::LEAD_CREATED => 'Lead Created',
            self::CUSTOMER_CREATED => 'Customer Registered',
            self::CUSTOMER_FIRST_SALE => 'First Sale',
            self::APPOINTMENT_BOOKED => 'Appointment Booked',
            self::APPOINTMENT_RESCHEDULED => 'Appointment Rescheduled',
            self::APPOINTMENT_REMINDER => 'Appointment Reminder (1 day before)',
            self::TICKET_CREATED => 'Service Ticket Created',
            self::WARRANTY_EXPIRING => 'Warranty Expiring (30 days before)',
            self::POINTS_MILESTONE => 'Points Milestone (not used)',
            self::MEMBERSHIP_UPGRADE => 'Membership Upgrade',
            self::POINTS_EXPIRING => 'Points Expiring (7 days before)',
        };
    }

    /** Marketing-type messages: skipped for opted-out numbers. Service messages always go out. */
    public function isPromotional(): bool
    {
        return in_array($this, [self::LEAD_CREATED, self::WARRANTY_EXPIRING, self::MEMBERSHIP_UPGRADE, self::POINTS_EXPIRING, self::CUSTOMER_FIRST_SALE], true);
    }

    /** Triggers that can be chosen for a new/edited automation. */
    public static function selectable(): array
    {
        return array_values(array_filter(self::cases(), fn ($c) => $c !== self::POINTS_MILESTONE));
    }
}
