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
use Illuminate\Support\Str;
use App\Services\RoleAccess;

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
        $this->middleware('permission:users.edit')->only(['edit', 'update', 'setStatus', 'resetPassword']);
        $this->middleware('permission:users.index')->only(['show']);
        $this->middleware('permission:users.delete')->only(['destroy']);
    }

    /**
     * Display a listing of the users.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        $f = [
            'q'      => trim((string) request('q')),
            'role'   => (int) request('role'),
            'branch' => (string) request('branch'),
            'status' => (string) request('status'),
        ];

        $staff = fn ($q) => $q->where(fn ($w) => $w->whereNull('type')->orWhereNotIn('type', ['vendor', 'professional']));

        $users = User::with(['roles', 'branch'])
            ->when($f['q'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%' . $f['q'] . '%')->orWhere('email', 'like', '%' . $f['q'] . '%')))
            ->when($f['role'], fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('roles.id', $f['role'])))
            ->when($f['branch'] === 'head', fn ($q) => $q->whereNull('vendor_id')->where(fn ($w) => $w->whereNull('type')->orWhere('type', '!=', 'vendor')))
            ->when($f['branch'] !== '' && ctype_digit($f['branch']), fn ($q) => $q->where(fn ($w) => $w->where('vendor_id', (int) $f['branch'])->orWhere('id', (int) $f['branch'])))
            ->when($f['status'] === 'active', fn ($q) => $q->where('status', 1))
            ->when($f['status'] === 'inactive', fn ($q) => $q->where('status', 0))
            ->when($f['status'] === 'norole', fn ($q) => $staff($q->where('status', 1))->whereDoesntHave('roles'))
            ->orderBy('name')->get();

        $counts = [
            'all'      => User::count(),
            'active'   => User::where('status', 1)->count(),
            'inactive' => User::where('status', 0)->count(),
            'norole'   => $staff(User::where('status', 1))->whereDoesntHave('roles')->count(),
        ];

        $guard = new AccessGuard();
        $manageable = $users->filter(fn ($u) => $guard->canManageUser($u))->pluck('id')->flip();

        return view('admin.user.index', [
            'users' => $users, 'manageable' => $manageable, 'counts' => $counts, 'f' => $f,
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'branches' => \App\Classes\Helper::branchOptions(),
        ]);
    }

    /** What this person can do, in plain words (their roles added together). */
    public function show(int $id): View
    {
        $user = User::with('roles', 'branch')->findOrFail($id);
        $fixed = $user->isVendor() || $user->isProfessional();
        $names = $user->isSuperAdmin() || $fixed ? collect() : $user->roles()->with('permissions:id,name')->get()->pluck('permissions')->flatten()->pluck('name')->unique();

        return view('admin.user.show', [
            'user'    => $user,
            'fixed'   => $fixed,
            'summary' => RoleAccess::summarise($names),
            'widgets' => RoleAccess::dashboardWidgets($names),
            'canEdit' => (new AccessGuard())->canManageUser($user),
        ]);
    }

    /** One-click Activate / Deactivate from the People list. */
    public function setStatus(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $request->validate(['status' => 'required|in:0,1']);
        $guard = new AccessGuard();

        if (!$guard->canManageUser($user)) {
            return back()->with('warning', 'You cannot change this account.');
        }
        if ($user->id === auth()->id()) {
            return back()->with('warning', 'You cannot change your own status.');
        }
        if ((int) $request->status === 0 && $user->isSuperAdmin()
            && User::where('status', 1)->where('id', '!=', $user->id)->get()->filter->isSuperAdmin()->isEmpty()) {
            return back()->with('warning', 'This is the last active Super Admin and cannot be deactivated.');
        }

        $user->status = (int) $request->status;
        $user->save();

        return back()->with('success', $user->name . ($user->status ? ' is active again.' : ' is deactivated and signed out on their next click.'));
    }

    /** Sets a temporary password, shown once to the admin; the person must choose their own at next sign-in. */
    public function resetPassword(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            return back()->with('warning', 'Change your own password from My Profile.');
        }
        if (!(new AccessGuard())->canManageUser($user)) {
            return back()->with('warning', 'You cannot reset this account.');
        }

        $temp = Str::password(10, symbols: false);
        $user->forceFill(['password' => Hash::make($temp), 'must_change_password' => true])->save();

        return back()->with('success', "New temporary password for {$user->name}: {$temp} — share it privately. They must choose their own at next sign-in.");
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
            'password' => 'required|string|min:8|confirmed',
            'roles'    => 'required|array',
        ]);

        if (!(new AccessGuard())->canGrantRoleIds($request->roles)) {
            return back()->withInput()->withErrors(['roles' => 'You can only give roles whose permissions you have yourself.']);
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'must_change_password' => true, // they pick their own at first sign-in
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

        $events = \App\Models\LoginEvent::where('user_id', $user->id)->latest('created_at')->limit(8)->get();

        return view('admin.user.edit', compact('user', 'roles', 'userRoles', 'events'));
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
            'password' => 'nullable|string|min:8|confirmed',
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
            // An admin-set password is temporary; changing your own is not
            $user->must_change_password = $user->id !== auth()->id();
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
