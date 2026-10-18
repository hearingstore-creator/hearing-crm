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

        // Super Admin, branch / professional permission lists, then role permissions (see User::hasAccess).
        // "a|b" means either permission is enough.
        $user = auth()->user();
        $allowed = collect(explode('|', $permission))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->contains(fn ($name) => $user->hasAccess($name));

        if (!$allowed) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
