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
        $parent = Menu::whereNull('parent_id')->where('name', 'Sales')->first();
        if (!$parent) {
            return;
        }

        $child = Menu::updateOrCreate(
            ['name' => 'Report & Delivery Hub', 'parent_id' => $parent->id],
            [
                'label' => 'Report & Delivery Hub',
                'route' => 'admin/sales/fulfillment',
                'icon'  => 'fa-clipboard-check',
                'order' => 4,
            ]
        );

        $adminRoles = Role::whereIn('name', ['Super Admin', 'Admin', 'Branch Manager', 'Manager', 'Staff', 'Sales Representative'])->get();

        foreach (['index', 'view', 'edit'] as $action) {
            $permission = Permission::firstOrCreate([
                'menu_id' => $child->id,
                'name'    => 'sale_fulfillment.' . $action,
                'action'  => $action,
            ]);

            foreach ($adminRoles as $role) {
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $menu = Menu::where('name', 'Report & Delivery Hub')->first();
        if ($menu) {
            Permission::where('menu_id', $menu->id)->delete();
            $menu->delete();
        }
    }
};
