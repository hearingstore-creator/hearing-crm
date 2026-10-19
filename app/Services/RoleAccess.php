<?php

namespace App\Services;

use App\Models\Permission;
use Illuminate\Support\Collection;

/**
 * Plain-language view of roles, from config/role_templates.php: which permissions a starter template
 * grants, and what a role's permissions add up to per area (None / View only / Add and edit / Full control).
 */
class RoleAccess
{
    public const LABELS = ['none' => 'No access', 'view' => 'View only', 'work' => 'Add and edit', 'full' => 'Full control'];

    private const VIEW = ['index', 'view'];
    private const FULL = ['delete', 'cancel', 'void_warranty', 'transfer', 'refund', 'manage'];
    private const NEVER = ['view_all_branches']; // company-wide reading is granted on purpose, never by a template

    public static function templates(): array
    {
        return config('role_templates.templates', []);
    }

    /** @return array<string, string[]> area => prefixes */
    public static function areas(): array
    {
        return config('role_templates.areas', []);
    }

    /** Permission names a template grants (only ones that exist). */
    public static function templatePermissionNames(string $template): array
    {
        $t = self::templates()[$template] ?? null;
        if (!$t) {
            return [];
        }

        $byPrefix = self::permissionsByPrefix();
        $names = [];

        foreach ($t['grants'] as $area => $level) {
            foreach (self::areas()[$area] ?? [] as $prefix) {
                foreach ($byPrefix[$prefix] ?? [] as $action) {
                    if (self::allows($level, $action)) {
                        $names[] = $prefix . '.' . $action;
                    }
                }
            }
        }

        foreach ($byPrefix['dashboard'] ?? [] as $action) {
            $basic = in_array($action, config('role_templates.dashboard_basic', []), true);
            if (($t['dashboard'] ?? 'basic') === 'all' ? $action !== 'view_all_data' : $basic) {
                $names[] = 'dashboard.' . $action;
            }
        }

        return array_values(array_unique($names));
    }

    public static function allows(string $level, string $action): bool
    {
        if (in_array($action, self::NEVER, true)) {
            return false;
        }

        return match ($level) {
            'view'  => in_array($action, self::VIEW, true),
            'work'  => !in_array($action, self::FULL, true),
            default => true,
        };
    }

    /**
     * What a set of permission names adds up to.
     *
     * @param  iterable<string> $names
     * @return array<string, string> area => none|view|work|full
     */
    public static function summarise(iterable $names): array
    {
        $have = collect($names)->flip();
        $out = [];

        foreach (self::areas() as $area => $prefixes) {
            $level = 'none';
            foreach ($prefixes as $prefix) {
                foreach (self::permissionsByPrefix()[$prefix] ?? [] as $action) {
                    if (!isset($have[$prefix . '.' . $action]) || in_array($action, self::NEVER, true)) {
                        continue;
                    }
                    $rank = in_array($action, self::VIEW, true) ? 'view' : (in_array($action, self::FULL, true) ? 'full' : 'work');
                    $level = self::higher($level, $rank);
                }
            }
            $out[$area] = $level;
        }

        return $out;
    }

    public static function dashboardWidgets(iterable $names): int
    {
        return collect($names)->filter(fn ($n) => str_starts_with($n, 'dashboard.') && $n !== 'dashboard.index')->count();
    }

    private static function higher(string $a, string $b): string
    {
        $order = ['none' => 0, 'view' => 1, 'work' => 2, 'full' => 3];

        return $order[$b] > $order[$a] ? $b : $a;
    }

    private static ?array $byPrefix = null;

    public static function forget(): void
    {
        self::$byPrefix = null;
    }

    /** @return array<string, string[]> prefix => actions that exist */
    private static function permissionsByPrefix(): array
    {
        if (self::$byPrefix === null) {
            self::$byPrefix = [];
            foreach (Permission::pluck('name') as $name) {
                [$prefix, $action] = explode('.', $name, 2) + [1 => ''];
                self::$byPrefix[$prefix][] = $action;
            }
        }

        return self::$byPrefix;
    }
}
