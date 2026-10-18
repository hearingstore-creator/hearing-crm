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
        $parent = Menu::whereNull('parent_id')->where('name', 'Report')->first();
        if (!$parent) {
            $parent = Menu::find(53);
        }

        if (!$parent) {
            return;
        }

        // 1. Advances Report
        $advMenu = Menu::updateOrCreate(
            ['name' => 'Advances Report', 'parent_id' => $parent->id],
            [
                'label' => 'Advances Report',
                'route' => 'admin/report/advances',
                'icon'  => 'fa-hand-holding-usd',
                'order' => 10,
            ]
        );

        // 2. Services Status Report
        $svcMenu = Menu::updateOrCreate(
            ['name' => 'Services Status', 'parent_id' => $parent->id],
            [
                'label' => 'Services Status',
                'route' => 'admin/report/services-status',
                'icon'  => 'fa-stethoscope',
                'order' => 11,
            ]
        );

        $roles = Role::all();

        // Create permissions
        foreach (['index', 'view'] as $action) {
            $pAdv = Permission::firstOrCreate([
                'menu_id' => $advMenu->id,
                'name'    => 'advances_report.' . $action,
                'action'  => $action,
            ]);

            $pSvc = Permission::firstOrCreate([
                'menu_id' => $svcMenu->id,
                'name'    => 'services_status_report.' . $action,
                'action'  => $action,
            ]);

            foreach ($roles as $role) {
                $role->permissions()->syncWithoutDetaching([$pAdv->id, $pSvc->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $menus = Menu::whereIn('name', ['Advances Report', 'Services Status'])->get();
        foreach ($menus as $menu) {
            Permission::where('menu_id', $menu->id)->delete();
            $menu->delete();
        }
    }
};
