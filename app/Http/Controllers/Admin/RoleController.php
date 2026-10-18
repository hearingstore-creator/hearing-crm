<?php

namespace App\Http\Controllers\Admin;

use App\Models\Role;
use App\Models\Menu;
use App\Services\AccessGuard;
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
    }

    public function index()
    {
        $roles = Role::all();
        $editable = (new AccessGuard())->grantableRoles()->pluck('id')->flip();
        return view('admin.roles.index', compact('roles', 'editable'));
    }

    public function create()
    {
        return view('admin.roles.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
        ]);

        Role::create([
            'name' => $request->name
        ]);

        return redirect()->route('roles.index')->with('success', 'Role created successfully.');
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
