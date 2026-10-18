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
        Log::info('ACL Seeder started');

        // 1. Create Roles
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin']);

        // 2. Menu structure from config
        $menus = config('acl.menus');

        // 3. Pre-process: Migrate children that moved to a different parent
        //    (e.g., "Add Ticket" moved from "Ticket" parent to "Support" parent)
        //    This preserves permission IDs and role_permission pivot records.
        $this->migrateMovedChildren($menus);

        // 4. Collect valid menu names from config for cleanup later
        $validParentNames = [];
        $validChildNames = [];

        // 5. Create/Update Menus & Permissions using actions from config
        foreach ($menus as $menuData) {
            $validParentNames[] = $menuData['name'];

            $parentMenu = Menu::updateOrCreate(
                ['name' => $menuData['name']],
                [
                    'label'    => $menuData['label'] ?? null,
                    'icon'     => $menuData['icon'] ?? null,
                    'route'    => $menuData['route'] ?? null,
                    'order'    => $menuData['order'] ?? 0,
                    'category' => $menuData['category'] ?? null,
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

            foreach ($menuData['children'] as $childIndex => $child) {
                $validChildNames[] = $child['name'];

                $childMenu = Menu::updateOrCreate(
                    ['name' => $child['name'], 'parent_id' => $parentMenu->id],
                    [
                        'label' => $child['label'] ?? null,
                        'route' => $child['route'],
                        'icon'  => $child['icon'] ?? null,
                        'order' => $child['order'] ?? $childIndex,
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

        // 6. Hard-delete child menus that no longer exist in acl.php
        //    (cascade will auto-delete their permissions & role_permission pivot rows)
        //    Must use forceDelete() because HelperTrait enables soft deletes,
        //    and MySQL onDelete('cascade') only fires on actual DELETE, not soft deletes.
        Menu::whereNotNull('parent_id')
            ->whereNotIn('name', $validChildNames)
            ->forceDelete();

        // 7. Hard-delete parent menus that no longer exist in acl.php
        //    (cascade will auto-delete their child menus, permissions & role_permission pivot rows)
        Menu::whereNull('parent_id')
            ->whereNotIn('name', $validParentNames)
            ->forceDelete();

        // 8. Hard-delete any remaining orphan permissions (safety net)
        $validMenuIds = Menu::pluck('id')->toArray();
        Permission::whereNotIn('menu_id', $validMenuIds)->forceDelete();

        // 9. Create Super Admin user only on a fresh install (no users yet).
        //    This seeder runs on every deploy, so never recreate the default login on live.
        if (User::count() === 0) {
            $admin = User::create([
                'email'    => 'admin@gmail.com',
                'name'     => 'Super Admin',
                'password' => bcrypt('123456'),
                'status'   => 1,
            ]);
            $admin->roles()->syncWithoutDetaching([$superAdmin->id]);
        }

        $this->command->info('ACL Seeder completed successfully!');
    }

    /**
     * Migrate children that have moved to a different parent.
     * 
     * When a child menu (e.g., "Add Ticket") moves from one parent (e.g., "Ticket")
     * to another (e.g., "Support"), we need to update its parent_id BEFORE the main
     * updateOrCreate loop runs. Otherwise, updateOrCreate would create a duplicate
     * (same name, different parent_id), and the cleanup would delete the old one
     * along with its permissions and role_permission pivot records.
     */
    private function migrateMovedChildren(array $menus): void
    {
        foreach ($menus as $menuData) {
            // Ensure the new parent exists first
            $newParent = Menu::whereNull('parent_id')
                ->where('name', $menuData['name'])
                ->first();

            // If the new parent doesn't exist yet, it will be created by updateOrCreate
            // in the main loop. Skip migration for now — the children will be created fresh.
            if (!$newParent) {
                continue;
            }

            foreach ($menuData['children'] as $child) {
                // Find this child under ANY parent (not just the current one)
                $existingChild = Menu::whereNotNull('parent_id')
                    ->where('name', $child['name'])
                    ->where('parent_id', '!=', $newParent->id)
                    ->first();

                if ($existingChild) {
                    // Move the child to the new parent (preserves ID, permissions, role_permission)
                    $existingChild->update(['parent_id' => $newParent->id]);
                    $this->command->info("  Migrated '{$child['name']}' from parent #{$existingChild->getOriginal('parent_id')} to '{$menuData['name']}' (#{$newParent->id})");
                }
            }
        }
    }
}
