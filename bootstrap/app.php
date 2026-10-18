<?php

use App\Http\Middleware\CheckPermission;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\Paginator;
use App\Models\Setting;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'permission' => CheckPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->booting(function () {
        // Set default string length for database schema
        Schema::defaultStringLength(191);
        
        // Set paginator to use Bootstrap
        Paginator::useBootstrap();
        
        // Define your gates here
        Gate::define('permission', function (User $user, string $permission) {
            $permissions = explode('|', $permission);

            if ($user->isSuperAdmin()) {
               return true;
            }

            // Check vendor permissions if user is a vendor
            if ($user->isVendor()) {
                $allowedVendorPermissions = config('vendor.allowed_permissions', []);

                foreach ($permissions as $perm) {
                    if (in_array($perm, $allowedVendorPermissions)) {
                        return true;
                    }
                }

                return false;
            }

            // Check role-based permissions for general users
            foreach ($permissions as $perm) {
                if ($user->roles()
                    ->whereHas('permissions', function ($q) use ($perm) {
                        $q->where('name', $perm);
                    })
                    ->exists()) {
                    return true; // if any permission matches
                }
            }

            return false;
        });

    })
    ->booted(function () {
        // View composers and session-dependent logic should go in booted()
        // because they need the full application to be loaded
        
        if (app()->runningInConsole()) {
            return; // Skip view composers in console commands
        }
        
        try {
            $hasSession = session()->get('hadModal');
            if (!$hasSession) {
                View::composer(['admin.layouts.master'], function ($view) {
                    // $stockOut = Stock::leftJoin('medicines', 'stocks.medicine_id', '=', 'medicines.id')
                    //     ->leftJoin('manufacturers', 'manufacturers.id', '=', 'medicines.manufacturer_id')
                    //     ->whereNull('stocks.deleted_at')
                    //     ->select('medicines.id', 'medicines.medicine_name','medicines.strength', 'manufacturers.manufacturer_name', DB::raw("SUM(stocks.in_qty) as in_qty"), DB::raw("SUM(stocks.out_qty) as out_qty"), DB::raw("SUM(stocks.in_qty) - SUM(stocks.out_qty) as stock"))
                    //     ->groupBy('medicines.id')
                    //     ->orderBy('medicines.id', 'desc')
                    //     ->havingRaw('stock = 0')
                    //     ->take(10)
                    //     ->get();

                    // $dateExpiredMedicine = Batch::with('medicine.manufacturer:id,manufacturer_name')
                    //     ->where('expiry_date','<', date('Y-m-d'))
                    //     ->orderBy('id', 'desc')
                    //     ->take(10)
                    //     ->get();

                    $setting = Setting::where('id', 1)->first();

                    $user = auth()->user();
                    $menus = $user ? $user->accessibleMenus() : [];
                    $view->with('menus', $menus);
                    
                    // $view->with('stockOut', $stockOut);
                     $view->with('setting', $setting);
                    // $view->with('dateExpiredMedicine', $dateExpiredMedicine);
                });
            }
        } catch (\Exception $e) {
            // Handle any exceptions gracefully
            // You might want to log this or handle it based on your needs
        }
    })
    ->create();
    