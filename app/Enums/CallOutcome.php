<?php

namespace App\Enums;

/** One-tap call results. Each one decides the next step so staff never have to type after a call. */
enum CallOutcome: string
{
    case NO_ANSWER      = 'no_answer';
    case BUSY           = 'busy';
    case CALL_BACK      = 'call_back';
    case INTERESTED     = 'interested';
    case NOT_INTERESTED = 'not_interested';
    case WRONG_NUMBER   = 'wrong_number';

    public function label(): string
    {
        return match ($this) {
            self::NO_ANSWER      => 'No answer',
            self::BUSY           => 'Busy',
            self::CALL_BACK      => 'Call back',
            self::INTERESTED     => 'Interested',
            self::NOT_INTERESTED => 'Not interested',
            self::WRONG_NUMBER   => 'Wrong number',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::NO_ANSWER      => 'fas fa-phone-slash',
            self::BUSY           => 'fas fa-hourglass-half',
            self::CALL_BACK      => 'fas fa-redo',
            self::INTERESTED     => 'fas fa-thumbs-up',
            self::NOT_INTERESTED => 'fas fa-thumbs-down',
            self::WRONG_NUMBER   => 'fas fa-ban',
        };
    }

    /** Minutes (office hours) until the automatic next call; null = no automatic call. */
    public function retryMinutes(): ?int
    {
        return match ($this) {
            self::NO_ANSWER  => 120,
            self::BUSY       => 30,
            self::INTERESTED => 24 * 60,
            default          => null,
        };
    }

    /** Results that usually end the deal: the screen suggests marking it Lost. */
    public function suggestsLost(): bool
    {
        return in_array($this, [self::NOT_INTERESTED, self::WRONG_NUMBER], true);
    }
}
