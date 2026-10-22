<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Country settings of this installation (Settings → Region & privacy): currency, phone code, time zone, consent rule.
 * Read once and cached; Settings forgets the cache when they change. Defaults are Bangladesh.
 */
class Region
{
    public const CACHE_KEY = 'crm.region';

    private const DEFAULTS = [
        'currency_code' => 'BDT', 'currency_symbol' => '৳', 'country_code' => 'BD', 'phone_prefix' => '880',
        'timezone' => 'Asia/Dhaka', 'marketing_needs_consent' => false,
    ];

    private static ?array $memo = null;

    public static function all(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }
        try {
            $row = Cache::rememberForever(self::CACHE_KEY, function () {
                if (!Schema::hasTable('settings') || !Schema::hasColumn('settings', 'currency_code')) {
                    return [];
                }

                return (array) (DB::table('settings')->where('id', 1)->first(array_keys(self::DEFAULTS)) ?? []);
            });
        } catch (\Throwable) {
            $row = [];
        }
        $values = array_merge(self::DEFAULTS, array_filter($row, fn ($v) => $v !== null && $v !== ''));
        $values['marketing_needs_consent'] = (bool) $values['marketing_needs_consent'];
        $values['phone_prefix'] = preg_replace('/\D/', '', (string) $values['phone_prefix']) ?: '880';

        return self::$memo = $values;
    }

    public static function forget(): void
    {
        self::$memo = null;
        Cache::forget(self::CACHE_KEY);
    }

    public static function currencySymbol(): string
    {
        return (string) self::all()['currency_symbol'];
    }

    public static function currencyCode(): string
    {
        return strtoupper((string) self::all()['currency_code']);
    }

    public static function phonePrefix(): string
    {
        return (string) self::all()['phone_prefix'];
    }

    public static function isBangladesh(): bool
    {
        return self::phonePrefix() === '880';
    }

    public static function timezone(): string
    {
        $tz = (string) self::all()['timezone'];

        return in_array($tz, timezone_identifiers_list(), true) ? $tz : 'Asia/Dhaka';
    }

    public static function needsConsent(): bool
    {
        return (bool) self::all()['marketing_needs_consent'];
    }

    /**
     * May this person get promotional messages? Always yes when the consent rule is off;
     * with the rule on, only people whose lead says they agreed. Staff, doctors and unknown recipients are not patients: yes.
     */
    public static function mayMarketTo(string $recipientType, ?int $recipientId): bool
    {
        if (!self::needsConsent()) {
            return true;
        }
        $leadId = match ($recipientType) {
            'lead' => $recipientId,
            'customer' => $recipientId ? DB::table('customers')->where('id', $recipientId)->value('lead_id') : null,
            default => -1,
        };
        if ($leadId === -1) {
            return true;
        }

        return $leadId && (bool) DB::table('leads')->where('id', $leadId)->value('marketing_consent');
    }

    /** Amount with the installation's currency symbol. */
    public static function money($value, int $decimals = 2): string
    {
        return self::currencySymbol() . number_format((float) $value, $decimals);
    }
}
