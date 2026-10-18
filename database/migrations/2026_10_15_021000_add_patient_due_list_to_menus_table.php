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
            ['name' => 'Patient Due List', 'parent_id' => $parent->id],
            [
                'label' => 'Patient Due List',
                'route' => 'admin/sales/due-list',
                'icon'  => 'fa-file-invoice-dollar',
                'order' => 3,
            ]
        );

        $superAdmin = Role::where('name', 'Super Admin')->first();
        $adminRoles = Role::whereIn('name', ['Super Admin', 'Admin', 'Branch Manager', 'Manager'])->get();

        foreach (['index', 'create'] as $action) {
            $permission = Permission::firstOrCreate([
                'menu_id' => $child->id,
                'name'    => 'patient_due_list.' . $action,
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
        $parent = Menu::whereNull('parent_id')->where('name', 'Sales')->first();
        if (!$parent) {
            return;
        }

        $child = Menu::where('parent_id', $parent->id)->where('name', 'Patient Due List')->first();
        if ($child) {
            Permission::where('menu_id', $child->id)->delete();
            $child->delete();
        }
    }
};
