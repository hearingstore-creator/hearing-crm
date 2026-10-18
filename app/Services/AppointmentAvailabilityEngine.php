<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentHold;
use App\Models\AppointmentHistory;
use App\Models\AppointmentService;
use App\Models\BranchSchedule;
use App\Models\OfficeHoliday;
use App\Models\Resource;
use App\Models\StaffBreak;
use App\Models\StaffLeave;
use App\Models\StaffSchedule;
use App\Models\StaffScheduleOverride;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentAvailabilityEngine
{
    /** Grid interval step in minutes */
    public const GRID_STEP = 15;

    /** Active statuses that occupy clinical time */
    public const OCCUPIED_STATUSES = [
        'scheduled',
        'booked',
        'confirmed',
        'arrived',
        'in_progress',
    ];

    /**
     * Get all available start times on the 15-minute grid for a given service, branch, and date.
     * Optionally filtered to a specific professional (or all eligible professionals if null).
     *
     * @return array{
     *     date: string,
     *     branch_id: int,
     *     service_id: int,
     *     duration: int,
     *     buffer: int,
     *     is_closed: bool,
     *     closure_reason: ?string,
     *     slots: array<int, array{
     *         time: string,
     *         label: string,
     *         period: string,
     *         user_id: int,
     *         user_name: string,
     *         resource_id: ?int
     *     }>
     * }
     */
    public function getAvailableTimes(
        int $branchId,
        int $serviceId,
        string $date,
        ?int $specificUserId = null,
        ?string $excludeHoldToken = null
    ): array {
        $tz = 'Asia/Dhaka';
        $carbonDate = Carbon::parse($date, $tz)->startOfDay();
        $isoDay = $carbonDate->dayOfWeekIso; // 1=Mon ... 7=Sun, 5=Fri

        $service = AppointmentService::with('professionals')->findOrFail($serviceId);
        $duration = (int) ($service->duration_minutes ?: 45);
        $buffer = (int) ($service->buffer_after_minutes ?: 10);
        $requiresBooth = (bool) $service->requires_resource;

        // 1. Check Branch Operating Schedule & Weekly Off
        $branchSchedule = BranchSchedule::where('vendor_id', $branchId)
            ->where('day_of_week', $isoDay)
            ->first();

        if ($branchSchedule && $branchSchedule->is_closed) {
            return [
                'date' => $date,
                'branch_id' => $branchId,
                'service_id' => $serviceId,
                'duration' => $duration,
                'buffer' => $buffer,
                'is_closed' => true,
                'closure_reason' => 'Branch is closed on ' . BranchSchedule::isoDayName($isoDay) . ' (Weekly Off)',
                'slots' => [],
            ];
        }

        // 2. Check Public / Branch Holiday
        $holiday = OfficeHoliday::where(function ($q) use ($branchId) {
            $q->whereNull('vendor_id')->orWhere('vendor_id', $branchId);
        })->whereDate('date', $date)->first();

        if ($holiday) {
            return [
                'date' => $date,
                'branch_id' => $branchId,
                'service_id' => $serviceId,
                'duration' => $duration,
                'buffer' => $buffer,
                'is_closed' => true,
                'closure_reason' => 'Branch is closed for ' . $holiday->name,
                'slots' => [],
            ];
        }

        $clinicOpen = $branchSchedule ? Carbon::parse($date . ' ' . $branchSchedule->open_time, $tz) : Carbon::parse($date . ' 09:30:00', $tz);
        $clinicClose = $branchSchedule ? Carbon::parse($date . ' ' . $branchSchedule->close_time, $tz) : Carbon::parse($date . ' 19:00:00', $tz);

        // 3. Find Eligible Staff at this Branch on this Day
        $staffQuery = StaffSchedule::where('vendor_id', $branchId)
            ->where('day_of_week', $isoDay)
            ->where('is_active', true)
            ->with('user');

        if ($specificUserId) {
            $staffQuery->where('user_id', $specificUserId);
        }

        $rosteredShifts = $staffQuery->get();

        if ($rosteredShifts->isEmpty()) {
            return [
                'date' => $date,
                'branch_id' => $branchId,
                'service_id' => $serviceId,
                'duration' => $duration,
                'buffer' => $buffer,
                'is_closed' => false,
                'closure_reason' => 'No specialists rostered at this branch on this date',
                'slots' => [],
            ];
        }

        // 4. Find Branch Sound Booths if required
        $boothIds = [];
        if ($requiresBooth) {
            $boothIds = Resource::where('vendor_id', $branchId)
                ->where('type', 'sound_booth')
                ->where('is_active', true)
                ->pluck('id')
                ->all();

            if (empty($boothIds)) {
                return [
                    'date' => $date,
                    'branch_id' => $branchId,
                    'service_id' => $serviceId,
                    'duration' => $duration,
                    'buffer' => $buffer,
                    'is_closed' => false,
                    'closure_reason' => 'No sound booth available at this branch',
                    'slots' => [],
                ];
            }
        }

        // 5. Fetch Existing Bookings on this Date & Branch
        $existingAppointments = Appointment::where('appointment_date', $date)
            ->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            })
            ->whereIn('status', self::OCCUPIED_STATUSES)
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->get(['id', 'user_id', 'resource_id', 'start_time', 'end_time', 'duration_minutes']);

        // 6. Fetch Active Website Holds
        $activeHoldsQuery = AppointmentHold::where('vendor_id', $branchId)
            ->whereDate('hold_date', $date)
            ->where('expires_at', '>', Carbon::now($tz));

        if ($excludeHoldToken) {
            $activeHoldsQuery->where('token', '!=', $excludeHoldToken);
        }

        $activeHolds = $activeHoldsQuery->get(['id', 'user_id', 'resource_id', 'start_time', 'end_time']);

        // 7. Generate Candidate Slots across shifts
        $now = Carbon::now($tz);
        $minNoticeMinutes = 15;
        $candidateTimes = [];

        foreach ($rosteredShifts as $shift) {
            $user = $shift->user;
            if (!$user || $user->status != 1) {
                continue;
            }

            // Check if professional can perform this service
            if ($service->professionals->isNotEmpty() && !$service->professionals->contains('id', $user->id)) {
                continue;
            }

            // Check Date-Specific Overrides for this user
            $override = StaffScheduleOverride::where('user_id', $user->id)
                ->where('override_date', $date)
                ->first();

            if ($override && $override->is_day_off) {
                continue; // Staff marked day off
            }

            $shiftStart = $override && $override->start_time
                ? Carbon::parse($date . ' ' . $override->start_time, $tz)
                : Carbon::parse($date . ' ' . $shift->start_time, $tz);

            $shiftEnd = $override && $override->end_time
                ? Carbon::parse($date . ' ' . $override->end_time, $tz)
                : Carbon::parse($date . ' ' . $shift->end_time, $tz);

            // Clamp shift to clinic opening hours
            if ($shiftStart->lt($clinicOpen)) $shiftStart = $clinicOpen->copy();
            if ($shiftEnd->gt($clinicClose)) $shiftEnd = $clinicClose->copy();

            // Loop through 15-minute grid candidate start times
            $curr = $shiftStart->copy();
            // Align to 15-minute grid
            $minRem = $curr->minute % self::GRID_STEP;
            if ($minRem > 0) {
                $curr->addMinutes(self::GRID_STEP - $minRem);
            }

            // Active breaks for this staff or clinic-wide at this branch (consistent weekly or day-specific)
            $breaks = StaffBreak::where('vendor_id', $branchId)
                ->where('is_active', true)
                ->where(function ($q) use ($user) {
                    $q->whereNull('user_id')->orWhere('user_id', $user->id);
                })
                ->where(function ($q) use ($isoDay) {
                    $q->whereNull('day_of_week')->orWhere('day_of_week', $isoDay);
                })
                ->get();

            // Approved leaves for this staff overlapping this date
            $leaves = StaffLeave::where('user_id', $user->id)
                ->where('status', 'approved')
                ->where('start_datetime', '<=', $date . ' 23:59:59')
                ->where('end_datetime', '>=', $date . ' 00:00:00')
                ->get();

            while ($curr->lt($shiftEnd)) {
                $startTimeStr = $curr->format('H:i:s');
                $procEnd = $curr->copy()->addMinutes($duration);
                $fullEndWithBuffer = $procEnd->copy()->addMinutes($buffer);

                // Rule A: Procedure and buffer must not exceed shift end or clinic close
                if ($procEnd->gt($shiftEnd) || $fullEndWithBuffer->gt($clinicClose)) {
                    $curr->addMinutes(self::GRID_STEP);
                    continue;
                }

                // Rule B: For today, slot must not be in the past or within min notice
                if ($carbonDate->isToday()) {
                    if ($curr->lte($now->copy()->addMinutes($minNoticeMinutes))) {
                        $curr->addMinutes(self::GRID_STEP);
                        continue;
                    }
                }

                $startTimeHi = $curr->format('H:i');
                $endTimeHi = $procEnd->format('H:i');

                // Rule C1: Staff Breaks (e.g. Lunch 13:30-14:30)
                // Buffer and procedure cannot overlap the break
                $breakConflict = $breaks->contains(function ($b) use ($curr, $fullEndWithBuffer, $date, $tz) {
                    $bStart = Carbon::parse($date . ' ' . $b->start_time, $tz);
                    $bEnd = Carbon::parse($date . ' ' . $b->end_time, $tz);
                    return $curr->lt($bEnd) && $fullEndWithBuffer->gt($bStart);
                });

                if ($breakConflict) {
                    $curr->addMinutes(self::GRID_STEP);
                    continue;
                }

                // Rule C2: Approved Leaves
                $leaveConflict = $leaves->contains(function ($l) use ($curr, $fullEndWithBuffer) {
                    return $curr->lt($l->end_datetime) && $fullEndWithBuffer->gt($l->start_datetime);
                });

                if ($leaveConflict) {
                    $curr->addMinutes(self::GRID_STEP);
                    continue;
                }

                // Rule C3: Staff must be free during [curr, fullEndWithBuffer]
                $staffConflict = $existingAppointments->contains(function ($appt) use ($user, $curr, $fullEndWithBuffer) {
                    if ($appt->user_id != $user->id) return false;
                    $apptStart = Carbon::parse($curr->format('Y-m-d') . ' ' . $appt->start_time);
                    $apptEnd = Carbon::parse($curr->format('Y-m-d') . ' ' . $appt->end_time);
                    return $curr->lt($apptEnd) && $fullEndWithBuffer->gt($apptStart);
                });

                if ($staffConflict) {
                    $curr->addMinutes(self::GRID_STEP);
                    continue;
                }

                // Rule D: Check website holds for this staff
                $holdConflict = $activeHolds->contains(function ($hold) use ($user, $curr, $fullEndWithBuffer) {
                    if ($hold->user_id && $hold->user_id != $user->id) return false;
                    $holdStart = Carbon::parse($curr->format('Y-m-d') . ' ' . $hold->start_time);
                    $holdEnd = Carbon::parse($curr->format('Y-m-d') . ' ' . $hold->end_time);
                    return $curr->lt($holdEnd) && $fullEndWithBuffer->gt($holdStart);
                });

                if ($holdConflict) {
                    $curr->addMinutes(self::GRID_STEP);
                    continue;
                }

                // Rule E: If service requires booth, check booth availability
                $assignedBoothId = null;
                if ($requiresBooth) {
                    foreach ($boothIds as $boothId) {
                        $boothOccupied = $existingAppointments->contains(function ($appt) use ($boothId, $curr, $fullEndWithBuffer) {
                            if ($appt->resource_id != $boothId) return false;
                            $apptStart = Carbon::parse($curr->format('Y-m-d') . ' ' . $appt->start_time);
                            $apptEnd = Carbon::parse($curr->format('Y-m-d') . ' ' . $appt->end_time);
                            return $curr->lt($apptEnd) && $fullEndWithBuffer->gt($apptStart);
                        });

                        $boothHeld = $activeHolds->contains(function ($hold) use ($boothId, $curr, $fullEndWithBuffer) {
                            if ($hold->resource_id && $hold->resource_id != $boothId) return false;
                            $holdStart = Carbon::parse($curr->format('Y-m-d') . ' ' . $hold->start_time);
                            $holdEnd = Carbon::parse($curr->format('Y-m-d') . ' ' . $hold->end_time);
                            return $curr->lt($holdEnd) && $fullEndWithBuffer->gt($holdStart);
                        });

                        if (!$boothOccupied && !$boothHeld) {
                            $assignedBoothId = $boothId;
                            break; // Found an available booth
                        }
                    }

                    if (!$assignedBoothId) {
                        // Booth is busy even though doctor is free!
                        $curr->addMinutes(self::GRID_STEP);
                        continue;
                    }
                }

                // Categorize period (Morning / Afternoon / Evening)
                $hour = (int) $curr->format('H');
                $period = $hour < 12 ? 'morning' : ($hour < 16 ? 'afternoon' : 'evening');

                // Key by start time so multiple doctors free at 10:00 consolidate seamlessly
                $timeKey = $curr->format('H:i');
                if (!isset($candidateTimes[$timeKey])) {
                    $candidateTimes[$timeKey] = [
                        'time'        => $curr->format('H:i'),
                        'time_full'   => $curr->format('H:i:s'),
                        'end_time'    => $procEnd->format('H:i:s'),
                        'label'       => $curr->format('g:i A'),
                        'period'      => $period,
                        'user_id'     => $user->id,
                        'user_name'   => $user->name,
                        'resource_id' => $assignedBoothId,
                    ];
                }

                $curr->addMinutes(self::GRID_STEP);
            }
        }

        ksort($candidateTimes);

        return [
            'date' => $date,
            'branch_id' => $branchId,
            'service_id' => $serviceId,
            'duration' => $duration,
            'buffer' => $buffer,
            'is_closed' => false,
            'closure_reason' => null,
            'slots' => array_values($candidateTimes),
        ];
    }

    /**
     * Atomically book an appointment with pessimistic row locks on User and Resource.
     */
    public function bookWithLock(array $data): Appointment
    {
        return DB::transaction(function () use ($data) {
            $userId = (int) $data['user_id'];
            $branchId = (int) $data['branch_id'];
            $date = $data['appointment_date'];
            $startTime = $data['start_time'];
            $duration = (int) ($data['duration_minutes'] ?? 45);

            // 1. Lock the PROFESSIONAL's User row
            $user = User::where('id', $userId)->lockForUpdate()->firstOrFail();

            // 2. Lock the RESOURCE row if provided
            $resource = null;
            if (!empty($data['resource_id'])) {
                $resource = Resource::where('id', $data['resource_id'])->lockForUpdate()->firstOrFail();
            }

            // 3. Re-verify availability inside the lock (unless forced overbook by authorized role)
            $isOverbook = !empty($data['is_overbooked']);
            if (!$isOverbook) {
                $serviceId = (int) $data['service_id'];
                $holdToken = $data['hold_token'] ?? null;
                $availability = $this->getAvailableTimes($branchId, $serviceId, $date, $userId, $holdToken);
                $foundSlot = collect($availability['slots'])->firstWhere('time', substr($startTime, 0, 5));

                if (!$foundSlot) {
                    throw ValidationException::withMessages([
                        'start_time' => 'This time slot was just booked by another user. Please choose another time.',
                    ]);
                }
            }

            // Compute end_time if missing
            if (empty($data['end_time'])) {
                $data['end_time'] = Carbon::parse($date . ' ' . $startTime)->addMinutes($duration)->format('H:i:s');
            }

            // 4. Create the appointment
            $appointment = Appointment::create($data);

            // 5. Delete website hold token if used
            if (!empty($data['hold_token'])) {
                AppointmentHold::where('token', $data['hold_token'])->delete();
            }

            // 6. Write initial audit record
            AppointmentHistory::create([
                'appointment_id' => $appointment->id,
                'from_status'    => 'none',
                'to_status'      => is_object($appointment->status) ? $appointment->status->value : (string) $appointment->status,
                'old_date'       => null,
                'new_date'       => $appointment->appointment_date,
                'old_start_time' => null,
                'new_start_time' => $appointment->start_time,
                'old_user_id'    => null,
                'new_user_id'    => $appointment->user_id,
                'reason'         => $isOverbook ? ('Overbooked: ' . ($data['overbook_reason'] ?? 'None')) : 'Initial Booking',
                'changed_by'     => auth()->id(),
                'created_at'     => now(),
            ]);

            return $appointment;
        });
    }
}
