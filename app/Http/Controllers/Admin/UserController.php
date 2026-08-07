<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
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
        return view('admin.user.index', compact('users'));
    }

    /**
     * Show the form for creating a new user.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function create(): View
    {
        $data['users'] = User::with('roles')->get();
        $data['roles'] = Role::all();
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

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'details'  => $request->details,
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
        $roles = Role::all();
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
        
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'roles'    => ! $user->isVendor() ? 'required|array' : 'nullable',
            'status' => 'required|in:0,1',
       ]);

        $user->name = $request->name;
        $user->email = $request->email;
        $user->status = $request->status;
        if ($request->password) {
            $user->password = bcrypt($request->password);
        }
        $user->details = $request->details;
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
        $user = User::find($id);
        $user->delete();
        return redirect('/admin/user')->with('success', 'Deleted Successfully');
    }
}
