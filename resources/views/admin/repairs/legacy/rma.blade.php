@extends('admin.layouts.master')
@section('page_title', 'Supplier RMA & Faulty Units')
@section('breadcrumb_parent', 'Repairs')
@section('breadcrumb_parent_url', route('repairs.index'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <style>
        .rma-kpi-card {
            border-radius: 8px;
            padding: 16px 20px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .rma-kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
    </style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1 class="mr-3 mb-0">
                <i class="fas fa-exchange-alt text-danger mr-2"></i>Supplier RMA &amp; Faulty Stock
            </h1>
            <p class="mt-1 mb-0">Track and return defective hearing aids replaced under warranty to suppliers for credit notes or replacements.</p>
        </div>
        <div class="header-actions d-flex align-items-center">
            <a href="{{ route('repairs.index') }}" class="btn btn-outline-primary btn-sm mr-2">
                <i class="fas fa-columns mr-1"></i>Repair Jobs Board
            </a>
            <form action="{{ route('repairs.rma.clear-settled') }}" method="POST" class="d-inline mr-2" onsubmit="return confirm('Clear and disperse all settled (Retained by Supplier / Credited / Scrapped) RMA records?');">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm" title="Clear and disperse settled RMA units">
                    <i class="fas fa-broom mr-1"></i>Clear Settled Records
                </button>
            </form>
            <a href="{{ route('repairs.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus-circle mr-1"></i>New Intake
            </a>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="row mb-4 crm-animate-in">
        <div class="col-md-3">
            <div class="rma-kpi-card">
                <div>
                    <div class="text-muted small font-weight-bold text-uppercase">Total Faulty Units</div>
                    <div class="font-weight-bold text-dark" style="font-size: 24px;">{{ $counts['total'] }}</div>
                </div>
                <div class="rma-kpi-icon bg-light text-dark">
                    <i class="fas fa-boxes"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="rma-kpi-card" style="border-left: 4px solid #f59e0b;">
                <div>
                    <div class="text-muted small font-weight-bold text-uppercase">Faulty in Clinic</div>
                    <div class="font-weight-bold text-warning" style="font-size: 24px;">{{ $counts['faulty_in_clinic'] }}</div>
                    <div class="text-muted" style="font-size: 11px;">Pending Dispatch</div>
                </div>
                <div class="rma-kpi-icon bg-warning text-white">
                    <i class="fas fa-clinic-medical"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="rma-kpi-card" style="border-left: 4px solid #3b82f6;">
                <div>
                    <div class="text-muted small font-weight-bold text-uppercase">With Supplier Lab</div>
                    <div class="font-weight-bold text-primary" style="font-size: 24px;">{{ $counts['with_supplier_lab'] }}</div>
                    <div class="text-muted" style="font-size: 11px;">Awaiting Credit/Return</div>
                </div>
                <div class="rma-kpi-icon bg-primary text-white">
                    <i class="fas fa-truck-loading"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="rma-kpi-card" style="border-left: 4px solid #10b981;">
                <div>
                    <div class="text-muted small font-weight-bold text-uppercase">Credited by Supplier</div>
                    <div class="font-weight-bold text-success" style="font-size: 24px;">{{ $counts['credited_by_supplier'] }}</div>
                    <div class="text-muted" style="font-size: 11px;">Claim Settled</div>
                </div>
                <div class="rma-kpi-icon bg-success text-white">
                    <i class="fas fa-check-double"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters & Search Card --}}
    <div class="card mb-4 border shadow-sm crm-animate-in">
        <div class="card-body py-3">
            <form action="{{ route('repairs.rma.index') }}" method="GET" class="form-row align-items-center">
                <div class="col-md-5 my-1">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        </div>
                        <input type="text" name="search" class="form-control" placeholder="Search by Serial No, Customer Name or Phone..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-3 my-1">
                    <select name="status" class="form-control">
                        <option value="">-- All RMA Statuses --</option>
                        <option value="faulty_in_clinic" {{ request('status') === 'faulty_in_clinic' ? 'selected' : '' }}>Faulty in Clinic (Pending Return)</option>
                        <option value="with_supplier_lab" {{ request('status') === 'with_supplier_lab' ? 'selected' : '' }}>With Supplier Lab</option>
                        <option value="credited_by_supplier" {{ request('status') === 'credited_by_supplier' ? 'selected' : '' }}>Credited by Supplier</option>
                        <option value="scrapped" {{ request('status') === 'scrapped' ? 'selected' : '' }}>Scrapped / Written Off</option>
                    </select>
                </div>
                <div class="col-md-4 my-1 d-flex">
                    <button type="submit" class="btn btn-primary btn-block mr-2"><i class="fas fa-filter mr-1"></i>Filter</button>
                    <a href="{{ route('repairs.rma.index') }}" class="btn btn-outline-secondary"><i class="fas fa-undo"></i></a>
                </div>
            </form>
        </div>
    </div>

    {{-- RMA Data Table --}}
    <div class="card border shadow-sm crm-animate-in">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted" style="font-size: 11px; text-transform: uppercase;">
                        <tr>
                            <th>Defective Serial No</th>
                            <th>Device / Model</th>
                            <th>Original Customer</th>
                            <th>Replacement Job</th>
                            <th>Warranty Expiry</th>
                            <th>RMA Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: 13px;">
                        @forelse($items as $row)
                            <tr>
                                <td>
                                    <div class="font-monospace font-weight-bold text-danger" style="font-size: 14px;">
                                        <i class="fas fa-barcode mr-1"></i>{{ $row->serial_no }}
                                    </div>
                                    @if($row->parent_serial_no)
                                        <div class="small text-muted">
                                            Parent S/N: <span class="font-monospace">{{ $row->parent_serial_no }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div class="font-weight-bold text-dark">{{ $row->product?->product_name ?? 'Hearing Aid Unit' }}</div>
                                    <div class="small text-muted">{{ $row->product?->brand?->brand_name ?? '' }}</div>
                                </td>
                                <td>
                                    <div class="font-weight-bold text-dark">{{ $row->customer?->lead?->full_name ?? 'N/A' }}</div>
                                    <div class="small text-muted">{{ $row->customer?->lead?->phone_number ?? '' }}</div>
                                </td>
                                <td>
                                    @if($row->replacementJob)
                                        <a href="{{ route('repairs.show', $row->replacementJob->id) }}" class="font-weight-bold text-primary">
                                            #{{ $row->replacementJob->job_no }}
                                        </a>
                                        <div class="small text-muted">{{ $row->replacementJob->created_at->format('d M Y') }}</div>
                                        @if($row->replacementJob->lab_ref_no)
                                            <div class="small font-monospace text-secondary" title="Supplier Replacement Memo">
                                                <i class="fas fa-file-invoice mr-1"></i>{{ $row->replacementJob->lab_ref_no }}
                                            </div>
                                        @endif
                                        @if($row->replacementJob->supplier)
                                            <div class="small text-muted">
                                                <i class="fas fa-truck mr-1"></i>{{ $row->replacementJob->supplier->supplier_name }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-muted">Direct RMA Intake</span>
                                    @endif
                                </td>
                                <td>
                                    @if($row->warranty_end_date)
                                        <div>{{ $row->warranty_end_date->format('d M Y') }}</div>
                                        @if($row->warranty_end_date->isPast())
                                            <span class="badge badge-secondary" style="font-size: 10px;">EXPIRED</span>
                                        @else
                                            <span class="badge badge-success" style="font-size: 10px;">UNDER WARRANTY</span>
                                        @endif
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $statusClass = match($row->status) {
                                            'faulty_in_clinic'     => 'badge-warning',
                                            'with_supplier_lab'    => 'badge-info',
                                            'retained_by_supplier' => 'badge-primary',
                                            'credited_by_supplier' => 'badge-success',
                                            'scrapped'             => 'badge-secondary',
                                            default                => 'badge-light'
                                        };
                                        $statusLabel = match($row->status) {
                                            'faulty_in_clinic'     => 'Faulty in Clinic',
                                            'with_supplier_lab'    => 'With Supplier Lab',
                                            'retained_by_supplier' => 'Retained by Supplier Lab',
                                            'credited_by_supplier' => 'Credited by Supplier',
                                            'scrapped'             => 'Scrapped',
                                            default                => str_replace('_', ' ', $row->status)
                                        };
                                    @endphp
                                    <span class="badge {{ $statusClass }} px-2 py-1 font-weight-bold">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="text-right text-nowrap">
                                    <button type="button" class="btn btn-outline-primary btn-sm btn-open-rma mr-1"
                                            data-id="{{ $row->id }}"
                                            data-serial="{{ $row->serial_no }}"
                                            data-status="{{ $row->status }}"
                                            data-supplier-id="{{ $row->replacementJob?->supplier_id }}"
                                            data-memo="{{ $row->replacementJob?->lab_ref_no }}"
                                            data-action="{{ route('repairs.rma.update-status', $row->id) }}"
                                            title="Update RMA Status">
                                        <i class="fas fa-edit mr-1"></i>Update
                                    </button>
                                    <form action="{{ route('repairs.rma.delete', $row->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Permanently delete RMA record for S/N {{ $row->serial_no }}? This will remove this entry from the system.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete RMA Entry">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox fa-3x mb-2 text-muted"></i>
                                    <div>No faulty RMA units found matching the selected criteria.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($items->hasPages())
                <div class="p-3 border-top d-flex justify-content-end">
                    {{ $items->links() }}
                </div>
            @endif
        </div>
    </div>

</div>

{{-- Global Modal for RMA Status Update (Moved outside table for clean backdrop and z-index) --}}
<div class="modal fade" id="globalRmaUpdateModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog" role="document">
        <form id="globalRmaForm" action="" method="POST">
            @csrf
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title font-weight-bold">
                        <i class="fas fa-exchange-alt mr-2"></i>Update RMA Status: <span id="modalSerialDisplay" class="font-monospace"></span>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="font-weight-bold">Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-control" required id="modalStatusSelect">
                            <option value="retained_by_supplier">Retained by Supplier Lab (Exchanged under Warranty)</option>
                            <option value="faulty_in_clinic">Faulty in Clinic (Pending Return)</option>
                            <option value="with_supplier_lab">Sent to Supplier / Manufacturer Lab</option>
                            <option value="credited_by_supplier">Credited by Supplier (Claim Settled)</option>
                            <option value="scrapped">Scrapped / Written Off</option>
                            <option value="active_with_customer">Disperse / Restored to Active Customer (Remove from RMA)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Manufacturer / Supplier</label>
                        <select name="supplier_id" class="form-control" id="modalSupplierSelect">
                            <option value="">-- Select Supplier --</option>
                            @foreach($suppliers as $sup)
                                <option value="{{ $sup->id }}">{{ $sup->supplier_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Return Challan / Supplier Memo #</label>
                        <input type="text" name="memo_no" class="form-control font-monospace" id="modalMemoInput" placeholder="e.g. Signia-RMA-4091 or Courier Memo #">
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Supplier Credit Note Amount (৳)</label>
                        <input type="number" step="0.01" name="credit_amount" class="form-control" placeholder="0.00">
                        <small class="text-muted">If supplier issued ledger credit, it will automatically debit Accounts Payable.</small>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Notes / Courier Details</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Sent via Sundarban Courier on 16 Oct..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold"><i class="fas fa-check mr-1"></i>Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('js')
<script>
    $(document).ready(function() {
        $(document).on('click', '.btn-open-rma', function(e) {
            e.preventDefault();
            var btn = $(this);
            $('#globalRmaForm').attr('action', btn.data('action'));
            $('#modalSerialDisplay').text('S/N ' + btn.data('serial'));
            $('#modalStatusSelect').val(btn.data('status'));
            if (btn.data('supplier-id')) {
                $('#modalSupplierSelect').val(btn.data('supplier-id'));
            } else {
                $('#modalSupplierSelect').val('');
            }
            if (btn.data('memo')) {
                $('#modalMemoInput').val(btn.data('memo'));
            } else {
                $('#modalMemoInput').val('');
            }
            $('#globalRmaUpdateModal').modal('show');
        });
    });
</script>
@endpush
@endsection
