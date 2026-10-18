@extends('admin.layouts.master')
@section('page_title', "Today's Appointment Queue")
@section('breadcrumb_parent', 'Appointments')
@section('breadcrumb_parent_url', url('admin/appointments'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <style>
        .today-pipeline-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-top: 20px;
        }
        @media (max-width: 1200px) {
            .today-pipeline-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 768px) {
            .today-pipeline-grid {
                grid-template-columns: 1fr;
            }
        }
        .pipeline-column {
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            min-height: 520px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .pipeline-header {
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }
        .pipeline-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .pipeline-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 22px;
            padding: 0 6px;
            border-radius: 11px;
            font-size: 11px;
            font-weight: 700;
        }
        .pipeline-cards {
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex-grow: 1;
            overflow-y: auto;
            max-height: 700px;
        }
        .patient-card {
            background: #ffffff;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 14px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            transition: all 0.2s ease;
            position: relative;
        }
        .patient-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-color: #cbd5e1;
        }
        .patient-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .patient-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #e0e7ff;
            color: #4338ca;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            flex-shrink: 0;
        }
        .patient-info {
            flex-grow: 1;
            margin-left: 10px;
        }
        .patient-name {
            font-weight: 600;
            font-size: 13px;
            color: #0f172a;
            line-height: 1.3;
        }
        .patient-phone {
            font-size: 11px;
            color: #64748b;
        }
        .card-time-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #f1f5f9;
            color: #334155;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .card-service-meta {
            font-size: 12px;
            color: #475569;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .card-doctor-meta {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .card-actions {
            border-top: 1px dashed #e2e8f0;
            padding-top: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
        }
        .pulse-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: pulse-green 1.5s infinite;
        }
        @keyframes pulse-green {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        .alert-tray {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 10px;
            padding: 14px 18px;
            margin-top: 16px;
        }
    </style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1>
                <i class="fas fa-user-clock" style="color: var(--accent-500); margin-right: 8px;"></i>
                Today's Front-Desk Queue
            </h1>
            <p>{{ $todayDateFormatted }} &bull; Live clinic reception & clinical service flow</p>
        </div>
        <div class="header-actions">
            @if(!empty($canViewAllBranches))
                <form method="GET" action="{{ route('appointments.today') }}" style="display: inline-block;">
                    <select name="branch_id" class="form-control form-control-sm" onchange="this.form.submit()" style="display: inline-block; width: auto; font-weight: 600; border-radius: 6px;">
                        @foreach ($branches as $bId => $bName)
                            <option value="{{ $bId }}" {{ $currentBranchId == $bId ? 'selected' : '' }}>
                                📍 {{ $bName }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif
            <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#quickWalkInModal">
                <i class="fas fa-walking mr-1"></i>+ Quick Walk-In
            </button>
            <a href="{{ url('admin/appointments/calendar') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-calendar mr-1"></i>Calendar View
            </a>
            <a href="{{ route('appointments.create') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-plus mr-1"></i>Book Future
            </a>
        </div>
    </div>

    @include('admin.alert_message')

    {{-- Special Attention Tray: Needs Rescheduling --}}
    @if($needsReschedule->isNotEmpty())
        <div class="alert-tray" style="background: #fef2f2; border-color: #fecaca;">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div style="font-weight: 700; color: #dc2626; font-size: 13px;">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Appointments Requiring Telephone Rescheduling ({{ $needsReschedule->count() }})
                </div>
                <small class="text-muted">Specialist leave or branch holiday conflicts</small>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @foreach($needsReschedule as $item)
                    @php $pName = $item->customer?->lead?->full_name ?? $item->lead?->full_name ?? 'Patient'; @endphp
                    <div style="background: #ffffff; border: 1px solid #f87171; border-radius: 8px; padding: 8px 12px; font-size: 12px; display: inline-flex; align-items: center; gap: 8px;">
                        <strong>{{ $pName }}</strong>
                        <span class="text-muted">({{ $item->customer?->lead?->phone_number ?? $item->lead?->phone_number ?? 'No phone' }})</span>
                        <span class="badge badge-warning text-dark">{{ $item->service?->name }}</span>
                        <button class="btn btn-xs btn-outline-danger" onclick="openRescheduleModal({{ $item->id }}, '{{ addslashes($pName) }}', '{{ $item->appointment_date->format('Y-m-d') }}', '{{ $item->user_id }}', '{{ $item->service_id }}')">
                            <i class="fas fa-redo-alt mr-1"></i>Reschedule
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Special Attention Tray: Time Unknown --}}
    @if($timeUnknown->isNotEmpty())
        <div class="alert-tray mb-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div style="font-weight: 700; color: #b45309; font-size: 13px;">
                    <i class="fas fa-question-circle mr-2"></i>Appointments with Time Unknown ({{ $timeUnknown->count() }})
                </div>
                <small class="text-muted">Migrated seed bookings without exact time slots</small>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @foreach($timeUnknown as $item)
                    @php $pName = $item->customer?->lead?->full_name ?? $item->lead?->full_name ?? 'Patient'; @endphp
                    <div style="background: #ffffff; border: 1px solid #fde68a; border-radius: 8px; padding: 8px 12px; font-size: 12px; display: inline-flex; align-items: center; gap: 8px;">
                        <strong>{{ $pName }}</strong>
                        <span class="text-muted">({{ $item->customer?->lead?->phone_number ?? $item->lead?->phone_number ?? 'No phone' }})</span>
                        <span class="badge badge-info">{{ $item->appointment_date->format('d M Y') }}</span>
                        <button class="btn btn-xs btn-warning text-dark" onclick="openRescheduleModal({{ $item->id }}, '{{ addslashes($pName) }}', '{{ $item->appointment_date->format('Y-m-d') }}', '{{ $item->user_id }}', '{{ $item->service_id }}')">
                            <i class="fas fa-clock mr-1"></i>Assign Time
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- 4 Pipeline Kanban Columns --}}
    <div class="today-pipeline-grid">

        {{-- Column 1: Scheduled / Confirmed --}}
        <div class="pipeline-column">
            <div class="pipeline-header">
                <div class="pipeline-title" style="color: #2563eb;">
                    <i class="fas fa-calendar-check"></i>
                    <span>Expected Today</span>
                </div>
                <span class="pipeline-count" style="background: #dbeafe; color: #1e40af;">{{ $scheduled->count() }}</span>
            </div>
            <div class="pipeline-cards">
                @forelse($scheduled as $appt)
                    @php
                        $patientName = $appt->customer?->lead?->full_name ?? $appt->lead?->full_name ?? 'Unknown';
                        $patientPhone = $appt->customer?->lead?->phone_number ?? $appt->lead?->phone_number ?? '';
                    @endphp
                    <div class="patient-card">
                        <div class="patient-card-header">
                            <div class="patient-avatar">{{ strtoupper(substr($patientName, 0, 1)) }}</div>
                            <div class="patient-info">
                                <div class="patient-name">{{ $patientName }}</div>
                                <div class="patient-phone">{{ $patientPhone ?: 'No phone' }}</div>
                            </div>
                        </div>

                        <div class="card-time-badge">
                            <i class="far fa-clock"></i>
                            {{ $appt->start_time ? date('g:i A', strtotime($appt->start_time)) : 'Time Unknown' }}
                            @if($appt->end_time) - {{ date('g:i A', strtotime($appt->end_time)) }} @endif
                        </div>

                        <div class="card-service-meta">
                            <span class="badge" style="background:#f1f5f9; color:#0f172a; border: 1px solid #cbd5e1;">
                                {{ $appt->service?->name ?? 'Clinical Service' }}
                            </span>
                            @if($appt->resource)
                                <span class="badge badge-info" style="font-size: 10px;">
                                    <i class="fas fa-door-open mr-1"></i>{{ $appt->resource->name }}
                                </span>
                            @endif
                        </div>

                        <div class="card-doctor-meta">
                            <i class="fas fa-user-md" style="color: #64748b;"></i>
                            <span>{{ $appt->user?->name ?? 'Any Specialist' }}</span>
                        </div>

                        @if($appt->customerAdvance)
                            <div style="font-size: 11px; color: #16a34a; font-weight: 600; margin-bottom: 6px;">
                                <i class="fas fa-receipt mr-1"></i>Advance ৳{{ number_format($appt->customerAdvance->amount, 2) }}
                            </div>
                        @endif

                        <div class="card-actions">
                            <form action="{{ route('appointments.check-in', $appt->id) }}" method="POST" style="margin: 0; flex-grow: 1;">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-block btn-warning" style="font-weight: 600; font-size: 11px;">
                                    <i class="fas fa-user-check mr-1"></i>Patient Arrived
                                </button>
                            </form>
                            <button type="button" class="btn btn-sm btn-outline-secondary" title="Reschedule" onclick="openRescheduleModal({{ $appt->id }}, '{{ addslashes($patientName) }}', '{{ $appt->appointment_date->format('Y-m-d') }}', '{{ $appt->user_id }}', '{{ $appt->service_id }}')">
                                <i class="fas fa-calendar-alt"></i>
                            </button>
                        </div>
                    </div>
                @empty
                    <div style="text-align: center; padding: 40px 10px; color: #94a3b8; font-size: 12px;">
                        <i class="far fa-calendar-check" style="font-size: 28px; margin-bottom: 8px; display: block; opacity: 0.5;"></i>
                        No scheduled patients waiting for arrival.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Column 2: Arrived / Waiting Room --}}
        <div class="pipeline-column" style="background: #fffbeb;">
            <div class="pipeline-header">
                <div class="pipeline-title" style="color: #d97706;">
                    <i class="fas fa-couch"></i>
                    <span>Waiting Room</span>
                </div>
                <span class="pipeline-count" style="background: #fef3c7; color: #92400e;">{{ $arrived->count() }}</span>
            </div>
            <div class="pipeline-cards">
                @forelse($arrived as $appt)
                    @php
                        $patientName = $appt->customer?->lead?->full_name ?? $appt->lead?->full_name ?? 'Unknown';
                        $patientPhone = $appt->customer?->lead?->phone_number ?? $appt->lead?->phone_number ?? '';
                    @endphp
                    <div class="patient-card" style="border-left: 4px solid #f59e0b;">
                        <div class="patient-card-header">
                            <div class="patient-avatar" style="background: #fef3c7; color: #b45309;">
                                {{ strtoupper(substr($patientName, 0, 1)) }}
                            </div>
                            <div class="patient-info">
                                <div class="patient-name">{{ $patientName }}</div>
                                <div class="patient-phone">{{ $patientPhone ?: 'No phone' }}</div>
                            </div>
                        </div>

                        <div class="card-time-badge" style="background: #fef3c7; color: #92400e;">
                            <i class="fas fa-clock"></i> Arrived: {{ $appt->updated_at?->format('g:i A') ?? 'Today' }}
                        </div>

                        <div class="card-service-meta">
                            <span class="badge badge-light" style="font-weight: 600;">
                                {{ $appt->service?->name ?? 'Clinical Service' }}
                            </span>
                            @if($appt->resource)
                                <span class="badge badge-info" style="font-size: 10px;">
                                    {{ $appt->resource->name }}
                                </span>
                            @endif
                        </div>

                        <div class="card-doctor-meta">
                            <i class="fas fa-stethoscope" style="color: #64748b;"></i>
                            <span>{{ $appt->user?->name ?? 'Specialist' }}</span>
                        </div>

                        <div class="card-actions">
                            <form action="{{ route('appointments.start-service', $appt->id) }}" method="POST" style="margin: 0; width: 100%;">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-block btn-primary" style="font-weight: 600; font-size: 11px;">
                                    <i class="fas fa-play mr-1"></i>Start Consultation
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div style="text-align: center; padding: 40px 10px; color: #94a3b8; font-size: 12px;">
                        <i class="fas fa-couch" style="font-size: 28px; margin-bottom: 8px; display: block; opacity: 0.5;"></i>
                        No patients currently waiting in reception.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Column 3: In Progress (In Room / Booth) --}}
        <div class="pipeline-column" style="background: #f5f3ff;">
            <div class="pipeline-header">
                <div class="pipeline-title" style="color: #7c3aed;">
                    <div class="pulse-indicator mr-1"></div>
                    <span>In Progress</span>
                </div>
                <span class="pipeline-count" style="background: #ede9fe; color: #5b21b6;">{{ $inProgress->count() }}</span>
            </div>
            <div class="pipeline-cards">
                @forelse($inProgress as $appt)
                    @php
                        $patientName = $appt->customer?->lead?->full_name ?? $appt->lead?->full_name ?? 'Unknown';
                        $patientPhone = $appt->customer?->lead?->phone_number ?? $appt->lead?->phone_number ?? '';
                    @endphp
                    <div class="patient-card" style="border-left: 4px solid #8b5cf6;">
                        <div class="patient-card-header">
                            <div class="patient-avatar" style="background: #ede9fe; color: #6d28d9;">
                                {{ strtoupper(substr($patientName, 0, 1)) }}
                            </div>
                            <div class="patient-info">
                                <div class="patient-name">{{ $patientName }}</div>
                                <div class="patient-phone">{{ $patientPhone ?: 'No phone' }}</div>
                            </div>
                        </div>

                        <div class="card-time-badge" style="background: #ede9fe; color: #6d28d9;">
                            <i class="fas fa-stethoscope"></i> Inside Room / Booth
                        </div>

                        <div class="card-service-meta">
                            <span class="badge badge-light" style="font-weight: 600;">
                                {{ $appt->service?->name ?? 'Clinical Service' }}
                            </span>
                            @if($appt->resource)
                                <span class="badge badge-success" style="font-size: 10px;">
                                    <i class="fas fa-door-open mr-1"></i>{{ $appt->resource->name }}
                                </span>
                            @endif
                        </div>

                        <div class="card-doctor-meta">
                            <i class="fas fa-user-md" style="color: #6d28d9;"></i>
                            <strong>{{ $appt->user?->name ?? 'Specialist' }}</strong>
                        </div>

                        <div class="card-actions">
                            <form action="{{ route('appointments.complete-service', $appt->id) }}" method="POST" style="margin: 0; width: 100%;">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-block btn-success" style="font-weight: 600; font-size: 11px;">
                                    <i class="fas fa-check-double mr-1"></i>Mark Completed
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div style="text-align: center; padding: 40px 10px; color: #94a3b8; font-size: 12px;">
                        <i class="fas fa-stethoscope" style="font-size: 28px; margin-bottom: 8px; display: block; opacity: 0.5;"></i>
                        No appointments currently in consultation.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Column 4: Completed Today --}}
        <div class="pipeline-column" style="background: #f0fdf4;">
            <div class="pipeline-header">
                <div class="pipeline-title" style="color: #16a34a;">
                    <i class="fas fa-check-circle"></i>
                    <span>Completed Today</span>
                </div>
                <span class="pipeline-count" style="background: #dcfce7; color: #15803d;">{{ $completed->count() }}</span>
            </div>
            <div class="pipeline-cards">
                @forelse($completed as $appt)
                    @php
                        $patientName = $appt->customer?->lead?->full_name ?? $appt->lead?->full_name ?? 'Unknown';
                    @endphp
                    <div class="patient-card" style="border-left: 4px solid #22c55e; opacity: 0.95;">
                        <div class="patient-card-header">
                            <div class="patient-avatar" style="background: #dcfce7; color: #15803d;">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="patient-info">
                                <div class="patient-name">{{ $patientName }}</div>
                                <div class="patient-phone" style="color: #16a34a;">
                                    Finished: {{ $appt->updated_at?->format('g:i A') ?? 'Today' }}
                                </div>
                            </div>
                        </div>

                        <div class="card-service-meta">
                            <span class="badge badge-light" style="font-weight: 600;">
                                {{ $appt->service?->name ?? 'Clinical Service' }}
                            </span>
                        </div>

                        <div class="card-doctor-meta">
                            <i class="fas fa-user-md" style="color: #64748b;"></i>
                            <span>{{ $appt->user?->name ?? 'Specialist' }}</span>
                        </div>

                        @if($appt->saleMaster)
                            <div style="font-size: 11px; margin-top: 6px;">
                                <a href="{{ route('sale.show', $appt->saleMaster->sale_invoice_no) }}" class="text-primary font-weight-bold">
                                    <i class="fas fa-file-invoice mr-1"></i>Invoice #{{ $appt->saleMaster->sale_invoice_no }}
                                </a>
                            </div>
                        @endif
                    </div>
                @empty
                    <div style="text-align: center; padding: 40px 10px; color: #94a3b8; font-size: 12px;">
                        <i class="far fa-check-circle" style="font-size: 28px; margin-bottom: 8px; display: block; opacity: 0.5;"></i>
                        No completed appointments today yet.
                    </div>
                @endforelse
            </div>
        </div>

    </div>

</div>

{{-- Quick Walk-In Intake Modal --}}
<div class="modal fade" id="quickWalkInModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background: #0f172a; color: #ffffff; border-top-left-radius: 12px; border-top-right-radius: 12px;">
                <h5 class="modal-title font-weight-bold" style="font-size: 15px;">
                    <i class="fas fa-walking mr-2 text-warning"></i>Front-Desk Quick Walk-In Intake
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('appointments.quick-walk-in') }}" method="POST">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $currentBranchId }}">
                <div class="modal-body" style="padding: 20px;">
                    <div class="form-group">
                        <label style="font-size: 12px; font-weight: 600; color: #334155;">Patient Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="patient_name" class="form-control" placeholder="e.g. Mohammad Ali" required>
                    </div>

                    <div class="form-group">
                        <label style="font-size: 12px; font-weight: 600; color: #334155;">Mobile Phone Number <span class="text-danger">*</span></label>
                        <input type="text" name="patient_phone" class="form-control" placeholder="01XXXXXXXXX" required>
                    </div>

                    <div class="form-group">
                        <label style="font-size: 12px; font-weight: 600; color: #334155;">Clinical Service <span class="text-danger">*</span></label>
                        <select name="service_id" class="form-control" required>
                            @foreach($appointmentServices as $svc)
                                <option value="{{ $svc->id }}">
                                    {{ $svc->name }} ({{ $svc->duration_minutes }}m) {{ $svc->requires_resource ? '• Requires Sound Booth' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="font-size: 12px; font-weight: 600; color: #334155;">Assign Specialist <span class="text-danger">*</span></label>
                        <select name="user_id" class="form-control" required>
                            @foreach($specialists as $doc)
                                <option value="{{ $doc->id }}">{{ $doc->name }} ({{ $doc->designation?->label() ?? 'Specialist' }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="font-size: 12px; font-weight: 600; color: #334155;">Notes / Chief Complaint</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Sudden hearing drop in right ear"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8fafc; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold">
                        <i class="fas fa-check mr-1"></i>Add Patient to Waiting Room
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Telephone Reschedule Modal --}}
<div class="modal fade" id="rescheduleModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background: #1e293b; color: #ffffff;">
                <h5 class="modal-title font-weight-bold" style="font-size: 15px;">
                    <i class="fas fa-calendar-alt mr-2 text-warning"></i>Reschedule Patient Appointment
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="rescheduleForm" method="POST">
                @csrf
                <div class="modal-body" style="padding: 20px;">
                    <div style="background: #f1f5f9; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px;">
                        <div style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 700;">Patient</div>
                        <div id="reschedulePatientName" style="font-size: 14px; font-weight: 700; color: #0f172a;"></div>
                    </div>

                    <input type="hidden" id="rescheduleApptId">
                    <input type="hidden" id="rescheduleServiceId">
                    <input type="hidden" id="rescheduleUserId">

                    <div class="form-group">
                        <label style="font-size: 12px; font-weight: 600; color: #334155;">New Date <span class="text-danger">*</span></label>
                        <input type="date" id="rescheduleDate" name="appointment_date" class="form-control" required min="{{ date('Y-m-d') }}">
                    </div>

                    <div class="form-group">
                        <label style="font-size: 12px; font-weight: 600; color: #334155;">Available Start Time <span class="text-danger">*</span></label>
                        <select id="rescheduleTimeSelect" name="start_time" class="form-control" required>
                            <option value="">Select a date first...</option>
                        </select>
                        <small id="rescheduleTimeNotice" class="text-muted" style="display:none;"></small>
                    </div>

                    <div class="form-group">
                        <label style="font-size: 12px; font-weight: 600; color: #334155;">Reason for Rescheduling <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="2" placeholder="e.g. Patient requested morning shift or specialist on leave" required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8fafc;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm font-weight-bold">
                        <i class="fas fa-check mr-1"></i>Confirm Reschedule & Send SMS
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const currentBranchId = {{ $currentBranchId }};

    function openRescheduleModal(apptId, patientName, currentDate, userId, serviceId) {
        $('#rescheduleApptId').val(apptId);
        $('#reschedulePatientName').text(patientName);
        $('#rescheduleServiceId').val(serviceId);
        $('#rescheduleUserId').val(userId);
        $('#rescheduleDate').val(currentDate);

        $('#rescheduleForm').attr('action', `/admin/appointments/${apptId}/reschedule`);
        loadRescheduleSlots(currentDate, serviceId, userId);

        $('#rescheduleModal').modal('show');
    }

    $('#rescheduleDate').on('change', function() {
        const d = $(this).val();
        const sId = $('#rescheduleServiceId').val();
        const uId = $('#rescheduleUserId').val();
        loadRescheduleSlots(d, sId, uId);
    });

    function loadRescheduleSlots(date, serviceId, userId) {
        const select = $('#rescheduleTimeSelect');
        const notice = $('#rescheduleTimeNotice');

        select.html('<option value="">Checking availability...</option>');
        notice.hide();

        $.getJSON('/admin/appointments/dynamic-availability', {
            branch_id: currentBranchId,
            service_id: serviceId,
            date: date,
            user_id: userId
        }).done(function(res) {
            select.empty();
            if (res.is_closed) {
                select.append(`<option value="">Closed: ${res.closure_reason}</option>`);
                notice.text(res.closure_reason).show();
            } else if (!res.slots || res.slots.length === 0) {
                select.append('<option value="">No slots available on this date</option>');
                notice.text(res.closure_reason || 'No rostered specialists on this day').show();
            } else {
                select.append('<option value="">-- Choose 15-Min Start Time --</option>');
                res.slots.forEach(s => {
                    select.append(`<option value="${s.time_full}">${s.label} (${s.user_name})</option>`);
                });
            }
        }).fail(function() {
            select.html('<option value="">Failed to load slots</option>');
        });
    }
</script>
@endpush
