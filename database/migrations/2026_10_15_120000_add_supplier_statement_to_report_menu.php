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
            return;
        }

        $child = Menu::updateOrCreate(
            ['name' => 'Supplier Statement', 'parent_id' => $parent->id],
            [
                'label' => 'Supplier Statement',
                'route' => 'admin/report/supplier-statement',
                'icon'  => 'fa-file-invoice',
                'order' => 4,
            ]
        );

        $adminRoles = Role::whereIn('name', ['Super Admin', 'Admin', 'Branch Manager', 'Manager'])->get();

        foreach (['index'] as $action) {
            $permission = Permission::firstOrCreate([
                'menu_id' => $child->id,
                'name'    => 'supplier_statement.' . $action,
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
        $parent = Menu::whereNull('parent_id')->where('name', 'Report')->first();
        if (!$parent) {
            return;
        }

        $child = Menu::where('parent_id', $parent->id)->where('name', 'Supplier Statement')->first();
        if ($child) {
            Permission::where('menu_id', $child->id)->delete();
            $child->delete();
        }
    }
};
