<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Menu;

/**
 * 2026_10_16_030000 renamed the Doctor menu's "Doctor & Referral" child to
 * "Doctor & Referral Statement", leaving two menus with that name (Doctor and Report).
 * config/acl.php now lists "Doctor & Referral" (Referral Report) and
 * "Doctor & Referral Statement" (Doctor Statement) under Doctor, so restore the
 * renamed child here. Its permission rows and role links are kept; AclSeeder then
 * moves the Report-menu statement under Doctor with its doctor_statement permission.
 */
return new class extends Migration
{
    public function up(): void
    {
        $doctorParent = Menu::whereNull('parent_id')->where('name', 'Doctor')->first();
        if (!$doctorParent) {
            return;
        }

        $hasReferral = Menu::where('parent_id', $doctorParent->id)->where('name', 'Doctor & Referral')->exists();
        if ($hasReferral) {
            return;
        }

        Menu::where('parent_id', $doctorParent->id)
            ->where('name', 'Doctor & Referral Statement')
            ->whereHas('permissions', fn ($q) => $q->where('name', 'doctor_&_referral.index'))
            ->update([
                'name'  => 'Doctor & Referral',
                'label' => 'Referral Report',
                'route' => 'admin/report/doctor-referral',
            ]);
    }

    public function down(): void
    {
    }
};
