<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\TimeSlot;
use App\Models\ProfessionalTimeSlot;

class AppointmentRedesignSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Assign Professionals to Branches as specified by the user
        // Dr. SM TAISER -> Hearing Store, Natore (Vendor ID 6)
        User::where('id', 3)->update(['vendor_id' => 6]);
        // Dr. MD BULBUL AHMED -> HQ / Rotates (Vendor ID null)
        User::where('id', 4)->update(['vendor_id' => null]);
        // Dr. Ador Chowdhury -> Universal Hearing Center, Mymensingh (Vendor ID 2)
        User::where('id', 5)->update(['vendor_id' => 2]);

        // 2. Seed Initial Resources (Sound Booths)
        DB::table('resources')->updateOrInsert(
            ['vendor_id' => 2, 'name' => 'Sound Booth 1'],
            ['type' => 'sound_booth', 'capacity' => 1, 'is_active' => 1, 'updated_at' => now(), 'created_at' => now()]
        );
        DB::table('resources')->updateOrInsert(
            ['vendor_id' => 6, 'name' => 'Sound Booth 1'],
            ['type' => 'sound_booth', 'capacity' => 1, 'is_active' => 1, 'updated_at' => now(), 'created_at' => now()]
        );

        // 3. Seed Branch Schedules for Mymensingh (2) and Natore (6)
        // ISO-8601: 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat, 7=Sun
        foreach ([2, 6] as $branchId) {
            for ($isoDay = 1; $isoDay <= 7; $isoDay++) {
                $isFriday = ($isoDay === 5);
                DB::table('branch_schedules')->updateOrInsert(
                    ['vendor_id' => $branchId, 'day_of_week' => $isoDay],
                    [
                        'open_time' => '09:30:00',
                        'close_time' => '19:00:00',
                        'is_closed' => $isFriday ? 1 : 0,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        // 4. Update appointment_services attributes
        // ID 1: Hearing Aid Counselling (consultation)
        AppointmentService::where('id', 1)->update([
            'duration_minutes' => 45,
            'buffer_after_minutes' => 10,
            'requires_resource' => 0,
            'resource_type' => null,
            'is_online_booking_enabled' => 0,
            'color_hex' => '#2563eb',
        ]);
        // ID 2: Hearing Assessment Package for HA (diagnostic) -> Requires Sound Booth!
        AppointmentService::where('id', 2)->update([
            'duration_minutes' => 45,
            'buffer_after_minutes' => 10,
            'requires_resource' => 1,
            'resource_type' => 'sound_booth',
            'is_online_booking_enabled' => 0,
            'color_hex' => '#059669',
        ]);
        // ID 3: Hearing Aid Fitting with REM (treatment)
        AppointmentService::where('id', 3)->update([
            'duration_minutes' => 60,
            'buffer_after_minutes' => 10,
            'requires_resource' => 0,
            'resource_type' => null,
            'is_online_booking_enabled' => 0,
            'color_hex' => '#7c3aed',
        ]);
        // ID 4: AVT Therapy (treatment)
        AppointmentService::where('id', 4)->update([
            'duration_minutes' => 45,
            'buffer_after_minutes' => 10,
            'requires_resource' => 0,
            'resource_type' => null,
            'is_online_booking_enabled' => 0,
            'color_hex' => '#d97706',
        ]);

        // 5. Seed Staff Schedules (Weekly Shifts)
        // Dr. SM TAISER (ID 3) at Natore (Vendor ID 6): Sat, Sun, Mon, Tue, Wed, Thu (ISO: 6, 7, 1, 2, 3, 4)
        foreach ([6, 7, 1, 2, 3, 4] as $isoDay) {
            DB::table('staff_schedules')->updateOrInsert(
                ['user_id' => 3, 'vendor_id' => 6, 'day_of_week' => $isoDay],
                ['start_time' => '09:30:00', 'end_time' => '18:30:00', 'is_active' => 1, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // Dr. Ador Chowdhury (ID 5) at Mymensingh (Vendor ID 2): Sat, Sun, Mon, Tue, Wed, Thu (ISO: 6, 7, 1, 2, 3, 4)
        foreach ([6, 7, 1, 2, 3, 4] as $isoDay) {
            DB::table('staff_schedules')->updateOrInsert(
                ['user_id' => 5, 'vendor_id' => 2, 'day_of_week' => $isoDay],
                ['start_time' => '09:30:00', 'end_time' => '18:30:00', 'is_active' => 1, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // Dr. MD BULBUL AHMED (ID 4) rotates: Sat, Mon, Wed at Mymensingh (2); Sun, Tue, Thu at Natore (6)
        foreach ([6, 1, 3] as $isoDay) { // Sat, Mon, Wed
            DB::table('staff_schedules')->updateOrInsert(
                ['user_id' => 4, 'vendor_id' => 2, 'day_of_week' => $isoDay],
                ['start_time' => '09:30:00', 'end_time' => '18:30:00', 'is_active' => 1, 'updated_at' => now(), 'created_at' => now()]
            );
        }
        foreach ([7, 2, 4] as $isoDay) { // Sun, Tue, Thu
            DB::table('staff_schedules')->updateOrInsert(
                ['user_id' => 4, 'vendor_id' => 6, 'day_of_week' => $isoDay],
                ['start_time' => '09:30:00', 'end_time' => '18:30:00', 'is_active' => 1, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // 6. Backfill the 10 existing appointments
        $appts = Appointment::with('professionalTimeSlot.timeSlot', 'service')->get();
        foreach ($appts as $a) {
            $slot = $a->professionalTimeSlot?->timeSlot;
            if ($slot) {
                $a->start_time = $slot->start_time->format('H:i:s');
                $a->end_time = $slot->end_time->format('H:i:s');
                $a->duration_minutes = 60;
                $a->is_time_unknown = 0;
            } else {
                $a->start_time = null;
                $a->end_time = null;
                $a->is_time_unknown = 1;
            }

            // Assign branch: if doctor is Ador (5) -> 2; Taiser (3) -> 6; Bulbul (4) -> 2
            if ($a->user_id == 5) {
                $a->branch_id = 2;
            } elseif ($a->user_id == 3) {
                $a->branch_id = 6;
            } else {
                $a->branch_id = 2; // Default Dr. Bulbul's initial clinic to Mymensingh
            }

            // If service requires resource (Hearing Assessment Package for HA), assign Sound Booth 1 of that branch
            if ($a->service?->requires_resource) {
                $boothId = DB::table('resources')->where('vendor_id', $a->branch_id)->where('type', 'sound_booth')->value('id');
                $a->resource_id = $boothId;
            }

            $a->save();
        }

        // 7. Seed Daily Lunch Breaks (13:30 to 14:30) for both branches
        foreach ([2, 6] as $branchId) {
            DB::table('staff_breaks')->updateOrInsert(
                ['vendor_id' => $branchId, 'name' => 'Lunch Break'],
                [
                    'user_id' => null, // applies to all staff at the branch
                    'start_time' => '13:30:00',
                    'end_time' => '14:30:00',
                    'is_active' => 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
