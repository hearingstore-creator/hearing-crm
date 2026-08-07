<?php

namespace App\Http\Controllers\Admin;

use App\Models\Role;
use App\Models\Menu;
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
        return view('admin.roles.index', compact('roles'));
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
        $menus = Menu::with(['children.permissions'])->whereNull('parent_id')->get();
        $rolePermissions = $role->permissions->pluck('id')->toArray();

        return view('admin.roles.edit', compact('role', 'menus', 'rolePermissions'));
    }

    public function update(Request $request, int $id)
    {
        $role = Role::findOrFail($id);
        $permissionIds = $request->input('permissions', []);
        $role->permissions()->sync($permissionIds);

        return redirect()->back()->with('success', 'Role updated successfully!');
    }
}
