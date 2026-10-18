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
        // 1. Report parent menu
        $reportParent = Menu::whereNull('parent_id')->where('name', 'Report')->first();
        if ($reportParent) {
            $reportChild = Menu::updateOrCreate(
                ['name' => 'Doctor & Referral Statement', 'parent_id' => $reportParent->id],
                [
                    'label' => 'Doctor Statement',
                    'route' => 'admin/report/doctor-statement',
                    'icon'  => 'fa-user-md',
                    'order' => 5,
                ]
            );

            $adminRoles = Role::whereIn('name', ['Super Admin', 'Admin', 'Branch Manager', 'Manager'])->get();
            foreach (['index'] as $action) {
                $permission = Permission::firstOrCreate([
                    'menu_id' => $reportChild->id,
                    'name'    => 'doctor_statement.' . $action,
                    'action'  => $action,
                ]);

                foreach ($adminRoles as $role) {
                    $role->permissions()->syncWithoutDetaching([$permission->id]);
                }
            }
        }

        // 2. Doctor parent menu
        $doctorParent = Menu::whereNull('parent_id')->where('name', 'Doctor')->first();
        if ($doctorParent) {
            // Update child #69 or create Doctor Statement
            $doctorChild = Menu::where('parent_id', $doctorParent->id)
                ->where(function ($q) {
                    $q->where('name', 'like', '%Referral%')
                      ->orWhere('route', 'like', '%doctor-referral%');
                })
                ->first();

            if ($doctorChild) {
                $doctorChild->name = 'Doctor & Referral Statement';
                $doctorChild->label = 'Doctor Statement';
                $doctorChild->route = 'admin/report/doctor-statement';
                $doctorChild->save();
            } else {
                $doctorChild = Menu::create([
                    'parent_id' => $doctorParent->id,
                    'name'      => 'Doctor & Referral Statement',
                    'label'     => 'Doctor Statement',
                    'route'     => 'admin/report/doctor-statement',
                    'icon'      => 'fa-file-invoice',
                    'order'     => 4,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $reportParent = Menu::whereNull('parent_id')->where('name', 'Report')->first();
        if ($reportParent) {
            $child = Menu::where('parent_id', $reportParent->id)->where('name', 'Doctor & Referral Statement')->first();
            if ($child) {
                Permission::where('menu_id', $child->id)->delete();
                $child->delete();
            }
        }
    }
};
