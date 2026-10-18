{{-- Communication Timeline Widget
     Usage: @include('admin.partials.communication-timeline', ['timelineItems' => $timelineItems])
     
     Each $timelineItem must have:
       - type: 'sms' | 'follow_up' | 'appointment'
       - date: Carbon instance
       - data: the original model (SmsLog, LeadFollowUp, Appointment)
--}}

<div class="crm-info-card crm-animate-in delay-4" style="margin-bottom: var(--space-6);">
    <div class="crm-info-card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div style="display: flex; align-items: center; gap: var(--space-3);">
            <div class="info-icon" style="background: var(--accent-500);"><i class="fas fa-stream"></i></div>
            <h6>Communication Timeline</h6>
            <span style="background: var(--accent-50, #EEF2FF); color: var(--accent-600); font-size: 11px; font-weight: 600; padding: 2px 10px; border-radius: 20px;">
                {{ count($timelineItems) }}
            </span>
        </div>
        {{-- Filter buttons --}}
        <div class="ct-filters">
            <button class="ct-filter-btn active" data-filter="all"><i class="fas fa-list mr-1"></i>All</button>
            <button class="ct-filter-btn" data-filter="sms"><i class="fas fa-comment-dots mr-1"></i>SMS</button>
            <button class="ct-filter-btn" data-filter="follow_up"><i class="fas fa-phone mr-1"></i>Follow-ups</button>
            <button class="ct-filter-btn" data-filter="appointment"><i class="fas fa-calendar mr-1"></i>Appointments</button>
            <button class="ct-filter-btn" data-filter="proposal"><i class="fas fa-file-invoice mr-1"></i>Quotations</button>
        </div>
    </div>
    <div class="crm-info-card-body" style="padding: 0;">
        @if(count($timelineItems) > 0)
            <div class="ct-list" id="ctTimeline">
                @foreach($timelineItems as $item)
                    <div class="ct-item" data-type="{{ $item['type'] }}">
                        {{-- Left: icon --}}
                        <div class="ct-icon-col">
                            @if($item['type'] === 'sms')
                                @php
                                    $log = $item['data'];
                                    $logStatus = $log->status instanceof \App\Enums\SmsStatus ? $log->status->value : $log->status;
                                @endphp
                                <div class="ct-icon {{ $logStatus === 'sent' ? 'ct-icon-success' : ($logStatus === 'failed' ? 'ct-icon-danger' : 'ct-icon-warning') }}">
                                    <i class="fas fa-comment-dots"></i>
                                </div>
                            @elseif($item['type'] === 'follow_up')
                                @php $fuStatus = $item['data']->status ?? 'pending'; @endphp
                                <div class="ct-icon {{ $fuStatus === 'completed' ? 'ct-icon-success' : 'ct-icon-info' }}">
                                    <i class="fas {{ $fuStatus === 'completed' ? 'fa-check' : 'fa-phone-alt' }}"></i>
                                </div>
                            @elseif($item['type'] === 'appointment')
                                <div class="ct-icon ct-icon-purple">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                            @elseif($item['type'] === 'proposal')
                                <div class="ct-icon" style="background: #eff6ff; color: #2563eb;">
                                    <i class="fas fa-file-invoice"></i>
                                </div>
                            @endif
                            <div class="ct-line"></div>
                        </div>

                        {{-- Right: content --}}
                        <div class="ct-content">
                            <div class="ct-header">
                                <div class="ct-date">
                                    <i class="fas fa-clock mr-1"></i>
                                    {{ $item['date']->format('d M Y, h:i A') }}
                                    <span class="ct-relative">({{ $item['date']->diffForHumans() }})</span>
                                </div>
                            </div>

                            @if($item['type'] === 'sms')
                                @php $log = $item['data']; $logStatus = $log->status instanceof \App\Enums\SmsStatus ? $log->status->value : $log->status; @endphp
                                <div class="ct-body">
                                    <div class="ct-title">
                                        <span class="ct-type-badge ct-badge-sms">SMS Sent</span>
                                        @if($log->campaign_name)
                                            <span class="ct-campaign">{{ $log->campaign_name }}</span>
                                        @elseif($log->campaign)
                                            <span class="ct-campaign">{{ $log->campaign->name }}</span>
                                        @endif
                                        @if($logStatus === 'sent')
                                            <span class="badge badge-success" style="font-size:10px;">✓ Delivered</span>
                                        @elseif($logStatus === 'failed')
                                            <span class="badge badge-danger" style="font-size:10px;">✗ Failed</span>
                                        @else
                                            <span class="badge badge-warning" style="font-size:10px;">Pending</span>
                                        @endif
                                    </div>
                                    <div class="ct-message">
                                        <span class="ct-msg-text">{{ \Illuminate\Support\Str::limit($log->message, 150) }}</span>
                                        @if(strlen($log->message) > 150)
                                            <button class="ct-expand-btn" onclick="this.previousElementSibling.textContent='{{ addslashes($log->message) }}'; this.remove();">Read more</button>
                                        @endif
                                    </div>
                                    <div class="ct-meta">
                                        <span><i class="fas fa-mobile-alt mr-1"></i>{{ $log->phone_number }}</span>
                                        @if($log->follow_up_created)
                                            <span class="ct-fu-badge"><i class="fas fa-check-circle mr-1"></i>Follow-up created</span>
                                        @endif
                                    </div>
                                </div>

                            @elseif($item['type'] === 'follow_up')
                                @php
                                    $fu = $item['data'];
                                    $fuSt = $fu->status ?? 'pending';
                                @endphp
                                <div class="ct-body{{ $fuSt === 'completed' ? ' ct-completed' : '' }}">
                                    <div class="ct-title">
                                        <span class="ct-type-badge ct-badge-followup">Follow-up</span>
                                        @if(is_array($fu->follow_up_mode))
                                            @foreach($fu->follow_up_mode as $mode)
                                                <span class="ct-mode-tag">{{ ucfirst(str_replace('_', ' ', $mode)) }}</span>
                                            @endforeach
                                        @endif
                                        @if($fuSt === 'completed')
                                            <span class="badge badge-success" style="font-size:10px;">✓ Completed</span>
                                        @else
                                            <span class="badge badge-warning" style="font-size:10px;">⏳ Pending</span>
                                        @endif
                                    </div>
                                    @if($fu->follow_up_notes)
                                        <div class="ct-message">{{ $fu->follow_up_notes }}</div>
                                    @endif
                                    @if($fuSt === 'completed' && $fu->outcome_notes)
                                        <div class="ct-outcome"><i class="fas fa-clipboard-check mr-1"></i><strong>Outcome:</strong> {{ $fu->outcome_notes }}</div>
                                    @endif
                                    <div class="ct-meta">
                                        @if($fu->assignedUser)
                                            <span><i class="fas fa-user mr-1"></i>{{ $fu->assignedUser->name }}</span>
                                        @endif
                                        @if($fuSt === 'completed' && $fu->completed_at)
                                            <span><i class="fas fa-check-circle mr-1" style="color:var(--success-500);"></i>Completed {{ $fu->completed_at->diffForHumans() }}</span>
                                        @endif
                                    </div>
                                    @if($fuSt !== 'completed')
                                        <div class="ct-actions" style="margin-top:8px;">
                                            <form class="ct-complete-form" method="POST" action="{{ url('admin/leads/follow-ups/' . $fu->id . '/complete') }}" style="display:flex;gap:8px;align-items:flex-end;">
                                                @csrf
                                                @method('PATCH')
                                                <div style="flex:1;">
                                                    <input type="text" name="outcome_notes" class="form-control form-control-sm" placeholder="Outcome notes (e.g. Customer interested in renewal)" style="font-size:12px;border-radius:6px;">
                                                </div>
                                                <button type="submit" class="btn btn-success btn-sm" style="white-space:nowrap;font-size:11px;border-radius:6px;">
                                                    <i class="fas fa-check mr-1"></i>Mark Complete
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                </div>

                            @elseif($item['type'] === 'appointment')
                                @php $apt = $item['data']; @endphp
                                <div class="ct-body">
                                    <div class="ct-title">
                                        <span class="ct-type-badge ct-badge-appointment">Appointment</span>
                                        @php
                                            $aptStatusVal = $apt->status instanceof \App\Enums\AppointmentStatus ? $apt->status->value : $apt->status;
                                            $aptStatusColors = ['scheduled'=>'warning','confirmed'=>'info','completed'=>'success','cancelled'=>'danger','no_show'=>'secondary'];
                                        @endphp
                                        <span class="badge badge-{{ $aptStatusColors[$aptStatusVal] ?? 'secondary' }}" style="font-size:10px;">
                                            {{ ucfirst(str_replace('_', ' ', $aptStatusVal)) }}
                                        </span>
                                    </div>
                                    @if($apt->notes)
                                        <div class="ct-message">{{ $apt->notes }}</div>
                                    @endif
                                    <div class="ct-meta">
                                        @if($apt->service)
                                            <span><i class="fas fa-stethoscope mr-1"></i>{{ $apt->service->name ?? '' }}</span>
                                        @endif
                                        <span><i class="fas fa-calendar mr-1"></i>{{ \Carbon\Carbon::parse($apt->appointment_date)->format('d M Y') }}</span>
                                    </div>
                                </div>
                            @elseif($item['type'] === 'proposal')
                                @php $prop = $item['data']; @endphp
                                <div class="ct-body">
                                    <div class="ct-title">
                                        <span class="ct-type-badge" style="background: #eff6ff; color: #2563eb; border-color: #bfdbfe;">
                                            <i class="fas fa-file-invoice mr-1"></i>Quotation {{ $prop->proposal_no ?: '#' . $prop->id }}
                                        </span>
                                        <span class="badge" style="background: {{ $prop->status_color }}18; color: {{ $prop->status_color }}; font-size:10px; font-weight: 600;">
                                            {{ $prop->status }}
                                        </span>
                                    </div>
                                    <div style="font-weight: 600; font-size: 13px; color: var(--page-text); margin-top: 4px;">
                                        {{ $prop->title }}
                                    </div>
                                    <div class="ct-meta" style="margin-top: 4px;">
                                        <span><i class="fas fa-tag mr-1"></i>Quoted: <strong>৳{{ number_format($prop->grand_total ?: $prop->total_amount, 2) }}</strong></span>
                                        @if($prop->items->isNotEmpty())
                                            <span><i class="fas fa-boxes mr-1"></i>{{ $prop->items->count() }} items</span>
                                        @endif
                                        @if($prop->doctor)
                                            <span><i class="fas fa-user-md mr-1"></i>Dr. {{ $prop->doctor->name }}</span>
                                        @endif
                                    </div>
                                    <div style="margin-top: 8px;">
                                        <a href="{{ route('proposal.show', $prop->id) }}" class="btn btn-outline-primary btn-sm" target="_blank" style="font-size: 11px; padding: 2px 10px; border-radius: 6px;">
                                            <i class="fas fa-eye mr-1"></i>View Official Quotation
                                        </a>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div style="text-align: center; padding: 40px; color: var(--page-text-muted);">
                <i class="fas fa-stream" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>
                No communication history yet.
            </div>
        @endif
    </div>
</div>

{{-- Timeline Filter JS --}}
<script>
document.querySelectorAll('.ct-filter-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.ct-filter-btn').forEach(function(b) { b.classList.remove('active'); });
        this.classList.add('active');
        var filter = this.getAttribute('data-filter');
        document.querySelectorAll('.ct-item').forEach(function(item) {
            if (filter === 'all' || item.getAttribute('data-type') === filter) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    });
});
</script>

<style>
/* Communication Timeline Styles */
.ct-filters { display: flex; gap: 4px; }
.ct-filter-btn {
    padding: 4px 10px; border-radius: 6px; border: 1px solid var(--card-border);
    background: var(--card-bg); cursor: pointer; font-size: 11px; font-weight: 600;
    color: var(--page-text-muted); transition: all .15s;
}
.ct-filter-btn:hover { border-color: var(--accent-300); }
.ct-filter-btn.active { background: var(--accent-500); color: #fff; border-color: var(--accent-500); }

.ct-list { padding: 16px 20px; }

.ct-item {
    display: flex; gap: 14px; padding-bottom: 0; position: relative;
}
.ct-item:last-child .ct-line { display: none; }

.ct-icon-col {
    display: flex; flex-direction: column; align-items: center; flex-shrink: 0;
}
.ct-icon {
    width: 34px; height: 34px; border-radius: 50%; display: flex;
    align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0;
}
.ct-icon-success { background: var(--success-100); color: var(--success-600); }
.ct-icon-danger { background: var(--danger-100, #FEE2E2); color: var(--danger-600); }
.ct-icon-warning { background: var(--warning-100); color: var(--warning-600); }
.ct-icon-info { background: var(--info-100, #DBEAFE); color: var(--info-600, #2563EB); }
.ct-icon-purple { background: #EDE9FE; color: #7C3AED; }

.ct-line {
    width: 2px; flex: 1; min-height: 16px; background: var(--gray-200);
    margin: 4px 0;
}

.ct-content {
    flex: 1; min-width: 0; padding-bottom: 20px;
}
.ct-header { margin-bottom: 6px; }
.ct-date {
    font-size: 11px; color: var(--page-text-muted); font-weight: 600;
}
.ct-relative { font-weight: 400; }

.ct-body {
    background: var(--gray-50); border: 1px solid var(--gray-100);
    border-radius: 8px; padding: 12px 14px;
}
.ct-title {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    margin-bottom: 6px;
}
.ct-type-badge {
    font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 99px;
    text-transform: uppercase; letter-spacing: .04em;
}
.ct-badge-sms { background: var(--success-100); color: var(--success-700); }
.ct-badge-followup { background: var(--info-100, #DBEAFE); color: var(--info-600, #2563EB); }
.ct-badge-appointment { background: #EDE9FE; color: #7C3AED; }

.ct-campaign {
    font-size: 12px; font-weight: 600; color: var(--page-text);
}
.ct-message {
    font-size: 12px; color: var(--page-text-secondary); line-height: 1.5;
    margin-bottom: 6px; white-space: pre-wrap;
}
.ct-expand-btn {
    background: none; border: none; cursor: pointer; font-size: 11px;
    color: var(--accent-600); font-weight: 600; padding: 0; margin-left: 4px;
}
.ct-meta {
    display: flex; gap: 14px; flex-wrap: wrap; font-size: 11px;
    color: var(--page-text-muted); font-weight: 500;
}
.ct-mode-tag {
    font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 99px;
    background: var(--gray-100); color: var(--page-text-secondary);
}
.ct-fu-badge {
    color: var(--success-600); font-weight: 600;
}
.ct-completed {
    background: var(--success-50, #F0FDF4); border-color: var(--success-200, #BBF7D0);
}
.ct-outcome {
    font-size: 12px; color: var(--success-700); line-height: 1.5;
    margin-bottom: 6px; padding: 6px 10px; border-radius: 6px;
    background: var(--success-50, #F0FDF4); border-left: 3px solid var(--success-500);
}
.ct-actions {
    border-top: 1px dashed var(--gray-200); padding-top: 8px;
}
</style>
