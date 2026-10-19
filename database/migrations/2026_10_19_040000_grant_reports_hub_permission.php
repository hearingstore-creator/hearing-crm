<?php

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * The Reports Hub page (config/acl.php, Report menu). Create its menu and permission the same way
 * AclSeeder does, and give it to every role that already holds a Report-menu permission, so people
 * who could open a report can also find it in the hub. The hub itself lists only reports the person may open.
 */
return new class extends Migration
{
    public function up(): void
    {
        $parent = Menu::whereNull('parent_id')->where('name', 'Report')->first();
        if (!$parent) {
            return;
        }

        $menu = Menu::updateOrCreate(
            ['name' => 'Reports Hub', 'parent_id' => $parent->id],
            ['label' => 'All Reports', 'route' => 'admin/reports', 'icon' => 'fa-th-large', 'order' => 0]
        );
        $permission = Permission::firstOrCreate(['menu_id' => $menu->id, 'name' => 'reports_hub.index', 'action' => 'index']);

        $reportMenuIds = Menu::where('parent_id', $parent->id)->where('id', '!=', $menu->id)->pluck('id');
        Role::whereHas('permissions', fn ($q) => $q->whereIn('menu_id', $reportMenuIds))
            ->get()
            ->each(fn ($role) => $role->permissions()->syncWithoutDetaching([$permission->id]));
    }

    public function down(): void
    {
    }
};
