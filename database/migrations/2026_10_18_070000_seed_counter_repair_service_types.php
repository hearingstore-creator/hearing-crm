<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\ServiceType;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            [
                'service_type_name' => 'Wax Guard / Filter Replacement',
                'booking_type'      => ServiceType::BOOKING_WALK_IN,
                'price'             => 150.00,
                'status'            => 1,
            ],
            [
                'service_type_name' => 'Sound Tube / Eartip Replacement',
                'booking_type'      => ServiceType::BOOKING_WALK_IN,
                'price'             => 250.00,
                'status'            => 1,
            ],
            [
                'service_type_name' => 'Earmold Buffing & Precision Polishing',
                'booking_type'      => ServiceType::BOOKING_WALK_IN,
                'price'             => 200.00,
                'status'            => 1,
            ],
            [
                'service_type_name' => 'Acoustic Dehumidifying & Vacuum Cleaning',
                'booking_type'      => ServiceType::BOOKING_WALK_IN,
                'price'             => 200.00,
                'status'            => 1,
            ],
        ];

        foreach ($defaults as $svc) {
            ServiceType::firstOrCreate(
                ['service_type_name' => $svc['service_type_name']],
                $svc
            );
        }
    }

    public function down(): void
    {
        // Keep data intact
    }
};
