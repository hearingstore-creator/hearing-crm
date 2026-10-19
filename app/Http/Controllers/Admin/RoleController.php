<?php

namespace App\Http\Controllers\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Menu;
use App\Services\AccessGuard;
use App\Services\RoleAccess;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class RoleController extends Controller
{
    /**
     * Configure middleware to handle authorization for different role operations
     * 
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:roles.index')->only(['index']);
        $this->middleware('permission:roles.create')->only(['create', 'store']);
        $this->middleware('permission:roles.edit')->only(['edit', 'update']);
        $this->middleware('permission:roles.delete')->only(['destroy']);
        $this->middleware('permission:roles.index')->only(['show']);
    }

    public function index()
    {
        $roles = Role::withCount(['users', 'permissions'])->with('users:users.id,users.name')->orderBy('name')->get();
        $editable = (new AccessGuard())->grantableRoles()->pluck('id')->flip();
        $withoutRole = User::where('status', 1)->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'vendor'))
            ->whereDoesntHave('roles')->count();

        return view('admin.roles.index', compact('roles', 'editable', 'withoutRole'));
    }

    public function create()
    {
        return view('admin.roles.create', [
            'templates' => RoleAccess::templates(),
            'picked'    => request('template'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255|unique:roles,name',
            'template' => 'nullable|string|in:' . implode(',', array_keys(RoleAccess::templates())),
        ]);

        $role = Role::create(['name' => $request->name]);
        $note = '';

        if ($request->filled('template')) {
            $wanted = RoleAccess::templatePermissionNames($request->template);
            $guard = new AccessGuard();
            // Nobody can hand out more than they hold, templates included
            $allowed = $guard->isSuper() ? $wanted : array_values(array_intersect($wanted, $guard->ownPermissionNames()->all()));
            $role->permissions()->sync(Permission::whereIn('name', $allowed)->pluck('id'));
            $note = ' Started from the ' . $request->template . ' template (' . count($allowed) . ' permissions)'
                . (count($allowed) < count($wanted) ? '; ' . (count($wanted) - count($allowed)) . ' skipped because you do not hold them yourself' : '') . '.';
        }

        return redirect()->route($request->filled('template') ? 'roles.show' : 'roles.index', $request->filled('template') ? [$role->id] : [])
            ->with('success', 'Role created.' . $note);
    }

    /** Plain-language summary of what a role can do. */
    public function show(int $id)
    {
        $role = Role::withCount('users')->with('users:users.id,users.name')->findOrFail($id);
        $names = $role->name === AccessGuard::SUPER_ADMIN ? null : $role->permissions()->pluck('permissions.name');

        return view('admin.roles.show', [
            'role'      => $role,
            'everything' => $names === null,
            'summary'   => $names === null ? [] : RoleAccess::summarise($names),
            'widgets'   => $names === null ? 0 : RoleAccess::dashboardWidgets($names),
            'canEdit'   => (new AccessGuard())->grantableRoles()->contains('id', $role->id),
        ]);
    }

    public function destroy(int $id)
    {
        $role = Role::withCount('users')->findOrFail($id);

        if ($role->name === AccessGuard::SUPER_ADMIN) {
            return back()->with('warning', 'The Super Admin role cannot be deleted.');
        }
        if (!(new AccessGuard())->grantableRoles()->contains('id', $role->id)) {
            return back()->with('warning', 'You cannot delete this role: it is one of your own, or has permissions you do not have.');
        }
        if ($role->users_count > 0) {
            return back()->with('warning', "{$role->name} is still given to {$role->users_count} " . ($role->users_count === 1 ? 'person' : 'people') . '. Move them to another role first.');
        }

        $role->permissions()->detach();
        $role->forceDelete(); // nobody holds it, and the name must be free to reuse

        return redirect()->route('roles.index')->with('success', 'Role deleted.');
    }

    public function edit(int $id)
    {
        $role = Role::findOrFail($id);
        abort_unless(
            (new AccessGuard())->canEditRole($role, $role->permissions()->pluck('permissions.id')->all()),
            403,
            'You cannot edit this role: it is Super Admin, one of your own roles, or has permissions you do not have.'
        );
        $menus = Menu::with(['children.permissions'])->whereNull('parent_id')->orderBy('order', 'asc')->get();
        $rolePermissions = $role->permissions->pluck('id')->toArray();

        return view('admin.roles.edit', compact('role', 'menus', 'rolePermissions'));
    }

    public function update(Request $request, int $id)
    {
        $role = Role::findOrFail($id);
        $request->validate(['permissions' => 'nullable|array', 'permissions.*' => 'integer|exists:permissions,id']);
        $permissionIds = $request->input('permissions', []);

        // Nobody can give a role permissions they do not hold, edit their own role, or touch Super Admin
        $guard = new AccessGuard();
        if (!$guard->canEditRole($role, $role->permissions()->pluck('permissions.id')->all())
            || !$guard->canEditRole($role, $permissionIds)) {
            return redirect()->back()->with('warning', 'You can only give a role permissions you have yourself, and you cannot edit your own role or Super Admin.');
        }

        $role->permissions()->sync($permissionIds);

        return redirect()->back()->with('success', 'Role updated successfully!');
    }
}
