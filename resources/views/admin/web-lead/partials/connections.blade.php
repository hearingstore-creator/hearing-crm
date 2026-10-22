@php
    $ok = fn (bool $v, string $yes = 'Set', string $no = 'Not set') => '<span class="wl-badge ' . ($v ? 'wl-processed' : 'wl-failed') . '"><i class="fas ' . ($v ? 'fa-check' : 'fa-times') . '"></i>' . ($v ? $yes : $no) . '</span>';
    $eventMeta = [
        'received'  => ['Waiting', 'wl-pending'],
        'processed' => ['Lead created', 'wl-processed'],
        'duplicate' => ['Already had it', 'wl-duplicate'],
        'ignored'   => ['Ignored', 'wl-spam'],
        'failed'    => ['Failed', 'wl-failed'],
    ];
@endphp

<div class="crm-animate-in delay-1" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:16px; margin-bottom:16px;">

    <section class="crm-table-card" style="padding:16px 18px;" id="wl-conn-meta">
        <h2 class="wl-h"><i class="fab fa-facebook"></i> Facebook / Instagram lead forms &amp; WhatsApp</h2>
        <table class="table table-sm mb-2" style="font-size:12.5px;">
            <tr><td>Webhook address</td><td><code id="wl-meta-url">{{ $meta['webhook'] }}</code> <button type="button" class="btn btn-secondary btn-sm py-0 js-copy" data-copy="#wl-meta-url">Copy</button></td></tr>
            <tr><td>App secret <span class="wl-muted">(META_APP_SECRET)</span></td><td>{!! $ok($meta['secret']) !!}</td></tr>
            <tr><td>Verify token <span class="wl-muted">(META_VERIFY_TOKEN)</span></td><td>{!! $ok($meta['verify']) !!}</td></tr>
            <tr><td>Page token <span class="wl-muted">(META_PAGE_TOKEN)</span></td><td>{!! $ok($meta['token'], 'Set', 'Needed for lead forms') !!}</td></tr>
            <tr><td>Lead-form leads use</td><td>{!! $meta['form'] ? e($meta['form']) : $ok(false, '', 'Set META_LEAD_FORM_KEY') !!}</td></tr>
            <tr><td>WhatsApp leads use</td><td>{!! $meta['waForm'] ? e($meta['waForm']) : $ok(false, '', 'Set WHATSAPP_LEAD_FORM_KEY') !!}</td></tr>
        </table>
        <details style="font-size:12.5px;">
            <summary style="cursor:pointer; font-weight:600;">How to connect</summary>
            <ol style="padding-left:18px; margin-top:6px;">
                <li>In Meta for Developers, create (or open) the business app for your Page and WhatsApp number.</li>
                <li>Webhooks → Page: callback URL above, verify token = the text in <code>META_VERIFY_TOKEN</code>; subscribe to <b>leadgen</b>.</li>
                <li>Webhooks → WhatsApp Business Account: same URL and token; subscribe to <b>messages</b>.</li>
                <li>Put the app secret in <code>META_APP_SECRET</code> and a long-lived Page token with <b>leads_retrieval</b> in <code>META_PAGE_TOKEN</code>.</li>
                <li>Pick a capture form (Forms tab) for branch and SLA, and put its key in <code>META_LEAD_FORM_KEY</code>.</li>
                <li>Test with Meta's Lead Ads Testing Tool; the call appears in the log below.</li>
            </ol>
            <p class="wl-muted mb-0">Ad campaigns are matched by their Meta campaign id; a new one is added to Marketing → Campaigns automatically, ready for its spend. Click-to-WhatsApp ads are credited the same way. A number writing again within {{ config('connectors.meta.whatsapp_repeat_hours') }} hours is the same enquiry.</p>
        </details>
    </section>

    <section class="crm-table-card" style="padding:16px 18px;" id="wl-conn-google">
        <h2 class="wl-h"><i class="fab fa-google"></i> Google Ads lead forms</h2>
        @forelse ($allForms as $f)
            <div style="font-size:12.5px; margin-bottom:8px;">
                <b>{{ $f->name }}</b><br>
                <code id="wl-g-{{ $f->id }}">{{ url('api/v1/hooks/google/' . $f->key) }}</code>
                <button type="button" class="btn btn-secondary btn-sm py-0 js-copy" data-copy="#wl-g-{{ $f->id }}">Copy</button>
            </div>
        @empty
            <p class="wl-muted">Create a capture form first (Forms tab).</p>
        @endforelse
        <details style="font-size:12.5px;">
            <summary style="cursor:pointer; font-weight:600;">How to connect</summary>
            <ol style="padding-left:18px; margin-top:6px;">
                <li>Google Ads → the lead form asset → Lead delivery → Webhook integration.</li>
                <li>Webhook URL: the address of the capture form these leads should use (above).</li>
                <li>Key: that form's <b>secret</b> (shown once when the form was made; Forms tab → new secret if lost).</li>
                <li>Send test data: it shows in the log below as “Google test lead”.</li>
            </ol>
        </details>
    </section>

    <section class="crm-table-card" style="padding:16px 18px;" id="wl-conn-webhook">
        <h2 class="wl-h"><i class="fas fa-plug"></i> Any other source (webhook)</h2>
        <p style="font-size:12.5px;" class="mb-2">Zapier, Make, landing-page builders, a missed-call service: send a POST (JSON or form) to the address of a capture form.</p>
        @foreach ($allForms as $f)
            <div style="font-size:12.5px; margin-bottom:8px;">
                <b>{{ $f->name }}</b><br>
                <code id="wl-w-{{ $f->id }}">{{ url('api/v1/hooks/lead/' . $f->key) }}?token=&lt;form secret&gt;</code>
            </div>
        @endforeach
        <details style="font-size:12.5px;">
            <summary style="cursor:pointer; font-weight:600;">Fields it understands</summary>
            <p class="mt-1 mb-1"><code>name</code> (or <code>first_name</code> + <code>last_name</code>), <code>phone</code> (or <code>mobile</code>, <code>msisdn</code>, <code>caller</code>), <code>email</code>, <code>message</code>,
                <code>source</code> (facebook, instagram, google, whatsapp, phone_call, missed_call …), <code>utm_source</code>, <code>utm_medium</code>, <code>utm_campaign</code>, <code>utm_content</code>, <code>utm_term</code>, <code>gclid</code>, <code>fbclid</code>, <code>page_url</code>, <code>id</code>.</p>
            <p class="wl-muted mb-0">The token can also go in the header <code>X-Webhook-Token</code>. A missed call with only a number becomes “Missed call …” with source Phone call.</p>
        </details>
    </section>
</div>

<div class="crm-table-card crm-animate-in delay-2">
    <h2 class="wl-h" style="padding:14px 18px 0;"><i class="fas fa-stream"></i> Calls received (latest 60)</h2>
    <div style="overflow-x:auto;">
        <table class="crm-table">
            <thead><tr><th>Received</th><th>From</th><th>Id</th><th>Result</th><th>Lead</th><th style="text-align:center;">Action</th></tr></thead>
            <tbody>
                @forelse ($events as $e)
                    @php [$eLabel, $eClass] = $eventMeta[$e->status] ?? [$e->status, '']; @endphp
                    <tr id="wl-event-{{ $e->id }}">
                        <td style="white-space:nowrap;">{{ $e->created_at->format('d M, h:i A') }}<br><span class="wl-muted">{{ $e->created_at->diffForHumans() }}</span></td>
                        <td>{{ \App\Models\LeadWebhookEvent::SOURCES[$e->source] ?? $e->source }}</td>
                        <td><span class="wl-muted" style="font-family:monospace;">{{ \Illuminate\Support\Str::limit((string) $e->external_id, 24) ?: '—' }}</span></td>
                        <td><span class="wl-badge {{ $eClass }}">{{ $eLabel }}</span>@if ($e->note)<br><span class="wl-muted">{{ $e->note }}</span>@endif</td>
                        <td>@if ($e->submission_id)<a href="{{ url('admin/web-leads') }}?q={{ urlencode(optional($e->submission)->phone ?? '') }}">Inbox row</a>@else<span class="wl-muted">—</span>@endif</td>
                        <td style="text-align:center;">
                            @if (in_array($e->status, ['failed', 'received'], true))
                                <form method="POST" action="{{ route('web-leads.events.retry', $e->id) }}">@csrf<button class="btn btn-secondary btn-sm" type="submit">Try again</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="wl-empty"><i class="fas fa-satellite-dish"></i><div>Nothing received yet. Connect a source above, then send a test lead.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
