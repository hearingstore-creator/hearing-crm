<?php

namespace App\Enums;

enum SmsTemplateCategory: string
{
    case WARRANTY = 'warranty';
    case LEAD_ONBOARDING = 'lead_onboarding';
    case CUSTOMER_ONBOARDING = 'customer_onboarding';
    case APPOINTMENT = 'appointment';
    case MEMBERSHIP = 'membership';

    public function label(): string
    {
        return match($this) {
            self::WARRANTY => 'Warranty',
            self::LEAD_ONBOARDING => 'Lead Onboarding',
            self::CUSTOMER_ONBOARDING => 'Customer Onboarding',
            self::APPOINTMENT => 'Appointment',
            self::MEMBERSHIP => 'Membership',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::WARRANTY => 'orange',
            self::LEAD_ONBOARDING => 'blue',
            self::CUSTOMER_ONBOARDING => 'green',
            self::APPOINTMENT => 'indigo',
            self::MEMBERSHIP => 'teal',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::WARRANTY => 'fas fa-shield-alt',
            self::LEAD_ONBOARDING => 'fas fa-user-plus',
            self::CUSTOMER_ONBOARDING => 'fas fa-handshake',
            self::APPOINTMENT => 'fas fa-calendar-check',
            self::MEMBERSHIP => 'fas fa-id-card',
        };
    }
}
