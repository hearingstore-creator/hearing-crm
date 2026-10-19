<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The one date filter every report uses: a preset (This month, Last month ...) or a custom range.
 * Report screens read `period`, `start_date` and `end_date` from the request and get back [start, end, key].
 */
class ReportPeriod
{
    public const PERIODS = [
        'today'        => 'Today',
        'yesterday'    => 'Yesterday',
        'this_week'    => 'This week',
        'this_month'   => 'This month',
        'last_month'   => 'Last month',
        'this_quarter' => 'This quarter',
        'this_year'    => 'This year',
        'last_year'    => 'Last year',
        'custom'       => 'Custom range',
    ];

    /** @return array{0: Carbon, 1: Carbon, 2: string} */
    public static function resolve(Request $request, string $default = 'this_month'): array
    {
        $period = (string) $request->input('period', $default);
        if ($request->filled('start_date') && $request->filled('end_date') && ! $request->filled('period')) {
            $period = 'custom';
        }
        if (! array_key_exists($period, self::PERIODS)) {
            $period = $default;
        }

        $now = now();
        [$start, $end] = match ($period) {
            'today'        => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday'    => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'this_week'    => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_month'   => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'this_year'    => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year'    => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'custom'       => [self::date($request->input('start_date'), $now->copy()->startOfMonth())->startOfDay(), self::date($request->input('end_date'), $now)->endOfDay()],
            default        => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };

        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end, $period];
    }

    private static function date($value, Carbon $fallback): Carbon
    {
        try {
            return $value ? Carbon::parse($value) : $fallback->copy();
        } catch (\Throwable) {
            return $fallback->copy();
        }
    }
}
