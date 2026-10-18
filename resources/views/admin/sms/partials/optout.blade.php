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
