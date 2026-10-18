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
        $parent = Menu::whereNull('parent_id')
            ->where(function ($q) {
                $q->where('name', 'repair')
                  ->orWhere('name', 'like', '%Repair%');
            })
            ->first();

        if ($parent) {
            $child = Menu::updateOrCreate(
                ['name' => 'Repair History', 'parent_id' => $parent->id],
                [
                    'label' => 'Repair History',
                    'route' => 'admin/repairs/history',
                    'icon'  => 'fa-history',
                    'order' => 7,
                ]
            );

            $roles = Role::whereIn('name', ['Super Admin', 'Admin', 'Branch Manager', 'Manager', 'Staff', 'Sales Representative'])->get();

            foreach (['index', 'view'] as $action) {
                $permission = Permission::firstOrCreate([
                    'menu_id' => $child->id,
                    'name'    => 'repair_history.' . $action,
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
        $child = Menu::where('name', 'Repair History')->first();
        if ($child) {
            Permission::where('menu_id', $child->id)->delete();
            $child->delete();
        }
    }
};
