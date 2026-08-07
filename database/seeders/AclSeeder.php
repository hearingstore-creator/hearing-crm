<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\User;
use Log;

class AclSeeder extends Seeder
{
    public function run(): void
    {
        Log::info('test');
        // 1. Create Roles
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin']);

        // 2. Menu structure from config
        $menus = config('acl.menus');

        // 3. Collect valid menu names from config for cleanup later
        $validParentNames = [];
        $validChildNames = [];

        // 4. Create Menus & Permissions using actions from config
        foreach ($menus as $menuData) {
            $validParentNames[] = $menuData['name'];

            $parentMenu = Menu::updateOrCreate(
                ['name' => $menuData['name']],
                [
                    'icon'  => $menuData['icon'] ?? null,
                    'route' => $menuData['route'] ?? null,
                    'order' => $menuData['order'] ?? 0,
                ]
            );

            // Parent menu permissions — use actions from config
            $parentActions = $menuData['actions'] ?? ['index'];
            foreach ($parentActions as $action) {
                Permission::firstOrCreate([
                    'menu_id' => $parentMenu->id,
                    'name'    => strtolower(str_replace(' ', '_', $menuData['name'])) . '.' . $action,
                    'action'  => $action
                ]);
            }

            foreach ($menuData['children'] as $child) {
                $validChildNames[] = $child['name'];

                $childMenu = Menu::updateOrCreate(
                    ['name' => $child['name'], 'parent_id' => $parentMenu->id],
                    [
                        'route' => $child['route'],
                        'order' => $child['order'] ?? 0,
                    ]
                );

                // Child menu permissions — use actions from config
                $childActions = $child['actions'] ?? ['index'];
                
                if ($childActions) {
                    foreach ($childActions as $action) {
                        Permission::firstOrCreate([
                            'menu_id' => $childMenu->id,
                            'name'    => strtolower(str_replace(' ', '_', $child['name'])) . '.' . $action,
                            'action'  => $action
                        ]);
                    }
                }
            }
        }

        // 5. Hard-delete child menus that no longer exist in acl.php
        //    (cascade will auto-delete their permissions & role_permission pivot rows)
        //    Must use forceDelete() because HelperTrait enables soft deletes,
        //    and MySQL onDelete('cascade') only fires on actual DELETE, not soft deletes.
        Menu::whereNotNull('parent_id')
            ->whereNotIn('name', $validChildNames)
            ->forceDelete();

        // 6. Hard-delete parent menus that no longer exist in acl.php
        //    (cascade will auto-delete their child menus, permissions & role_permission pivot rows)
        Menu::whereNull('parent_id')
            ->whereNotIn('name', $validParentNames)
            ->forceDelete();

        // 7. Hard-delete any remaining orphan permissions (safety net)
        $validMenuIds = Menu::pluck('id')->toArray();
        Permission::whereNotIn('menu_id', $validMenuIds)->forceDelete();

        // 8. Create Super Admin user and attach the role
        $admin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name'     => 'Super Admin',
                'password' => bcrypt('123456'),
                'status'   => 1,
            ]
        );
        $admin->roles()->syncWithoutDetaching([$superAdmin->id]);

        $this->command->info('ACL Seeder completed successfully!');
    }
}
