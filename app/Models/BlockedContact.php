<?php

namespace App\Models;

use App\Classes\Helper;
use Illuminate\Database\Eloquent\Model;

/**
 * Blocklist for website leads: phone (normalised), email (lower-case) or IP.
 * Type 'sms' = SMS opt-out list (normalised phone), managed in the SMS Center.
 */
class BlockedContact extends Model
{
    public const TYPES = ['phone', 'email', 'ip'];
    public const SMS_OPT_OUT = 'sms';

    protected $fillable = ['type', 'value', 'reason', 'created_by'];

    public static function normalize(string $type, ?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return match ($type) {
            'phone', self::SMS_OPT_OUT => Helper::normalizePhone($value),
            'email' => mb_strtolower($value),
            default => $value,
        };
    }

    /** True when any of the given contacts is blocked. Returns the matching type or null. */
    public static function match(?string $phone, ?string $email, ?string $ip): ?string
    {
        $pairs = array_filter([
            'phone' => self::normalize('phone', $phone),
            'email' => self::normalize('email', $email),
            'ip'    => self::normalize('ip', $ip),
        ]);
        if (!$pairs) {
            return null;
        }

        return static::where(function ($q) use ($pairs) {
            foreach ($pairs as $type => $value) {
                $q->orWhere(fn ($w) => $w->where('type', $type)->where('value', $value));
            }
        })->value('type');
    }
}
