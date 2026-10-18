<?php

namespace App\Enums;

/** Activity types on a deal (stored in lead_follow_ups.activity_type). */
enum ActivityType: string
{
    case CALL = 'call';
    case VISIT = 'visit';
    case SMS = 'sms';
    case WHATSAPP = 'whatsapp';
    case EMAIL = 'email';
    case TASK = 'task';
    case NOTE = 'note';

    public function label(): string
    {
        return match ($this) {
            self::CALL     => 'Call',
            self::VISIT    => 'Visit / Appointment',
            self::SMS      => 'SMS',
            self::WHATSAPP => 'WhatsApp',
            self::EMAIL    => 'Email',
            self::TASK     => 'Task',
            self::NOTE     => 'Note',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CALL     => 'fas fa-phone-alt',
            self::VISIT    => 'fas fa-calendar-check',
            self::SMS      => 'fas fa-sms',
            self::WHATSAPP => 'fab fa-whatsapp',
            self::EMAIL    => 'fas fa-envelope',
            self::TASK     => 'fas fa-tasks',
            self::NOTE     => 'fas fa-sticky-note',
        };
    }

    /** Old follow_up_mode value, kept filled so existing screens and reports still work. */
    public function legacyMode(): string
    {
        return match ($this) {
            self::CALL     => 'phone_call',
            self::VISIT    => 'visit',
            self::SMS      => 'sms',
            self::EMAIL    => 'email',
            default        => 'other',
        };
    }

    public static function fromLegacyMode(?string $mode): self
    {
        return match ($mode) {
            'phone_call' => self::CALL,
            'visit'      => self::VISIT,
            'sms'        => self::SMS,
            'email'      => self::EMAIL,
            default      => self::TASK,
        };
    }

    /** Types that can be scheduled (a note is only logged). */
    public static function schedulable(): array
    {
        return array_values(array_filter(self::cases(), fn ($t) => $t !== self::NOTE));
    }
}
