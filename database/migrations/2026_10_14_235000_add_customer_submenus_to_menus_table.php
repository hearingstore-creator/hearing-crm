<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
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
        $parent = Menu::whereNull('parent_id')->where('name', 'Customer')->first();
        if (!$parent) {
            return;
        }

        $submenus = [
            [
                'name' => 'Customer Ledger',
                'label' => 'Customer Ledger',
                'route' => 'admin/customer-ledger/create',
                'order' => 2,
                'actions' => ['index', 'create'],
            ],
            [
                'name' => 'Customer Payment',
                'label' => 'Customer Payment',
                'route' => 'admin/customer-payment',
                'order' => 3,
                'actions' => ['index', 'create'],
            ],
        ];

        $superAdmin = Role::where('name', 'Super Admin')->first();

        foreach ($submenus as $item) {
            $child = Menu::updateOrCreate(
                ['name' => $item['name'], 'parent_id' => $parent->id],
                [
                    'label' => $item['label'],
                    'route' => $item['route'],
                    'order' => $item['order'],
                ]
            );

            foreach ($item['actions'] as $action) {
                $permission = Permission::firstOrCreate([
                    'menu_id' => $child->id,
                    'name'    => strtolower(str_replace(' ', '_', $item['name'])) . '.' . $action,
                    'action'  => $action,
                ]);

                if ($superAdmin) {
                    $superAdmin->permissions()->syncWithoutDetaching([$permission->id]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $parent = Menu::whereNull('parent_id')->where('name', 'Customer')->first();
        if (!$parent) {
            return;
        }

        $names = ['Customer Ledger', 'Customer Payment'];
        $children = Menu::where('parent_id', $parent->id)->whereIn('name', $names)->get();
        foreach ($children as $child) {
            Permission::where('menu_id', $child->id)->forceDelete();
            $child->forceDelete();
        }
    }
};
