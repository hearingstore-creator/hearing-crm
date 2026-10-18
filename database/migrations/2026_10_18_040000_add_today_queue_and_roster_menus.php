<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $parent = Menu::whereNull('parent_id')->where('name', 'Appointment')->first();
        if (!$parent) {
            return;
        }

        // 1. Today's Queue Menu
        $todayMenu = Menu::updateOrCreate(
            ['name' => "Today's Queue", 'parent_id' => $parent->id],
            [
                'label' => "Today's Queue",
                'route' => 'admin/appointments/today',
                'icon'  => 'fa-tasks',
                'order' => 1,
            ]
        );

        // 2. Staff Rosters & Leave Menu
        $rosterMenu = Menu::updateOrCreate(
            ['name' => 'Staff Rosters & Leave', 'parent_id' => $parent->id],
            [
                'label' => 'Rosters & Leave',
                'route' => 'admin/roster',
                'icon'  => 'fa-user-clock',
                'order' => 4,
            ]
        );

        // Adjust other menu orders for clean layout
        Menu::where('parent_id', $parent->id)->where('name', 'Add Appointment')->update(['order' => 0]);
        Menu::where('parent_id', $parent->id)->where('name', 'Appointment List')->update(['order' => 2]);
        Menu::where('parent_id', $parent->id)->where('name', 'Calendar View')->update(['order' => 3]);
        Menu::where('parent_id', $parent->id)->where('name', 'Professional List')->update(['order' => 5]);
        Menu::where('parent_id', $parent->id)->where('name', 'Service List')->update(['order' => 6]);
        Menu::where('parent_id', $parent->id)->where('name', 'Time Slot')->update(['order' => 7]);

        $roles = Role::whereIn('name', [
            'Super Admin', 'Admin', 'Branch Manager', 'Manager', 'Staff', 'Call Center'
        ])->get();

        // Permissions for Today's Queue
        foreach (['index', 'view', 'edit'] as $action) {
            $perm = Permission::firstOrCreate([
                'menu_id' => $todayMenu->id,
                'name'    => 'appointment_today.' . $action,
                'action'  => $action,
            ]);

            foreach ($roles as $role) {
                $role->permissions()->syncWithoutDetaching([$perm->id]);
            }
        }

        // Permissions for Rosters
        foreach (['index', 'view', 'create', 'edit'] as $action) {
            $perm = Permission::firstOrCreate([
                'menu_id' => $rosterMenu->id,
                'name'    => 'roster.' . $action,
                'action'  => $action,
            ]);

            foreach ($roles as $role) {
                $role->permissions()->syncWithoutDetaching([$perm->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $todayMenu = Menu::where('name', "Today's Queue")->first();
        if ($todayMenu) {
            Permission::where('menu_id', $todayMenu->id)->delete();
            $todayMenu->delete();
        }

        $rosterMenu = Menu::where('name', 'Staff Rosters & Leave')->first();
        if ($rosterMenu) {
            Permission::where('menu_id', $rosterMenu->id)->delete();
            $rosterMenu->delete();
        }
    }
};
