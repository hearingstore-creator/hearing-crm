<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * DataScope — decides whose records a user may see in dashboards and KPI cards.
 *
 *  - Super Admin, or any role with `dashboard.view_all_data`  → everything
 *  - Vendor   → everything *within* their vendor (models already carry a global vendor scope)
 *  - Everyone else → only records they created or are assigned to
 *
 * Read-only helper: it never changes what a user can *edit*, only what is counted/listed.
 */
class DataScope
{
    protected static ?bool $seesAll = null;

    public static function user(): ?User
    {
        return auth()->user();
    }

    public static function uid(): ?int
    {
        return auth()->id();
    }

    /** Clear the per-request cache (useful in tests / queue workers after switching users). */
    public static function reset(): void
    {
        static::$seesAll = null;
    }

    /** True when the current user should see company-wide (or full vendor) data. */
    public static function seesAll(): bool
    {
        if (static::$seesAll !== null) {
            return static::$seesAll;
        }

        $user = static::user();

        if (!$user) {
            return static::$seesAll = false;
        }

        return static::$seesAll = $user->isSuperAdmin()
            || $user->isVendor()
            || $user->hasPermission('dashboard.view_all_data');
    }

    /** Gate shortcut for widget permissions (Super Admin / vendor config handled by the gate). */
    public static function can(string $permission): bool
    {
        return auth()->check() && Gate::allows('permission', $permission);
    }

    /** Short label shown in the UI so users know which data they are looking at. */
    public static function label(): string
    {
        $user = static::user();
        if ($user && $user->isVendor()) {
            return 'Your vendor data';
        }

        return static::seesAll() ? 'All data' : 'My data';
    }

    // ------------------------------------------------------------------
    //  Query scopes (each returns the same builder for chaining)
    // ------------------------------------------------------------------

    public static function leads(Builder $q): Builder
    {
        if (static::seesAll()) {
            return $q;
        }
        $uid = static::uid();
        $table = $q->getModel()->getTable();

        return $q->where(function ($w) use ($uid, $table) {
            $w->where("$table.created_by", $uid)
              ->orWhereHas('followUps', fn ($f) => $f->where('assigned_user', $uid));
        });
    }

    public static function customers(Builder $q): Builder
    {
        if (static::seesAll()) {
            return $q;
        }
        $uid = static::uid();
        $table = $q->getModel()->getTable();

        return $q->where(function ($w) use ($uid, $table) {
            $w->where("$table.created_by", $uid)
              ->orWhereHas('lead', fn ($l) => $l->where('created_by', $uid));
        });
    }

    public static function followUps(Builder $q): Builder
    {
        if (static::seesAll()) {
            return $q;
        }
        $uid = static::uid();
        $table = $q->getModel()->getTable();

        return $q->where(fn ($w) => $w->where("$table.assigned_user", $uid)->orWhere("$table.created_by", $uid));
    }

    /** Tasks & tickets share the same ownership columns. */
    public static function assignable(Builder $q): Builder
    {
        if (static::seesAll()) {
            return $q;
        }
        $uid = static::uid();
        $table = $q->getModel()->getTable();

        return $q->where(fn ($w) => $w->where("$table.assigned_to", $uid)->orWhere("$table.created_by", $uid));
    }

    public static function appointments(Builder $q): Builder
    {
        $table = $q->getModel()->getTable();

        if (static::seesAll()) {
            if (request()->filled('branch_id')) {
                $q->where("$table.branch_id", (int) request('branch_id'));
            }
            return $q;
        }

        $user = static::user();
        $branchId = $user ? $user->branchId() : null;

        if ($branchId) {
            return $q->where("$table.branch_id", $branchId);
        }

        $uid = static::uid();
        return $q->where(fn ($w) => $w->where("$table.user_id", $uid)->orWhere("$table.created_by", $uid));
    }

    /** Sales / purchases / ledgers — anything with a created_by column. */
    public static function ownedBy(Builder $q): Builder
    {
        if (static::seesAll()) {
            return $q;
        }
        $table = $q->getModel()->getTable();

        return $q->where("$table.created_by", static::uid());
    }
}
