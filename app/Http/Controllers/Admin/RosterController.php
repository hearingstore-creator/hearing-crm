<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Classes\Helper;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentHistory;
use App\Models\BranchSchedule;
use App\Models\StaffBreak;
use App\Models\StaffLeave;
use App\Models\StaffSchedule;
use App\Models\StaffScheduleOverride;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RosterController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:roster.index|roster.view')->only(['index']);
        $this->middleware('permission:roster.edit')->only([
            'saveShift', 'deleteShift', 'saveBranchSchedule', 'saveBreak', 'deleteBreak', 'saveStaffHoliday',
            'approveLeave', 'rejectLeave', 'deleteLeave',
        ]);
        // Staff (professionals have roster.view) can ask for leave; approval still needs roster.edit
        $this->middleware('permission:roster.create|roster.edit|roster.view')->only(['requestLeave']);
    }

    /**
     * Roster & Schedule Dashboard.
     */
    public function index(Request $request): View
    {
        $branchId = $request->filled('branch_id') ? (int) $request->branch_id : (Helper::currentBranchId() ?? 2);

        $data['branches'] = Helper::branchOptions();
        $data['currentBranchId'] = $branchId;
        $data['canViewAllBranches'] = auth()->check() ? auth()->user()->canViewAllBranches() : false;

        // Specialists
        $data['professionals'] = User::where('type', 'professional')
            ->where('status', 1)
            ->with(['staffSchedules' => function ($q) use ($branchId) {
                $q->where('vendor_id', $branchId)->orderBy('day_of_week');
            }])
            ->orderBy('name')
            ->get();

        // Clinic Branch Operating Hours
        $data['branchSchedules'] = BranchSchedule::where('vendor_id', $branchId)
            ->orderBy('day_of_week')
            ->get()
            ->keyBy('day_of_week');

        // Ensure all 7 days exist for UI
        foreach (range(1, 7) as $dayNum) {
            if (!isset($data['branchSchedules'][$dayNum])) {
                $data['branchSchedules'][$dayNum] = BranchSchedule::create([
                    'vendor_id'   => $branchId,
                    'day_of_week' => $dayNum,
                    'open_time'   => '09:30:00',
                    'close_time'  => '19:00:00',
                    'is_closed'   => ($dayNum === 5), // default Friday closed
                ]);
            }
        }

        // Branch Breaks (ordered by day_of_week, start_time)
        $data['breaks'] = StaffBreak::where('vendor_id', $branchId)
            ->with('user')
            ->orderByRaw('day_of_week IS NULL, day_of_week ASC')
            ->orderBy('start_time')
            ->get();

        // Approved Staff Holidays & Days Off (Active & Upcoming)
        $data['staffHolidays'] = StaffLeave::with(['user', 'approver', 'branch'])
            ->where('status', 'approved')
            ->where(function ($q) use ($branchId) {
                $q->where('vendor_id', $branchId)->orWhereNull('vendor_id');
            })
            ->whereDate('end_datetime', '>=', now()->subDays(30)->toDateString())
            ->orderBy('start_datetime', 'desc')
            ->get();

        // Pending Leave Requests
        $data['leaves'] = StaffLeave::with(['user', 'approver', 'branch'])
            ->where(function ($q) use ($branchId) {
                $q->where('vendor_id', $branchId)->orWhereNull('vendor_id');
            })
            ->orderBy('start_datetime', 'desc')
            ->paginate(15);

        return view('admin.roster.index', $data);
    }

    /**
     * Save/Update Branch Operating Hours and Weekly Off Days (e.g. 7-day office).
     */
    public function saveBranchSchedule(Request $request): RedirectResponse
    {
        $branchId = (int) $request->input('vendor_id');
        $days = $request->input('days', []);

        foreach (range(1, 7) as $day) {
            $dayData = $days[$day] ?? [];
            $isClosed = !empty($dayData['is_closed']);
            $openTime = !empty($dayData['open_time']) ? $dayData['open_time'] : '09:30:00';
            $closeTime = !empty($dayData['close_time']) ? $dayData['close_time'] : '19:00:00';

            BranchSchedule::updateOrCreate(
                [
                    'vendor_id'   => $branchId,
                    'day_of_week' => $day,
                ],
                [
                    'is_closed'  => $isClosed,
                    'open_time'  => $openTime,
                    'close_time' => $closeTime,
                ]
            );
        }

        return redirect()->route('roster.index', ['branch_id' => $branchId])
            ->with('success', 'Branch operating schedule updated successfully.');
    }

    /**
     * Save/Update a weekly duty shift for a professional.
     * Enforces the Single-Branch-Per-Day Staff Rotation Rule!
     */
    public function saveShift(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id'     => 'required|integer|exists:users,id',
            'vendor_id'   => 'required|integer|exists:users,id',
            'day_of_week' => 'required|integer|between:1,7',
            'start_time'  => 'required|string',
            'end_time'    => 'required|string|after:start_time',
            'shift_id'    => 'nullable|integer|exists:staff_schedules,id',
        ]);

        $userId = $validated['user_id'];
        $dayOfWeek = $validated['day_of_week'];
        $newBranchId = $validated['vendor_id'];

        // Enforce Rotation Constraint: Staff works at ONLY ONE branch per day
        $conflictBranch = StaffSchedule::where('user_id', $userId)
            ->where('day_of_week', $dayOfWeek)
            ->where('vendor_id', '!=', $newBranchId)
            ->where('is_active', true)
            ->when(!empty($validated['shift_id']), fn($q) => $q->where('id', '!=', $validated['shift_id']))
            ->first();

        if ($conflictBranch) {
            $otherBranchName = $conflictBranch->branch?->company_name ?? $conflictBranch->branch?->name ?? "Branch #{$conflictBranch->vendor_id}";
            return redirect()->back()->with('warning', "Staff rotation conflict: This specialist is already scheduled at {$otherBranchName} on " . BranchSchedule::isoDayName($dayOfWeek) . ". A specialist can work at only one branch per calendar day.");
        }

        if (!empty($validated['shift_id'])) {
            StaffSchedule::where('id', $validated['shift_id'])->update([
                'user_id'     => $userId,
                'vendor_id'   => $newBranchId,
                'day_of_week' => $dayOfWeek,
                'start_time'  => $validated['start_time'],
                'end_time'    => $validated['end_time'],
                'is_active'   => true,
            ]);
            $msg = 'Shift updated successfully.';
        } else {
            StaffSchedule::create([
                'user_id'     => $userId,
                'vendor_id'   => $newBranchId,
                'day_of_week' => $dayOfWeek,
                'start_time'  => $validated['start_time'],
                'end_time'    => $validated['end_time'],
                'is_active'   => true,
            ]);
            $msg = 'Shift added successfully.';
        }

        return redirect()->route('roster.index', ['branch_id' => $newBranchId])->with('success', $msg);
    }

    /**
     * Delete a duty shift.
     */
    public function deleteShift(int $id): RedirectResponse
    {
        $shift = StaffSchedule::findOrFail($id);
        $branchId = $shift->vendor_id;
        $shift->delete();

        return redirect()->route('roster.index', ['branch_id' => $branchId])->with('success', 'Shift deleted.');
    }

    /**
     * Save daily break (can be consistent weekly OR day-wise different, and specialist-specific).
     */
    public function saveBreak(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'vendor_id'   => 'required|integer|exists:users,id',
            'user_id'     => 'nullable|integer|exists:users,id',
            'day_of_week' => 'nullable|integer|between:1,7',
            'name'        => 'required|string|max:100',
            'start_time'  => 'required|string',
            'end_time'    => 'required|string|after:start_time',
            'break_id'    => 'nullable|integer|exists:staff_breaks,id',
        ]);

        $dayOfWeek = !empty($validated['day_of_week']) ? (int) $validated['day_of_week'] : null;

        if (!empty($validated['break_id'])) {
            StaffBreak::where('id', $validated['break_id'])->update([
                'vendor_id'   => $validated['vendor_id'],
                'user_id'     => ($validated['user_id'] ?? null) ?: null,
                'day_of_week' => $dayOfWeek,
                'name'        => $validated['name'],
                'start_time'  => $validated['start_time'],
                'end_time'    => $validated['end_time'],
                'is_active'   => true,
            ]);
        } else {
            StaffBreak::create([
                'vendor_id'   => $validated['vendor_id'],
                'user_id'     => ($validated['user_id'] ?? null) ?: null,
                'day_of_week' => $dayOfWeek,
                'name'        => $validated['name'],
                'start_time'  => $validated['start_time'],
                'end_time'    => $validated['end_time'],
                'is_active'   => true,
            ]);
        }

        return redirect()->route('roster.index', ['branch_id' => $validated['vendor_id']])
            ->with('success', 'Break schedule saved.');
    }

    /**
     * Delete a break.
     */
    public function deleteBreak(int $id): RedirectResponse
    {
        $b = StaffBreak::findOrFail($id);
        $branchId = $b->vendor_id;
        $b->delete();

        return redirect()->route('roster.index', ['branch_id' => $branchId])->with('success', 'Break removed.');
    }

    /**
     * Manual control for Staff Holiday / Day Off.
     * Instantly records approved holiday and moves conflicting appointments to Needs Rescheduling.
     */
    public function saveStaffHoliday(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id'    => 'required|integer|exists:users,id',
            'vendor_id'  => 'required|integer|exists:users,id',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'reason'     => 'required|string|max:500',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $branchId = (int) $validated['vendor_id'];
        $startDate = $validated['start_date'];
        $endDate = $validated['end_date'];
        $reason = $validated['reason'];

        $startDt = Carbon::parse($startDate)->startOfDay();
        $endDt = Carbon::parse($endDate)->endOfDay();

        // 1. Create Approved Staff Leave record
        $leave = StaffLeave::create([
            'user_id'        => $user->id,
            'vendor_id'      => $branchId,
            'start_datetime' => $startDt,
            'end_datetime'   => $endDt,
            'leave_type'     => 'full_day',
            'reason'         => $reason,
            'status'         => 'approved',
            'approved_by'    => auth()->id(),
            'approved_at'    => now(),
        ]);

        // 2. Set StaffScheduleOverride for each day in range
        $curr = Carbon::parse($startDate);
        $endLimit = Carbon::parse($endDate);
        while ($curr->lte($endLimit)) {
            StaffScheduleOverride::updateOrCreate(
                [
                    'user_id'       => $user->id,
                    'override_date' => $curr->format('Y-m-d'),
                ],
                [
                    'vendor_id'  => $branchId,
                    'is_day_off' => true,
                    'notes'      => $reason,
                ]
            );
            $curr->addDay();
        }

        // 3. Conflict Detection: Find all active bookings for this doctor overlapping the period
        $conflictingAppts = Appointment::where('user_id', $user->id)
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->whereIn('status', ['scheduled', 'booked', 'confirmed', 'arrived'])
            ->get();

        $affectedCount = 0;
        foreach ($conflictingAppts as $appt) {
            $appt->needs_reschedule = 1;
            $appt->save();

            AppointmentHistory::create([
                'appointment_id' => $appt->id,
                'from_status'    => is_object($appt->status) ? $appt->status->value : (string) $appt->status,
                'to_status'      => is_object($appt->status) ? $appt->status->value : (string) $appt->status,
                'old_date'       => $appt->appointment_date,
                'new_date'       => $appt->appointment_date,
                'old_start_time' => $appt->start_time,
                'new_start_time' => $appt->start_time,
                'old_user_id'    => $appt->user_id,
                'new_user_id'    => $appt->user_id,
                'reason'         => "Specialist assigned holiday/off-day ({$reason}). Moved to Needs Rescheduling queue.",
                'changed_by'     => auth()->id(),
                'created_at'     => now(),
            ]);

            $affectedCount++;
        }

        $notice = "Holiday / Off-Day saved for {$user->name}.";
        if ($affectedCount > 0) {
            $notice .= " Alert: {$affectedCount} conflicting patient booking(s) moved to the Front-Desk 'Needs Rescheduling' queue for telephone rescheduling.";
        }

        return redirect()->route('roster.index', ['branch_id' => $branchId])->with('success', $notice);
    }

    /**
     * Delete/Cancel a Staff Leave or Holiday.
     */
    public function deleteLeave(int $id): RedirectResponse
    {
        $leave = StaffLeave::findOrFail($id);
        $branchId = $leave->vendor_id ?: Helper::currentBranchId();
        $userId = $leave->user_id;

        $startDate = Carbon::parse($leave->start_datetime)->format('Y-m-d');
        $endDate = Carbon::parse($leave->end_datetime)->format('Y-m-d');

        // Remove overrides in that window
        StaffScheduleOverride::where('user_id', $userId)
            ->whereBetween('override_date', [$startDate, $endDate])
            ->where('is_day_off', true)
            ->delete();

        $leave->delete();

        return redirect()->route('roster.index', ['branch_id' => $branchId])
            ->with('success', 'Staff leave / holiday removed successfully.');
    }

    /**
     * Request Leave for a Specialist.
     */
    public function requestLeave(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id'        => 'required|integer|exists:users,id',
            'vendor_id'      => 'nullable|integer|exists:users,id',
            'start_datetime' => 'required|date',
            'end_datetime'   => 'required|date|after_or_equal:start_datetime',
            'leave_type'     => 'required|string|in:full_day,half_day_morning,half_day_afternoon,custom_hours',
            'reason'         => 'required|string|max:500',
        ]);

        $leave = StaffLeave::create([
            'user_id'        => $validated['user_id'],
            'vendor_id'      => $validated['vendor_id'] ?: Helper::currentBranchId(),
            'start_datetime' => $validated['start_datetime'],
            'end_datetime'   => $validated['end_datetime'],
            'leave_type'     => $validated['leave_type'],
            'reason'         => $validated['reason'],
            'status'         => 'requested',
        ]);

        return redirect()->back()->with('success', 'Leave request submitted.');
    }

    /**
     * Approve Leave Request with Conflict Detection.
     * Automatically moves conflicting appointments to "Needs Rescheduling" queue.
     */
    public function approveLeave(int $id): RedirectResponse
    {
        $leave = StaffLeave::with('user')->findOrFail($id);
        $leave->status = 'approved';
        $leave->approved_by = auth()->id();
        $leave->approved_at = now();
        $leave->save();

        $startDate = Carbon::parse($leave->start_datetime)->format('Y-m-d');
        $endDate = Carbon::parse($leave->end_datetime)->format('Y-m-d');

        // Set StaffScheduleOverride for each day in range
        $curr = Carbon::parse($startDate);
        $endLimit = Carbon::parse($endDate);
        while ($curr->lte($endLimit)) {
            StaffScheduleOverride::updateOrCreate(
                [
                    'user_id'       => $leave->user_id,
                    'override_date' => $curr->format('Y-m-d'),
                ],
                [
                    'vendor_id'  => $leave->vendor_id ?: Helper::currentBranchId(),
                    'is_day_off' => true,
                    'notes'      => $leave->reason,
                ]
            );
            $curr->addDay();
        }

        // Conflict Detection: Find all active appointments for this doctor overlapping the leave window
        $conflictingAppts = Appointment::where('user_id', $leave->user_id)
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->whereIn('status', ['scheduled', 'booked', 'confirmed', 'arrived'])
            ->get();

        $affectedCount = 0;
        foreach ($conflictingAppts as $appt) {
            $apptStart = Carbon::parse($appt->appointment_date->format('Y-m-d') . ' ' . ($appt->start_time ?: '00:00:00'));
            $apptEnd = Carbon::parse($appt->appointment_date->format('Y-m-d') . ' ' . ($appt->end_time ?: '23:59:59'));

            if ($apptStart->lt($leave->end_datetime) && $apptEnd->gt($leave->start_datetime)) {
                $appt->needs_reschedule = 1;
                $appt->save();

                AppointmentHistory::create([
                    'appointment_id' => $appt->id,
                    'from_status'    => is_object($appt->status) ? $appt->status->value : (string) $appt->status,
                    'to_status'      => is_object($appt->status) ? $appt->status->value : (string) $appt->status,
                    'old_date'       => $appt->appointment_date,
                    'new_date'       => $appt->appointment_date,
                    'old_start_time' => $appt->start_time,
                    'new_start_time' => $appt->start_time,
                    'old_user_id'    => $appt->user_id,
                    'new_user_id'    => $appt->user_id,
                    'reason'         => "Specialist approved for leave ({$leave->reason}). Moved to Needs Rescheduling queue.",
                    'changed_by'     => auth()->id(),
                    'created_at'     => now(),
                ]);

                $affectedCount++;
            }
        }

        $notice = "Leave approved for {$leave->user->name}.";
        if ($affectedCount > 0) {
            $notice .= " Alert: {$affectedCount} conflicting patient booking(s) moved to the Front-Desk 'Needs Rescheduling' queue for telephone rescheduling.";
        }

        return redirect()->back()->with('success', $notice);
    }

    /**
     * Reject Leave Request.
     */
    public function rejectLeave(Request $request, int $id): RedirectResponse
    {
        $leave = StaffLeave::findOrFail($id);
        $leave->status = 'rejected';
        $leave->approved_by = auth()->id();
        $leave->approved_at = now();
        $leave->notes = $request->input('notes', 'Leave request declined by manager.');
        $leave->save();

        return redirect()->back()->with('warning', 'Leave request rejected.');
    }
}
