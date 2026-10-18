<div class="job-card">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <a href="{{ route('repairs.show', $job->id) }}" class="job-no text-primary">
            {{ $job->job_no }}
        </a>
        @php
            $days = $job->days_in_stage;
            if ($days === 0) {
                $ageText = 'Today';
            } elseif ($days === 1) {
                $ageText = '1 day';
            } else {
                $ageText = $days . ' days';
            }
        @endphp
        <span class="job-age {{ $days >= 7 ? 'urgent' : '' }}" title="{{ $days }} days in current stage">
            <i class="far fa-clock mr-1"></i>{{ $ageText }}
        </span>
    </div>

    <div class="customer-name" title="{{ $job->customer?->lead?->full_name ?? ($job->customer?->name ?? 'Walk-in Patient') }}">
        {{ $job->customer?->lead?->full_name ?? ($job->customer?->name ?? 'Walk-in Patient') }}
    </div>

    <div class="device-info">
        <span class="d-block text-truncate" title="{{ $job->product_display_name }}">{{ $job->product_display_name }}</span>
        <span class="text-muted font-weight-bold text-monospace" style="font-size: 11px;">S/N: {{ $job->original_serial_no }}</span>
        @if($job->replaced_serial_no)
            <div class="text-success font-weight-bold small">&rarr; Repl: {{ $job->replaced_serial_no }}</div>
        @endif
    </div>

    {{-- Pipeline Path Badge --}}
    <div class="mb-2">
        @if($job->pipeline_path === 'supplier_warranty')
            <span class="path-badge warranty"><i class="fas fa-shield-alt mr-1"></i>Supplier Warranty</span>
        @elseif($job->pipeline_path === 'in_house')
            <span class="path-badge in-house"><i class="fas fa-tools mr-1"></i>In-House</span>
        @else
            <span class="path-badge paid"><i class="fas fa-receipt mr-1"></i>Paid Service</span>
        @endif

        @if($job->is_quick_service)
            <span class="badge badge-pill badge-warning text-dark ml-1 font-weight-bold">⚡ Quick</span>
        @endif
    </div>

    {{-- Stage Specific Metadata --}}
    @if($stageNum == 2)
        <div class="p-2 mb-2 bg-light rounded small" style="border-left: 3px solid var(--stage-2);">
            <div class="text-dark font-weight-bold text-truncate">{{ $job->supplier?->supplier_name ?? 'External Lab' }}</div>
            @if($job->dispatch_type === 'courier' && $job->courier_tracking_no)
                <div class="text-muted text-truncate"><i class="fas fa-shipping-fast mr-1"></i>{{ $job->courier_name }}: #{{ $job->courier_tracking_no }}</div>
            @elseif($job->dispatch_type === 'by_hand' && $job->carrier_person_name)
                <div class="text-muted text-truncate"><i class="fas fa-walking mr-1"></i>Bearer: {{ $job->carrier_person_name }}</div>
            @endif
        </div>
    @elseif($stageNum == 3)
        <div class="p-2 mb-2 rounded small" style="background: #faf5ff; border-left: 3px solid var(--stage-3);">
            <div class="text-muted">Quoted Estimate:</div>
            <div class="font-weight-bold" style="color: var(--stage-3); font-size: 13px;">৳{{ number_format($job->estimate_amount, 2) }}</div>
            @if($job->void_reason)
                <div class="text-danger small mt-1 font-weight-semibold text-truncate"><i class="fas fa-exclamation-triangle mr-1"></i>{{ $job->void_reason }}</div>
            @endif
        </div>
    @elseif($stageNum == 5)
        <div class="p-2 mb-2 rounded small" style="background: #f0fdf4; border-left: 3px solid var(--stage-5);">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted">Total Due:</span>
                <span class="font-weight-bold {{ $job->customer_due > 0 ? 'text-danger' : 'text-success' }}">
                    ৳{{ number_format($job->customer_due, 2) }}
                </span>
            </div>
        </div>
    @elseif($stageNum == 6)
        <div class="p-2 mb-2 rounded small" style="background: #f8fafc; border-left: 3px solid var(--stage-6);">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted">Delivered:</span>
                <span class="font-weight-bold text-dark">
                    {{ $job->delivered_at ? \Carbon\Carbon::parse($job->delivered_at)->format('d M, Y') : 'Settled' }}
                </span>
            </div>
            @if($job->total_customer_paid > 0)
                <div class="text-muted small mt-1">Paid: ৳{{ number_format($job->total_customer_paid, 2) }}</div>
            @endif
        </div>
    @endif

    {{-- Single Primary Next-Action Button --}}
    @if($stageNum == 1)
        @if($job->pipeline_path === 'in_house')
            <a href="{{ route('repairs.show', $job->id) }}" class="btn-action-primary btn-success text-white">
                <i class="fas fa-check mr-1"></i>Complete In-House
            </a>
        @else
            <a href="{{ route('repairs.show', $job->id) }}#dispatch" class="btn-action-primary btn-primary text-white">
                <i class="fas fa-paper-plane mr-1"></i>Dispatch to Lab
            </a>
        @endif
    @elseif($stageNum == 2)
        <a href="{{ route('repairs.show', $job->id) }}#lab-report" class="btn-action-primary btn-warning text-dark font-weight-bold">
            <i class="fas fa-clipboard-check mr-1"></i>Log Lab Findings
        </a>
    @elseif($stageNum == 3)
        <a href="{{ route('repairs.show', $job->id) }}#approval" class="btn-action-primary text-white" style="background: var(--stage-3);">
            <i class="fas fa-phone mr-1"></i>Patient Decision
        </a>
    @elseif($stageNum == 4)
        <a href="{{ route('repairs.show', $job->id) }}#receive-unit" class="btn-action-primary text-white" style="background: var(--stage-4);">
            <i class="fas fa-inbox mr-1"></i>Receive from Lab
        </a>
    @elseif($stageNum == 5)
        <a href="{{ route('repairs.show', $job->id) }}#deliver" class="btn-action-primary text-white" style="background: var(--stage-5);">
            <i class="fas fa-hand-holding mr-1"></i>Deliver &amp; Settle
        </a>
    @elseif($stageNum == 6)
        <a href="{{ route('repairs.show', $job->id) }}" class="btn-action-primary text-white" style="background: var(--stage-6);">
            <i class="fas fa-file-invoice mr-1"></i>View Details &amp; Slip
        </a>
    @endif
</div>
