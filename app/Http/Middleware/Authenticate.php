<?php

namespace App\Http\Middleware;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class Authenticate extends Middleware
{
    /**
     * Signed in is not enough: a deactivated person is signed out on their very next click, and someone
     * whose password was just set by an admin must choose their own before using anything else.
     */
    protected function authenticate($request, array $guards)
    {
        parent::authenticate($request, $guards);

        $user = $request->user();
        if ($user && $user->status !== null && (int) $user->status === 0) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->flash('login_notice', 'Your account has been deactivated. Please ask your administrator.');

            throw new AuthenticationException('Account deactivated.', $guards, $this->redirectTo($request));
        }

        if ($user && $user->must_change_password && ! $request->routeIs('profile.*', 'logout')) {
            throw new HttpResponseException(
                $request->expectsJson()
                    ? response()->json(['message' => 'Change your password first.'], 423)
                    : redirect()->route('profile.show')->with('warning', 'Please choose a new password before you continue.')
            );
        }
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return route('login');
        }
    }
}
