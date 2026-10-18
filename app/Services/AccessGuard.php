<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who may grant which access. One rule: nobody can give what they do not have.
 *
 * - Only Super Admin assigns the Super Admin role or edits / deletes a Super Admin account.
 * - Others may assign a role, or edit a role, only when every permission in it is one they hold,
 *   and never a role they belong to themselves.
 * - In the Users screen, others manage only staff accounts (not branch logins or professionals,
 *   whose rights come from config/vendor.php and config/professional.php) whose roles they could grant.
 */
class AccessGuard
{
    public const SUPER_ADMIN = 'Super Admin';

    public function __construct(private ?User $actor = null)
    {
        $this->actor = $actor ?? auth()->user();
    }

    public function isSuper(): bool
    {
        return (bool) $this->actor?->isSuperAdmin();
    }

    /** @return Collection<int, string> permission names the actor holds through roles */
    public function ownPermissionNames(): Collection
    {
        return collect($this->actor?->loadedPermissionNames() ?? []);
    }

    /** Roles the actor may hand out (to users) or edit. */
    public function grantableRoles(): Collection
    {
        $roles = Role::with('permissions:id,name')->orderBy('name')->get();
        if ($this->isSuper()) {
            return $roles;
        }

        $own = $this->ownPermissionNames()->flip();
        $mine = $this->actor->roles()->pluck('roles.id')->flip();

        return $roles->filter(fn (Role $role) => $role->name !== self::SUPER_ADMIN
            && !isset($mine[$role->id])
            && $role->permissions->every(fn ($p) => isset($own[$p->name])))->values();
    }

    public function canGrantRoleIds(array $roleIds): bool
    {
        $allowed = $this->grantableRoles()->pluck('id')->map(fn ($id) => (int) $id)->flip();

        return collect($roleIds)->every(fn ($id) => isset($allowed[(int) $id]));
    }

    /** May the actor change this role's permissions to the given ids? */
    public function canEditRole(Role $role, array $newPermissionIds): bool
    {
        if ($this->isSuper()) {
            return true;
        }
        if (!$this->grantableRoles()->contains('id', $role->id)) {
            return false; // Super Admin role, a role the actor has, or a role above them
        }
        $own = $this->ownPermissionNames()->flip();
        $newNames = DB::table('permissions')->whereIn('id', $newPermissionIds)->pluck('name');

        return $newNames->every(fn ($name) => isset($own[$name]));
    }

    /** May the actor edit (or reset the password of) this user account in the Users screen? */
    public function canManageUser(User $target): bool
    {
        if ($this->isSuper()) {
            return true;
        }
        if ($target->isSuperAdmin() || $target->isVendor() || $target->isProfessional()) {
            return false;
        }
        if ($target->id === $this->actor->id) {
            return true; // own profile; role changes are blocked separately
        }

        return $this->canGrantRoleIds($target->roles()->pluck('roles.id')->all());
    }

    /** Reason a user cannot be deleted, or null when deleting is fine. */
    public function deleteProblem(User $target): ?string
    {
        if ($target->id === $this->actor->id) {
            return 'You cannot delete your own account.';
        }
        if ($target->isSuperAdmin() && !$this->isSuper()) {
            return 'Only a Super Admin can delete a Super Admin.';
        }
        if ($target->isSuperAdmin()) {
            $superAdmins = DB::table('role_user')
                ->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->join('users', 'users.id', '=', 'role_user.user_id')
                ->where('roles.name', self::SUPER_ADMIN)
                ->whereNull('users.deleted_at')
                ->distinct()
                ->count('role_user.user_id');
            if ($superAdmins <= 1) {
                return 'This is the last Super Admin account and cannot be deleted.';
            }
        }
        if ($target->isVendor()) {
            return 'Branch logins are managed on the Branches screen (set the branch Inactive there).';
        }
        if ($target->isProfessional()) {
            return 'Professionals are managed on the Professionals screen.';
        }
        if (!$this->canManageUser($target)) {
            return 'You cannot delete a user with roles you are not allowed to grant.';
        }
        if (DB::table('activity_logs')->where('user_id', $target->id)->exists()) {
            return "{$target->name} has worked in the CRM, so the account is kept for the history. Set the user to Inactive instead.";
        }

        return null;
    }
}
