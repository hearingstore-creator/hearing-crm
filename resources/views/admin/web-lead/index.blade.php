@extends('admin.layouts.master')
@section('page_title', 'Lead Inbox')
@section('breadcrumb_parent', 'Leads')
@section('breadcrumb_parent_url', url('admin/deals'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/kpi-cards.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/deal-board.css') }}?v={{ filemtime(public_path('assets/css/deal-board.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/web-leads.css') }}">
@endpush

@php
    $statusMeta = [
        'pending'   => ['Pending', 'fa-hourglass-half', 'wl-pending'],
        'processed' => ['New deal', 'fa-check', 'wl-processed'],
        'duplicate' => ['Added to deal', 'fa-link', 'wl-duplicate'],
        'spam'      => ['Spam', 'fa-ban', 'wl-spam'],
        'failed'    => ['Failed', 'fa-exclamation-triangle', 'wl-failed'],
    ];
    $sourceMeta = [
        'website'   => ['Website', 'fas fa-globe'],
        'facebook'  => ['Facebook', 'fab fa-facebook'],
        'instagram' => ['Instagram', 'fab fa-instagram'],
        'whatsapp'  => ['WhatsApp', 'fab fa-whatsapp'],
        'google'    => ['Google', 'fab fa-google'],
        'phone_call'=> ['Missed call / phone', 'fas fa-phone'],
        'webhook'   => ['Webhook', 'fas fa-plug'],
    ];
    $tabUrl = fn ($t, $extra = []) => url('admin/web-leads') . '?' . http_build_query(array_filter(['tab' => $t === 'inbox' ? null : $t] + $extra));
@endphp

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-inbox" style="color: var(--accent-500); margin-right: 8px;"></i>Lead Inbox</h1>
            <p>Website, Facebook / Instagram lead forms, WhatsApp, Google lead forms and webhooks land here first, then become a person + deal in <b>New</b> with a call activity.</p>
        </div>
        <div class="header-actions">
            <div class="deal-views" role="tablist" aria-label="Website lead sections">
                <a href="{{ $tabUrl('inbox') }}" id="wl-tab-inbox" class="{{ $tab === 'inbox' ? 'active' : '' }}"><i class="fas fa-inbox"></i> Inbox</a>
                @if ($canManage)
                    <a href="{{ $tabUrl('forms') }}" id="wl-tab-forms" class="{{ $tab === 'forms' ? 'active' : '' }}"><i class="fas fa-plug"></i> Forms</a>
                    <a href="{{ $tabUrl('connections') }}" id="wl-tab-connections" class="{{ $tab === 'connections' ? 'active' : '' }}"><i class="fas fa-satellite-dish"></i> Connections</a>
                    <a href="{{ $tabUrl('blocklist') }}" id="wl-tab-blocklist" class="{{ $tab === 'blocklist' ? 'active' : '' }}"><i class="fas fa-shield-alt"></i> Blocklist</a>
                @endif
            </div>
            <a href="{{ url('admin/deals?source=website') }}" class="btn btn-secondary btn-sm" id="wl-open-board"><i class="fas fa-columns mr-1"></i>Website deals on board</a>
        </div>
    </div>

    @foreach (['success' => 'ok', 'error' => 'err'] as $key => $cls)
        @if (session($key))
            <div class="wl-flash {{ $cls }} crm-animate-in" role="status"><i class="fas {{ $cls === 'ok' ? 'fa-check-circle' : 'fa-exclamation-circle' }}"></i>{{ session($key) }}</div>
        @endif
    @endforeach
    @if ($errors->any())
        <div class="wl-flash err crm-animate-in" role="alert"><i class="fas fa-exclamation-circle"></i>{{ $errors->first() }}</div>
    @endif

    @if (session('new_secret'))
        @php $ns = session('new_secret'); @endphp
        <div class="wl-secret crm-animate-in" id="wl-new-secret">
            <div class="wl-secret-head"><i class="fas fa-key"></i> Credentials for <b>{{ $ns['form'] }}</b> – copy the secret now, it will not be shown again.</div>
            <div class="wl-kv"><span>Form key</span><code id="wl-key-value">{{ $ns['key'] }}</code><button type="button" class="btn btn-secondary btn-sm js-copy" data-copy="#wl-key-value" id="wl-copy-key">Copy</button></div>
            <div class="wl-kv"><span>Secret</span><code id="wl-secret-value">{{ $ns['secret'] }}</code><button type="button" class="btn btn-secondary btn-sm js-copy" data-copy="#wl-secret-value" id="wl-copy-secret">Copy</button></div>
            <div class="wl-kv"><span>Endpoint</span><code>POST {{ $endpoint }}</code></div>
        </div>
    @endif

    @if ($tab === 'inbox')
        <div class="kpi-row crm-animate-in delay-1">
            @include('admin.partials.kpi-card', ['id' => 'wl-kpi-today', 'color' => 'indigo', 'icon' => 'fas fa-globe',
                'label' => 'Today', 'value' => number_format($today), 'sub' => 'real enquiries received'])
            @include('admin.partials.kpi-card', ['id' => 'wl-kpi-pending', 'color' => ($counts['pending'] ?? 0) ? 'violet' : 'slate', 'icon' => 'fas fa-hourglass-half',
                'label' => 'Waiting', 'value' => number_format($counts['pending'] ?? 0), 'sub' => 'in the queue'])
            @include('admin.partials.kpi-card', ['id' => 'wl-kpi-failed', 'color' => ($counts['failed'] ?? 0) ? 'rose' : 'slate', 'icon' => 'fas fa-exclamation-triangle',
                'label' => 'Failed', 'value' => number_format($counts['failed'] ?? 0), 'sub' => 'need a retry'])
            @include('admin.partials.kpi-card', ['id' => 'wl-kpi-spam', 'color' => 'slate', 'icon' => 'fas fa-ban',
                'label' => 'Spam blocked', 'value' => number_format($counts['spam'] ?? 0), 'sub' => 'honeypot, speed, links, blocklist'])
        </div>

        <form method="GET" action="{{ url('admin/web-leads') }}" class="deal-toolbar crm-animate-in delay-2" id="wl-filters">
            <div class="deal-search">
                <i class="fas fa-search"></i>
                <input type="text" name="q" id="wl-q" class="form-control" value="{{ request('q') }}" placeholder="Name, phone, email or campaign">
            </div>
            <select name="status" id="wl-status" class="form-control" aria-label="Status">
                <option value="">All statuses</option>
                @foreach ($statusMeta as $key => [$label])
                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }} ({{ $counts[$key] ?? 0 }})</option>
                @endforeach
            </select>
            <select name="source" id="wl-source" class="form-control" aria-label="Source">
                <option value="">All sources</option>
                @foreach ($sourceMeta as $key => [$label])<option value="{{ $key }}" @selected(request('source') === $key)>{{ $label }}</option>@endforeach
            </select>
            <button type="submit" class="btn btn-primary" id="wl-filter-apply"><i class="fas fa-filter mr-1"></i>Apply</button>
            @if ($status || request('q'))<a href="{{ url('admin/web-leads') }}" class="btn btn-secondary" id="wl-filter-reset">Reset</a>@endif
        </form>

        <div class="crm-table-card crm-animate-in delay-3">
            <div style="overflow-x: auto;">
                <table class="crm-table">
                    <thead>
                        <tr><th>Received</th><th>Person</th><th>Enquiry</th><th>Campaign</th><th>Status</th><th>Deal</th><th style="text-align: center;">Action</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($submissions as $s)
                            @php [$sLabel, $sIcon, $sClass] = $statusMeta[$s->status] ?? [$s->status, 'fa-circle', '']; @endphp
                            <tr id="wl-row-{{ $s->id }}">
                                <td style="white-space: nowrap;">{{ $s->created_at->format('d M, h:i A') }}<br><span class="wl-muted">{{ $s->created_at->diffForHumans() }}</span></td>
                                <td>
                                    <div class="crm-name-cell">
                                        <div class="crm-avatar">{{ strtoupper(mb_substr($s->name ?? '?', 0, 1)) }}</div>
                                        <div class="crm-name-text">
                                            <span class="name">{{ $s->name }}</span>
                                            <span class="wl-muted">{{ $s->phone }}{{ $s->email ? ' · ' . $s->email : '' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td style="max-width: 280px;">
                                    @if ($s->inquiry_for)
                                        <div class="wl-tags">@foreach ($s->inquiry_for as $iq)<span>{{ \App\Enums\InquiryType::tryFrom($iq)?->label() ?? $iq }}</span>@endforeach</div>
                                    @endif
                                    @if ($s->message)<div class="wl-msg" title="{{ $s->message }}">{{ \Illuminate\Support\Str::limit($s->message, 90) }}</div>@endif
                                    @php [$srcLabel, $srcIcon] = $sourceMeta[$s->source ?: 'website'] ?? [ucfirst((string) $s->source), 'fa-plug']; @endphp
                                    <span class="wl-muted"><i class="{{ $srcIcon }}"></i> {{ $srcLabel }} · {{ $s->form->name ?? 'Deleted form' }}</span>
                                </td>
                                <td>
                                    @if ($s->utm_campaign || $s->utm_source)
                                        <b>{{ $s->utm_campaign ?: '—' }}</b><br><span class="wl-muted">{{ trim($s->utm_source . ' / ' . $s->utm_medium, ' /') }}</span>
                                    @else
                                        <span class="wl-muted">Direct</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="wl-badge {{ $sClass }}"><i class="fas {{ $sIcon }}"></i>{{ $sLabel }}</span>
                                    @if ($s->spam_reason)<br><span class="wl-muted">{{ $s->spam_reason }}</span>@endif
                                    @if ($s->error)<br><span class="wl-muted" title="{{ $s->error }}">{{ \Illuminate\Support\Str::limit($s->error, 60) }}</span>@endif
                                </td>
                                <td>
                                    @if ($s->lead)
                                        <a href="{{ url('admin/leads/' . $s->lead_id) }}" id="wl-lead-{{ $s->id }}"><b>{{ $s->lead->full_name }}</b></a>
                                        @if ($s->deal)
                                            <br><span class="wl-muted"><span class="wl-dot" style="background: {{ $s->deal->stage->color ?? '#94a3b8' }};"></span>{{ $s->deal->status === 'open' ? ($s->deal->stage->name ?? 'Open') : ucfirst($s->deal->status) }}</span>
                                        @endif
                                    @else
                                        <span class="wl-muted">—</span>
                                    @endif
                                </td>
                                <td style="text-align: center; white-space: nowrap;">
                                    @if (in_array($s->status, ['spam', 'failed'], true))
                                        <form method="POST" action="{{ url('admin/web-leads/' . $s->id . '/retry') }}" class="d-inline">@csrf
                                            <button type="submit" class="btn btn-primary btn-sm" id="wl-retry-{{ $s->id }}" title="{{ $s->status === 'spam' ? 'Not spam – create the deal' : 'Try again' }}"><i class="fas fa-redo mr-1"></i>{{ $s->status === 'spam' ? 'Not spam' : 'Retry' }}</button>
                                        </form>
                                    @endif
                                    @if (in_array($s->status, ['pending', 'failed'], true))
                                        <form method="POST" action="{{ url('admin/web-leads/' . $s->id . '/spam') }}" class="d-inline">@csrf
                                            <button type="submit" class="btn btn-secondary btn-sm" id="wl-spam-{{ $s->id }}" title="Mark as spam"><i class="fas fa-ban"></i></button>
                                        </form>
                                    @endif
                                    @if ($canManage && $s->normalized_phone)
                                        <form method="POST" action="{{ url('admin/web-leads/blocklist') }}" class="d-inline" onsubmit="return confirm('Block this phone number? Future submissions from it go to spam.');">@csrf
                                            <input type="hidden" name="type" value="phone"><input type="hidden" name="value" value="{{ $s->phone }}"><input type="hidden" name="reason" value="From inbox #{{ $s->id }}">
                                            <button type="submit" class="btn btn-secondary btn-sm" id="wl-block-{{ $s->id }}" title="Block phone"><i class="fas fa-user-slash"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="wl-empty"><i class="fas fa-inbox"></i><div>No website submissions{{ $status ? ' with this status' : ' yet' }}.</div>
                                @if ($canManage && !$status)<a href="{{ $tabUrl('forms') }}" class="btn btn-primary btn-sm mt-2" id="wl-empty-setup">Connect your website</a>@endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($submissions->hasPages())<div class="crm-table-footer">{{ $submissions->links() }}</div>@endif
        </div>

    @elseif ($tab === 'forms')
        <div class="wl-grid crm-animate-in delay-1">
            <div class="crm-table-card wl-card">
                <h2 class="wl-h"><i class="fas fa-plus-circle"></i> New capture form</h2>
                <p class="wl-muted">One per website or landing page. Leads from it are routed to the chosen branch (or Head Office).</p>
                <form method="POST" action="{{ url('admin/web-leads/forms') }}" id="wl-form-create">@csrf
                    @include('admin.web-lead._form_fields', ['f' => null, 'prefix' => 'new'])
                    <button type="submit" class="btn btn-primary" id="wl-form-create-submit"><i class="fas fa-key mr-1"></i>Create &amp; get credentials</button>
                </form>
            </div>

            <div class="crm-table-card wl-card">
                <h2 class="wl-h"><i class="fas fa-code"></i> How your website sends a lead</h2>
                <p class="wl-muted">Your website's <b>server</b> sends the form (never from the browser, so the secret stays hidden):</p>
<pre class="wl-code" id="wl-snippet">$body = json_encode([
  'name' => $_POST['name'], 'phone' => $_POST['phone'],
  'email' => $_POST['email'] ?? null, 'message' => $_POST['message'] ?? null,
  'inquiry_for' => ['hearing_test'],          // optional
  'utm_source' => $_POST['utm_source'] ?? null, 'utm_medium' => $_POST['utm_medium'] ?? null,
  'utm_campaign' => $_POST['utm_campaign'] ?? null, 'page_url' => $_SERVER['HTTP_REFERER'] ?? null,
  'client_ip' => $_SERVER['REMOTE_ADDR'], 'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
  'elapsed_seconds' => time() - (int) $_POST['form_started'],  // hidden field set when the page loads
  'website' => $_POST['website'] ?? '',       // hidden honeypot field, humans leave it empty
  'turnstile_token' => $_POST['cf-turnstile-response'] ?? null,
]);
$ts = (string) time();
$ch = curl_init('{{ $endpoint }}');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json',
    'X-Form-Key: YOUR_FORM_KEY', 'X-Timestamp: ' . $ts,
    'X-Signature: ' . hash_hmac('sha256', $ts . '.' . $body, 'YOUR_SECRET')]]);
$response = curl_exec($ch);   // 202 = received</pre>
                <ul class="wl-list">
                    <li><b>202</b> received · <b>422</b> fix the data (e.g. not a BD mobile) · <b>429</b> too many tries · <b>401</b> wrong key/signature</li>
                    <li>Bots caught by the honeypot, speed, link or blocklist checks also get 202 and land in the inbox as spam.</li>
                    <li>Cloudflare Turnstile is checked when <code>TURNSTILE_SECRET</code> is set in <code>.env</code>.</li>
                </ul>
            </div>
        </div>

        <div class="crm-table-card crm-animate-in delay-2">
            <div class="crm-table-header"><div class="table-title"><i class="fas fa-plug"></i> Capture forms <span class="ps-badge">{{ $forms->count() }}</span></div></div>
            @forelse ($forms as $f)
                <details class="wl-form-row" id="wl-form-{{ $f->id }}">
                    <summary>
                        <span class="wl-badge {{ $f->is_active ? 'wl-processed' : 'wl-spam' }}">{{ $f->is_active ? 'Active' : 'Off' }}</span>
                        <b>{{ $f->name }}</b>
                        <code class="wl-muted">{{ $f->key }}</code>
                        <span class="wl-muted">→ {{ $f->vendor->name ?? 'Head Office' }} · call within {{ $f->sla_minutes }} min · {{ $f->submissions_count }} received · last {{ $f->last_used_at?->diffForHumans() ?? 'never' }}</span>
                    </summary>
                    <div class="wl-form-edit">
                        <div class="wl-share" id="wl-share-{{ $f->id }}">
                            <div class="wl-share-head"><i class="fas fa-window-restore"></i> Website popup <span class="wl-muted">– no secret needed</span>
                                <a href="{{ $f->publicUrl() }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" id="wl-preview-{{ $f->id }}" style="margin-left: auto;"><i class="fas fa-external-link-alt mr-1"></i>Preview</a>
                            </div>
                            <div class="wl-kv"><span>Direct link</span><code id="wl-link-{{ $f->id }}">{{ $f->publicUrl() }}</code><button type="button" class="btn btn-secondary btn-sm js-copy" data-copy="#wl-link-{{ $f->id }}" id="wl-copy-link-{{ $f->id }}">Copy</button></div>
                            <div class="wl-kv"><span>Popup code</span><code id="wl-embed-{{ $f->id }}">&lt;script src="{{ $f->publicUrl('/embed.js') }}" data-label="Free consultation" async&gt;&lt;/script&gt;</code><button type="button" class="btn btn-secondary btn-sm js-copy" data-copy="#wl-embed-{{ $f->id }}" id="wl-copy-embed-{{ $f->id }}">Copy</button></div>
                            <div class="wl-muted" style="font-size: 12px;">Paste the popup code in WordPress (footer / "Insert Headers and Footers"). It adds a floating button. Any link with <code>#crm-lead-form</code> or class <code>crm-lead-popup</code> also opens it. Add <code>data-auto-open="20"</code> to open it once after 20 seconds, or <code>data-button="false"</code> to hide the floating button.</div>
                        </div>
                        <form method="POST" action="{{ url('admin/web-leads/forms/' . $f->id) }}" id="wl-form-edit-{{ $f->id }}">@csrf @method('PUT')
                            @include('admin.web-lead._form_fields', ['f' => $f, 'prefix' => 'f' . $f->id])
                            <button type="submit" class="btn btn-primary btn-sm" id="wl-form-save-{{ $f->id }}"><i class="fas fa-save mr-1"></i>Save</button>
                        </form>
                        <form method="POST" action="{{ url('admin/web-leads/forms/' . $f->id . '/secret') }}" class="mt-2" onsubmit="return confirm('Generate a new secret? The website stops working until it uses the new one.');">@csrf
                            <button type="submit" class="btn btn-secondary btn-sm" id="wl-form-secret-{{ $f->id }}"><i class="fas fa-sync mr-1"></i>New secret</button>
                        </form>
                    </div>
                </details>
            @empty
                <div class="wl-empty"><i class="fas fa-plug"></i><div>No forms yet. Create one to connect your website.</div></div>
            @endforelse
        </div>

    @elseif ($tab === 'blocklist')
        <div class="wl-grid crm-animate-in delay-1">
            <div class="crm-table-card wl-card">
                <h2 class="wl-h"><i class="fas fa-user-slash"></i> Block a contact</h2>
                <form method="POST" action="{{ url('admin/web-leads/blocklist') }}" id="wl-block-form">@csrf
                    <div class="form-row">
                        <div class="form-group col-sm-4">
                            <label for="wl-block-type">Type</label>
                            <select name="type" id="wl-block-type" class="form-control">
                                <option value="phone">Phone</option><option value="email">Email</option><option value="ip">IP address</option>
                            </select>
                        </div>
                        <div class="form-group col-sm-8">
                            <label for="wl-block-value">Value</label>
                            <input type="text" name="value" id="wl-block-value" class="form-control" maxlength="191" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="wl-block-reason">Reason</label>
                        <input type="text" name="reason" id="wl-block-reason" class="form-control" maxlength="191" placeholder="Optional">
                    </div>
                    <button type="submit" class="btn btn-danger" id="wl-block-submit"><i class="fas fa-ban mr-1"></i>Block</button>
                </form>
            </div>
        </div>
        <div class="crm-table-card crm-animate-in delay-2">
            <table class="crm-table">
                <thead><tr><th>Type</th><th>Value</th><th>Reason</th><th>Added</th><th style="text-align: center;">Action</th></tr></thead>
                <tbody>
                    @forelse ($blocked as $b)
                        <tr id="wl-blocked-{{ $b->id }}">
                            <td><span class="wl-badge wl-spam">{{ ucfirst($b->type) }}</span></td>
                            <td><code>{{ $b->value }}</code></td>
                            <td>{{ $b->reason ?? '—' }}</td>
                            <td>{{ $b->created_at->format('d M Y') }}</td>
                            <td style="text-align: center;">
                                <form method="POST" action="{{ url('admin/web-leads/blocklist/' . $b->id) }}">@csrf @method('DELETE')
                                    <button type="submit" class="btn btn-secondary btn-sm" id="wl-unblock-{{ $b->id }}">Unblock</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="wl-empty"><i class="fas fa-shield-alt"></i><div>The blocklist is empty.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
            @if ($blocked->hasPages())<div class="crm-table-footer">{{ $blocked->links() }}</div>@endif
        </div>
    @elseif ($tab === 'connections')
        @include('admin.web-lead.partials.connections')
    @endif
</div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('.js-copy').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var text = document.querySelector(btn.getAttribute('data-copy')).textContent.trim();
            navigator.clipboard.writeText(text).then(function () { btn.textContent = 'Copied'; setTimeout(function () { btn.textContent = 'Copy'; }, 1500); });
        });
    });
</script>
@endpush
