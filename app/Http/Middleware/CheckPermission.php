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
        $user = auth()->user();

        if (!auth()->check()) {
            // Redirect to login for unauthenticated users
            return redirect()->route('login');
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Allow vendors to access purchase, product, sale, and doctor payment routes
        if ($user->isVendor()) {
            $allowedVendorPermissions = config('vendor.allowed_permissions', []);

            if (in_array($permission, $allowedVendorPermissions)) {
                return $next($request);
            }
        }

        // Allow professionals to access appointment and related routes
        if ($user->isProfessional()) {
            $allowedProfessionalPermissions = config('professional.allowed_permissions', []);

            if (in_array($permission, $allowedProfessionalPermissions)) {
                return $next($request);
            }
        }

        if (!$user->hasPermission($permission)) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
