@extends('admin.layouts.master')
@section('page_title', 'Appointment Calendar')
@section('breadcrumb_parent', 'Appointments')
@section('breadcrumb_parent_url', url('admin/appointments'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <style>
        .cal-container {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .stats-strip {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding: 14px 18px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .stat-mini {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            border-radius: 8px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            min-width: 140px;
            flex: 1;
        }
        .stat-mini-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 14px;
        }
        .stat-mini-val {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.1;
        }
        .stat-mini-lbl {
            font-size: 11px;
            color: #64748b;
            font-weight: 500;
            text-transform: uppercase;
        }
        .cal-top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 18px;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            flex-wrap: wrap;
            gap: 12px;
        }
        .cal-nav-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #334155;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s;
        }
        .cal-nav-btn:hover {
            background: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
        }
        .cal-today-btn {
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid #2563eb;
            background: transparent;
            color: #2563eb;
            cursor: pointer;
            transition: all 0.15s;
        }
        .cal-today-btn:hover {
            background: #2563eb;
            color: #ffffff;
        }
        .cal-month-label {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            margin-left: 8px;
        }
        .cal-view-tabs {
            display: flex;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
        }
        .cal-view-tab {
            padding: 5px 14px;
            font-size: 12px;
            font-weight: 600;
            border: none;
            background: #ffffff;
            color: #475569;
            cursor: pointer;
            transition: all 0.15s;
        }
        .cal-view-tab.active {
            background: #2563eb;
            color: #ffffff;
        }
        .cal-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .cal-table th {
            background: #f8fafc;
            color: #334155;
            font-size: 12px;
            font-weight: 700;
            padding: 10px 8px;
            border: 1px solid #e2e8f0;
            text-align: center;
        }
        .cal-table th.resource-col-header {
            background: #f0fdf4;
            color: #166534;
            border-left: 2px solid #22c55e;
        }
        .cal-table td {
            border: 1px solid #e2e8f0;
            padding: 4px;
            height: 54px;
            vertical-align: top;
            font-size: 11px;
            position: relative;
        }
        .td-time {
            width: 85px;
            background: #f8fafc;
            text-align: right;
            padding: 8px 10px !important;
            font-weight: 600;
            color: #475569;
            font-size: 11px;
        }
        .appt-card {
            border-radius: 6px;
            padding: 6px 8px;
            margin-bottom: 2px;
            cursor: pointer;
            transition: all 0.15s;
            border-left: 3px solid #2563eb;
            font-size: 11px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .appt-card:hover {
            transform: scale(1.02);
            box-shadow: 0 3px 8px rgba(0,0,0,0.12);
        }
        .empty-slot-btn {
            width: 100%;
            height: 100%;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            border: 1px dashed transparent;
            border-radius: 4px;
            background: transparent;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.15s;
        }
        .empty-slot-btn:hover {
            border-color: #93c5fd;
            background: #eff6ff;
            color: #2563eb;
        }
    </style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-calendar-alt" style="color: var(--accent-500); margin-right: 8px;"></i>Appointment Calendar</h1>
            <p>Multi-column specialist and sound booth schedule & availability</p>
        </div>
        <div class="header-actions">
            <a href="{{ route('appointments.today') }}" class="btn btn-warning btn-sm" style="font-weight: 600;">
                <i class="fas fa-user-clock mr-1"></i>Today's Queue
            </a>
            @can('permission', 'appointment_list.index')
                <a href="{{ url('admin/appointments') }}" class="btn btn-secondary btn-sm">
                    <i class="fas fa-list mr-1"></i>List View
                </a>
            @endcan
            @can('permission', 'add_appointment.create')
                <a href="{{ route('appointments.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus mr-1"></i>Book Appointment
                </a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')

    <div class="cal-container crm-animate-in delay-1">
        {{-- Stats Strip --}}
        <div class="stats-strip">
            <div class="stat-mini">
                <div class="stat-mini-icon" style="background:#2563eb"><i class="fas fa-calendar-check"></i></div>
                <div>
                    <div class="stat-mini-val" id="stat-total">0</div>
                    <div class="stat-mini-lbl">All Time</div>
                </div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-icon" style="background:#f59e0b"><i class="fas fa-calendar-day"></i></div>
                <div>
                    <div class="stat-mini-val" id="stat-today">0</div>
                    <div class="stat-mini-lbl">Today</div>
                </div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-icon" style="background:#0284c7"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="stat-mini-val" id="stat-scheduled">0</div>
                    <div class="stat-mini-lbl">Scheduled</div>
                </div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-icon" style="background:#8b5cf6"><i class="fas fa-user-clock"></i></div>
                <div>
                    <div class="stat-mini-val" id="stat-arrived">0</div>
                    <div class="stat-mini-lbl">Waiting</div>
                </div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-icon" style="background:#16a34a"><i class="fas fa-check-double"></i></div>
                <div>
                    <div class="stat-mini-val" id="stat-completed">0</div>
                    <div class="stat-mini-lbl">Completed</div>
                </div>
            </div>
        </div>

        {{-- Top Bar with Navigation & Branch Filter --}}
        <div class="cal-top-bar">
            <div class="d-flex align-items-center gap-2">
                <button class="cal-nav-btn" onclick="navigateCalendar(-1)" aria-label="Previous"><i class="fas fa-chevron-left"></i></button>
                <button class="cal-nav-btn" onclick="navigateCalendar(1)" aria-label="Next"><i class="fas fa-chevron-right"></i></button>
                <button class="cal-today-btn" onclick="goToday()">Today</button>
                <span class="cal-month-label" id="cal-date-label">Loading...</span>
            </div>

            <div class="d-flex align-items-center gap-3">
                @if(!empty($canViewAllBranches))
                    <div class="d-flex align-items-center gap-1">
                        <label style="font-size: 11px; margin: 0; color: #64748b; font-weight: 600;">BRANCH:</label>
                        <select id="branchSelect" class="form-control form-control-sm" style="font-weight: 600; width: auto;" onchange="changeBranch(this.value)">
                            @foreach ($branches as $bId => $bName)
                                <option value="{{ $bId }}" {{ $currentBranchId == $bId ? 'selected' : '' }}>
                                    📍 {{ $bName }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="cal-view-tabs">
                    <button class="cal-view-tab active" id="tab-day" onclick="setViewMode('day')">Day (Staff & Booth)</button>
                    <button class="cal-view-tab" id="tab-week" onclick="setViewMode('week')">Week View</button>
                </div>
            </div>
        </div>

        {{-- Calendar Grid Area --}}
        <div style="overflow-x: auto; min-height: 500px;" id="calendar-grid">
            <div style="text-align: center; padding: 60px; color: #94a3b8;">
                <i class="fas fa-spinner fa-spin mr-2"></i>Loading appointment schedule...
            </div>
        </div>
    </div>
</div>

{{-- Appointment Action Drawer / Modal --}}
<div class="modal fade" id="appointmentDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background: #0f172a; color: #ffffff;">
                <h5 class="modal-title font-weight-bold" style="font-size: 15px;">
                    <i class="fas fa-calendar-check mr-2 text-primary"></i>Appointment Details
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <div>
                        <div style="font-size: 16px; font-weight: 700; color: #0f172a;" id="modalPatientName"></div>
                        <div style="font-size: 12px; color: #64748b;" id="modalPatientPhone"></div>
                    </div>
                    <span id="modalStatusBadge" class="badge" style="font-size: 11px; padding: 4px 8px;"></span>
                </div>

                <div class="row mb-3">
                    <div class="col-6">
                        <small class="text-muted d-block text-uppercase" style="font-size: 10px; font-weight: 700;">Date & Time</small>
                        <div style="font-size: 13px; font-weight: 600; color: #1e293b;" id="modalDateTime"></div>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block text-uppercase" style="font-size: 10px; font-weight: 700;">Specialist</small>
                        <div style="font-size: 13px; font-weight: 600; color: #1e293b;" id="modalDoctor"></div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-6">
                        <small class="text-muted d-block text-uppercase" style="font-size: 10px; font-weight: 700;">Clinical Service</small>
                        <div style="font-size: 13px; font-weight: 600; color: #1e293b;" id="modalService"></div>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block text-uppercase" style="font-size: 10px; font-weight: 700;">Resource / Room</small>
                        <div style="font-size: 13px; font-weight: 600; color: #1e293b;" id="modalResource"></div>
                    </div>
                </div>

                <div id="modalAdvanceSection" class="p-2 mb-3 rounded" style="background: #f0fdf4; border: 1px solid #bbf7d0; display: none;">
                    <div class="d-flex align-items-center justify-content-between">
                        <span style="font-size: 12px; color: #166534; font-weight: 600;">
                            <i class="fas fa-check-circle mr-1"></i>Advance Paid: <strong id="modalAdvanceAmount"></strong>
                        </span>
                        <a href="#" id="modalReceiptLink" target="_blank" class="btn btn-xs btn-outline-success">Print Receipt</a>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 pt-2 border-top">
                    <button type="button" class="btn btn-sm btn-warning font-weight-bold" id="btnActionArrived" onclick="triggerStatusUpdate('check-in')">
                        <i class="fas fa-user-check mr-1"></i>Mark Arrived
                    </button>
                    <button type="button" class="btn btn-sm btn-primary font-weight-bold" id="btnActionStart" onclick="triggerStatusUpdate('start-service')">
                        <i class="fas fa-stethoscope mr-1"></i>Start Service
                    </button>
                    <button type="button" class="btn btn-sm btn-success font-weight-bold" id="btnActionComplete" onclick="triggerStatusUpdate('complete-service')">
                        <i class="fas fa-check-double mr-1"></i>Mark Complete
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary ml-auto" id="btnActionReschedule" onclick="openRescheduleFromModal()">
                        <i class="fas fa-calendar-alt mr-1"></i>Reschedule
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let currentViewMode = 'day'; // 'day' or 'week'
    let selectedDate = new Date();
    let currentBranch = {{ $currentBranchId }};
    let appointmentsData = [];
    let branchSpecialists = [];
    let branchResources = [];
    let activeModalAppt = null;

    // Standard business hours for hearing clinic: 09:30 AM to 18:30 PM
    const calendarHours = [
        '09:30', '10:00', '10:30', '11:00', '11:30', '12:00',
        '12:30', '13:00', '13:30', '14:00', '14:30', '15:00',
        '15:30', '16:00', '16:30', '17:00', '17:30', '18:00', '18:30'
    ];

    document.addEventListener('DOMContentLoaded', function() {
        updateDateLabel();
        loadCalendarData();
    });

    function changeBranch(bId) {
        currentBranch = bId;
        loadCalendarData();
    }

    function setViewMode(mode) {
        currentViewMode = mode;
        document.querySelectorAll('.cal-view-tab').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + mode).classList.add('active');
        updateDateLabel();
        renderCalendar();
    }

    function navigateCalendar(dir) {
        if (currentViewMode === 'day') {
            selectedDate.setDate(selectedDate.getDate() + dir);
        } else {
            selectedDate.setDate(selectedDate.getDate() + (dir * 7));
        }
        updateDateLabel();
        loadCalendarData();
    }

    function goToday() {
        selectedDate = new Date();
        updateDateLabel();
        loadCalendarData();
    }

    function updateDateLabel() {
        const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

        if (currentViewMode === 'day') {
            document.getElementById('cal-date-label').textContent = 
                `${days[selectedDate.getDay()]}, ${selectedDate.getDate()} ${months[selectedDate.getMonth()]} ${selectedDate.getFullYear()}`;
        } else {
            const start = getMonday(new Date(selectedDate));
            const end = new Date(start);
            end.setDate(end.getDate() + 6);
            document.getElementById('cal-date-label').textContent = 
                `${start.getDate()} ${months[start.getMonth()].substr(0,3)} – ${end.getDate()} ${months[end.getMonth()].substr(0,3)} ${end.getFullYear()}`;
        }
    }

    function getMonday(d) {
        const day = d.getDay();
        const diff = d.getDate() - day + (day === 0 ? -6 : 1);
        const res = new Date(d);
        res.setDate(diff);
        return res;
    }

    function formatDateYmd(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${dd}`;
    }

    function loadCalendarData() {
        let startStr, endStr;
        if (currentViewMode === 'day') {
            startStr = formatDateYmd(selectedDate);
            endStr = startStr;
        } else {
            const mon = getMonday(new Date(selectedDate));
            const sun = new Date(mon);
            sun.setDate(sun.getDate() + 6);
            startStr = formatDateYmd(mon);
            endStr = formatDateYmd(sun);
        }

        const url = `/admin/appointments/calendar-data?start_date=${startStr}&end_date=${endStr}&branch_id=${currentBranch}`;

        fetch(url)
            .then(res => res.json())
            .then(data => {
                appointmentsData = data.appointments || [];
                branchSpecialists = data.specialists || [];
                branchResources = data.resources || [];
                updateStats(data.stats || {});
                renderCalendar();
            });
    }

    function updateStats(s) {
        document.getElementById('stat-total').textContent = s.total || 0;
        document.getElementById('stat-today').textContent = s.today || 0;
        document.getElementById('stat-scheduled').textContent = s.scheduled || 0;
        document.getElementById('stat-arrived').textContent = s.arrived || 0;
        document.getElementById('stat-completed').textContent = s.completed || 0;
    }

    function renderCalendar() {
        const container = document.getElementById('calendar-grid');

        if (currentViewMode === 'day') {
            renderMultiColumnDayView(container);
        } else {
            renderWeekView(container);
        }
    }

    /**
     * Wireframe 2: Day View with Staff Columns + Sound Booth Column!
     */
    function renderMultiColumnDayView(container) {
        const dateStr = formatDateYmd(selectedDate);

        let html = '<table class="cal-table"><thead><tr>';
        html += '<th class="td-time">TIME</th>';

        // Specialist columns
        branchSpecialists.forEach(spec => {
            html += `<th><i class="fas fa-user-md mr-1 text-primary"></i>${spec.name}</th>`;
        });

        // Sound Booth column
        html += `<th class="resource-col-header"><i class="fas fa-door-open mr-1 text-success"></i>SOUND BOOTH 1 (RESOURCE)</th>`;
        html += '</tr></thead><tbody>';

        calendarHours.forEach(time => {
            const timeLabel = formatTimeLabel(time);
            html += `<tr>`;
            html += `<td class="td-time">${timeLabel}</td>`;

            // Cells for each specialist
            branchSpecialists.forEach(spec => {
                const appt = appointmentsData.find(a => 
                    a.date === dateStr && 
                    a.professional_id === spec.id && 
                    isTimeInAppointment(time, a.start_time, a.end_time)
                );

                if (appt) {
                    html += `<td>`;
                    html += renderApptCard(appt);
                    html += `</td>`;
                } else {
                    html += `<td>`;
                    html += `<a href="/admin/appointments/create?user_id=${spec.id}&date=${dateStr}&branch_id=${currentBranch}" class="empty-slot-btn" title="Click to book">`;
                    html += `<i class="fas fa-plus mr-1" style="font-size:9px"></i>Book`;
                    html += `</a>`;
                    html += `</td>`;
                }
            });

            // Cell for Sound Booth
            const boothAppt = appointmentsData.find(a => 
                a.date === dateStr && 
                a.resource_name && a.resource_name.toLowerCase().includes('booth') && 
                isTimeInAppointment(time, a.start_time, a.end_time)
            );

            if (boothAppt) {
                html += `<td style="background: #f0fdf4;">`;
                html += renderApptCard(boothAppt, true);
                html += `</td>`;
            } else {
                html += `<td style="background: #fcfdfd;">`;
                html += `<div style="color: #10b981; font-size: 11px; text-align: center; padding-top: 10px; font-weight: 500;">`;
                html += `<i class="fas fa-check-circle mr-1"></i>Available`;
                html += `</div>`;
                html += `</td>`;
            }

            html += `</tr>`;
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    }

    function renderWeekView(container) {
        const mon = getMonday(new Date(selectedDate));
        const days = [];
        for (let i = 0; i < 7; i++) {
            const d = new Date(mon);
            d.setDate(d.getDate() + i);
            days.push(d);
        }

        const dayNames = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

        let html = '<table class="cal-table"><thead><tr>';
        html += '<th class="td-time">TIME</th>';
        days.forEach((d, idx) => {
            const isTodayDate = formatDateYmd(d) === formatDateYmd(new Date());
            html += `<th style="${isTodayDate ? 'background:#e0f2fe; color:#0369a1;' : ''}">`;
            html += `<div>${dayNames[idx]}</div><div style="font-size:14px; font-weight:800;">${d.getDate()}</div>`;
            html += `</th>`;
        });
        html += '</tr></thead><tbody>';

        calendarHours.forEach(time => {
            const timeLabel = formatTimeLabel(time);
            html += `<tr>`;
            html += `<td class="td-time">${timeLabel}</td>`;

            days.forEach(d => {
                const dateStr = formatDateYmd(d);
                const dayAppts = appointmentsData.filter(a => 
                    a.date === dateStr && 
                    isTimeInAppointment(time, a.start_time, a.end_time)
                );

                html += `<td>`;
                if (dayAppts.length > 0) {
                    dayAppts.forEach(a => {
                        html += renderApptCard(a);
                    });
                } else {
                    html += `<a href="/admin/appointments/create?date=${dateStr}&branch_id=${currentBranch}" class="empty-slot-btn">`;
                    html += `<i class="fas fa-plus" style="font-size:9px"></i>`;
                    html += `</a>`;
                }
                html += `</td>`;
            });

            html += `</tr>`;
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    }

    function isTimeInAppointment(slotTime, startTime, endTime) {
        if (!startTime) return false;
        return slotTime >= startTime && slotTime < (endTime || startTime);
    }

    function formatTimeLabel(time24) {
        const parts = time24.split(':');
        let h = parseInt(parts[0], 10);
        const m = parts[1];
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return `${h}:${m} ${ampm}`;
    }

    function renderApptCard(a, isBooth = false) {
        const bg = a.bg_color || '#eff6ff';
        const color = a.color || '#2563eb';
        const safeJson = JSON.stringify(a).replace(/"/g, '&quot;');

        let text = `<div class="appt-card" style="background:${bg}; border-left-color:${color};" onclick="openApptModal(${safeJson})">`;
        text += `<div style="font-weight:700; color:${color}; font-size:11px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">`;
        text += `${a.patient}`;
        text += `</div>`;
        text += `<div style="font-size:10px; color:#475569; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">`;
        text += isBooth ? `Booth • Dr. ${a.professional}` : a.service;
        text += `</div>`;
        text += `<div style="display:flex; align-items:center; justify-content:space-between; margin-top:2px;">`;
        text += `<span style="font-size:9px; color:#64748b;">${a.start_label}</span>`;
        text += `<span class="badge" style="background:${color}; color:#fff; font-size:9px; padding:1px 4px;">${a.status_label}</span>`;
        text += `</div>`;
        text += `</div>`;
        return text;
    }

    function openApptModal(appt) {
        activeModalAppt = appt;
        $('#modalPatientName').text(appt.patient);
        $('#modalPatientPhone').text(appt.phone || 'No phone provided');
        $('#modalDateTime').text(`${appt.date} • ${appt.start_label} - ${appt.end_label}`);
        $('#modalDoctor').text(appt.professional || 'Unassigned');
        $('#modalService').text(`${appt.service} (${appt.duration}m)`);
        $('#modalResource').text(appt.resource_name || 'Standard Consultation Room');

        $('#modalStatusBadge').text(appt.status_label)
            .css({'background': appt.bg_color, 'color': appt.color, 'border': `1px solid ${appt.color}`});

        if (appt.advance_amount > 0) {
            $('#modalAdvanceAmount').text(`৳${appt.advance_amount.toFixed(2)}`);
            $('#modalReceiptLink').attr('href', appt.receipt_url || '#');
            $('#modalAdvanceSection').show();
        } else {
            $('#modalAdvanceSection').hide();
        }

        // Show/hide action buttons according to status
        const st = appt.status;
        $('#btnActionArrived').toggle(st === 'scheduled' || st === 'booked' || st === 'confirmed');
        $('#btnActionStart').toggle(st === 'arrived');
        $('#btnActionComplete').toggle(st === 'in_progress');

        $('#appointmentDetailModal').modal('show');
    }

    function triggerStatusUpdate(action) {
        if (!activeModalAppt) return;
        const apptId = activeModalAppt.id;

        $.post(`/admin/appointments/${apptId}/${action}`, {
            _token: '{{ csrf_token() }}'
        }).done(function(res) {
            $('#appointmentDetailModal').modal('hide');
            loadCalendarData();
        }).fail(function() {
            alert('Failed to update status.');
        });
    }

    function openRescheduleFromModal() {
        if (!activeModalAppt) return;
        $('#appointmentDetailModal').modal('hide');
        window.location.href = `/admin/appointments/${activeModalAppt.id}/edit`;
    }
</script>
@endpush
