@extends('admin.layouts.master')
@section('page_title', 'Staff Roster & Duty Schedule')
@section('breadcrumb_parent', 'Appointments')
@section('breadcrumb_parent_url', url('admin/appointments'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <style>
        .roster-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            overflow: hidden;
        }
        .roster-header {
            padding: 16px 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .roster-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            padding: 16px;
        }
        @media (max-width: 1200px) {
            .roster-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        @media (max-width: 768px) {
            .roster-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        .day-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            min-height: 120px;
            display: flex;
            flex-direction: column;
        }
        .day-box.is-closed {
            background: #fef2f2;
            border-color: #fecaca;
        }
        .day-box.has-shifts {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }
        .day-name {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #475569;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .shift-item {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 6px 8px;
            margin-bottom: 6px;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }
        .shift-time {
            font-weight: 600;
            color: #0f172a;
        }
        .operating-chip {
            flex: 1;
            min-width: 110px;
            padding: 10px 12px;
            border-radius: 8px;
            text-align: center;
            transition: all 0.2s ease;
        }
        .operating-chip.is-open {
            border: 1px solid #bbf7d0;
            background: #f0fdf4;
        }
        .operating-chip.is-closed {
            border: 1px solid #fecaca;
            background: #fef2f2;
        }
    </style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-calendar-week" style="color: var(--accent-500); margin-right: 8px;"></i>Specialist Roster & Duty Hours</h1>
            <p>Manage weekly duty shifts, 7-day office schedules, consistent or day-specific lunch breaks, and staff holidays</p>
        </div>
        <div class="header-actions">
            @if(!empty($canViewAllBranches))
                <form method="GET" action="{{ route('roster.index') }}" style="display: inline-block;">
                    <select name="branch_id" class="form-control form-control-sm" onchange="this.form.submit()" style="display: inline-block; width: auto; font-weight: 600;">
                        @foreach ($branches as $bId => $bName)
                            <option value="{{ $bId }}" {{ $currentBranchId == $bId ? 'selected' : '' }}>
                                📍 {{ $bName }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif
            <button type="button" class="btn btn-outline-dark btn-sm" data-toggle="modal" data-target="#branchScheduleModal">
                <i class="fas fa-store mr-1"></i>Clinic Hours (7-Day Office)
            </button>
            <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#addHolidayModal">
                <i class="fas fa-calendar-times mr-1"></i>Staff Holiday / Off-Day
            </button>
            <button type="button" class="btn btn-secondary btn-sm" data-toggle="modal" data-target="#addBreakModal">
                <i class="fas fa-coffee mr-1"></i>Add Clinic Break
            </button>
            <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addShiftModal">
                <i class="fas fa-plus mr-1"></i>Add Shift
            </button>
        </div>
    </div>

    @include('admin.alert_message')

    @if (empty($branchHasBooth))
        <div class="alert alert-warning m-2">
            <i class="fas fa-door-closed mr-1"></i>This branch has no active sound booth, so hearing tests (Pure Tone Audiometry and similar) cannot be booked here.
            @can('permission', 'sound_booths.index')
                <a href="{{ url('admin/sound-booths') }}" style="font-weight: 600;">Add a sound booth</a>
            @endcan
        </div>
    @endif

    {{-- Section 0: Branch Operating Hours & 7-Day Office Status --}}
    <div class="roster-card crm-animate-in" style="margin-bottom: 20px;">
        <div class="roster-header" style="padding: 12px 20px;">
            <div class="d-flex align-items-center">
                <i class="fas fa-store-alt text-primary mr-2" style="font-size: 16px;"></i>
                <h6 class="mb-0 font-weight-bold" style="font-size: 14px; color: #1e293b;">
                    Branch Operating Schedule & Weekly Off
                </h6>
                @php
                    $openDaysCount = $branchSchedules->filter(fn($s) => !$s->is_closed)->count();
                @endphp
                <span class="badge {{ $openDaysCount === 7 ? 'badge-success' : 'badge-info' }} ml-2" style="font-size: 11px;">
                    {{ $openDaysCount === 7 ? '7-Day Office (Open Every Day)' : "{$openDaysCount} Days / Week" }}
                </span>
            </div>
            <button type="button" class="btn btn-xs btn-outline-primary" data-toggle="modal" data-target="#branchScheduleModal">
                <i class="fas fa-edit mr-1"></i>Configure Clinic Hours / Weekly Off
            </button>
        </div>
        <div style="padding: 14px 20px; background: #ffffff;">
            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                @php
                    $orderedDays = [
                        6 => 'Saturday',
                        7 => 'Sunday',
                        1 => 'Monday',
                        2 => 'Tuesday',
                        3 => 'Wednesday',
                        4 => 'Thursday',
                        5 => 'Friday',
                    ];
                @endphp
                @foreach($orderedDays as $dNum => $dName)
                    @php
                        $sched = $branchSchedules->get($dNum);
                        $isClosed = $sched ? $sched->is_closed : false;
                    @endphp
                    <div class="operating-chip {{ $isClosed ? 'is-closed' : 'is-open' }}">
                        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: {{ $isClosed ? '#991b1b' : '#166534' }};">
                            {{ $dName }}
                        </div>
                        @if($isClosed)
                            <div style="font-size: 11px; font-weight: 700; color: #dc2626; margin-top: 4px;">
                                <i class="fas fa-door-closed mr-1"></i>CLOSED
                            </div>
                        @else
                            <div style="font-size: 11px; font-weight: 600; color: #15803d; margin-top: 4px;">
                                <i class="far fa-clock mr-1"></i>
                                {{ date('g:i A', strtotime($sched->open_time ?? '09:30')) }} - {{ date('g:i A', strtotime($sched->close_time ?? '19:00')) }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Section 1: Specialist Weekly Duty Rosters --}}
    @foreach($professionals as $prof)
        <div class="roster-card crm-animate-in">
            <div class="roster-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="crm-avatar">{{ strtoupper(substr($prof->name, 0, 1)) }}</div>
                    <div>
                        <h5 class="mb-0 font-weight-bold" style="font-size: 15px; color: #0f172a;">{{ $prof->name }}</h5>
                        <small class="text-muted">{{ $prof->designation?->label() ?? 'Specialist' }} &bull; Rotation Rule: 1 Branch / Day</small>
                    </div>
                </div>
                <button class="btn btn-xs btn-outline-primary" onclick="openShiftModal({{ $prof->id }}, '{{ addslashes($prof->name) }}')">
                    <i class="fas fa-plus mr-1"></i>Add Shift for {{ explode(' ', $prof->name)[0] }}
                </button>
            </div>

            <div class="roster-grid">
                @foreach($orderedDays as $dayNum => $dayName)
                    @php
                        $dayShifts = $prof->staffSchedules->where('day_of_week', $dayNum);
                        $branchDay = $branchSchedules->get($dayNum);
                        $isBranchClosed = $branchDay ? $branchDay->is_closed : false;
                    @endphp
                    <div class="day-box {{ $isBranchClosed ? 'is-closed' : ($dayShifts->isNotEmpty() ? 'has-shifts' : '') }}">
                        <div class="day-name">
                            <span>{{ $dayName }}</span>
                            @if($isBranchClosed)
                                <span class="badge badge-danger text-white" style="font-size: 8px;">Clinic Closed</span>
                            @elseif($dayShifts->isEmpty())
                                <span class="badge badge-warning text-dark" style="font-size: 8px;">Roster Leave</span>
                            @endif
                        </div>

                        @forelse($dayShifts as $shift)
                            <div class="shift-item">
                                <div>
                                    <div class="shift-time">
                                        <i class="far fa-clock mr-1 text-primary"></i>
                                        {{ date('g:i A', strtotime($shift->start_time)) }} - {{ date('g:i A', strtotime($shift->end_time)) }}
                                    </div>
                                </div>
                                <form action="{{ route('roster.shift.delete', $shift->id) }}" method="POST" style="margin:0;" onsubmit="return confirm('Remove this duty shift?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-xs text-danger" style="padding: 0 4px;" title="Delete Shift">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                            </div>
                        @empty
                            <div style="font-size: 11px; color: #94a3b8; font-style: italic; margin-top: auto; padding-top: 10px;">
                                @if($isBranchClosed)
                                    Clinic Off Day
                                @else
                                    <i class="fas fa-bed mr-1 text-muted"></i>Rostered Off
                                @endif
                            </div>
                        @endforelse
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    {{-- Section 2: Clinic Daily Breaks (Weekly Consistent or Day-Wise Specific) --}}
    <div class="roster-card crm-animate-in">
        <div class="roster-header">
            <div>
                <h5 class="mb-0 font-weight-bold" style="font-size: 14px;">
                    <i class="fas fa-utensils text-warning mr-2"></i>Daily Clinic Breaks (Lunch & Prayer)
                </h5>
                <small class="text-muted">Can be consistent weekly across all days OR configured for specific days (e.g. Friday Jumu'ah & lunch)</small>
            </div>
            <button type="button" class="btn btn-xs btn-primary" data-toggle="modal" data-target="#addBreakModal">
                <i class="fas fa-plus mr-1"></i>Add Break
            </button>
        </div>
        <div class="p-3">
            <div class="table-responsive">
                <table class="crm-table">
                    <thead>
                        <tr>
                            <th>Break Name</th>
                            <th>Target Specialist</th>
                            <th>Day / Schedule</th>
                            <th>Time Window</th>
                            <th>Status</th>
                            <th style="width: 80px; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($breaks as $b)
                            <tr>
                                <td style="font-weight: 600;">
                                    <i class="fas fa-coffee text-muted mr-1"></i>{{ $b->name }}
                                </td>
                                <td>
                                    @if($b->user)
                                        <span class="badge badge-light" style="font-size: 11px;">
                                            <i class="fas fa-user-md mr-1 text-primary"></i>{{ $b->user->name }}
                                        </span>
                                    @else
                                        <span class="text-muted" style="font-size: 12px; font-weight: 500;">
                                            All Branch Specialists
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($b->day_of_week === null)
                                        <span class="badge badge-success" style="font-size: 11px;">
                                            <i class="fas fa-check-double mr-1"></i>All Days (Consistent Weekly)
                                        </span>
                                    @else
                                        <span class="badge badge-info" style="font-size: 11px;">
                                            <i class="fas fa-calendar-day mr-1"></i>{{ $b->dayName() }} Only
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <span style="font-size: 12px; font-weight: 600; color: #2563eb;">
                                        <i class="far fa-clock mr-1"></i>
                                        {{ date('g:i A', strtotime($b->start_time)) }} – {{ date('g:i A', strtotime($b->end_time)) }}
                                    </span>
                                </td>
                                <td><span class="badge badge-success">Active</span></td>
                                <td style="text-align: center;">
                                    <form action="{{ route('roster.break.delete', $b->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Remove this break schedule?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-outline-danger" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-3 text-muted">No breaks configured for this branch.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Section 3: Staff Holidays & Days Off (Staff-Wise Control) --}}
    <div class="roster-card crm-animate-in">
        <div class="roster-header">
            <div>
                <h5 class="mb-0 font-weight-bold" style="font-size: 14px;">
                    <i class="fas fa-calendar-times text-danger mr-2"></i>Staff Holidays & Scheduled Days Off (Staff-Wise Control)
                </h5>
                <small class="text-muted">Directly assign holiday or off-days to specialists. Automatically flags conflicting bookings for rescheduling.</small>
            </div>
            <button type="button" class="btn btn-xs btn-danger" data-toggle="modal" data-target="#addHolidayModal">
                <i class="fas fa-plus mr-1"></i>Add Staff Holiday / Off-Day
            </button>
        </div>
        <div class="p-3">
            <div class="table-responsive">
                <table class="crm-table">
                    <thead>
                        <tr>
                            <th>Specialist</th>
                            <th>Holiday Period</th>
                            <th>Duration</th>
                            <th>Reason / Notes</th>
                            <th>Status</th>
                            <th style="width: 100px; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($staffHolidays as $h)
                            @php
                                $start = \Carbon\Carbon::parse($h->start_datetime);
                                $end = \Carbon\Carbon::parse($h->end_datetime);
                                $daysDiff = $start->diffInDays($end) + 1;
                            @endphp
                            <tr>
                                <td style="font-weight: 600;">
                                    <i class="fas fa-user-md text-primary mr-1"></i>{{ $h->user?->name ?? 'Unknown' }}
                                </td>
                                <td>
                                    <span style="font-weight: 600; color: #0f172a;">
                                        {{ $start->format('d M Y') }}
                                        @if($start->format('Y-m-d') !== $end->format('Y-m-d'))
                                            &nbsp;to&nbsp; {{ $end->format('d M Y') }}
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-light" style="font-size: 11px;">
                                        {{ $daysDiff }} {{ \Illuminate\Support\Str::plural('day', $daysDiff) }}
                                    </span>
                                </td>
                                <td>{{ $h->reason ?: 'Scheduled Off-Day' }}</td>
                                <td>
                                    <span class="badge badge-danger">Holiday / Off</span>
                                </td>
                                <td style="text-align: center;">
                                    <form action="{{ route('roster.leave.delete', $h->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Remove this holiday/day-off for {{ addslashes($h->user?->name) }}? Normal duty availability will be restored.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-outline-danger" title="Remove Holiday">
                                            <i class="fas fa-trash-alt mr-1"></i>Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-3 text-muted">No upcoming staff holidays recorded. Click "+ Add Staff Holiday / Off-Day" to record one.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Section 4: Staff Leave Requests & Approvals Queue --}}
    <div class="roster-card crm-animate-in">
        <div class="roster-header">
            <div>
                <h5 class="mb-0 font-weight-bold" style="font-size: 14px;">
                    <i class="fas fa-plane-departure text-primary mr-2"></i>Staff Leave Requests & Conflict Detector
                </h5>
                <small class="text-muted">Formal leave requests submitted by staff. Approving leave detects conflicting bookings and moves them to Needs Rescheduling.</small>
            </div>
            <button type="button" class="btn btn-xs btn-warning" data-toggle="modal" data-target="#requestLeaveModal">
                <i class="fas fa-plus mr-1"></i>Request Leave
            </button>
        </div>
        <div class="p-3">
            <div class="table-responsive">
                <table class="crm-table">
                    <thead>
                        <tr>
                            <th>Specialist</th>
                            <th>Period</th>
                            <th>Leave Type</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th style="width: 140px; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($leaves as $l)
                            <tr>
                                <td style="font-weight: 600;">{{ $l->user?->name }}</td>
                                <td>
                                    {{ $l->start_datetime->format('d M Y, g:i A') }}
                                    <br><span class="text-muted" style="font-size: 11px;">to {{ $l->end_datetime->format('d M Y, g:i A') }}</span>
                                </td>
                                <td><span class="badge badge-light">{{ ucfirst(str_replace('_', ' ', $l->leave_type)) }}</span></td>
                                <td>{{ $l->reason ?: '—' }}</td>
                                <td>
                                    @if($l->status === 'approved')
                                        <span class="badge badge-success">Approved</span>
                                    @elseif($l->status === 'rejected')
                                        <span class="badge badge-danger">Rejected</span>
                                    @else
                                        <span class="badge badge-warning text-dark">Requested</span>
                                    @endif
                                </td>
                                <td style="text-align: center;">
                                    @if($l->status === 'requested')
                                        <form action="{{ route('roster.leave.approve', $l->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Approve leave? Any conflicting bookings will be moved to the Needs Rescheduling queue.');">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-success" title="Approve">
                                                <i class="fas fa-check mr-1"></i>Approve
                                            </button>
                                        </form>
                                        <form action="{{ route('roster.leave.reject', $l->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-danger" title="Reject">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted" style="font-size: 11px;">Processed</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-3 text-muted">No pending leave requests.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($leaves->hasPages())
                <div class="mt-3">{{ $leaves->links() }}</div>
            @endif
        </div>
    </div>

</div>

{{-- Modal 1: Configure Branch Operating Schedule & Weekly Off (7-Day Office) --}}
<div class="modal fade" id="branchScheduleModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; border:none;">
            <div class="modal-header" style="background:#0f172a; color:#fff;">
                <div>
                    <h5 class="modal-title font-weight-bold" style="font-size: 15px;">
                        <i class="fas fa-store mr-2"></i>Configure Branch Operating Hours & Weekly Off
                    </h5>
                    <small style="color: #94a3b8;">Set opening/closing hours per day. To run a 7-day office, uncheck "Closed" for Friday.</small>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form action="{{ route('roster.branch-schedule.save') }}" method="POST">
                @csrf
                <input type="hidden" name="vendor_id" value="{{ $currentBranchId }}">
                <div class="modal-body" style="padding:20px;">
                    <div class="alert alert-info py-2" style="font-size: 12px; border-radius: 8px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>7-Day Office Tip:</strong> Uncheck <em>Closed</em> on Friday to keep the clinic open every day. Specialists will then take rotational roster off-days.
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 140px;">Day of Week</th>
                                    <th style="width: 120px; text-align: center;">Closed (Weekly Off)</th>
                                    <th>Open Time</th>
                                    <th>Close Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orderedDays as $dNum => $dName)
                                    @php
                                        $sched = $branchSchedules->get($dNum);
                                        $isClosed = $sched ? (bool) $sched->is_closed : ($dNum === 5);
                                        $openT = $sched ? substr($sched->open_time, 0, 5) : '09:30';
                                        $closeT = $sched ? substr($sched->close_time, 0, 5) : '19:00';
                                    @endphp
                                    <tr>
                                        <td class="align-middle font-weight-bold" style="font-size: 13px;">
                                            {{ $dName }}
                                        </td>
                                        <td class="align-middle text-center">
                                            <input type="checkbox" name="days[{{ $dNum }}][is_closed]" value="1" id="closed_check_{{ $dNum }}" {{ $isClosed ? 'checked' : '' }} style="transform: scale(1.3); cursor: pointer;">
                                        </td>
                                        <td class="align-middle">
                                            <input type="time" name="days[{{ $dNum }}][open_time]" class="form-control form-control-sm" value="{{ $openT }}">
                                        </td>
                                        <td class="align-middle">
                                            <input type="time" name="days[{{ $dNum }}][close_time]" class="form-control form-control-sm" value="{{ $closeT }}">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8fafc;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold">
                        <i class="fas fa-save mr-1"></i>Save Operating Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal 2: Add Staff Holiday / Day Off (Manual Control) --}}
<div class="modal fade" id="addHolidayModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border:none;">
            <div class="modal-header" style="background:#991b1b; color:#fff;">
                <h5 class="modal-title font-weight-bold" style="font-size: 15px;">
                    <i class="fas fa-calendar-times mr-2"></i>Assign Staff Holiday / Off-Day
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form action="{{ route('roster.holiday.save') }}" method="POST">
                @csrf
                <input type="hidden" name="vendor_id" value="{{ $currentBranchId }}">
                <div class="modal-body" style="padding:20px;">
                    <div class="alert alert-warning py-2 mb-3" style="font-size: 12px; border-radius: 8px;">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <strong>Automatic Conflict Detection:</strong> Any active patient bookings for this specialist during the selected dates will be moved to the <em>Needs Rescheduling</em> queue.
                    </div>

                    <div class="form-group">
                        <label style="font-size:12px; font-weight:600;">Specialist / Employee</label>
                        <select name="user_id" class="form-control" required>
                            @foreach($professionals as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="col-6">
                            <label style="font-size:12px; font-weight:600;">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="col-6">
                            <label style="font-size:12px; font-weight:600;">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="{{ now()->toDateString() }}" required>
                        </div>
                    </div>

                    <div class="form-group mt-3">
                        <label style="font-size:12px; font-weight:600;">Reason / Notes</label>
                        <input type="text" name="reason" class="form-control" placeholder="e.g. Roster Off-Day, Emergency Leave, Annual Vacation" required>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8fafc;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm font-weight-bold">
                        <i class="fas fa-calendar-check mr-1"></i>Save Staff Holiday
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal 3: Add/Configure Daily Clinic Break --}}
<div class="modal fade" id="addBreakModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border:none;">
            <div class="modal-header" style="background:#0f172a; color:#fff;">
                <h5 class="modal-title font-weight-bold" style="font-size: 15px;">
                    <i class="fas fa-coffee mr-2"></i>Configure Clinic Daily Break
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form action="{{ route('roster.break.save') }}" method="POST">
                @csrf
                <input type="hidden" name="vendor_id" value="{{ $currentBranchId }}">
                <div class="modal-body" style="padding:20px;">
                    <div class="form-group">
                        <label style="font-size:12px; font-weight:600;">Break Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Lunch Break, Friday Jumu'ah & Lunch" required>
                    </div>

                    <div class="form-group">
                        <label style="font-size:12px; font-weight:600;">Day Application</label>
                        <select name="day_of_week" class="form-control">
                            <option value="">All Days (Consistent Weekly)</option>
                            <option value="6">Saturday Only</option>
                            <option value="7">Sunday Only</option>
                            <option value="1">Monday Only</option>
                            <option value="2">Tuesday Only</option>
                            <option value="3">Wednesday Only</option>
                            <option value="4">Thursday Only</option>
                            <option value="5">Friday Only (e.g. Jumu'ah Prayer & Lunch)</option>
                        </select>
                        <small class="text-muted">Choose "All Days" if consistent weekly, or choose a specific day like Friday.</small>
                    </div>

                    <div class="form-group">
                        <label style="font-size:12px; font-weight:600;">Applies To Specialist</label>
                        <select name="user_id" class="form-control">
                            <option value="">All Specialists at this Branch</option>
                            @foreach($professionals as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="col-6">
                            <label style="font-size:12px; font-weight:600;">Start Time</label>
                            <input type="time" name="start_time" class="form-control" value="13:30" required>
                        </div>
                        <div class="col-6">
                            <label style="font-size:12px; font-weight:600;">End Time</label>
                            <input type="time" name="end_time" class="form-control" value="14:30" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8fafc;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold">Save Break</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal 4: Add Weekly Duty Shift --}}
<div class="modal fade" id="addShiftModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border:none;">
            <div class="modal-header" style="background:#0f172a; color:#fff;">
                <h5 class="modal-title font-weight-bold" style="font-size: 15px;">Add Weekly Duty Shift</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form action="{{ route('roster.shift.save') }}" method="POST">
                @csrf
                <input type="hidden" name="vendor_id" value="{{ $currentBranchId }}">
                <div class="modal-body" style="padding:20px;">
                    <div class="form-group">
                        <label style="font-size:12px; font-weight:600;">Specialist</label>
                        <select name="user_id" id="shiftDoctorSelect" class="form-control" required>
                            @foreach($professionals as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="font-size:12px; font-weight:600;">Day of Week</label>
                        <select name="day_of_week" class="form-control" required>
                            <option value="6">Saturday</option>
                            <option value="7">Sunday</option>
                            <option value="1">Monday</option>
                            <option value="2">Tuesday</option>
                            <option value="3">Wednesday</option>
                            <option value="4">Thursday</option>
                            <option value="5">Friday</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="col-6">
                            <label style="font-size:12px; font-weight:600;">Start Time</label>
                            <input type="time" name="start_time" class="form-control" value="09:30" required>
                        </div>
                        <div class="col-6">
                            <label style="font-size:12px; font-weight:600;">End Time</label>
                            <input type="time" name="end_time" class="form-control" value="18:30" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8fafc;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold">Save Shift</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal 5: Request Leave Modal --}}
<div class="modal fade" id="requestLeaveModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border:none;">
            <div class="modal-header" style="background:#1e293b; color:#fff;">
                <h5 class="modal-title font-weight-bold" style="font-size: 15px;">Submit Staff Leave Request</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form action="{{ route('roster.leave.request') }}" method="POST">
                @csrf
                <input type="hidden" name="vendor_id" value="{{ $currentBranchId }}">
                <div class="modal-body" style="padding:20px;">
                    <div class="form-group">
                        <label style="font-size:12px; font-weight:600;">Specialist</label>
                        <select name="user_id" class="form-control" required>
                            @foreach($professionals as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="font-size:12px; font-weight:600;">Leave Type</label>
                        <select name="leave_type" class="form-control" required>
                            <option value="full_day">Full Day</option>
                            <option value="half_day_morning">Half Day (Morning)</option>
                            <option value="half_day_afternoon">Half Day (Afternoon)</option>
                            <option value="custom_hours">Custom Hours</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="col-6">
                            <label style="font-size:12px; font-weight:600;">Start Date & Time</label>
                            <input type="datetime-local" name="start_datetime" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label style="font-size:12px; font-weight:600;">End Date & Time</label>
                            <input type="datetime-local" name="end_datetime" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group mt-3">
                        <label style="font-size:12px; font-weight:600;">Reason</label>
                        <textarea name="reason" class="form-control" rows="2" placeholder="e.g. Medical emergency, annual vacation" required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8fafc;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm font-weight-bold">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function openShiftModal(docId, docName) {
        $('#shiftDoctorSelect').val(docId);
        $('#addShiftModal').modal('show');
    }
</script>
@endpush
