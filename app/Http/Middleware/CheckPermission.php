<?php

namespace App\Http\Middleware;

use Closure;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next, $permission)
    {
        if (!auth()->check()) {
            // Redirect to login for unauthenticated users
            return redirect()->route('login');
        }

        // Super Admin, branch / professional permission lists, then role permissions (see User::hasAccess)
        if (!auth()->user()->hasAccess($permission)) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
