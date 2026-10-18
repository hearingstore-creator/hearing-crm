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
        $parent = Menu::updateOrCreate(
            ['name' => 'Repair & Service', 'parent_id' => null],
            [
                'label'    => 'Repair & Service',
                'category' => 'OPERATIONS',
                'icon'     => 'fa-tools',
                'route'    => null,
                'order'    => 12,
            ]
        );

        $submenus = [
            [
                'name'  => 'Repair Jobs Board',
                'label' => 'Repair Jobs Board',
                'route' => 'admin/repairs',
                'icon'  => 'fa-columns',
                'order' => 1,
            ],
            [
                'name'  => 'New Repair Intake',
                'label' => 'New Repair Intake',
                'route' => 'admin/repairs/create',
                'icon'  => 'fa-plus-circle',
                'order' => 2,
            ],
            [
                'name'  => 'Quick Walk-in Service',
                'label' => 'Quick Walk-in Service',
                'route' => 'admin/repairs/quick',
                'icon'  => 'fa-bolt',
                'order' => 3,
            ],
            [
                'name'  => 'Warranty & Serial Check',
                'label' => 'Warranty & Serial Check',
                'route' => 'admin/repairs/lookup',
                'icon'  => 'fa-barcode',
                'order' => 4,
            ],
        ];

        $roles = Role::whereIn('name', ['Super Admin', 'Admin', 'Branch Manager', 'Manager', 'Staff', 'Sales Representative'])->get();

        foreach ($submenus as $sub) {
            $child = Menu::updateOrCreate(
                ['name' => $sub['name'], 'parent_id' => $parent->id],
                [
                    'label' => $sub['label'],
                    'route' => $sub['route'],
                    'icon'  => $sub['icon'],
                    'order' => $sub['order'],
                ]
            );

            foreach (['index', 'create', 'view', 'edit', 'delete'] as $action) {
                $permission = Permission::firstOrCreate([
                    'menu_id' => $child->id,
                    'name'    => 'repair.' . $action,
                    'action'  => $action,
                ]);

                foreach ($roles as $role) {
                    $role->permissions()->syncWithoutDetaching([$permission->id]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $parent = Menu::where('name', 'Repair & Service')->whereNull('parent_id')->first();
        if ($parent) {
            $children = Menu::where('parent_id', $parent->id)->get();
            foreach ($children as $child) {
                Permission::where('menu_id', $child->id)->delete();
                $child->delete();
            }
            $parent->delete();
        }
    }
};
