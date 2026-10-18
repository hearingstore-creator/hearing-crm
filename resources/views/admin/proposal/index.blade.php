@extends('admin.layouts.master')
@section('page_title', 'Proposals & Quotations')
@section('breadcrumb_parent', 'Sales')
@section('breadcrumb_parent_url', url('admin/proposal'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<style>
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    .kpi-card {
        background: var(--card-bg, #ffffff);
        border: 1px solid var(--card-border, #e2e8f0);
        border-radius: 12px;
        padding: 16px 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .kpi-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }
    .kpi-value {
        font-size: 20px;
        font-weight: 700;
        color: var(--page-text, #1e293b);
        line-height: 1.2;
    }
    .kpi-label {
        font-size: 12px;
        color: var(--page-text-muted, #64748b);
        font-weight: 500;
        margin-top: 2px;
    }
    .quote-badge {
        font-family: monospace;
        font-weight: 700;
        color: #1e40af;
        background: #eff6ff;
        padding: 3px 8px;
        border-radius: 6px;
        border: 1px solid #bfdbfe;
        font-size: 12px;
    }
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 9999px;
        font-size: 11px;
        font-weight: 600;
    }
    .status-pill .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
    }
    .filter-tabs {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding-bottom: 8px;
        margin-bottom: 16px;
        border-bottom: 1px solid var(--card-border, #e2e8f0);
    }
    .filter-tab {
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 500;
        color: var(--page-text-muted, #64748b);
        text-decoration: none;
        background: #f1f5f9;
        transition: all 0.2s;
        white-space: nowrap;
    }
    .filter-tab:hover {
        background: #e2e8f0;
        color: var(--page-text, #1e293b);
    }
    .filter-tab.active {
        background: var(--primary-color, #2563eb);
        color: #ffffff;
        font-weight: 600;
    }
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-file-invoice" style="color: var(--accent-500, #3b82f6); margin-right: 8px;"></i>Proposals & Quotations</h1>
            <p>Generate, track, and convert clinical hearing care treatment quotations</p>
        </div>
        <div class="header-actions">
            @can('permission', 'proposals.create')
                <a href="{{ route('proposal.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus mr-1"></i>Create Quotation
                </a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')

    {{-- KPI Cards --}}
    <div class="kpi-grid crm-animate-in delay-1">
        <div class="kpi-card">
            <div class="kpi-icon" style="background: #eff6ff; color: #2563eb;">
                <i class="fas fa-file-contract"></i>
            </div>
            <div>
                <div class="kpi-value">{{ number_format($totalCount) }}</div>
                <div class="kpi-label">Total Proposals (৳{{ number_format($totalAmount, 0) }})</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background: #f0fdf4; color: #16a34a;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <div class="kpi-value">{{ number_format($acceptedCount) }}</div>
                <div class="kpi-label">Accepted (৳{{ number_format($acceptedAmount, 0) }})</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background: #fffbeb; color: #d97706;">
                <i class="fas fa-clock"></i>
            </div>
            <div>
                <div class="kpi-value">{{ number_format($pendingCount) }}</div>
                <div class="kpi-label">Pending / Sent (৳{{ number_format($pendingAmount, 0) }})</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background: #faf5ff; color: #9333ea;">
                <i class="fas fa-chart-line"></i>
            </div>
            <div>
                <div class="kpi-value">{{ $conversionRate }}%</div>
                <div class="kpi-label">Conversion Rate ({{ $convertedCount }} Invoiced)</div>
            </div>
        </div>
    </div>

    {{-- Main Table Card --}}
    <div class="crm-table-card crm-animate-in delay-2">
        <div class="crm-table-header" style="flex-wrap: wrap; gap: 12px;">
            {{-- Status Tabs --}}
            <div class="filter-tabs" style="margin-bottom: 0; border-bottom: none;">
                <a href="{{ route('proposal.index') }}" class="filter-tab {{ $currentStatus === 'all' ? 'active' : '' }}">
                    All Proposals ({{ $totalCount }})
                </a>
                <a href="{{ route('proposal.index', ['status' => \App\Enums\ProposalStatus::DRAFT->value]) }}" class="filter-tab {{ $currentStatus === \App\Enums\ProposalStatus::DRAFT->value ? 'active' : '' }}">
                    Drafts
                </a>
                <a href="{{ route('proposal.index', ['status' => \App\Enums\ProposalStatus::SENT->value]) }}" class="filter-tab {{ $currentStatus === \App\Enums\ProposalStatus::SENT->value ? 'active' : '' }}">
                    Sent
                </a>
                <a href="{{ route('proposal.index', ['status' => \App\Enums\ProposalStatus::ACCEPTED->value]) }}" class="filter-tab {{ $currentStatus === \App\Enums\ProposalStatus::ACCEPTED->value ? 'active' : '' }}">
                    Accepted
                </a>
                <a href="{{ route('proposal.index', ['status' => \App\Enums\ProposalStatus::CONVERTED->value]) }}" class="filter-tab {{ $currentStatus === \App\Enums\ProposalStatus::CONVERTED->value ? 'active' : '' }}">
                    Converted
                </a>
                <a href="{{ route('proposal.index', ['status' => \App\Enums\ProposalStatus::DECLINED->value]) }}" class="filter-tab {{ $currentStatus === \App\Enums\ProposalStatus::DECLINED->value ? 'active' : '' }}">
                    Declined
                </a>
            </div>

            {{-- Search & Date Filters --}}
            <form action="{{ route('proposal.index') }}" method="GET" class="d-flex align-items-center" style="gap: 8px; margin-left: auto;">
                @if($currentStatus !== 'all')
                    <input type="hidden" name="status" value="{{ $currentStatus }}">
                @endif
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Search quote #, patient, phone..." value="{{ request('q') }}" style="width: 220px;">
                <input type="date" name="from_date" class="form-control form-control-sm" value="{{ request('from_date') }}" title="From Date">
                <input type="date" name="to_date" class="form-control form-control-sm" value="{{ request('to_date') }}" title="To Date">
                <button type="submit" class="btn btn-secondary btn-sm" title="Search"><i class="fas fa-search"></i></button>
                @if(request()->anyFilled(['q', 'from_date', 'to_date', 'status']))
                    <a href="{{ route('proposal.index') }}" class="btn btn-outline-secondary btn-sm" title="Reset Filters"><i class="fas fa-times"></i></a>
                @endif
            </form>
        </div>

        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th style="width: 140px;">Quotation #</th>
                        <th>Title / Treatment Plan</th>
                        <th>Patient / Recipient</th>
                        <th>Doctor / Branch</th>
                        <th style="width: 110px;">Quote Date</th>
                        <th style="width: 110px;">Valid Until</th>
                        <th style="width: 130px; text-align: right;">Amount</th>
                        <th style="width: 120px; text-align: center;">Status</th>
                        <th style="width: 140px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($proposals as $p)
                    <tr>
                        <td>
                            <a href="{{ route('proposal.show', $p->id) }}" class="quote-badge" title="View Quotation">
                                {{ $p->proposal_no ?: '#' . str_pad($p->id, 5, '0', STR_PAD_LEFT) }}
                            </a>
                        </td>
                        <td>
                            <div style="font-weight: 600; color: #1e293b;">{{ $p->title }}</div>
                            @if($p->items->isNotEmpty())
                                <small class="text-muted" style="font-size: 11px;">
                                    <i class="fas fa-box-open mr-1"></i>{{ $p->items->count() }} line items: 
                                    {{ Str::limit($p->items->pluck('item_name')->join(', '), 45) }}
                                </small>
                            @endif
                        </td>
                        <td>
                            <div style="font-weight: 500;">
                                @if($p->lead)
                                    <a href="{{ route('leads.show', $p->lead_id) }}" style="color: #2563eb; text-decoration: none;">
                                        <i class="fas fa-user mr-1"></i>{{ $p->recipient_display_name }}
                                    </a>
                                    <span class="badge badge-light" style="font-size: 10px;">Lead</span>
                                @elseif($p->customer)
                                    <span style="color: #16a34a;"><i class="fas fa-user-check mr-1"></i>{{ $p->recipient_display_name }}</span>
                                    <span class="badge badge-light" style="font-size: 10px;">Patient</span>
                                @else
                                    <span>{{ $p->recipient_display_name }}</span>
                                    <span class="badge badge-light" style="font-size: 10px;">Direct</span>
                                @endif
                            </div>
                            <div style="font-size: 11px; color: #64748b;">
                                <i class="fas fa-phone mr-1"></i>{{ $p->recipient_phone_display }}
                            </div>
                        </td>
                        <td>
                            <div style="font-size: 12px;">
                                @if($p->doctor)
                                    <span title="Referring Doctor" style="color: #0f766e;"><i class="fas fa-user-md mr-1"></i>{{ $p->doctor->name }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </div>
                            @if($p->vendor)
                                <small class="text-muted" style="font-size: 11px;"><i class="fas fa-clinic-medical mr-1"></i>{{ $p->vendor->name }}</small>
                            @endif
                        </td>
                        <td style="font-size: 12px;">
                            {{ $p->proposal_date ? $p->proposal_date->format('d M Y') : '—' }}
                        </td>
                        <td style="font-size: 12px;">
                            @if($p->valid_until)
                                <span class="{{ $p->valid_until->isPast() && $p->status !== \App\Enums\ProposalStatus::CONVERTED->value ? 'text-danger font-weight-bold' : '' }}">
                                    {{ $p->valid_until->format('d M Y') }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td style="text-align: right;">
                            <div style="font-weight: 700; font-size: 13px; color: #0f172a;">
                                ৳{{ number_format($p->grand_total ?: $p->total_amount, 2) }}
                            </div>
                            @if($p->discount_amount > 0)
                                <small class="text-success" style="font-size: 10px;">
                                    Disc: -৳{{ number_format($p->discount_amount, 2) }}
                                </small>
                            @endif
                        </td>
                        <td style="text-align: center;">
                            @php
                                $statusEnum = \App\Enums\ProposalStatus::tryFrom($p->status) ?: \App\Enums\ProposalStatus::DRAFT;
                            @endphp
                            <span class="status-pill" style="background: {{ $statusEnum->color() }}18; color: {{ $statusEnum->color() }};">
                                <span class="dot" style="background: {{ $statusEnum->color() }};"></span>
                                {{ $p->status }}
                            </span>
                        </td>
                        <td>
                            <div class="crm-actions" style="justify-content: center; gap: 4px;">
                                {{-- View / Print --}}
                                <a href="{{ route('proposal.show', $p->id) }}" class="action-btn btn-view" title="View Official Quotation">
                                    <i class="fas fa-eye"></i>
                                </a>

                                {{-- Edit --}}
                                @can('permission', 'proposals.edit')
                                    <a href="{{ route('proposal.edit', $p->id) }}" class="action-btn btn-edit" title="Edit Quotation">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                @endcan

                                {{-- 1-Click Convert to Sale --}}
                                @if($p->status !== \App\Enums\ProposalStatus::CONVERTED->value)
                                    <form action="{{ route('proposal.convert', $p->id) }}" method="POST" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="action-btn" style="background: #f0fdf4; color: #16a34a; border-color: #bbf7d0;" title="Convert to Invoice" onclick="return confirm('Convert Quotation {{ $p->proposal_no }} into an active Sale Invoice?')">
                                            <i class="fas fa-file-invoice-dollar"></i>
                                        </button>
                                    </form>
                                @else
                                    <span class="badge badge-success" title="Invoiced" style="font-size: 10px;"><i class="fas fa-check"></i></span>
                                @endif

                                {{-- Delete --}}
                                @can('permission', 'proposals.delete')
                                    <form action="{{ route('proposal.destroy', $p->id) }}" method="POST" style="display: inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="action-btn btn-delete" title="Delete" onclick="confirmDelete(event)">
                                            <i class="far fa-trash-alt"></i>
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 48px 24px; color: #94a3b8;">
                            <i class="fas fa-file-signature" style="font-size: 36px; margin-bottom: 12px; display: block; opacity: 0.5;"></i>
                            <div style="font-size: 15px; font-weight: 600; color: #64748b;">No proposals or quotations found</div>
                            <p style="font-size: 13px; margin-top: 4px;">Click "+ Create Quotation" above to prepare a treatment plan proposal.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3">
            {{ $proposals->links() }}
        </div>
    </div>
</div>
@endsection
