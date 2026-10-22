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
    case TICKET_SOLVED = 'ticket_solved';
    case WARRANTY_EXPIRING = 'warranty_expiring';
    case MEMBERSHIP_UPGRADE = 'membership_upgrade';
    case POINTS_EXPIRING = 'points_expiring';
    case REPAIR_RECEIVED = 'repair_received';
    case REPAIR_READY = 'repair_ready';
    case ORDER_READY = 'order_ready';
    case PAYMENT_RECEIVED = 'payment_received';
    case DUE_REMINDER = 'due_reminder';
    case REFERRAL_EARNED = 'referral_earned';
    case REFERRAL_PAID = 'referral_paid';
    case BIRTHDAY = 'birthday';
    case UPGRADE_DUE = 'upgrade_due';
    case SERVICE_DUE = 'service_due';
    case NPS_SURVEY = 'nps_survey';
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
            self::TICKET_SOLVED => 'Service Ticket Solved',
            self::WARRANTY_EXPIRING => 'Warranty Expiring (30 days before)',
            self::POINTS_MILESTONE => 'Points Milestone (not used)',
            self::MEMBERSHIP_UPGRADE => 'Membership Upgrade',
            self::POINTS_EXPIRING => 'Points Expiring (7 days before)',
            self::REPAIR_RECEIVED => 'Repair Received',
            self::REPAIR_READY => 'Repair Ready for Pickup',
            self::ORDER_READY => 'Order Ready for Pickup (Delivery Hub)',
            self::PAYMENT_RECEIVED => 'Payment Received',
            self::DUE_REMINDER => 'Payment Due Reminder',
            self::REFERRAL_EARNED => 'Referral Commission Earned',
            self::REFERRAL_PAID => 'Referral Commission Paid',
            self::BIRTHDAY => 'Birthday Greeting',
            self::UPGRADE_DUE => 'Hearing Aid Upgrade',
            self::SERVICE_DUE => 'Yearly Service Reminder',
            self::NPS_SURVEY => 'Satisfaction Survey',
        };
    }

    /** Marketing-type messages: skipped for opted-out numbers. Service messages always go out. */
    public function isPromotional(): bool
    {
        return in_array($this, [self::LEAD_CREATED, self::WARRANTY_EXPIRING, self::MEMBERSHIP_UPGRADE, self::POINTS_EXPIRING, self::CUSTOMER_FIRST_SALE, self::BIRTHDAY, self::UPGRADE_DUE], true);
    }

    /**
     * Essential messages tell a patient something they need to act on (come in, pick up, be there).
     * Everything else is a courtesy or marketing message and is the first to switch off when SMS costs climb.
     */
    public function isEssential(): bool
    {
        return in_array($this, [
            self::APPOINTMENT_BOOKED, self::APPOINTMENT_RESCHEDULED, self::APPOINTMENT_REMINDER,
            self::TICKET_CREATED, self::TICKET_SOLVED,
            self::REPAIR_RECEIVED, self::REPAIR_READY, self::ORDER_READY,
        ], true);
    }

    /** Triggers that can be chosen for a new/edited automation. */
    public static function selectable(): array
    {
        return array_values(array_filter(self::cases(), fn ($c) => $c !== self::POINTS_MILESTONE));
    }
}
