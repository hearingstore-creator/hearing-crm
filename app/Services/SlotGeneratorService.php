<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Day;
use App\Models\ProfessionalTimeSlot;
use App\Models\User;
use Carbon\Carbon;

class SlotGeneratorService
{
    /**
     * Get available time slots for a professional on a given date.
     */
    public function generate(User $professional, string $date, ?int $excludeAppointmentId = null): array
    {
        $day = Day::fromCarbon(Carbon::parse($date));

        // Check if professional works on this day
        $workingDay = $professional->workingDays()
            ->where('day_id', $day->id)
            ->where('is_active', true)
            ->first();

        if (!$workingDay) {
            return [];
        }

        // Get professional's time slot pivots for this specific day
        $profSlotRows = ProfessionalTimeSlot::where('user_id', $professional->id)
            ->where('day_id', $day->id)
            ->whereHas('timeSlot', function ($q) {
                $q->where('status', 1);
            })
            ->with('timeSlot')
            ->get();

        if ($profSlotRows->isEmpty()) {
            return [];
        }

        // Get booked pivot IDs for this professional on this date (excluding current appointment)
        $query = Appointment::where('user_id', $professional->id)
            ->where('appointment_date', $date)
            ->whereIn('status', [AppointmentStatus::SCHEDULED, AppointmentStatus::COMPLETED]);

        if ($excludeAppointmentId) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        $bookedSlotIds = $query->pluck('professional_time_slot_id')->toArray();

        $slots = [];
        foreach ($profSlotRows as $row) {
            $timeSlot = $row->timeSlot;

            $slots[] = [
                'professional_time_slot_id' => $row->id,
                'start_time'               => $timeSlot->start_time->format('g:i A'),
                'end_time'                 => $timeSlot->end_time->format('g:i A'),
                'available'               => !in_array($row->id, $bookedSlotIds),
            ];
        }

        return $slots;
    }

    /**
     * Check if a specific professional time slot is available on a given date.
     */
    public function isSlotAvailable(User $professional, string $date, int $professionalTimeSlotId): bool
    {
        return !Appointment::where('user_id', $professional->id)
            ->where('appointment_date', $date)
            ->where('professional_time_slot_id', $professionalTimeSlotId)
            ->whereIn('status', [AppointmentStatus::SCHEDULED, AppointmentStatus::COMPLETED])
            ->exists();
    }
}
