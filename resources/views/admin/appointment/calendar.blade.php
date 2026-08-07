@extends('admin.layouts.master')

@section('content')
<section class="content pt-3">
    <div class="container-fluid">
        <!-- Calendar Card -->
        <div class="row">
            <div class="col-md-12">
                <div class="card card-outline card-primary">
                    <div class="stats-strip" id="stats-strip">
                        <div class="stat-mini">
                            <div class="stat-mini-icon" style="background:#007bff"><i class="fas fa-calendar-check"></i></div>
                            <div class="stat-mini-body">
                                <div class="stat-mini-val" id="stat-total">0</div>
                                <div class="stat-mini-lbl">Total</div>
                            </div>
                        </div>
                        <div class="stat-mini">
                            <div class="stat-mini-icon" style="background:#fd7e14"><i class="fas fa-calendar-day"></i></div>
                            <div class="stat-mini-body">
                                <div class="stat-mini-val" id="stat-today">0</div>
                                <div class="stat-mini-lbl">Today</div>
                            </div>
                        </div>
                        <div class="stat-mini">
                            <div class="stat-mini-icon" style="background:#007bff"><i class="fas fa-clock"></i></div>
                            <div class="stat-mini-body">
                                <div class="stat-mini-val" id="stat-scheduled">0</div>
                                <div class="stat-mini-lbl">Scheduled</div>
                            </div>
                        </div>
                        <div class="stat-mini">
                            <div class="stat-mini-icon" style="background:#dc3545"><i class="fas fa-times-circle"></i></div>
                            <div class="stat-mini-body">
                                <div class="stat-mini-val" id="stat-cancelled">0</div>
                                <div class="stat-mini-lbl">Cancelled</div>
                            </div>
                        </div>
                        <div class="stat-mini">
                            <div class="stat-mini-icon" style="background:#28a745"><i class="fas fa-check-circle"></i></div>
                            <div class="stat-mini-body">
                                <div class="stat-mini-val" id="stat-completed">0</div>
                                <div class="stat-mini-lbl">Completed</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-header bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h5 class="mb-0">Appointment Calendar</h5>
                                <small class="text-muted">Weekly & daily view of all scheduled appointments</small>
                            </div>
                            @can('permission', 'appointment_list.index')
                            <a href="{{ url('admin/appointments') }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-list-ul mr-1"></i> Appointment List
                            </a>
                            @endcan
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <div class="cal-wrap">

                            <!-- Top Bar -->
                            <div class="cal-top-bar">
                                <div class="d-flex align-items-center gap-2">
                                    <button class="cal-nav-btn" onclick="shiftWeek(-1)" aria-label="Previous">
                                        <i class="fas fa-chevron-left"></i>
                                    </button>
                                    <button class="cal-nav-btn" onclick="shiftWeek(1)" aria-label="Next">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                    <button class="cal-today-btn" onclick="goToday()">Today</button>
                                    <span class="cal-month-label" id="cal-month-label"></span>
                                </div>
                                <div class="cal-view-tabs">
                                    <button class="cal-view-tab active" id="tab-week" onclick="setView('week')">Week</button>
                                    <button class="cal-view-tab" id="tab-day" onclick="setView('day')">Day</button>
                                </div>
                            </div>

                            <!-- Professional Filter -->
                            <div class="cal-filter-bar">
                                <div class="prof-filter-row" id="prof-filter-row">
                                    <div class="pfc active" onclick="filterProf(this, 'all')">
                                        <i class="fas fa-users mr-1" style="font-size:11px"></i> All Professionals
                                    </div>
                                    @foreach ($professionals as $prof)
                                    @php
                                        $colors = ['#007bff','#28a745','#e67e22','#9b59b6','#e74c3c','#1abc9c','#f39c12','#34495e'];
                                        $color = $colors[$loop->index % count($colors)];
                                    @endphp
                                    <div class="pfc" data-prof="{{ $prof->id }}" onclick="filterProf(this, {{ $prof->id }})">
                                        <span class="pfc-dot" style="background:{{ $color }}"></span> {{ $prof->name }}
                                    </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Calendar Grid -->
                            <div class="cal-grid-wrap" id="calendar-view"></div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
    /* ==================== STATE ==================== */
    let currentView = 'week';
    let currentWeekStart = getMonday(new Date());
    let selectedProf = 'all';
    let appointmentsCache = [];

    const dayNames = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];

    /* Time slots from database — used to build calendar rows */
    const timeSlots = [
        @foreach ($timeSlots as $slot)
        { start: '{{ $slot->start_time->format('H:i') }}', end: '{{ $slot->end_time->format('H:i') }}', startLabel: '{{ $slot->start_time->format('g:i A') }}', endLabel: '{{ $slot->end_time->format('g:i A') }}' },
        @endforeach
    ];

    /* ==================== INIT ==================== */
    document.addEventListener('DOMContentLoaded', function() {
        updateLabel();
        renderCalendar();
        loadAppointments();
    });

    /* ==================== DATE HELPERS ==================== */
    function getMonday(d) {
        d = new Date(d);
        const day = d.getDay();
        const diff = d.getDate() - day + (day === 0 ? -6 : 1);
        d.setDate(diff);
        d.setHours(0,0,0,0);
        return d;
    }

    function formatDate(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth()+1).padStart(2,'0');
        const dd = String(d.getDate()).padStart(2,'0');
        return `${y}-${m}-${dd}`;
    }

    function addDays(d, n) {
        const r = new Date(d);
        r.setDate(r.getDate() + n);
        return r;
    }

    function isToday(d) {
        const t = new Date();
        return d.getFullYear()===t.getFullYear() && d.getMonth()===t.getMonth() && d.getDate()===t.getDate();
    }

    /* ==================== LABEL ==================== */
    function updateLabel() {
        let html = '';
        if (currentView === 'week') {
            const end = addDays(currentWeekStart, 6);
            if (currentWeekStart.getMonth() === end.getMonth()) {
                html = `${monthNames[currentWeekStart.getMonth()]} ${currentWeekStart.getDate()} – ${end.getDate()}, ${currentWeekStart.getFullYear()}`;
            } else if (currentWeekStart.getFullYear() === end.getFullYear()) {
                html = `${monthNames[currentWeekStart.getMonth()].substr(0,3)} ${currentWeekStart.getDate()} – ${monthNames[end.getMonth()].substr(0,3)} ${end.getDate()}, ${currentWeekStart.getFullYear()}`;
            } else {
                html = `${monthNames[currentWeekStart.getMonth()].substr(0,3)} ${currentWeekStart.getDate()}, ${currentWeekStart.getFullYear()} – ${monthNames[end.getMonth()].substr(0,3)} ${end.getDate()}, ${end.getFullYear()}`;
            }
        } else {
            html = `${dayNames[currentWeekStart.getDay() === 0 ? 6 : currentWeekStart.getDay()-1]}, ${monthNames[currentWeekStart.getMonth()]} ${currentWeekStart.getDate()}, ${currentWeekStart.getFullYear()}`;
        }
        document.getElementById('cal-month-label').textContent = html;
    }

    /* ==================== NAVIGATION ==================== */
    function shiftWeek(dir) {
        if (currentView === 'week') {
            currentWeekStart = addDays(currentWeekStart, dir * 7);
        } else {
            currentWeekStart = addDays(currentWeekStart, dir);
        }
        updateLabel();
        renderCalendar();
        loadAppointments();
    }

    function goToday() {
        if (currentView === 'week') {
            currentWeekStart = getMonday(new Date());
        } else {
            currentWeekStart = new Date();
            currentWeekStart.setHours(0,0,0,0);
        }
        updateLabel();
        renderCalendar();
        loadAppointments();
    }

    /* ==================== VIEW TOGGLE ==================== */
    function setView(view) {
        currentView = view;
        document.querySelectorAll('.cal-view-tab').forEach(t => t.classList.remove('active'));
        document.getElementById('tab-' + view).classList.add('active');

        if (view === 'week') {
            currentWeekStart = getMonday(currentWeekStart);
        }
        updateLabel();
        renderCalendar();
        loadAppointments();
    }

    /* ==================== FILTER ==================== */
    function filterProf(el, profId) {
        selectedProf = profId;
        document.querySelectorAll('.pfc').forEach(c => c.classList.remove('active'));
        el.classList.add('active');
        loadAppointments();
    }

    /* ==================== DATA LOADING ==================== */
    function loadAppointments() {
        let start, end;
        if (currentView === 'week') {
            start = formatDate(currentWeekStart);
            end = formatDate(addDays(currentWeekStart, 6));
        } else {
            start = formatDate(currentWeekStart);
            end = start;
        }

        let url = `{{ url('admin/appointments/calendar-data') }}?start_date=${start}&end_date=${end}`;
        if (selectedProf !== 'all') url += `&user_id=${selectedProf}`;

        fetch(url)
            .then(r => r.json())
            .then(response => {
                appointmentsCache = response.appointments || [];
                updateStats(response.stats || {});
                renderCalendar();
            });
    }

    /* ==================== STATS ==================== */
    function updateStats(stats) {
        document.getElementById('stat-total').textContent = stats.total || 0;
        document.getElementById('stat-today').textContent = stats.today || 0;
        document.getElementById('stat-scheduled').textContent = stats.scheduled || 0;
        document.getElementById('stat-cancelled').textContent = stats.cancelled || 0;
        document.getElementById('stat-completed').textContent = stats.completed || 0;
    }

    /* ==================== RENDER ==================== */
    function renderCalendar() {
        const container = document.getElementById('calendar-view');

        if (currentView === 'week') {
            renderWeek(container);
        } else {
            renderDay(container);
        }
    }

    function renderWeek(container) {
        const days = [];
        for (let i = 0; i < 7; i++) {
            days.push(addDays(currentWeekStart, i));
        }

        let html = '<table class="cal-table"><thead><tr>';
        html += '<th class="th-time"></th>';
        days.forEach((d, idx) => {
            const today = isToday(d) ? ' today' : '';
            html += `<th class="th-day${today}"><div class="th-day-name">${dayNames[idx]}</div><div class="th-day-num">${d.getDate()}</div></th>`;
        });
        html += '</tr></thead><tbody>';

        timeSlots.forEach(slot => {
            html += `<tr class="time-row">`;
            html += `<td class="td-time">${slot.startLabel}<br><span style="color:#c4c4c4;font-size:10px">${slot.endLabel}</span></td>`;
            for (let i = 0; i < 7; i++) {
                const d = days[i];
                const todayClass = isToday(d) ? ' today-col' : '';
                html += `<td class="time-cell${todayClass}" data-date="${formatDate(d)}" data-slot="${slot.start}">`;
                html += getApptBlock(formatDate(d), slot.start);
                html += '</td>';
            }
            html += '</tr>';
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    }

    function renderDay(container) {
        const d = currentWeekStart;
        const today = isToday(d);
        const dayIdx = d.getDay() === 0 ? 6 : d.getDay() - 1;

        let html = '<table class="cal-table day-view"><thead><tr>';
        html += '<th class="th-time"></th>';
        html += `<th class="th-day${today ? ' today' : ''}" style="min-width:160px"><div class="th-day-name">${dayNames[dayIdx]}</div><div class="th-day-num">${d.getDate()}</div></th>`;
        html += '</tr></thead><tbody>';

        const dateStr = formatDate(d);
        timeSlots.forEach(slot => {
            html += `<tr class="time-row">`;
            html += `<td class="td-time">${slot.startLabel}<br><span style="color:#c4c4c4;font-size:10px">${slot.endLabel}</span></td>`;
            const todayClass = today ? ' today-col' : '';
            html += `<td class="time-cell${todayClass}" data-date="${dateStr}" data-slot="${slot.start}">`;
            html += getApptBlock(dateStr, slot.start);
            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    }

    function getApptBlock(date, slotStart) {
        const match = appointmentsCache.find(a => {
            return a.date === date && a.start_time === slotStart;
        });
        if (!match) return '';

        return `<div class="appt-block" style="background:${match.bg_color};border-left-color:${match.color}" title="Patient: ${match.patient}\nProfessional: ${match.professional}\nDate: ${match.date}\nTime: ${match.start_label} – ${match.end_label}\nService: ${match.service}\nStatus: ${match.status_label}">
            <div class="appt-block-name" style="color:${match.color}">${match.patient}</div>
            <div class="appt-block-service" style="color:${match.color}">${match.service}</div>
            <div class="appt-block-status" style="background:${match.color};color:#fff">${match.status_label}</div>
        </div>`;
    }
</script>
@endpush
