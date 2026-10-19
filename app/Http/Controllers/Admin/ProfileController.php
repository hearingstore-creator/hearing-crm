<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/** Every signed-in person's own page: who they are, recent sign-ins and a way to change their password. */
class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return view('admin.profile.show', [
            'user'    => $user->load('roles', 'branch'),
            'events'  => LoginEvent::where('user_id', $user->id)->latest('created_at')->limit(8)->get(),
            'forced'  => (bool) $user->must_change_password,
        ]);
    }

    public function password(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => 'required|string',
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'That is not your current password.']);
        }
        if (Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['password' => 'Choose a password different from the current one.']);
        }

        $user->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => false])->save();

        return redirect()->route('profile.show')->with('success', 'Your password has been changed.');
    }
}
