<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Configure middleware to handle authorization for different users operations
     * 
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:users.index')->only(['index']);
        $this->middleware('permission:users.create')->only(['create', 'store']);
        $this->middleware('permission:users.edit')->only(['edit', 'update']);
        $this->middleware('permission:users.delete')->only(['destroy']);
    }

    /**
     * Display a listing of the users.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        $users = User::orderBy('id', 'desc')->get();
        $guard = new AccessGuard();
        $manageable = $users->filter(fn ($u) => $guard->canManageUser($u))->pluck('id')->flip();
        return view('admin.user.index', compact('users', 'manageable'));
    }

    /**
     * Show the form for creating a new user.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function create(): View
    {
        $data['users'] = User::with('roles')->get();
        // Only roles this person may hand out (never Super Admin unless they are one)
        $data['roles'] = (new AccessGuard())->grantableRoles();
        return view('admin.user.create', $data);
    }

    /**
     * Store a newly created user in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:6',
            'roles'    => 'required|array',
        ]);

        if (!(new AccessGuard())->canGrantRoleIds($request->roles)) {
            return back()->withInput()->withErrors(['roles' => 'You can only give roles whose permissions you have yourself.']);
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'details'  => $request->details,
            'vendor_id' => \App\Classes\Helper::staffBranchFromRequest($request->vendor_id),
        ]);

        // Attach multiple roles
        $user->roles()->sync($request->roles);

        return redirect()->route('user.index')->with('success', 'User created successfully!');
    }

    /**
     * Show the form for editing the specified user.
     *
     * @param int $id
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(int $id): View
    {
        $user = User::with('roles')->findOrFail($id);
        $guard = new AccessGuard();
        abort_unless($guard->canManageUser($user), 403, 'You cannot edit this user.');
        // Grantable roles, plus the ones the user already has so they stay visible
        $roles = $guard->grantableRoles()->merge($user->roles)->unique('id')->values();
        $userRoles = $user->roles->pluck('id')->toArray();

        return view('admin.user.edit', compact('user', 'roles', 'userRoles'));
    }

    /**
     * Update the specified user in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $guard = new AccessGuard();
        abort_unless($guard->canManageUser($user), 403, 'You cannot edit this user.');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6',
            'roles'    => ! $user->isVendor() ? 'required|array' : 'nullable',
            'status' => 'required|in:0,1',
       ]);

        $currentRoleIds = $user->roles()->pluck('roles.id')->map(fn ($r) => (int) $r)->sort()->values()->all();
        $newRoleIds = collect($request->roles ?? [])->map(fn ($r) => (int) $r)->sort()->values()->all();
        if (!$guard->isSuper()) {
            if ($user->id === auth()->id() && $newRoleIds !== $currentRoleIds) {
                return back()->withInput()->withErrors(['roles' => 'You cannot change your own roles.']);
            }
            if ($user->id === auth()->id() && (int) $request->status !== (int) $user->status) {
                return back()->withInput()->withErrors(['status' => 'You cannot change your own status.']);
            }
            if ($user->id !== auth()->id() && !$guard->canGrantRoleIds($newRoleIds)) {
                return back()->withInput()->withErrors(['roles' => 'You can only give roles whose permissions you have yourself.']);
            }
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->status = $request->status;
        if ($request->password) {
            $user->password = bcrypt($request->password);
        }
        $user->details = $request->details;
        if (!$user->isVendor() && !$user->isSuperAdmin() && $request->has('vendor_id')) {
            $user->vendor_id = \App\Classes\Helper::staffBranchFromRequest($request->vendor_id);
        }
        $user->save();

        $user->roles()->sync($request->roles);

        return redirect()->route('user.index')->with('success', 'User updated successfully!');
    }

    /**
     * Remove the specified user from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        // Not yourself, not the last Super Admin, not branch logins / professionals, not people with history
        if ($problem = (new AccessGuard())->deleteProblem($user)) {
            return redirect()->back()->with('warning', $problem);
        }

        $user->delete();
        return redirect('/admin/user')->with('success', 'Deleted Successfully');
    }
}
