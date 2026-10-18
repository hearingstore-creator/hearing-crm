@extends('admin.layouts.master')
@section('page_title', 'Repair & Service Pipeline (Plan B)')
@section('breadcrumb_parent', 'Operations')
@section('breadcrumb_parent_url', route('repairs.index'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/kpi-cards.css') }}">
    <style>
        :root {
            --stage-1: #2563eb;
            --stage-2: #d97706;
            --stage-3: #7c3aed;
            --stage-4: #0891b2;
            --stage-5: #059669;
            --stage-6: #475569;
        }

        .pipeline-tabs {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        @media (max-width: 1200px) {
            .pipeline-tabs {
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }
        }
        @media (max-width: 640px) {
            .pipeline-tabs {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }
        }
        .pipeline-tab {
            width: 100%;
            min-width: 0;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-decoration: none !important;
            transition: all 0.2s ease;
            position: relative;
            box-sizing: border-box;
            min-height: 64px;
        }
        .pipeline-tab:hover {
            border-color: #cbd5e1;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.06);
        }
        .pipeline-tab.active {
            border-width: 2px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        }
        .pipeline-tab.s1.active { border-color: var(--stage-1); background: #eff6ff; }
        .pipeline-tab.s2.active { border-color: var(--stage-2); background: #fffbeb; }
        .pipeline-tab.s3.active { border-color: var(--stage-3); background: #faf5ff; }
        .pipeline-tab.s4.active { border-color: var(--stage-4); background: #ecfeff; }
        .pipeline-tab.s5.active { border-color: var(--stage-5); background: #f0fdf4; }
        .pipeline-tab.s6.active { border-color: var(--stage-6); background: #f8fafc; }

        .pipeline-tab .step-no {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
            line-height: 1.2;
        }
        .pipeline-tab.s1 .step-no { color: var(--stage-1); }
        .pipeline-tab.s2 .step-no { color: var(--stage-2); }
        .pipeline-tab.s3 .step-no { color: var(--stage-3); }
        .pipeline-tab.s4 .step-no { color: var(--stage-4); }
        .pipeline-tab.s5 .step-no { color: var(--stage-5); }
        .pipeline-tab.s6 .step-no { color: var(--stage-6); }

        .pipeline-tab .step-name {
            font-size: 12.5px;
            font-weight: 600;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-right: 32px;
            line-height: 1.2;
        }
        .pipeline-tab .step-count {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            right: 12px;
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
            background: #f1f5f9;
            border-radius: 999px;
            padding: 2px 8px;
            min-width: 24px;
            text-align: center;
            line-height: 1.4;
        }
        .pipeline-tab.active .step-count {
            background: rgba(15, 23, 42, 0.12);
        }

        /* Toolbar Action Buttons & View Mode Toggle */
        .btn-filter-all {
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            padding: 7px 14px !important;
            border-radius: 8px !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            text-decoration: none !important;
            border: 1px solid #cbd5e1 !important;
            background: #ffffff !important;
            color: #334155 !important;
            transition: all 0.2s ease !important;
            margin-right: 12px !important;
            height: 36px !important;
            box-sizing: border-box !important;
            line-height: 1 !important;
        }
        .btn-filter-all:hover {
            background: #f1f5f9 !important;
            border-color: #94a3b8 !important;
            color: #0f172a !important;
            transform: translateY(-1px);
        }
        .btn-filter-all.active {
            background: #0f172a !important;
            border-color: #0f172a !important;
            color: #ffffff !important;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.25) !important;
        }
        .badge-all-jobs {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 999px;
            background: #e2e8f0;
            color: #334155;
            display: inline-block;
        }
        .btn-filter-all.active .badge-all-jobs {
            background: rgba(255, 255, 255, 0.25);
            color: #ffffff;
        }

        .repair-view-toggle {
            display: inline-flex;
            align-items: center;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            padding: 3px;
            gap: 3px;
            height: 36px;
            box-sizing: border-box;
        }
        .view-toggle-item {
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            padding: 5px 13px !important;
            border-radius: 7px !important;
            font-size: 12.5px !important;
            font-weight: 600 !important;
            color: #64748b !important;
            text-decoration: none !important;
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
            transition: all 0.15s ease !important;
            height: 28px !important;
            line-height: 1 !important;
        }
        .view-toggle-item:hover {
            color: #0f172a !important;
            background: rgba(255, 255, 255, 0.6) !important;
        }
        .view-toggle-item.active {
            background: #ffffff !important;
            color: var(--primary, #2563eb) !important;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08) !important;
            font-weight: 700 !important;
        }

        /* Tasks Ribbon */
        .tasks-ribbon {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-radius: 10px;
            padding: 12px 18px;
            color: #ffffff;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
        }
        .task-chip {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            padding: 6px 12px;
            display: inline-flex;
            align-items: center;
            margin-right: 8px;
            margin-bottom: 4px;
            font-size: 12px;
            transition: all 0.2s;
        }
        .task-chip:hover {
            background: rgba(255, 255, 255, 0.18);
        }
        .task-chip .task-btn {
            background: #10b981;
            color: #ffffff;
            border: none;
            border-radius: 4px;
            padding: 2px 6px;
            margin-left: 8px;
            font-size: 11px;
            cursor: pointer;
        }

        /* Kanban Board Horizontal Scroll Pipeline */
        .board-wrapper {
            position: relative;
            margin-bottom: 25px;
        }
        .board-container {
            display: grid;
            grid-template-columns: repeat(6, minmax(260px, 1fr));
            gap: 14px;
            overflow-x: auto;
            padding: 4px 2px 20px;
            min-height: calc(100vh - 320px);
        }
        .board-container::-webkit-scrollbar {
            height: 10px;
        }
        .board-container::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 8px;
        }
        .board-container::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 8px;
        }
        .board-container::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .board-col {
            width: 100%;
            min-width: 260px;
            box-sizing: border-box;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 280px);
            min-height: 520px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            transition: all 0.2s ease;
        }
        .board-col.col-highlighted {
            border: 2px solid var(--stage-color, #2563eb);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12), 0 8px 24px -4px rgba(0,0,0,0.12);
        }
        .board-col.col-1 { --stage-color: var(--stage-1); }
        .board-col.col-2 { --stage-color: var(--stage-2); }
        .board-col.col-3 { --stage-color: var(--stage-3); }
        .board-col.col-4 { --stage-color: var(--stage-4); }
        .board-col.col-5 { --stage-color: var(--stage-5); }
        .board-col.col-6 { --stage-color: var(--stage-6); }

        .board-col-header {
            padding: 12px 14px;
            font-weight: 700;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 3px solid var(--stage-color, #64748b);
            border-bottom: 1px solid #e2e8f0;
            background: #ffffff;
        }
        .board-col-header .stage-title {
            display: flex;
            align-items: center;
            gap: 7px;
            color: #1e293b;
            font-weight: 700;
            font-size: 13.5px;
        }
        .board-col-header .stage-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--stage-color, #64748b);
            display: inline-block;
        }
        .board-col-header .col-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 999px;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
        .board-col-header .col-table-link {
            color: #94a3b8;
            font-size: 12px;
            margin-left: 6px;
            transition: color 0.15s;
        }
        .board-col-header .col-table-link:hover {
            color: var(--stage-color, #2563eb);
        }

        .board-cards {
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            overflow-y: auto;
            flex: 1;
        }
        .board-cards::-webkit-scrollbar {
            width: 6px;
        }
        .board-cards::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .board-empty-state {
            padding: 40px 16px;
            text-align: center;
            color: #94a3b8;
            font-size: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
        }
        .job-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
            position: relative;
            width: 100%;
            box-sizing: border-box;
        }
        .job-card:hover {
            border-color: #94a3b8;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.08);
        }
        .job-card .job-no {
            font-weight: 700;
            font-size: 13px;
            color: #0f172a;
        }
        .job-card .job-age {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 4px;
            background: #f1f5f9;
            color: #475569;
        }
        .job-card .job-age.urgent {
            background: #fee2e2;
            color: #b91c1c;
        }
        .job-card .customer-name {
            font-weight: 600;
            font-size: 13px;
            color: #1e293b;
            margin-top: 4px;
        }
        .job-card .device-info {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 6px;
        }
        .path-badge {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
        }
        .path-badge.warranty { background: #dcfce7; color: #15803d; }
        .path-badge.in-house { background: #dbeafe; color: #1d4ed8; }
        .path-badge.paid { background: #fef3c7; color: #b45309; }

        .btn-action-primary {
            font-size: 11px;
            font-weight: 700;
            width: 100%;
            border-radius: 6px;
            padding: 6px 10px;
            text-align: center;
            display: inline-block;
            margin-top: 8px;
            text-decoration: none !important;
            transition: all 0.2s;
        }
    </style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Top Action & Dual-Run Bar --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 mt-2">
        <div class="page-title-group">
            <h1 class="h3 font-weight-bold text-gray-800 mb-1">
                <i class="fas fa-tools text-primary mr-2"></i>Repair &amp; Service Pipeline
            </h1>
            <p class="text-muted small mb-0">Streamlined 6-stage workflow with automated dispatch tasks, teardown routing &amp; supplier reconciliation</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-2 mt-md-0">
            {{-- User Guide --}}
            <a href="{{ asset('repair-user-guide.html') }}" target="_blank" class="btn btn-outline-info btn-sm shadow-sm font-weight-bold mr-2" title="Open complete user manual in new tab">
                <i class="fas fa-book-open mr-1"></i>User Guide
            </a>
            {{-- Dual-Run Switch to Plan A --}}
            <a href="{{ route('repairs.index', ['mode' => 'legacy']) }}" class="btn btn-outline-secondary btn-sm shadow-sm font-weight-bold mr-2">
                <i class="fas fa-history mr-1"></i>Switch to Plan A (Legacy)
            </a>
            <a href="{{ route('repairs.supplier-reconciliation') }}" class="btn btn-outline-primary btn-sm shadow-sm font-weight-bold mr-2">
                <i class="fas fa-file-invoice-dollar mr-1"></i>Supplier Reconciliation
            </a>
            <a href="{{ route('repairs.quick') }}" class="btn btn-outline-success btn-sm shadow-sm font-weight-bold mr-2">
                <i class="fas fa-bolt mr-1"></i>Quick Walk-in
            </a>
            <a href="{{ route('repairs.create') }}" class="btn btn-primary btn-sm shadow-sm font-weight-bold">
                <i class="fas fa-plus-circle mr-1"></i>New Repair Job
            </a>
        </div>
    </div>

    {{-- Urgent Tasks Ribbon --}}
    @if($urgentTasks->count() > 0 || $totalPendingTasks > 0)
    <div class="tasks-ribbon d-flex flex-wrap align-items-center justify-content-between">
        <div class="d-flex align-items-center flex-wrap mr-3">
            <span class="badge badge-warning text-dark font-weight-bold mr-2 px-2 py-1">
                <i class="fas fa-bell mr-1"></i>{{ $totalPendingTasks }} Pending Tasks
            </span>
            <span class="small font-weight-bold mr-3 d-none d-md-inline">Urgent Follow-ups:</span>
            @foreach($urgentTasks as $task)
                <div class="task-chip">
                    <span class="font-weight-bold mr-1">{{ $task->repairJob?->job_no }}:</span>
                    <span>{{ $task->title }}</span>
                    <button type="button" class="task-btn" onclick="completeTask({{ $task->id }}, this)" title="Mark Done">
                        <i class="fas fa-check"></i> Done
                    </button>
                </div>
            @endforeach
        </div>
        <div class="small text-muted text-right">
            <span>Automated Task Engine Active</span>
        </div>
    </div>
    @endif

    {{-- 6-Stage Pipeline Cards --}}
    <div class="pipeline-tabs">
        <a href="{{ route('repairs.index', array_merge(request()->except('stage'), ['stage' => in_array($stage, ['1', 'received']) ? 'all' : '1'])) }}" 
           class="pipeline-tab s1 {{ in_array($stage, ['1', 'received']) ? 'active' : '' }}" title="Stage 1: Received (Click to filter or unfilter)">
            <div class="step-no">STAGE 1</div>
            <div class="step-name">Received</div>
            <div class="step-count">{{ $stageCounts['stage1_received'] }}</div>
        </a>

        <a href="{{ route('repairs.index', array_merge(request()->except('stage'), ['stage' => in_array($stage, ['2', 'at_lab']) ? 'all' : '2'])) }}" 
           class="pipeline-tab s2 {{ in_array($stage, ['2', 'at_lab']) ? 'active' : '' }}" title="Stage 2: At Lab (Click to filter or unfilter)">
            <div class="step-no">STAGE 2</div>
            <div class="step-name">At Lab</div>
            <div class="step-count">{{ $stageCounts['stage2_at_lab'] }}</div>
        </a>

        <a href="{{ route('repairs.index', array_merge(request()->except('stage'), ['stage' => in_array($stage, ['3', 'waiting_approval']) ? 'all' : '3'])) }}" 
           class="pipeline-tab s3 {{ in_array($stage, ['3', 'waiting_approval']) ? 'active' : '' }}" title="Stage 3: Waiting Approval (Click to filter or unfilter)">
            <div class="step-no">STAGE 3</div>
            <div class="step-name">Waiting Approval</div>
            <div class="step-count">{{ $stageCounts['stage3_approval'] }}</div>
        </a>

        <a href="{{ route('repairs.index', array_merge(request()->except('stage'), ['stage' => in_array($stage, ['4', 'in_repair']) ? 'all' : '4'])) }}" 
           class="pipeline-tab s4 {{ in_array($stage, ['4', 'in_repair']) ? 'active' : '' }}" title="Stage 4: In Repair (Click to filter or unfilter)">
            <div class="step-no">STAGE 4</div>
            <div class="step-name">In Repair</div>
            <div class="step-count">{{ $stageCounts['stage4_in_repair'] }}</div>
        </a>

        <a href="{{ route('repairs.index', array_merge(request()->except('stage'), ['stage' => in_array($stage, ['5', 'ready']) ? 'all' : '5'])) }}" 
           class="pipeline-tab s5 {{ in_array($stage, ['5', 'ready']) ? 'active' : '' }}" title="Stage 5: Ready for Pickup (Click to filter or unfilter)">
            <div class="step-no">STAGE 5</div>
            <div class="step-name">Ready for Pickup</div>
            <div class="step-count">{{ $stageCounts['stage5_ready'] }}</div>
        </a>

        <a href="{{ route('repairs.index', array_merge(request()->except('stage'), ['stage' => in_array($stage, ['6', 'delivered']) ? 'all' : '6'])) }}" 
           class="pipeline-tab s6 {{ in_array($stage, ['6', 'delivered']) ? 'active' : '' }}" title="Stage 6: Delivered (Click to filter or unfilter)">
            <div class="step-no">STAGE 6</div>
            <div class="step-name">Delivered</div>
            <div class="step-count">{{ $stageCounts['stage6_delivered'] }}</div>
        </a>
    </div>

    {{-- Filter, Search & View Toggle Toolbar --}}
    <div class="card shadow-sm border-0 mb-4" style="border-radius: 8px;">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('repairs.index') }}" class="row align-items-center g-2" id="filterForm">
                <input type="hidden" name="stage" value="{{ $stage }}">
                <input type="hidden" name="view" value="{{ $viewMode }}">

                <div class="{{ $branches->count() > 1 ? 'col-md-4' : 'col-md-5' }}">
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted"></i></span>
                        </div>
                        <input type="text" name="search" class="form-control border-left-0" 
                                placeholder="Scan Barcode or Search Job #, Serial #, Tracking #, Patient Name..." 
                                value="{{ $search }}" id="globalSearchInput" autofocus>
                    </div>
                </div>

                @if($branches->count() > 1)
                <div class="col-md-3">
                    <select name="vendor_id" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">All Clinic Branches</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" {{ request('vendor_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div class="{{ $branches->count() > 1 ? 'col-md-5' : 'col-md-7' }} text-md-right mt-2 mt-md-0 d-flex flex-wrap align-items-center justify-content-md-end">
                    <a href="{{ route('repairs.index', array_merge(request()->except('stage'), ['stage' => 'all'])) }}" 
                       class="btn-filter-all {{ $stage === 'all' ? 'active' : '' }}" title="Show all stages">
                        <i class="fas fa-layer-group"></i>
                        <span>All Jobs</span>
                        <span class="badge-all-jobs">({{ $stageCounts['all'] }})</span>
                    </a>

                    <div class="repair-view-toggle">
                        <a href="{{ route('repairs.index', array_merge(request()->all(), ['view' => 'board'])) }}" 
                           class="view-toggle-item {{ $viewMode === 'board' ? 'active' : '' }}">
                            <i class="fas fa-columns"></i>
                            <span>Board View</span>
                        </a>
                        <a href="{{ route('repairs.index', array_merge(request()->all(), ['view' => 'list'])) }}" 
                           class="view-toggle-item {{ $viewMode === 'list' ? 'active' : '' }}">
                            <i class="fas fa-list"></i>
                            <span>List View</span>
                        </a>
                    </div>

                    @if($search || request('vendor_id') || $stage !== 'all')
                        <a href="{{ route('repairs.index') }}" class="btn btn-outline-danger btn-sm ml-2" title="Clear Filters" style="height: 36px; display: inline-flex; align-items: center; border-radius: 8px; font-weight: 600;">
                            <i class="fas fa-times mr-1"></i> Clear
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- KANBAN BOARD VIEW (Active Pipeline) --}}
    @if($viewMode === 'board')
    <div class="board-wrapper">
        <div class="board-container">
            {{-- Column 1: Received --}}
            <div class="board-col col-1 {{ in_array($stage, ['1', 'received']) ? 'col-highlighted' : '' }}" id="col-stage-1">
                <div class="board-col-header">
                    <div class="stage-title">
                        <span class="stage-dot"></span>
                        <span>1. Received</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="col-badge">{{ count($boardJobs['received'] ?? []) }}</span>
                        <a href="{{ route('repairs.index', array_merge(request()->except('stage'), ['stage' => '1', 'view' => 'list'])) }}" class="col-table-link" title="View all in list table">
                            <i class="fas fa-list"></i>
                        </a>
                    </div>
                </div>
                <div class="board-cards">
                    @forelse($boardJobs['received'] ?? [] as $job)
                        @include('admin.repairs.partials.kanban_card', ['job' => $job, 'stageNum' => 1])
                    @empty
                        <div class="board-empty-state">
                            <i class="fas fa-inbox mb-2" style="font-size: 24px; opacity: 0.4;"></i>
                            <span>No jobs in intake</span>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Column 2: At Lab --}}
            <div class="board-col col-2 {{ in_array($stage, ['2', 'at_lab']) ? 'col-highlighted' : '' }}" id="col-stage-2">
                <div class="board-col-header">
                    <div class="stage-title">
                        <span class="stage-dot"></span>
                        <span>2. At Lab</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="col-badge">{{ count($boardJobs['at_lab'] ?? []) }}</span>
                        <a href="{{ route('repairs.index', array_merge(request()->except('stage'), ['stage' => '2', 'view' => 'list'])) }}" class="col-table-link" title="View all in list table">
                            <i class="fas fa-list"></i>
                        </a>
                    </div>
                </div>
                <div class="board-cards">
                    @forelse($boardJobs['at_lab'] ?? [] as $job)
                        @include('admin.repairs.partials.kanban_card', ['job' => $job, 'stageNum' => 2])
                    @empty
                        <div class="board-empty-state">
                            <i class="fas fa-shipping-fast mb-2" style="font-size: 24px; opacity: 0.4;"></i>
                            <span>No jobs at supplier lab</span>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Column 3: Waiting Approval --}}
            <div class="board-col col-3 {{ in_array($stage, ['3', 'waiting_approval']) ? 'col-highlighted' : '' }}" id="col-stage-3">
                <div class="board-col-header">
                    <div class="stage-title">
                        <span class="stage-dot"></span>
                        <span>3. Waiting Approval</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="col-badge">{{ count($boardJobs['waiting_approval'] ?? []) }}</span>
                        <a href="{{ route('repairs.index', array_merge(request()->except('stage'), ['stage' => '3', 'view' => 'list'])) }}" class="col-table-link" title="View all in list table">
                            <i class="fas fa-list"></i>
                        </a>
                    </div>
                </div>
                <div class="board-cards">
                    @forelse($boardJobs['waiting_approval'] ?? [] as $job)
                        @include('admin.repairs.partials.kanban_card', ['job' => $job, 'stageNum' => 3])
                    @empty
                        <div class="board-empty-state">
                            <i class="fas fa-phone mb-2" style="font-size: 24px; opacity: 0.4;"></i>
                            <span>No jobs pending patient call</span>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Column 4: In Repair --}}
            <div class="board-col col-4 {{ in_array($stage, ['4', 'in_repair']) ? 'col-highlighted' : '' }}" id="col-stage-4">
                <div class="board-col-header">
                    <div class="stage-title">
                        <span class="stage-dot"></span>
                        <span>4. In Repair</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="col-badge">{{ count($boardJobs['in_repair'] ?? []) }}</span>
                        <a href="{{ route('repairs.index', array_merge(request()->except('stage'), ['stage' => '4', 'view' => 'list'])) }}" class="col-table-link" title="View all in list table">
                            <i class="fas fa-list"></i>
                        </a>
                    </div>
                </div>
                <div class="board-cards">
                    @forelse($boardJobs['in_repair'] ?? [] as $job)
                        @include('admin.repairs.partials.kanban_card', ['job' => $job, 'stageNum' => 4])
                    @empty
                        <div class="board-empty-state">
                            <i class="fas fa-tools mb-2" style="font-size: 24px; opacity: 0.4;"></i>
                            <span>No jobs currently under repair</span>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Column 5: Ready for Pickup --}}
            <div class="board-col col-5 {{ in_array($stage, ['5', 'ready']) ? 'col-highlighted' : '' }}" id="col-stage-5">
                <div class="board-col-header">
                    <div class="stage-title">
                        <span class="stage-dot"></span>
                        <span>5. Ready for Pickup</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="col-badge">{{ count($boardJobs['ready_for_pickup'] ?? []) }}</span>
                        <a href="{{ route('repairs.index', array_merge(request()->except('stage'), ['stage' => '5', 'view' => 'list'])) }}" class="col-table-link" title="View all in list table">
                            <i class="fas fa-list"></i>
                        </a>
                    </div>
                </div>
                <div class="board-cards">
                    @forelse($boardJobs['ready_for_pickup'] ?? [] as $job)
                        @include('admin.repairs.partials.kanban_card', ['job' => $job, 'stageNum' => 5])
                    @empty
                        <div class="board-empty-state">
                            <i class="fas fa-check-circle mb-2" style="font-size: 24px; opacity: 0.4;"></i>
                            <span>No jobs waiting for patient pickup</span>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Column 6: Delivered --}}
            <div class="board-col col-6 {{ in_array($stage, ['6', 'delivered']) ? 'col-highlighted' : '' }}" id="col-stage-6">
                <div class="board-col-header">
                    <div class="stage-title">
                        <span class="stage-dot"></span>
                        <span>6. Delivered</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="col-badge">{{ count($boardJobs['delivered'] ?? []) }}</span>
                        <a href="{{ route('repairs.index', array_merge(request()->except('stage'), ['stage' => '6', 'view' => 'list'])) }}" class="col-table-link" title="View all delivered in table">
                            <i class="fas fa-list"></i>
                        </a>
                    </div>
                </div>
                <div class="board-cards">
                    @forelse($boardJobs['delivered'] ?? [] as $job)
                        @include('admin.repairs.partials.kanban_card', ['job' => $job, 'stageNum' => 6])
                    @empty
                        <div class="board-empty-state">
                            <i class="fas fa-hand-holding mb-2" style="font-size: 24px; opacity: 0.4;"></i>
                            <span>No recent deliveries</span>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    @else
    {{-- LIST TABLE VIEW --}}
    <div class="card shadow-sm border-0 mb-4" style="border-radius: 8px;">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                    <thead class="bg-light text-muted text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">
                        <tr>
                            <th class="pl-3">Job Card #</th>
                            <th>Patient / Contact</th>
                            <th>Device &amp; Serial</th>
                            <th>Pipeline Path</th>
                            <th>Current Stage</th>
                            <th>Age</th>
                            <th>Financial Due</th>
                            <th class="text-right pr-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($jobs as $job)
                        <tr>
                            <td class="pl-3 font-weight-bold">
                                <a href="{{ route('repairs.show', $job->id) }}" class="text-primary font-weight-bold">
                                    {{ $job->job_no }}
                                </a>
                                @if($job->is_quick_service)
                                    <span class="badge badge-pill badge-warning text-dark ml-1 font-weight-bold">⚡ Quick</span>
                                @endif
                                <div class="text-muted small">{{ $job->created_at->format('d M, Y') }}</div>
                            </td>
                            <td>
                                <div class="font-weight-bold text-dark">{{ $job->customer?->lead?->full_name ?? 'Third-Party Patient' }}</div>
                                <div class="text-muted small"><i class="fas fa-phone-alt mr-1"></i>{{ $job->customer?->lead?->phone_number ?? '-' }}</div>
                            </td>
                            <td>
                                <div class="font-weight-semibold text-dark">{{ $job->product_display_name }}</div>
                                <div class="small">
                                    <span class="text-muted">S/N:</span> <strong class="text-monospace text-primary">{{ $job->original_serial_no }}</strong>
                                    @if($job->replaced_serial_no)
                                        <span class="text-success ml-1">&rarr; New: <strong>{{ $job->replaced_serial_no }}</strong></span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if($job->pipeline_path === 'supplier_warranty')
                                    <span class="path-badge warranty"><i class="fas fa-shield-alt mr-1"></i>Supplier Warranty</span>
                                @elseif($job->pipeline_path === 'in_house')
                                    <span class="path-badge in-house"><i class="fas fa-tools mr-1"></i>In-House</span>
                                @else
                                    <span class="path-badge paid"><i class="fas fa-receipt mr-1"></i>Paid Service</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge px-2 py-1 font-weight-bold" 
                                      style="background: #e2e8f0; color: #334155; font-size: 11px;">
                                    {{ $job->stage_name }}
                                </span>
                                @if($job->supplier)
                                    <div class="small text-muted mt-1"><i class="fas fa-building mr-1"></i>{{ $job->supplier->supplier_name }}</div>
                                @endif
                            </td>
                            <td>
                                @php $days = $job->days_in_stage; @endphp
                                <span class="job-age {{ $days >= 7 ? 'urgent' : '' }}">
                                    {{ $days }} {{ Str::plural('day', $days) }}
                                </span>
                            </td>
                            <td>
                                @if($job->customer_due > 0)
                                    <span class="font-weight-bold text-danger">৳{{ number_format($job->customer_due, 2) }}</span>
                                @else
                                    <span class="text-success font-weight-bold">৳0.00 (Settled)</span>
                                @endif
                            </td>
                            <td class="text-right pr-3">
                                <a href="{{ route('repairs.show', $job->id) }}" class="btn btn-sm btn-outline-primary font-weight-bold px-3">
                                    Manage <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fas fa-clipboard-check fa-3x text-gray-300 mb-3 d-block"></i>
                                <h5>No service jobs found matching this criteria</h5>
                                <p class="small">Try clearing search filters or create a new repair job.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($jobs->hasPages())
            <div class="p-3 border-top d-flex justify-content-end">
                {{ $jobs->links() }}
            </div>
            @endif
        </div>
    </div>
    @endif

</div>
@endsection

@push('js')
<script>
    // Complete task quickly via AJAX
    function completeTask(taskId, btn) {
        if (!confirm('Mark this task as completed?')) return;
        
        fetch(`/admin/repairs/tasks/${taskId}/complete`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const chip = btn.closest('.task-chip');
                if (chip) {
                    chip.style.opacity = '0';
                    setTimeout(() => chip.remove(), 200);
                }
            }
        })
        .catch(err => {
            console.error(err);
            alert('Failed to complete task.');
        });
    }

    // Barcode scanner listener
    let barcodeBuffer = '';
    let lastKeyTime = Date.now();
    window.addEventListener('keydown', function(e) {
        const currentTime = Date.now();
        if (currentTime - lastKeyTime > 100) {
            barcodeBuffer = '';
        }
        lastKeyTime = currentTime;

        if (e.key === 'Enter' && barcodeBuffer.length > 3) {
            const input = document.getElementById('globalSearchInput');
            if (input && document.activeElement !== input) {
                input.value = barcodeBuffer;
                document.getElementById('filterForm').submit();
            }
            barcodeBuffer = '';
        } else if (e.key.length === 1) {
            barcodeBuffer += e.key;
        }
    });

    @if($viewMode === 'board' && !empty($stage) && $stage !== 'all')
    // Smooth auto-scroll to the selected stage column
    document.addEventListener('DOMContentLoaded', function() {
        const stageMap = {
            '1': 'col-stage-1', 'received': 'col-stage-1',
            '2': 'col-stage-2', 'at_lab': 'col-stage-2',
            '3': 'col-stage-3', 'waiting_approval': 'col-stage-3',
            '4': 'col-stage-4', 'in_repair': 'col-stage-4',
            '5': 'col-stage-5', 'ready': 'col-stage-5',
            '6': 'col-stage-6', 'delivered': 'col-stage-6'
        };
        const targetColId = stageMap['{{ $stage }}'];
        if (targetColId) {
            setTimeout(function() {
                const targetCol = document.getElementById(targetColId);
                if (targetCol) {
                    targetCol.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                }
            }, 100);
        }
    });
    @endif
</script>
@endpush
