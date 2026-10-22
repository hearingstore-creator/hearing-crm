<div class="tab-pane fade" id="optout-tab" role="tabpanel">
    <div class="so-wrap">
        <div class="so-intro">
            <div class="so-intro-icon"><i class="fas fa-user-slash"></i></div>
            <div>
                <h3 class="so-title">SMS opt-out list</h3>
                <p class="so-text">Numbers here get <strong>no campaigns and no promotional automations</strong>
                    (welcome, warranty, points, membership, thank-you). Service messages – appointment booked/reminder and
                    service tickets – are still sent.</p>
            </div>
        </div>

        @if(auth()->user()->hasAccess('compose_&_send.create'))
        <form method="POST" action="{{ url('admin/sms/opt-outs') }}" class="so-form" id="soAddForm">
            @csrf
            <div class="so-field">
                <label for="soPhone">Phone number</label>
                <input type="text" class="form-control" id="soPhone" name="phone" placeholder="01XXXXXXXXX" required maxlength="20">
            </div>
            <div class="so-field so-field-grow">
                <label for="soReason">Reason <span class="so-muted">(optional)</span></label>
                <input type="text" class="form-control" id="soReason" name="reason" placeholder="e.g. Asked on call not to send SMS" maxlength="190">
            </div>
            <button type="submit" class="btn so-btn" id="soAddBtn"><i class="fas fa-plus mr-1"></i> Add to list</button>
        </form>
        @endif

        @if (isset($replies) && $replies->count())
            <div style="margin:0 0 18px; padding:12px 14px; border:1px solid var(--gray-100); border-radius:10px;">
                <div style="font-size:12px; font-weight:700; text-transform:uppercase; color:var(--page-text-muted); margin-bottom:6px;"><i class="fas fa-reply mr-1"></i>Replies from patients</div>
                @foreach ($replies as $r)
                    <div style="display:flex; gap:12px; padding:5px 0; border-top:1px solid var(--gray-100); font-size:13px;">
                        <span style="font-family:monospace; min-width:110px;">{{ $r->phone_number }}</span>
                        <span style="flex:1;">{{ \Illuminate\Support\Str::limit($r->message, 120) }}</span>
                        @if ($r->action === 'stop')<span class="badge badge-danger" title="Added to the opt-out list">STOP</span>@endif
                        <span class="text-muted" style="white-space:nowrap;">{{ $r->received_at?->copy()->timezone('Asia/Dhaka')->format('d M, g:i A') }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        @if($optOuts->isEmpty())
            <div class="so-empty" id="soEmpty">
                <i class="fas fa-check-circle"></i>
                <p>Nobody has opted out. Everyone can receive SMS.</p>
            </div>
        @else
            <div class="so-list" id="soList">
                @foreach($optOuts as $o)
                <div class="so-row" id="soRow{{ $o->id }}">
                    <div class="so-phone"><i class="fas fa-phone-slash"></i> {{ $o->value }}</div>
                    <div class="so-reason">{{ $o->reason ?: '—' }}</div>
                    <div class="so-date">{{ $o->created_at?->format('d M Y') }}</div>
                    @if(auth()->user()->hasAccess('compose_&_send.create'))
                    <form method="POST" action="{{ url('admin/sms/opt-outs/' . $o->id) }}" onsubmit="return confirm('Allow SMS to {{ $o->value }} again?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="so-remove" id="soRemove{{ $o->id }}" title="Remove from list"><i class="fas fa-times"></i></button>
                    </form>
                    @endif
                </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
