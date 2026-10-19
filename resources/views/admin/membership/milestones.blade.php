@extends('admin.layouts.master')
@section('page_title', 'Milestones')
@section('breadcrumb_parent', 'Membership & Rewards')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<style>
    .milestone-card {
        background: var(--card-bg, #fff);
        border: 1px solid var(--card-border, #e2e8f0);
        border-radius: 14px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .badge-spend {
        background: #ecfdf5;
        color: #065f46;
        border: 1px solid #a7f3d0;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 6px;
    }
    .badge-count {
        background: #eff6ff;
        color: #1e40af;
        border: 1px solid #bfdbfe;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 6px;
    }
    .badge-bonus {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 20px;
    }
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div class="page-title-group">
            <h1><i class="fas fa-flag-checkered" style="color: var(--accent-500, #6366f1); margin-right: 8px;"></i>Membership Milestones</h1>
            <p>One-time bonus point milestones automatically awarded when patients cross spend or purchase thresholds</p>
        </div>
        @if(\App\Classes\Helper::hasPermission('milestones.create') && (!auth()->user()->branchId() || auth()->user()->isSuperAdmin()))
        <div>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addMilestoneModal">
                <i class="fas fa-plus mr-1"></i> Add Milestone
            </button>
        </div>
        @endif
    </div>

    @include('admin.alert_message')

    @if ($errors->any())
    <div class="alert alert-danger crm-animate-in" style="border-radius: 10px;">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Milestones Table Card --}}
    <div class="milestone-card crm-animate-in delay-1">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead style="background: #f8fafc; font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em;">
                    <tr>
                        <th style="padding: 16px 20px;">Name</th>
                        <th style="padding: 16px 20px;">Type</th>
                        <th style="padding: 16px 20px;">Threshold</th>
                        <th style="padding: 16px 20px;">Bonus Points</th>
                        <th style="padding: 16px 20px;">Awards Granted</th>
                        <th style="padding: 16px 20px;">Status</th>
                        <th style="padding: 16px 20px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($milestones as $milestone)
                    <tr>
                        <td style="padding: 16px 20px; font-weight: 600;">
                            {{ $milestone->name }}
                        </td>
                        <td style="padding: 16px 20px;">
                            @if ($milestone->type === 'lifetime_spend')
                                <span class="badge-spend"><i class="fas fa-money-bill-wave mr-1"></i> Lifetime Spend</span>
                            @else
                                <span class="badge-count"><i class="fas fa-shopping-bag mr-1"></i> Purchase Count</span>
                            @endif
                        </td>
                        <td style="padding: 16px 20px; font-weight: 600;">
                            @if ($milestone->type === 'lifetime_spend')
                                ৳{{ number_format($milestone->threshold, 2) }}
                            @else
                                {{ number_format((int)$milestone->threshold) }} purchases
                            @endif
                        </td>
                        <td style="padding: 16px 20px;">
                            <span class="badge-bonus"><i class="fas fa-coins mr-1"></i> +{{ number_format($milestone->bonus_points) }} pts</span>
                        </td>
                        <td style="padding: 16px 20px;">
                            <span class="badge badge-light" style="font-size: 13px; font-weight: 600; padding: 4px 10px; border-radius: 6px; border: 1px solid #e2e8f0;">
                                <i class="fas fa-users mr-1 text-muted"></i> {{ $milestone->awards_count }}
                            </span>
                        </td>
                        <td style="padding: 16px 20px;">
                            @if ($milestone->is_active)
                                <span class="badge badge-success" style="font-size: 12px; padding: 4px 8px;"><i class="fas fa-check-circle mr-1"></i> Active</span>
                            @else
                                <span class="badge badge-secondary" style="font-size: 12px; padding: 4px 8px;"><i class="fas fa-pause-circle mr-1"></i> Inactive</span>
                            @endif
                        </td>
                        <td style="padding: 16px 20px; text-align: right;">
                            <div class="d-inline-flex gap-2">
                                @if(\App\Classes\Helper::hasPermission('milestones.edit') && (!auth()->user()->branchId() || auth()->user()->isSuperAdmin()))
                                <form action="{{ route('membership.milestones.toggle', $milestone->id) }}" method="POST" style="display: inline-block; margin-right: 4px;">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $milestone->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" title="{{ $milestone->is_active ? 'Disable Milestone' : 'Enable Milestone' }}">
                                        <i class="fas {{ $milestone->is_active ? 'fa-pause' : 'fa-play' }}"></i>
                                    </button>
                                </form>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#editMilestoneModal{{ $milestone->id }}" title="Edit Milestone" style="margin-right: 4px;">
                                    <i class="fas fa-edit"></i>
                                </button>
                                @endif

                                @if(\App\Classes\Helper::hasPermission('milestones.delete') && (!auth()->user()->branchId() || auth()->user()->isSuperAdmin()))
                                <form action="{{ route('membership.milestones.destroy', $milestone->id) }}" method="POST" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this milestone? Existing awarded points will stay intact.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Milestone">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fas fa-flag-checkered fa-3x mb-3 text-secondary" style="opacity: 0.4;"></i>
                            <p class="mb-0">No milestones configured yet. Click "Add Milestone" above to create one.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- Add Milestone Modal --}}
@if(\App\Classes\Helper::hasPermission('milestones.create') && (!auth()->user()->branchId() || auth()->user()->isSuperAdmin()))
<div class="modal fade" id="addMilestoneModal" tabindex="-1" role="dialog" aria-labelledby="addMilestoneModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 14px; overflow: hidden; border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
            <form action="{{ route('membership.milestones.store') }}" method="POST">
                @csrf
                <div class="modal-header" style="background: linear-gradient(135deg, #1e293b, #0f172a); color: #fff; padding: 18px 24px;">
                    <h5 class="modal-title" id="addMilestoneModalLabel" style="font-weight: 700;">
                        <i class="fas fa-flag-checkered mr-2" style="color: #6366f1;"></i> Add New Milestone
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" style="padding: 24px;">
                    <div class="form-group mb-3">
                        <label class="form-label font-weight-bold">Milestone Name <i class="text-danger">*</i></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. 50k Spend Club, 5th Purchase Reward" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label font-weight-bold">Milestone Type <i class="text-danger">*</i></label>
                        <select name="type" class="form-control" required>
                            <option value="lifetime_spend">Lifetime Spend (৳ total across all sales)</option>
                            <option value="purchase_count">Purchase Count (Nth sale)</option>
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label font-weight-bold">Threshold <i class="text-danger">*</i></label>
                        <input type="number" step="0.01" min="0.01" name="threshold" class="form-control" placeholder="e.g. 50000 for spend or 5 for purchase count" required>
                        <small class="text-muted">For lifetime spend, enter amount in Taka. For purchase count, enter number of completed sales.</small>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label font-weight-bold">Bonus Points Awarded <i class="text-danger">*</i></label>
                        <input type="number" step="1" min="1" max="1000000" name="bonus_points" class="form-control" placeholder="e.g. 500" required>
                        <small class="text-muted">Whole points between 1 and 1,000,000 awarded once per patient.</small>
                    </div>

                    <div class="form-group form-check mb-0">
                        <input type="checkbox" name="is_active" class="form-check-input" id="add_is_active" value="1" checked>
                        <label class="form-check-label font-weight-bold" for="add_is_active">Active (available to award immediately)</label>
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8fafc; padding: 14px 24px;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save mr-1"></i> Create Milestone</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Edit Milestone Modals --}}
@if(\App\Classes\Helper::hasPermission('milestones.edit') && (!auth()->user()->branchId() || auth()->user()->isSuperAdmin()))
@foreach ($milestones as $milestone)
<div class="modal fade" id="editMilestoneModal{{ $milestone->id }}" tabindex="-1" role="dialog" aria-labelledby="editMilestoneModalLabel{{ $milestone->id }}" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 14px; overflow: hidden; border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
            <form action="{{ route('membership.milestones.update', $milestone->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header" style="background: linear-gradient(135deg, #1e293b, #0f172a); color: #fff; padding: 18px 24px;">
                    <h5 class="modal-title" id="editMilestoneModalLabel{{ $milestone->id }}" style="font-weight: 700;">
                        <i class="fas fa-edit mr-2" style="color: #6366f1;"></i> Edit Milestone: {{ $milestone->name }}
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" style="padding: 24px;">
                    <div class="form-group mb-3">
                        <label class="form-label font-weight-bold">Milestone Name <i class="text-danger">*</i></label>
                        <input type="text" name="name" class="form-control" value="{{ $milestone->name }}" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label font-weight-bold">Milestone Type <i class="text-danger">*</i></label>
                        <select name="type" class="form-control" required>
                            <option value="lifetime_spend" {{ $milestone->type === 'lifetime_spend' ? 'selected' : '' }}>Lifetime Spend (৳ total across all sales)</option>
                            <option value="purchase_count" {{ $milestone->type === 'purchase_count' ? 'selected' : '' }}>Purchase Count (Nth sale)</option>
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label font-weight-bold">Threshold <i class="text-danger">*</i></label>
                        <input type="number" step="0.01" min="0.01" name="threshold" class="form-control" value="{{ $milestone->threshold }}" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label font-weight-bold">Bonus Points Awarded <i class="text-danger">*</i></label>
                        <input type="number" step="1" min="1" max="1000000" name="bonus_points" class="form-control" value="{{ $milestone->bonus_points }}" required>
                    </div>

                    <div class="form-group form-check mb-0">
                        <input type="checkbox" name="is_active" class="form-check-input" id="edit_is_active{{ $milestone->id }}" value="1" {{ $milestone->is_active ? 'checked' : '' }}>
                        <label class="form-check-label font-weight-bold" for="edit_is_active{{ $milestone->id }}">Active</label>
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8fafc; padding: 14px 24px;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save mr-1"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endif

@endsection
