<!-- Compose & Send Tab — 3-Step Wizard -->
<div class="tab-pane fade show active" id="compose-tab">

    {{-- Stats Strip --}}
    <div class="crm-stats-row" style="margin-bottom: 20px;">
        <div class="crm-stat-card" onclick="void(0)" style="cursor:default;">
            <div class="stat-icon-mini" style="background: var(--accent-100); color: var(--accent-600);"><i class="fas fa-paper-plane"></i></div>
            <div class="stat-info"><div class="stat-count">{{ $stats['total_sent'] }}</div><div class="stat-label" style="font-size:11px;color:var(--page-text-muted);">Total Sent</div></div>
        </div>
        <div class="crm-stat-card" onclick="void(0)" style="cursor:default;">
            <div class="stat-icon-mini" style="background: var(--success-100); color: var(--success-600);"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info"><div class="stat-count">{{ $stats['today_sent'] }}</div><div class="stat-label" style="font-size:11px;color:var(--page-text-muted);">Sent Today</div></div>
        </div>
        <div class="crm-stat-card" onclick="void(0)" style="cursor:default;">
            <div class="stat-icon-mini" style="background: var(--info-100, #E0F2FE); color: var(--info-600, #0284C7);"><i class="fas fa-file-alt"></i></div>
            <div class="stat-info"><div class="stat-count">{{ $stats['templates'] }}</div><div class="stat-label" style="font-size:11px;color:var(--page-text-muted);">Active Templates</div></div>
        </div>
        <div class="crm-stat-card" onclick="void(0)" style="cursor:default;">
            <div class="stat-icon-mini" style="background: var(--warning-100); color: var(--warning-600);"><i class="fas fa-robot"></i></div>
            <div class="stat-info"><div class="stat-count">{{ $automations->where('is_active', true)->count() }}</div><div class="stat-label" style="font-size:11px;color:var(--page-text-muted);">Active Automations</div></div>
        </div>
        @if (isset($gateway))
        <div class="crm-stat-card" onclick="void(0)" style="cursor:default;" id="smsBalanceCard">
            <div class="stat-icon-mini" style="background: {{ $gateway['low'] ? 'var(--danger-100, #FEE2E2)' : 'var(--success-100)' }}; color: {{ $gateway['low'] ? 'var(--danger-600, #DC2626)' : 'var(--success-600)' }};"><i class="fas fa-wallet"></i></div>
            <div class="stat-info">
                <div class="stat-count" id="smsBalanceValue">{{ $gateway['balance'] !== null ? number_format((float) $gateway['balance'], 2) : '—' }}</div>
                <div class="stat-label" style="font-size:11px;color:var(--page-text-muted);">
                    Gateway balance
                    <a href="#" id="smsBalanceRefresh" title="Ask the gateway now" style="margin-left:4px;"><i class="fas fa-sync-alt"></i></a>
                </div>
                <div style="font-size:10px;color:var(--page-text-muted);" id="smsBalanceNote">
                    @if ($gateway['checked_at']) checked {{ $gateway['checked_at']->copy()->timezone(config('app.timezone'))->format('d M, g:i A') }} @else not checked yet @endif
                    @if ($gateway['low']) · <strong style="color:var(--danger-600,#DC2626);">low</strong> @endif
                </div>
            </div>
        </div>
        @endif
    </div>

    @if (isset($pendingApproval) && $pendingApproval->count())
        <div class="crm-table-card" style="margin-bottom: 16px; padding: 12px 16px; border-left:4px solid var(--warning-500);">
            <div style="font-size:12px; font-weight:700; text-transform:uppercase; color:var(--page-text-muted); margin-bottom:6px;"><i class="fas fa-user-check mr-1"></i>Waiting for approval</div>
            @foreach ($pendingApproval as $camp)
                <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; padding:6px 0; border-top:1px solid var(--gray-100); font-size:13px;">
                    <span><strong>{{ $camp->name }}</strong> <span class="text-muted">· {{ $camp->total_recipients }} doctors / staff · "{{ \Illuminate\Support\Str::limit($camp->message_body, 70) }}"</span></span>
                    @if (auth()->user()->hasAccess('compose_&_send.approve'))
                        <span style="display:flex; gap:6px; white-space:nowrap;">
                            <form action="{{ route('sms.campaigns.approve', $camp->id) }}" method="POST">@csrf<button class="btn btn-success btn-sm" type="submit" onclick="return confirm('Send this message to {{ $camp->total_recipients }} people?');">Approve &amp; send</button></form>
                            <form action="{{ route('sms.campaigns.reject', $camp->id) }}" method="POST" onsubmit="this.note.value = prompt('Reason (optional)') || ''">@csrf<input type="hidden" name="note"><button class="btn btn-outline-danger btn-sm" type="submit">Reject</button></form>
                        </span>
                    @else
                        <span class="badge badge-warning">Waiting for a manager</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if (isset($scheduled) && $scheduled->count())
        <div class="crm-table-card" style="margin-bottom: 16px; padding: 12px 16px;">
            <div style="font-size:12px; font-weight:700; text-transform:uppercase; color:var(--page-text-muted); margin-bottom:6px;"><i class="far fa-clock mr-1"></i>Scheduled campaigns</div>
            @foreach ($scheduled as $camp)
                <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-top:1px solid var(--gray-100); font-size:13px;">
                    <span><strong>{{ $camp->name }}</strong> <span class="text-muted">· {{ $camp->scheduled_at?->copy()->timezone(config('app.timezone'))->format('g:i A, d M Y') }}</span></span>
                    <form action="{{ route('sms.campaigns.cancel', $camp->id) }}" method="POST" onsubmit="return confirm('Cancel this scheduled campaign? Nothing will be sent.');">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm" type="submit">Cancel</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Step Indicator --}}
    <div class="ab-steps-bar">
        <div class="ab-step active" data-step="1">
            <div class="ab-step-num">1</div>
            <div class="ab-step-info"><strong>Select Audience</strong><small>Choose recipients & filters</small></div>
        </div>
        <div class="ab-step-line"></div>
        <div class="ab-step" data-step="2">
            <div class="ab-step-num">2</div>
            <div class="ab-step-info"><strong>Compose Message</strong><small>Template or custom message</small></div>
        </div>
        <div class="ab-step-line"></div>
        <div class="ab-step" data-step="3">
            <div class="ab-step-num">3</div>
            <div class="ab-step-info"><strong>Review & Send</strong><small>Confirm and dispatch</small></div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════
         STEP 1: SELECT AUDIENCE
         ═══════════════════════════════════════════════════ --}}
    <div class="ab-wizard-step" id="abStep1">
        <div class="ab-layout">
            {{-- LEFT RAIL --}}
            <aside class="ab-rail">
                <div class="ab-section">
                    <div class="ab-section-label"><i class="fas fa-filter mr-1"></i> Recipient Type</div>
                    <div class="ab-type-switch" id="abTypeSwitch">
                        <button class="ab-type-btn active" data-type="leads"><i class="fas fa-user-plus mr-1"></i> Leads</button>
                        <button class="ab-type-btn" data-type="customers"><i class="fas fa-users mr-1"></i> Customers</button>
                        @if (auth()->user()->hasAccess('compose_&_send.team'))
                            <button class="ab-type-btn" data-type="team" title="Doctors, referrers and employees"><i class="fas fa-user-md mr-1"></i> Team &amp; Doctors</button>
                        @endif
                    </div>
                </div>
                <div class="ab-section">
                    <div class="ab-section-label"><i class="fas fa-bookmark mr-1"></i> Saved Filter Groups</div>
                    <div id="abSavedGroups">
                        <p class="ab-empty-text">No saved groups yet.</p>
                    </div>
                    <p class="ab-note"><i class="fas fa-info-circle mr-1"></i> Saved groups recalculate counts dynamically.</p>
                </div>
            </aside>

            {{-- CENTER CANVAS --}}
            <main class="ab-canvas">
                <div class="crm-form-section" style="margin-bottom: 16px;">
                    <div class="crm-form-section-header">
                        <div class="section-icon" style="background: var(--warning-500);"><i class="fas fa-bolt"></i></div>
                        <h6>Quick Filters</h6>
                    </div>
                    <div class="crm-form-section-body" style="padding: 16px 20px;">
                        <div class="ab-quick-grid" id="abQuickFilters"></div>
                    </div>
                </div>

                <div class="crm-form-section" style="margin-bottom: 16px;">
                    <div class="crm-form-section-header">
                        <div class="section-icon" style="background: var(--accent-500);"><i class="fas fa-sliders-h"></i></div>
                        <h6>Advanced Filters</h6>
                    </div>
                    <div class="crm-form-section-body" style="padding: 16px 20px;">
                        <div id="abAdvancedFilters"></div>
                    </div>
                </div>

                <div class="crm-form-section">
                    <div class="crm-form-section-header" style="justify-content:space-between;">
                        <div style="display:flex;align-items:center;gap:var(--space-3);">
                            <div class="section-icon" style="background: var(--info-600, #0284C7);"><i class="fas fa-list-ul"></i></div>
                            <h6>Filter Builder</h6>
                        </div>
                        <button class="btn btn-sm btn-outline-danger" id="abClearAll" style="font-size:12px;"><i class="fas fa-times mr-1"></i>Clear Filters</button>
                    </div>
                    <div class="crm-form-section-body" style="padding: 16px 20px;">
                        <div id="abBuilderRows">
                            <p class="text-muted" style="font-size:13px;"><i class="fas fa-funnel-dollar mr-1"></i> No filters yet. Pick a quick filter or open any advanced category.</p>
                        </div>
                    </div>
                </div>
            </main>

            {{-- RIGHT SUMMARY --}}
            <aside class="ab-summary">
                <div class="crm-form-section" style="position:sticky;top:20px;">
                    <div class="crm-form-section-header">
                        <div class="section-icon" style="background: var(--success-500);"><i class="fas fa-users"></i></div>
                        <h6>Recipients Found</h6>
                    </div>
                    <div class="crm-form-section-body" style="padding: 20px; text-align:center;">
                        <div class="ab-type-badge" id="abSummaryType">Leads</div>
                        <div class="ab-summary-count" id="abSummaryCount">0</div>
                        <div class="ab-summary-label">match current filters</div>

                        <div class="ab-breakdown" id="abBreakdown">
                            <div class="ab-bd-row"><span><span class="ab-bd-dot" style="background:var(--success-500);"></span> Valid Mobile</span><span class="ab-bd-val" id="abValidPhones">0</span></div>
                            <div class="ab-bd-row"><span><span class="ab-bd-dot" style="background:var(--danger-500);"></span> Missing Mobile</span><span class="ab-bd-val" id="abMissingPhones">0</span></div>
                            <div class="ab-bd-row ab-bd-total"><span><i class="fas fa-paper-plane mr-1"></i> Ready to Send</span><span class="ab-bd-val" id="abReadyToSend" style="color:var(--accent-600);font-size:15px;">0</span></div>
                        </div>

                        <div class="ab-summary-actions">
                            <button class="btn btn-outline-primary btn-sm btn-block" id="abViewRecipients"><i class="fas fa-eye mr-1"></i> View Recipients</button>
                            <button class="btn btn-outline-secondary btn-sm btn-block" id="abSaveGroup"><i class="fas fa-bookmark mr-1"></i> Save Filter Group</button>
                            <button class="btn btn-success btn-block" id="abNextStep2" disabled><i class="fas fa-arrow-right mr-1"></i> Next: Compose Message</button>
                        </div>

                        <p class="ab-consent-note"><i class="fas fa-shield-alt mr-1"></i> Contacts without valid mobile are auto-excluded from send.</p>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════
         STEP 2: COMPOSE MESSAGE
         ═══════════════════════════════════════════════════ --}}
    <div class="ab-wizard-step" id="abStep2" style="display:none;">
        <div class="row">
            <div class="col-lg-8">
                <div class="crm-form-section" style="margin-bottom:16px;">
                    <div class="crm-form-section-header">
                        <div class="section-icon" style="background: var(--accent-500);"><i class="fas fa-envelope"></i></div>
                        <h6>Campaign Details</h6>
                    </div>
                    <div class="crm-form-section-body" style="padding:20px;">
                        <div class="form-group">
                            <label style="font-weight:600;font-size:13px;">Campaign Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="abCampaignName" placeholder="e.g. Warranty Reminder - Sept 2026" maxlength="255">
                        </div>
                    </div>
                </div>

                <div class="crm-form-section" style="margin-bottom:16px;">
                    <div class="crm-form-section-header">
                        <div class="section-icon" style="background: var(--warning-500);"><i class="fas fa-file-alt"></i></div>
                        <h6>Message Source</h6>
                    </div>
                    <div class="crm-form-section-body" style="padding:20px;">
                        <div class="ab-msg-tabs">
                            <button class="ab-msg-tab active" data-target="template"><i class="fas fa-file-alt mr-1"></i> Use Template</button>
                            <button class="ab-msg-tab" data-target="custom"><i class="fas fa-pencil-alt mr-1"></i> Custom Message</button>
                        </div>

                        {{-- Template Picker --}}
                        <div id="abMsgTemplate" class="ab-msg-panel">
                            <label style="font-weight:600;font-size:13px;margin-bottom:8px;display:block;">Select Template</label>
                            <select class="form-control" id="abTemplateSelect" style="margin-bottom:12px;">
                                <option value="">— Choose a template —</option>
                                @foreach(\App\Models\SmsTemplate::where('is_active',1)->orderBy('name')->get() as $tpl)
                                    <option value="{{ $tpl->id }}" data-body="{{ $tpl->body }}">{{ $tpl->name }}</option>
                                @endforeach
                            </select>
                            <div id="abTemplatePreview" style="display:none;">
                                <label style="font-weight:600;font-size:12px;color:var(--page-text-muted);">Template Preview</label>
                                <div class="ab-msg-preview" id="abTemplateBody"></div>
                            </div>
                        </div>

                        {{-- Custom Message --}}
                        <div id="abMsgCustom" class="ab-msg-panel" style="display:none;">
                            <label style="font-weight:600;font-size:13px;margin-bottom:8px;display:block;">Message Body <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="abCustomMessage" rows="5" maxlength="1000" placeholder="Type your message here... Use @{{name}} for recipient name."></textarea>
                            <div class="d-flex justify-content-between mt-2">
                                <small class="text-muted">Variables: <code>@{{name}}</code>, <code>@{{phone}}</code>, <code>@{{company_phone}}</code></small>
                                <small class="text-muted"><span id="abCharCount">0</span>/1000</small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Follow-up Auto-Creation (lead follow-ups: not offered for doctors / employees) --}}
                <div class="crm-form-section" id="abFollowUpSection">
                    <div class="crm-form-section-header">
                        <div class="section-icon" style="background: var(--success-500);"><i class="fas fa-calendar-plus"></i></div>
                        <h6>Auto-Create Follow-up</h6>
                    </div>
                    <div class="crm-form-section-body" style="padding:20px;">
                        <div class="custom-control custom-switch mb-3">
                            <input type="checkbox" class="custom-control-input" id="abFollowUpToggle">
                            <label class="custom-control-label" for="abFollowUpToggle" style="font-weight:600;font-size:13px;">
                                Create a follow-up task for each recipient after sending
                            </label>
                        </div>
                        <div id="abFollowUpFields" style="display:none;">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label style="font-size:12px;font-weight:600;">Follow-up After (Days)</label>
                                        <input type="number" class="form-control" id="abFollowUpDays" value="3" min="1" max="30">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label style="font-size:12px;font-weight:600;">Assigned To</label>
                                        <select class="form-control" id="abFollowUpUser">
                                            <option value="">— Auto (Campaign Creator) —</option>
                                            @foreach(\App\Models\User::select('id','name')->get() as $user)
                                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label style="font-size:12px;font-weight:600;">Follow-up Mode</label>
                                        <select class="form-control" id="abFollowUpMode">
                                            <option value="phone_call">Phone Call</option>
                                            <option value="whatsapp">WhatsApp</option>
                                            <option value="sms">SMS</option>
                                            <option value="visit">Visit</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <p class="text-muted" style="font-size:11px;"><i class="fas fa-info-circle mr-1"></i>A follow-up will be created for each successfully delivered SMS, visible on the recipient's profile timeline.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="crm-form-section" style="position:sticky;top:20px;">
                    <div class="crm-form-section-header">
                        <div class="section-icon" style="background: var(--success-500);"><i class="fas fa-info-circle"></i></div>
                        <h6>Campaign Summary</h6>
                    </div>
                    <div class="crm-form-section-body" style="padding:20px;">
                        <div class="ab-campaign-summary">
                            <div class="ab-cs-row"><span class="ab-cs-label">Recipients</span><span class="ab-cs-val" id="abStep2Recipients">0</span></div>
                            <div class="ab-cs-row"><span class="ab-cs-label">Ready to Send</span><span class="ab-cs-val" id="abStep2Ready" style="color:var(--accent-600);">0</span></div>
                            <div class="ab-cs-row"><span class="ab-cs-label">Audience</span><span class="ab-cs-val" id="abStep2Audience">—</span></div>
                            <div class="ab-cs-row"><span class="ab-cs-label">Filters Applied</span><span class="ab-cs-val" id="abStep2Filters">0</span></div>
                        </div>
                        <hr style="border-color:var(--gray-100);">
                        <div class="ab-summary-actions">
                            <button class="btn btn-outline-secondary btn-sm btn-block" id="abBackStep1"><i class="fas fa-arrow-left mr-1"></i> Back to Audience</button>
                            <button class="btn btn-success btn-block" id="abNextStep3" disabled><i class="fas fa-arrow-right mr-1"></i> Next: Review & Send</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════
         STEP 3: REVIEW & SEND
         ═══════════════════════════════════════════════════ --}}
    <div class="ab-wizard-step" id="abStep3" style="display:none;">
        <div class="row">
            <div class="col-lg-8">
                <div class="crm-form-section" style="margin-bottom:16px;">
                    <div class="crm-form-section-header">
                        <div class="section-icon" style="background: var(--accent-500);"><i class="fas fa-clipboard-check"></i></div>
                        <h6>Review Campaign</h6>
                    </div>
                    <div class="crm-form-section-body" style="padding:20px;">
                        <div class="ab-review-grid">
                            <div class="ab-review-item">
                                <label>Campaign Name</label>
                                <div id="abReviewName" class="ab-review-val">—</div>
                            </div>
                            <div class="ab-review-item">
                                <label>Audience</label>
                                <div id="abReviewAudience" class="ab-review-val">—</div>
                            </div>
                            <div class="ab-review-item">
                                <label>Recipients</label>
                                <div id="abReviewRecipients" class="ab-review-val">—</div>
                            </div>
                            <div class="ab-review-item">
                                <label>Ready to Send</label>
                                <div id="abReviewReady" class="ab-review-val" style="color:var(--accent-600);font-weight:700;">—</div>
                            </div>
                        </div>
                        <hr style="border-color:var(--gray-100);">
                        <label style="font-weight:600;font-size:13px;">Message Preview</label>
                        <div class="ab-msg-preview" id="abReviewMessage" style="min-height:80px;">—</div>

                        <label style="font-weight:600;font-size:13px;margin-top:16px;">Active Filters</label>
                        <div id="abReviewFilters"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="crm-form-section" style="position:sticky;top:20px;">
                    <div class="crm-form-section-header">
                        <div class="section-icon" style="background: var(--danger-500);"><i class="fas fa-paper-plane"></i></div>
                        <h6>Confirm & Send</h6>
                    </div>
                    <div class="crm-form-section-body" style="padding:20px;text-align:center;">
                        <div style="margin-bottom:16px;">
                            <i class="fas fa-exclamation-triangle text-warning" style="font-size:24px;"></i>
                            <p style="font-size:13px;color:var(--page-text-muted);margin-top:8px;">This action will send SMS messages to all valid recipients. This cannot be undone.</p>
                            <p style="font-size:12px;color:var(--page-text-muted);margin-top:4px;">
                                More than {{ config('crm.sms_campaign_admin_threshold', 500) }} recipients needs a Super Admin.
                                Campaigns started between {{ config('crm.sms_quiet_from_hour', 21) }}:00 and {{ config('crm.sms_quiet_until_hour', 9) }}:00 are sent at {{ config('crm.sms_quiet_until_hour', 9) }}:00.
                            </p>
                        </div>

                        {{-- Test first: one real SMS with sample details --}}
                        <div style="text-align:left; border:1px dashed var(--gray-200); border-radius:8px; padding:10px 12px; margin-bottom:14px;">
                            <label style="font-weight:600;font-size:12px;margin-bottom:4px;">Send a test to your phone</label>
                            <div class="input-group input-group-sm">
                                <input type="text" id="abTestPhone" class="form-control" placeholder="{{ auth()->user()->mobile_no ?: '01XXXXXXXXX' }}" value="">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-primary" id="abTestSend"><i class="fas fa-vial mr-1"></i>Test</button>
                                </div>
                            </div>
                            <small class="text-muted">Uses sample details such as "Sample Patient". It is a real SMS and counts as one. Leave the number empty to use your own.</small>
                            <div id="abTestResult" style="font-size:12px;margin-top:4px;"></div>
                        </div>

                        <form id="abSendForm" method="POST" action="{{ url('admin/sms/send') }}">
                            @csrf
                            <input type="hidden" name="campaign_name" id="abFormCampaignName">
                            <input type="hidden" name="message_body" id="abFormMessageBody">
                            <input type="hidden" name="audience_type" id="abFormAudienceType">
                            <input type="hidden" name="sms_template_id" id="abFormTemplateId">
                            <input type="hidden" name="filter_criteria" id="abFormFilterCriteria">
                            <input type="hidden" name="create_follow_up" id="abFormFollowUp" value="0">
                            <input type="hidden" name="follow_up_days" id="abFormFollowUpDays" value="">
                            <input type="hidden" name="follow_up_assigned_user" id="abFormFollowUpUser" value="">
                            <input type="hidden" name="follow_up_mode" id="abFormFollowUpMode" value="">

                            <div style="text-align:left; margin-bottom:14px;">
                                <label style="font-weight:600;font-size:12px;margin-bottom:4px;">Send time (optional)</label>
                                <input type="datetime-local" name="scheduled_at" id="abScheduledAt" class="form-control form-control-sm" min="{{ now(config('app.timezone'))->format('Y-m-d\TH:i') }}">
                                <small class="text-muted">Leave empty to send now. Bangladesh time. The audience is worked out again at send time.</small>
                            </div>

                            <div class="ab-summary-actions">
                                <button type="button" class="btn btn-outline-secondary btn-sm btn-block" id="abBackStep2"><i class="fas fa-arrow-left mr-1"></i> Back to Compose</button>
                                <button type="submit" class="btn btn-danger btn-block" id="abSendBtn"><i class="fas fa-paper-plane mr-1"></i> Send Campaign Now</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════
         VIEW RECIPIENTS MODAL
         ═══════════════════════════════════════════════════ --}}
    <div class="modal fade" id="abViewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius:12px;border:none;">
                <div class="modal-header" style="background:var(--table-header-bg);border-radius:12px 12px 0 0;">
                    <h5 class="modal-title" id="abViewModalTitle"><i class="fas fa-users mr-2"></i>Recipients</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body p-0">
                    <div id="abRecipientLoading" class="text-center py-4" style="display:none;">
                        <i class="fas fa-spinner fa-spin mr-1"></i> Loading recipients...
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" style="font-size:13px;">
                            <thead><tr><th>Name</th><th>Mobile</th><th>Segment</th><th>Status</th></tr></thead>
                            <tbody id="abRecipientRows"></tbody>
                        </table>
                    </div>
                    <div class="text-center text-muted py-2" id="abRecipientMore" style="font-size:12px;"></div>
                </div>
                <div class="modal-footer"><button class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>

    {{-- SAVE FILTER GROUP MODAL --}}
    <div class="modal fade" id="abSaveModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content" style="border-radius:12px;border:none;">
                <div class="modal-header" style="background:var(--table-header-bg);border-radius:12px 12px 0 0;">
                    <h5 class="modal-title"><i class="fas fa-bookmark mr-2"></i>Save Filter Group</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label style="font-size:13px;font-weight:600;">Group Name</label>
                        <input type="text" class="form-control" id="abGroupName" placeholder="e.g. Warranty Expiry – 30 Days" maxlength="100">
                    </div>
                    <div id="abSavePreview" style="font-size:12px;color:var(--page-text-muted);background:var(--gray-50);padding:10px 12px;border-radius:8px;border:1px solid var(--gray-100);"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary btn-sm" id="abConfirmSave"><i class="fas fa-save mr-1"></i>Save Group</button>
                </div>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
$('#smsBalanceRefresh').on('click', function (e) {
    e.preventDefault();
    var icon = $(this).find('i').addClass('fa-spin');
    $.ajax({ url: '{{ route('sms.gateway-balance') }}', method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
        .done(function (r) {
            $('#smsBalanceValue').text(r.balance);
            $('#smsBalanceNote').text('checked ' + r.checked_at + (r.low ? ' · low' : ''));
        })
        .fail(function (xhr) {
            var r = xhr.responseJSON || {};
            $('#smsBalanceNote').text(r.message || 'Could not reach the gateway.').attr('title', r.raw || '');
        })
        .always(function () { icon.removeClass('fa-spin'); });
});
</script>
@endpush
