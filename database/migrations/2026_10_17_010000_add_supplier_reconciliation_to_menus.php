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
        $parent = Menu::where('name', 'Repair & Service')->whereNull('parent_id')->first();
        if ($parent) {
            // Update order and label of RMA
            Menu::where('parent_id', $parent->id)
                ->where('name', 'Supplier RMA & Replacements')
                ->update(['label' => 'Supplier RMA & Replacements', 'order' => 5]);

            $child = Menu::updateOrCreate(
                ['name' => 'Supplier Reconciliation', 'parent_id' => $parent->id],
                [
                    'label' => 'Supplier Reconciliation',
                    'route' => 'admin/repairs/supplier-reconciliation',
                    'icon'  => 'fa-file-invoice-dollar',
                    'order' => 6,
                ]
            );

            $roles = Role::whereIn('name', ['Super Admin', 'Admin', 'Branch Manager', 'Manager', 'Staff', 'Sales Representative'])->get();

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
        $child = Menu::where('name', 'Supplier Reconciliation')->first();
        if ($child) {
            Permission::where('menu_id', $child->id)->delete();
            $child->delete();
        }
    }
};
