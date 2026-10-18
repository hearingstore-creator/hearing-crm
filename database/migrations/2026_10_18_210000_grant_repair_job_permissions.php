<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;

/**
 * RepairController now checks permissions per action. Create the new Repair Jobs Board
 * permissions (same names AclSeeder builds from config/acl.php) and give view / edit /
 * payment to every role that already opens the board, so current staff keep working.
 * void_warranty and replacement are left for Super Admin to assign in Roles & Permissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        $board = Menu::whereNotNull('parent_id')->where('name', 'Repair Jobs Board')->first();
        if (!$board) {
            return;
        }

        $permissions = [];
        foreach (['view', 'edit', 'payment', 'void_warranty', 'replacement'] as $action) {
            $permissions[$action] = Permission::firstOrCreate([
                'menu_id' => $board->id,
                'name'    => 'repair_jobs_board.' . $action,
                'action'  => $action,
            ]);
        }

        $grantIds = collect($permissions)->only(['view', 'edit', 'payment'])->pluck('id')->all();

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'repair_jobs_board.index'))
            ->get()
            ->each(fn ($role) => $role->permissions()->syncWithoutDetaching($grantIds));
    }

    public function down(): void
    {
    }
};
