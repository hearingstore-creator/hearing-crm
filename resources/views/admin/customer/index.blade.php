@extends('admin.layouts.master')
@section('page_title', 'Customer Management')
@section('breadcrumb_parent', 'Customers')
@section('breadcrumb_parent_url', url('admin/customers'))

@push('css')
  <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/kpi-cards.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-users" style="color: var(--accent-500); margin-right: 8px;"></i>Customer Management</h1>
            <p>View and manage your customer database</p>
        </div>
        <div class="header-actions">
            @can('permission', 'customer_list.import')
                <a href="{{ asset('file/demo_customer.xlsx') }}" download class="btn btn-secondary btn-sm">
                    <i class="fas fa-file-download mr-1"></i>Sample File
                </a>
                <a href="#" data-toggle="modal" data-target="#importCustomers" class="btn btn-secondary btn-sm">
                    <i class="fas fa-upload mr-1"></i>Import
                </a>
            @endcan
        </div>
    </div>

    {{-- Summary Cards --}}
    @php
        $cs = $customerStats;
        $tierColors = ['Bronze' => 'orange', 'Silver' => 'slate', 'Gold' => 'amber', 'Platinum' => 'violet', 'Diamond' => 'sky'];
        $tierIcons = ['Bronze' => 'fas fa-medal', 'Silver' => 'fas fa-medal', 'Gold' => 'fas fa-crown', 'Platinum' => 'fas fa-gem', 'Diamond' => 'far fa-gem'];
        $noFilter = !request()->filled('membership_tier_id') && !request()->boolean('frequent');
    @endphp
    <div class="kpi-row crm-animate-in">
        @include('admin.partials.kpi-card', [
            'href' => url('admin/customers'), 'color' => 'indigo', 'icon' => 'fas fa-users', 'id' => 'kpi-cust-total',
            'label' => 'Total Customers', 'value' => number_format($cs['total']),
            'sub' => '<b>+' . $cs['new_month'] . '</b> joined this month', 'active' => $noFilter,
        ])
        @include('admin.partials.kpi-card', [
            'href' => url('admin/customers?frequent=1'), 'color' => 'green', 'icon' => 'fas fa-redo-alt', 'id' => 'kpi-cust-frequent',
            'label' => 'Frequent Customers', 'value' => number_format($cs['frequent']),
            'sub' => '3+ purchases in last 12 months', 'active' => request()->boolean('frequent'),
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'teal', 'icon' => 'far fa-id-card', 'id' => 'kpi-cust-members',
            'label' => 'Members (with tier)', 'value' => number_format($cs['total'] - $cs['no_tier']),
            'sub' => '<a href="' . url('admin/customers?membership_tier_id=none') . '" style="color:inherit;text-decoration:underline;"><b>' . $cs['no_tier'] . '</b> without a tier</a>',
            'active' => request('membership_tier_id') === 'none',
        ])
    </div>
    @if($cs['tiers']->isNotEmpty())
        <div class="kpi-row-title"><i class="fas fa-layer-group"></i>Membership tiers</div>
        <div class="kpi-row kpi-tight crm-animate-in">
            @foreach($cs['tiers'] as $tier)
                @include('admin.partials.kpi-card', [
                    'href' => url('admin/customers?membership_tier_id=' . $tier['id']), 'small' => true,
                    'color' => $tierColors[$tier['name']] ?? 'teal', 'icon' => $tierIcons[$tier['name']] ?? 'fas fa-award',
                    'id' => 'kpi-cust-tier-' . $tier['id'],
                    'label' => $tier['name'], 'value' => number_format($tier['count']),
                    'sub' => '<b>' . ($cs['total'] ? round($tier['count'] / $cs['total'] * 100) : 0) . '%</b> of customers',
                    'active' => (string) request('membership_tier_id') === (string) $tier['id'],
                ])
            @endforeach
        </div>
    @endif

    {{-- Filter Panel --}}
    <div class="crm-filter-panel crm-animate-in delay-1">
        <button class="crm-filter-toggle {{ request()->hasAny(['phone_number','lead_uid','email','membership_tier_id']) ? 'active' : '' }}" 
                onclick="this.classList.toggle('active'); document.getElementById('filterBody').style.display = this.classList.contains('active') ? 'block' : 'none';">
            <i class="fas fa-sliders-h"></i>
            <span>Filters</span>
            <i class="fas fa-chevron-down" style="margin-left: auto;"></i>
        </button>
        <div id="filterBody" class="crm-filter-body" style="{{ request()->hasAny(['name','phone_number','lead_uid','email','membership_tier_id','vendor_id','purchase_period']) ? '' : 'display:none;' }}">
            <form method="GET">
                <div class="filter-row">
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Name</label>
                        <input type="text" name="name" class="form-control" placeholder="Customer name"
                            value="{{ request('name', '') }}">
                    </div>
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
                    @if(isset($branches) && count($branches) > 1)
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Branch</label>
                        <select name="vendor_id" class="form-control">
                            <option value="">All Branches</option>
                            @foreach($branches as $id => $name)
                                <option value="{{ $id }}" {{ (string) request('vendor_id') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Purchase Activity</label>
                        <select name="purchase_period" class="form-control">
                            <option value="">All Customers</option>
                            <option value="1" {{ request('purchase_period') === '1' ? 'selected' : '' }}>Bought in last 1 month</option>
                            <option value="3" {{ request('purchase_period') === '3' ? 'selected' : '' }}>Bought in last 3 months</option>
                            <option value="6" {{ request('purchase_period') === '6' ? 'selected' : '' }}>Bought in last 6 months</option>
                            <option value="12" {{ request('purchase_period') === '12' ? 'selected' : '' }}>Bought in last 12 months</option>
                            <option value="never" {{ request('purchase_period') === 'never' ? 'selected' : '' }}>Never Purchased</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Membership</label>
                        <select name="membership_tier_id" class="form-control">
                            <option value="">All Tiers</option>
                            <option value="none" {{ request('membership_tier_id') === 'none' ? 'selected' : '' }}>No Tier</option>
                            @foreach($customerStats['tiers'] as $tier)
                                <option value="{{ $tier['id'] }}" {{ (string) request('membership_tier_id') === (string) $tier['id'] ? 'selected' : '' }}>{{ $tier['name'] }}</option>
                            @endforeach
                        </select>
                        @if(request()->boolean('frequent'))<input type="hidden" name="frequent" value="1">@endif
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
                    <div class="filter-actions">
                        <button class="btn btn-primary btn-sm" type="submit" title="Search">
                            <i class="fas fa-search mr-1"></i>Search
                        </button>
                        <a href="{{ url('admin/customers') }}" class="btn btn-secondary btn-sm" title="Clear">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @include('admin.alert_message')

    {{-- Data Table --}}
    <div class="crm-table-card crm-animate-in delay-2">
        <div class="crm-table-header">
            <div class="table-title">
                <i class="fas fa-list" style="color: var(--page-text-muted);"></i>
                All Customers
                <span class="table-count">{{ count($customers) }}</span>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th>Branch</th>
                        <th style="text-align: right;">Total Spent</th>
                        <th>Last Purchase</th>
                        <th>Membership</th>
                        <th>Created</th>
                        <th style="width: 110px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $key => $customer)
                        <tr>
                            <td style="color: var(--page-text-muted); font-size: 12px;">{{ $key + 1 }}</td>
                            <td>
                                <div class="crm-name-cell">
                                    <div class="crm-avatar">{{ strtoupper(substr($customer->lead->full_name ?? 'C', 0, 1)) }}</div>
                                    <div class="crm-name-text">
                                        <a href="{{ route('customers.show', $customer->id) }}" style="color: var(--page-text); font-weight: 600; text-decoration: none;">
                                            {{ $customer->lead->full_name ?? '—' }}
                                        </a>
                                        <span class="uid">{{ $customer->customer_uid ?? '—' }} · {{ $customer->lead->lead_uid ?? '' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 500; font-size: 13px;">{{ $customer->lead->phone_number ?? '—' }}</div>
                                @if($customer->lead?->email)
                                    <div style="font-size: 11px; color: var(--page-text-muted);">{{ $customer->lead->email }}</div>
                                @endif
                            </td>
                            <td>
                                @if($customer->lead?->vendor)
                                    <span class="crm-status-badge badge-status-new" style="font-size: 11px;">
                                        <span class="dot"></span>{{ $customer->lead->vendor->name }}
                                    </span>
                                @else
                                    <span style="color: var(--page-text-muted); font-size: 12px;">Head Office</span>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                <div style="font-weight: 600; font-size: 13px; color: var(--accent-600);">
                                    ৳{{ number_format($customer->total_spent ?? 0, 2) }}
                                </div>
                                <div style="font-size: 11px; color: var(--page-text-muted);">
                                    {{ $customer->sale_masters_count ?? 0 }} {{ \Illuminate\Support\Str::plural('order', $customer->sale_masters_count ?? 0) }}
                                </div>
                            </td>
                            <td>
                                @if($customer->last_purchase_date)
                                    <div style="font-size: 12px; font-weight: 500;">
                                        {{ \Carbon\Carbon::parse($customer->last_purchase_date)->format('d M Y') }}
                                    </div>
                                    <div style="font-size: 11px; color: var(--page-text-muted);">
                                        {{ \Carbon\Carbon::parse($customer->last_purchase_date)->diffForHumans() }}
                                    </div>
                                @else
                                    <span style="color: var(--page-text-muted); font-size: 12px; font-style: italic;">No purchase</span>
                                @endif
                            </td>
                            <td>
                                @if($customer->membershipTier)
                                    <span class="crm-status-badge badge-status-won">
                                        <span class="dot"></span>
                                        {{ $customer->membershipTier->name }}
                                    </span>
                                @else
                                    <span style="font-size: 12px; color: var(--page-text-muted);">{{ $customer->membership_id ?? 'Standard' }}</span>
                                @endif
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--page-text-muted);">
                                    {{ $customer->created_at->format('d M Y') }}
                                </span>
                            </td>
                            <td>
                                <div class="crm-actions">
                                    @can('permission', 'customer_list.show')
                                        <a href="{{ route('customers.show', $customer->id) }}" class="action-btn btn-view" title="View Profile">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    @endcan
                                    @can('permission', 'customer_list.edit')
                                        <a href="{{ route('customers.edit', $customer->id) }}" class="action-btn btn-edit" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                    @endcan
                                    @if(auth()->user()->isSuperAdmin())
                                        <form action="{{ route('customers.destroy', $customer->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="action-btn btn-delete" title="Delete" onclick="return confirm('Are you sure you want to delete this customer?');">
                                                <i class="far fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);">
                                <i class="fas fa-inbox" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>
                                No customers found. Try adjusting your filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($customers, 'hasPages') && $customers->hasPages())
            <div class="crm-table-footer">
                {{ $customers->appends(request()->query())->links() }}
            </div>
        @endif
    </div>

</div>

{{-- Import Modal --}}
<div class="modal fade crm-modal" id="importCustomers" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-import mr-2" style="color: var(--accent-500);"></i>Import Customers</h5>
                <button type="button" class="close" data-dismiss="modal" style="color: var(--page-text-muted);">&times;</button>
            </div>
            <form action="{{ route('customers.import') }}" enctype="multipart/form-data" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="import-info-box">
                        <strong><i class="fas fa-info-circle mr-1"></i>File Format Guide</strong>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 12px;">
                            <div>
                                <strong style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--accent-500);">Required Columns</strong>
                                <ul>
                                    <li><code>full_name</code> — Customer's full name</li>
                                    <li><code>phone_number</code> — Phone (unique)</li>
                                    <li><code>age</code> — Customer age</li>
                                </ul>
                            </div>
                            <div>
                                <strong style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--success-500);">Optional Columns</strong>
                                <ul>
                                    <li><code>email</code>, <code>address</code>, <code>gender</code></li>
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
