@extends('admin.layouts.master')
@section('page_title', 'Lead Management')
@section('breadcrumb_parent', 'Leads')
@section('breadcrumb_parent_url', url('admin/leads'))

@push('css')
  <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/kpi-cards.css') }}">
  <style>
    .temp-badge { display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:700; padding:3px 9px; border-radius:999px; white-space:nowrap; }
    .temp-badge.t-hot  { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
    .temp-badge.t-warm { background:#fffbeb; color:#d97706; border:1px solid #fde68a; }
    .temp-badge.t-cold { background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; }
    [data-theme="dark"] .temp-badge { background:transparent; }
  </style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-funnel-dollar" style="color: var(--accent-500); margin-right: 8px;"></i>Lead Management</h1>
            <p>Track, manage, and convert your leads into customers</p>
        </div>
        <div class="header-actions">
            @can('permission', 'lead_list.import')
                <a href="{{ asset('file/demo_lead.xlsx') }}" download class="btn btn-secondary btn-sm">
                    <i class="fas fa-file-download mr-1"></i>Sample File
                </a>
                <a href="#" data-toggle="modal" data-target="#importLeads" class="btn btn-secondary btn-sm">
                    <i class="fas fa-upload mr-1"></i>Import
                </a>
            @endcan
            @if (auth()->user()->isSuperAdmin())
                <a href="{{ url('admin/leads-export') }}" class="btn btn-secondary btn-sm">
                    <i class="fas fa-download mr-1"></i>Export
                </a>
            @endif
            @can('permission', 'deal_board.index')
                <a href="{{ url('admin/deals') }}" class="btn btn-secondary btn-sm" id="btn-deals-board">
                    <i class="fas fa-columns mr-1"></i>Deals Board
                </a>
            @endcan
            @if (auth()->user()->isSuperAdmin())
                <a href="{{ route('leads.merge') }}" class="btn btn-secondary btn-sm" id="btn-merge-duplicates">
                    <i class="fas fa-object-group mr-1"></i>Merge Duplicates
                </a>
            @endif
            @can('permission', 'add_lead.create')
                <a href="{{ route('leads.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus mr-1"></i>Add Lead
                </a>
            @endcan
        </div>
    </div>

    {{-- Stats Row --}}
    @php
        $statusCounts = [];
        $allLeadsForStats = \App\Models\Lead::selectRaw('lead_status, COUNT(*) as count')
            ->groupBy('lead_status')
            ->pluck('count', 'lead_status')
            ->toArray();
        $totalLeads = array_sum($allLeadsForStats);
        $stageCounts = [];
        foreach ($allLeadsForStats as $st => $n) {
            $k = \App\Models\Lead::LEGACY_STAGE[$st] ?? $st;
            $stageCounts[$k] = ($stageCounts[$k] ?? 0) + $n;
        }
        $tempCounts = \App\Models\Lead::selectRaw("COALESCE(lead_temperature, 'warm') as temp, COUNT(*) as count")
            ->groupBy('temp')
            ->pluck('count', 'temp')
            ->toArray();
        $todayLeads = \App\Models\Lead::whereDate('created_at', today())->count();
        $tempFilterUrl = fn ($t) => url('admin/leads') . '?' . http_build_query(array_merge(request()->except(['page', 'lead_temperature']), ['lead_temperature' => $t]));
    @endphp

    {{-- Lead category (Hot / Warm / Cold) --}}
    <div class="kpi-row crm-animate-in">
        @include('admin.partials.kpi-card', [
            'href' => url('admin/leads'), 'color' => 'indigo', 'icon' => 'fas fa-users', 'id' => 'kpi-leads-total',
            'label' => 'Total Leads', 'value' => number_format($totalLeads),
            'sub' => '<b>+' . $todayLeads . '</b> added today',
            'active' => !request()->filled('lead_temperature') && !request()->filled('lead_status'),
        ])
        @foreach (\App\Enums\LeadTemperature::cases() as $temp)
            @php $tc = (int) ($tempCounts[$temp->value] ?? 0); @endphp
            @include('admin.partials.kpi-card', [
                'href' => $tempFilterUrl($temp->value),
                'color' => ['hot' => 'rose', 'warm' => 'amber', 'cold' => 'sky'][$temp->value],
                'icon' => $temp->icon(), 'id' => 'kpi-leads-' . $temp->value,
                'label' => $temp->label() . 's', 'value' => number_format($tc),
                'sub' => '<b>' . ($totalLeads ? round($tc / $totalLeads * 100) : 0) . '%</b> of all leads',
                'active' => request('lead_temperature') === $temp->value,
            ])
        @endforeach
    </div>

    <div class="crm-stats-row crm-animate-in delay-1" id="lead-stage-cards">
        @foreach ($stageMap as $key => $st)
            <a class="crm-stat-card lead-stage-card {{ request('lead_status') === $key ? 'is-active' : '' }}" id="lead-stage-{{ $key }}"
               href="{{ url('admin/leads?lead_status=' . $key) }}" style="--stage: {{ $st['color'] }};">
                <div class="stat-icon-mini"><i class="{{ $st['icon'] }}"></i></div>
                <div class="stat-info">
                    <div class="stat-count">{{ number_format($stageCounts[$key] ?? 0) }}</div>
                    <div class="stat-name">{{ $st['label'] }}</div>
                </div>
            </a>
        @endforeach
    </div>

    {{-- Filter Panel --}}
    <div class="crm-filter-panel crm-animate-in delay-2">
        <button class="crm-filter-toggle {{ request()->hasAny(['phone_number','lead_uid','email','lead_status','lead_temperature','source_of_entry','vendor_id']) ? 'active' : '' }}" 
                onclick="this.classList.toggle('active'); document.getElementById('filterBody').style.display = this.classList.contains('active') ? 'block' : 'none';">
            <i class="fas fa-sliders-h"></i>
            <span>Filters</span>
            <i class="fas fa-chevron-down" style="margin-left: auto;"></i>
        </button>
        <div id="filterBody" class="crm-filter-body" style="{{ request()->hasAny(['phone_number','lead_uid','email','lead_status','lead_temperature','source_of_entry','vendor_id']) ? '' : 'display:none;' }}">
            <form method="GET">
                <div class="filter-row">
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Phone</label>
                        <input type="text" name="phone_number" class="form-control" placeholder="Phone number"
                            value="{{ request('phone_number', '') }}">
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Lead ID</label>
                        <input type="text" name="lead_uid" class="form-control" placeholder="Lead ID"
                            value="{{ request('lead_uid', '') }}">
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Email</label>
                        <input type="text" name="email" class="form-control" placeholder="Email"
                            value="{{ request('email', '') }}">
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">From</label>
                        <input type="date" name="start_date" class="form-control"
                            value="{{ request('start_date', $start_date) }}">
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">To</label>
                        <input type="date" name="end_date" class="form-control"
                            value="{{ request('end_date', $end_date) }}">
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Stage</label>
                        <select class="form-control" name="lead_status" id="filter-stage">
                            <option value="">All Stages</option>
                            @foreach ($stageMap as $key => $st)
                                <option {{ request('lead_status') == $key ? 'selected':'' }} value="{{ $key }}">{{ $st['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Category</label>
                        <select class="form-control" name="lead_temperature">
                            <option value="">All Categories</option>
                            @foreach (\App\Enums\LeadTemperature::cases() as $temp)
                                <option {{ request('lead_temperature') == $temp->value ? 'selected':'' }} value="{{ $temp->value }}">{{ $temp->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Source</label>
                        <select class="form-control" name="source_of_entry">
                            <option value="">All Sources</option>
                            @foreach ($sourceOfEntries as $entry)
                                <option {{ request('source_of_entry') == $entry->value ? 'selected':'' }} value="{{ $entry->value }}">{{ $entry->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Branch</label>
                        <select class="form-control" name="vendor_id">
                            <option value="">All Branches</option>
                            @foreach ($vendors as $vendor)
                                <option {{ request('vendor_id') == $vendor->id ? 'selected':'' }} value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button class="btn btn-primary btn-sm" type="submit" title="Search">
                            <i class="fas fa-search mr-1"></i>Search
                        </button>
                        <a href="{{ url('admin/leads') }}" class="btn btn-secondary btn-sm" title="Clear">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @include('admin.alert_message')

    {{-- Data Table --}}
    <div class="crm-table-card crm-animate-in delay-3">
        <div class="crm-table-header">
            <div class="table-title">
                <i class="fas fa-list" style="color: var(--page-text-muted);"></i>
                All Leads
                <span class="table-count">{{ $leads->total() }}</span>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Lead</th>
                        <th>Phone</th>
                        <th>Inquiry For</th>
                        <th>Type</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th>Category</th>
                        <th>Created</th>
                        <th style="width: 110px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($leads as $key => $lead)
                        <tr>
                            <td style="color: var(--page-text-muted); font-size: 12px;">{{ $leads->firstItem() + $key }}</td>
                            <td>
                                <div class="crm-name-cell">
                                    <div class="crm-avatar">{{ strtoupper(substr($lead->full_name, 0, 1)) }}</div>
                                    <div class="crm-name-text">
                                        <span class="name">{{ $lead->full_name }}</span>
                                        <span class="uid">{{ $lead->lead_uid ?? '—' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $lead->phone_number }}</td>
                            <td>
                                <span style="font-size: 12px; color: var(--page-text-secondary);">
                                    {{ Str::limit($lead->inquiry_for_label, 30) ?: '—' }}
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 12px;">{{ $lead->lead_type_label ?? '—' }}</span>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--page-text-secondary);">
                                    {{ optional($lead->vendor)->name ?? '—' }}
                                </span>
                            </td>
                            <td>
                                <span class="crm-status-badge lead-stage-badge" style="--stage: {{ $lead->status_color }};">
                                    <span class="dot"></span>
                                    {{ $lead->status_label }}
                                </span>
                            </td>
                            <td>
                                @php $lt = \App\Enums\LeadTemperature::fromValueOrDefault($lead->lead_temperature); @endphp
                                <span class="temp-badge t-{{ $lt->value }}"><i class="{{ $lt->icon() }}"></i>{{ ucfirst($lt->value) }}</span>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--page-text-muted);">
                                    {{ $lead->created_at->format('d M Y') }}
                                </span>
                            </td>
                            <td>
                                <div class="crm-actions">
                                    @can('permission', 'lead_list.index')
                                        <a href="{{ route('leads.show', $lead->id) }}" class="action-btn btn-view" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    @endcan
                                    @if ($lead->lead_status != 'won')
                                        @can('permission', 'lead_list.edit')
                                            <a href="{{ route('leads.edit', $lead->id) }}" class="action-btn btn-edit" title="Edit">
                                                <i class="fas fa-pen"></i>
                                            </a>
                                        @endcan
                                        @if (auth()->user()->isSuperAdmin())
                                            <form action="{{ route('leads.destroy', $lead->id) }}" method="POST" style="display:inline;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="action-btn btn-delete" title="Delete"
                                                    onclick="confirmDelete(event)">
                                                    <i class="far fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);">
                                <i class="fas fa-inbox" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>
                                No leads found. Try adjusting your filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($leads->hasPages())
            <div class="crm-table-footer">
                <span>Showing {{ $leads->firstItem() }}–{{ $leads->lastItem() }} of {{ $leads->total() }} leads</span>
                {{ $leads->appends(request()->query())->links() }}
            </div>
        @endif
    </div>

</div>

{{-- Import Modal --}}
<div class="modal fade crm-modal" id="importLeads" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-import mr-2" style="color: var(--accent-500);"></i>Import Leads</h5>
                <button type="button" class="close" data-dismiss="modal" style="color: var(--page-text-muted);">&times;</button>
            </div>
            <form action="{{ url('admin/leads-import') }}" enctype="multipart/form-data" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="import-info-box">
                        <strong><i class="fas fa-info-circle mr-1"></i>File Format Guide</strong>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 12px;">
                            <div>
                                <strong style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--accent-500);">Required Columns</strong>
                                <ul>
                                    <li><code>full_name</code> — Lead's full name</li>
                                    <li><code>phone_number</code> — Phone (unique)</li>
                                    <li><code>notes</code> — Notes or remarks</li>
                                </ul>
                            </div>
                            <div>
                                <strong style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--success-500);">Optional Columns</strong>
                                <ul>
                                    <li><code>email</code>, <code>age</code>, <code>gender</code></li>
                                    <li><code>contact_person_name</code>, <code>relation</code></li>
                                    <li><code>inquiry_for</code>, <code>lead_status</code></li>
                                    <li><code>source_of_entry</code>, <code>lead_type</code></li>
                                </ul>
                            </div>
                        </div>
                        <hr>
                        <small>Supported: CSV, XLSX, XLS · Max 10MB</small>
                    </div>

                    <div class="form-group">
                        <label>Excel File <span style="color: var(--danger-500);">*</span></label>
                        <input type="file" name="file" class="form-control" accept=".csv,.xlsx,.xls" required
                            style="padding: 10px !important;">
                    </div>

                    @error('file')
                        <div style="background: var(--danger-50); border: 1px solid rgba(239,68,68,0.2); border-radius: 8px; padding: 12px; color: var(--danger-600); font-size: 13px;">
                            {{ $message }}
                        </div>
                    @enderror
                    @error('rows')
                        <div style="background: var(--danger-50); border: 1px solid rgba(239,68,68,0.2); border-radius: 8px; padding: 12px; color: var(--danger-600); font-size: 13px; margin-top: 8px;">
                            <strong>Validation Errors:</strong>
                            <ul style="margin: 4px 0 0 0; padding-left: 18px;">
                                @foreach($errors->get('rows') as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-upload mr-1"></i>Upload & Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
  <script src="{{ asset('assets/plugins/select2/js/select2.min.js') }}"></script>
@endpush

