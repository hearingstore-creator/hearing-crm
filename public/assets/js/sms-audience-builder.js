/**
 * SMS Audience Builder — 3-Step Wizard
 * Step 1: Select Audience → Step 2: Compose Message → Step 3: Review & Send
 * All filter categories/options loaded dynamically from the database.
 */
(function(){
'use strict';

var BASE = window.SMS_BASE_URL || '/admin';
var CSRF = window.SMS_CSRF || '';

/* ─── State ──────────────────────────────────── */
var state = {
    step: 1,
    type: 'leads',
    quickFilterId: null,
    filters: [],
    results: null,
    config: null,
    messageMode: 'template', // 'template' or 'custom'
    templateId: '',
    templateBody: '',
    customMessage: '',
    campaignName: ''
};

/* ─── AJAX ───────────────────────────────────── */
function ajaxPost(url, data, cb) {
    $.ajax({
        url: BASE + '/' + url, method: 'POST',
        headers: {'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json'},
        contentType: 'application/json', data: JSON.stringify(data),
        success: cb, error: function(xhr) { console.error('AB Error:', xhr.responseText); }
    });
}
function ajaxGet(url, data, cb) {
    $.ajax({ url: BASE + '/' + url, data: data, headers: {'Accept': 'application/json'}, success: cb });
}
function ajaxDel(url, cb) {
    $.ajax({ url: BASE + '/' + url, method: 'DELETE', headers: {'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json'}, success: cb });
}

/* ═══════════════════════════════════════════════
   WIZARD STEP NAVIGATION
   ═══════════════════════════════════════════════ */
function goToStep(n) {
    state.step = n;
    // Show/hide panels
    $('.ab-wizard-step').hide();
    $('#abStep' + n).fadeIn(200);
    // Update step bar
    $('.ab-step').each(function() {
        var s = parseInt($(this).data('step'));
        $(this).removeClass('active done');
        if (s === n) $(this).addClass('active');
        else if (s < n) $(this).addClass('done');
    });
    // Populate step content
    if (n === 2) populateStep2();
    if (n === 3) populateStep3();
    // Scroll to top
    $('html, body').animate({ scrollTop: $('#compose-tab').offset().top - 60 }, 200);
}

/* ─── Step 1 → Step 2: Next button ───────────── */
$('#abNextStep2').on('click', function() {
    if (!state.results || state.results.stats.ready_to_send < 1) {
        if (typeof toastr !== 'undefined') toastr.warning('Select filters with at least 1 valid recipient first');
        return;
    }
    goToStep(2);
});

/* ─── Step 2 → Step 1: Back ──────────────────── */
$('#abBackStep1').on('click', function() { goToStep(1); });

/* ─── Step 2 → Step 3: Next ──────────────────── */
$('#abNextStep3').on('click', function() {
    state.campaignName = $.trim($('#abCampaignName').val());
    if (!state.campaignName) {
        $('#abCampaignName').focus();
        if (typeof toastr !== 'undefined') toastr.warning('Enter a campaign name');
        return;
    }
    var msg = getMessageBody();
    if (!msg) {
        if (typeof toastr !== 'undefined') toastr.warning('Select a template or write a message');
        return;
    }
    goToStep(3);
});

/* ─── Step 3 → Step 2: Back ──────────────────── */
$('#abBackStep2').on('click', function() { goToStep(2); });

/* ═══════════════════════════════════════════════
   STEP 2: COMPOSE
   ═══════════════════════════════════════════════ */
function populateStep2() {
    var r = state.results;
    $('#abStep2Recipients').text(r ? r.stats.total : 0);
    $('#abStep2Ready').text(r ? r.stats.ready_to_send : 0);
    $('#abStep2Audience').text(state.type === 'leads' ? 'Leads' : 'Customers');
    $('#abStep2Filters').text(state.filters.length);
    validateStep2();
}

/* Message source tabs */
$('.ab-msg-tab').on('click', function() {
    var target = $(this).data('target');
    state.messageMode = target;
    $('.ab-msg-tab').removeClass('active');
    $(this).addClass('active');
    $('.ab-msg-panel').hide();
    $('#abMsg' + (target === 'template' ? 'Template' : 'Custom')).show();
    validateStep2();
});

/* Template select */
$('#abTemplateSelect').on('change', function() {
    var opt = $(this).find(':selected');
    state.templateId = $(this).val();
    state.templateBody = opt.data('body') || '';
    if (state.templateBody) {
        $('#abTemplateBody').text(state.templateBody);
        $('#abTemplatePreview').show();
    } else {
        $('#abTemplatePreview').hide();
    }
    validateStep2();
});

/* Custom message */
$('#abCustomMessage').on('input', function() {
    state.customMessage = $(this).val();
    $('#abCharCount').text(state.customMessage.length);
    validateStep2();
});

function getMessageBody() {
    if (state.messageMode === 'template') return state.templateBody;
    return state.customMessage;
}

function validateStep2() {
    var valid = false;
    var name = $.trim($('#abCampaignName').val());
    var msg = getMessageBody();
    if (name && msg) valid = true;
    $('#abNextStep3').prop('disabled', !valid);
}

$('#abCampaignName').on('input', validateStep2);

/* Follow-up toggle */
$('#abFollowUpToggle').on('change', function() {
    $('#abFollowUpFields').toggle(this.checked);
});

/* ═══════════════════════════════════════════════
   STEP 3: REVIEW & SEND
   ═══════════════════════════════════════════════ */
function populateStep3() {
    var r = state.results;
    $('#abReviewName').text(state.campaignName);
    $('#abReviewAudience').text(state.type === 'leads' ? 'Leads' : 'Customers');
    $('#abReviewRecipients').text(r ? r.stats.total : 0);
    $('#abReviewReady').text(r ? r.stats.ready_to_send : 0);
    $('#abReviewMessage').text(getMessageBody());

    // Show filter summary
    var filterHtml = '';
    state.filters.forEach(function(f) {
        var labels = f._labels || f.values || [f.value];
        filterHtml += '<div class="ab-b-row" style="margin-bottom:4px;">' +
            '<span class="ab-b-cat" style="color:' + (f.catColor || '#666') + '">' + esc(f.catLabel || f.field) + '</span>' +
            '<span class="ab-b-op">' + esc(f.op) + '</span>' +
            '<span class="ab-b-val">' + labels.map(function(l) { return '<span class="ab-v-chip">' + esc(l) + '</span>'; }).join(' ') + '</span>' +
        '</div>';
    });
    $('#abReviewFilters').html(filterHtml || '<p class="text-muted" style="font-size:12px;">No filters — all records</p>');

    // Follow-up info
    var fuEnabled = $('#abFollowUpToggle').is(':checked');
    if (fuEnabled) {
        var fuDays = $('#abFollowUpDays').val() || 3;
        var fuMode = $('#abFollowUpMode option:selected').text();
        var fuUser = $('#abFollowUpUser option:selected').text();
        filterHtml += '<div class="ab-b-row" style="margin-top:8px;border-top:1px dashed var(--gray-200);padding-top:8px;">' +
            '<span class="ab-b-cat" style="color:var(--success-600);">Follow-up</span>' +
            '<span class="ab-b-op">After ' + fuDays + ' days</span>' +
            '<span class="ab-b-val"><span class="ab-v-chip">' + esc(fuMode) + '</span><span class="ab-v-chip">' + esc(fuUser) + '</span></span>' +
        '</div>';
        $('#abReviewFilters').html(filterHtml);
    }

    // Populate hidden form
    $('#abFormCampaignName').val(state.campaignName);
    $('#abFormMessageBody').val(getMessageBody());
    $('#abFormAudienceType').val(state.type);
    $('#abFormTemplateId').val(state.templateId || '');
    $('#abFormFilterCriteria').val(JSON.stringify(state.filters.map(function(f) {
        return { field: f.field, op: f.op, values: f.values, value: f.value };
    })));

    // Follow-up hidden fields
    $('#abFormFollowUp').val(fuEnabled ? '1' : '0');
    $('#abFormFollowUpDays').val(fuEnabled ? ($('#abFollowUpDays').val() || 3) : '');
    $('#abFormFollowUpUser').val(fuEnabled ? ($('#abFollowUpUser').val() || '') : '');
    $('#abFormFollowUpMode').val(fuEnabled ? ($('#abFollowUpMode').val() || '') : '');
}

/* ═══════════════════════════════════════════════
   STEP 1: AUDIENCE BUILDER (filter logic)
   ═══════════════════════════════════════════════ */

/* ─── Load Config ────────────────────────────── */
function loadConfig(cb) {
    ajaxGet('sms/audience-filter-config', { type: state.type }, function(data) {
        state.config = data;
        if (cb) cb();
    });
}

/* ─── Type Switch ────────────────────────────── */
$('#abTypeSwitch').on('click', '.ab-type-btn', function() {
    var t = $(this).data('type');
    if (t === state.type) return;
    state.type = t;
    state.quickFilterId = null;
    state.filters = [];
    state.config = null;
    $('#abTypeSwitch .ab-type-btn').removeClass('active');
    $(this).addClass('active');
    loadConfig(function() { renderAll(); });
});

/* ─── Quick Filters ──────────────────────────── */
function renderQuickFilters() {
    var wrap = $('#abQuickFilters').empty();
    if (!state.config || !state.config.quick_filters) {
        wrap.html('<span class="text-muted" style="font-size:13px;"><i class="fas fa-spinner fa-spin mr-1"></i> Loading...</span>');
        return;
    }
    state.config.quick_filters.forEach(function(qf) {
        var isActive = state.quickFilterId === qf.id;
        var chip = $('<button class="ab-qf-chip' + (isActive ? ' active' : '') + '">' +
            '<span>' + esc(qf.label) + '</span><span class="ab-qf-count">' + (qf.count != null ? qf.count : '—') + '</span></button>');
        chip.on('click', function() {
            if (state.quickFilterId === qf.id) {
                state.quickFilterId = null;
                state.filters = state.filters.filter(function(f) { return !f._qf; });
            } else {
                state.filters = state.filters.filter(function(f) { return !f._qf; });
                state.quickFilterId = qf.id;
                qf.filters.forEach(function(f) {
                    state.filters.push($.extend({}, f, { _qf: qf.id, catLabel: 'Quick Filter', catColor: 'var(--warning-500)', _labels: [qf.label] }));
                });
            }
            recompute();
        });
        wrap.append(chip);
    });
}

/* ─── Advanced Filters ───────────────────────── */
function renderAccordion() {
    var wrap = $('#abAdvancedFilters').empty();
    if (!state.config || !state.config.categories) return;
    var acc = $('<div class="ab-accordion"></div>');
    state.config.categories.forEach(function(cat) {
        var isActive = state.filters.some(function(f) { return f.field === cat.id && !f._qf; });
        var details = $('<details class="ab-acc-item">' +
            '<summary><span class="ab-acc-swatch" style="background:' + cat.color + '"></span>' +
            '<span class="ab-acc-title">' + esc(cat.label) + '</span>' +
            '<span class="ab-acc-active-badge' + (isActive ? ' show' : '') + '">active</span>' +
            '<i class="fas fa-chevron-down ab-acc-chevron"></i></summary>' +
            '<div class="ab-acc-body"></div></details>');
        buildCategoryBody(cat, details.find('.ab-acc-body'));
        acc.append(details);
    });
    wrap.append(acc);
}

function buildCategoryBody(cat, body) {
    if (cat.kind === 'chip') {
        if (!cat.options || !cat.options.length) {
            body.append('<p class="text-muted" style="font-size:12px;">No options available.</p>');
            return;
        }
        var row = $('<div class="ab-chip-row"></div>');
        var selected = getChipSelection(cat.id);
        cat.options.forEach(function(opt) {
            var chip = $('<button class="ab-chip' + (selected.indexOf(opt.v) >= 0 ? ' active' : '') + '">' + esc(opt.l) + '</button>');
            chip.on('click', function() { toggleChipValue(cat, opt); });
            row.append(chip);
        });
        body.append(row);
    } else if (cat.kind === 'op') {
        var opRow = $('<div class="ab-op-row"></div>');
        if (cat.opType === 'warranty') {
            opRow.html('<div class="ab-op-field"><label>Within Days</label><input type="number" id="ab_op_' + cat.id + '" placeholder="30" min="1" max="365"></div>');
        } else if (cat.opType === 'days') {
            opRow.html('<div class="ab-op-field"><label>Operator</label><select id="ab_opsel_' + cat.id + '"><option value="lte">Less Than</option><option value="gte">Greater Than</option><option value="between">Between</option></select></div>' +
                '<div class="ab-op-field"><label>Days</label><input type="number" id="ab_op_' + cat.id + '" placeholder="30"></div>' +
                '<div class="ab-op-field" id="ab_op2wrap_' + cat.id + '" style="display:none;"><label>And</label><input type="number" id="ab_op2_' + cat.id + '" placeholder="90"></div>');
        } else {
            opRow.html('<div class="ab-op-field"><label>Operator</label><select id="ab_opsel_' + cat.id + '"><option value="eq">Equals</option><option value="gte">Greater Than</option><option value="lte">Less Than</option><option value="between">Between</option></select></div>' +
                '<div class="ab-op-field"><label>Value</label><input type="number" id="ab_op_' + cat.id + '"></div>' +
                '<div class="ab-op-field" id="ab_op2wrap_' + cat.id + '" style="display:none;"><label>And</label><input type="number" id="ab_op2_' + cat.id + '"></div>');
        }
        var applyBtn = $('<button class="ab-op-apply"><i class="fas fa-check mr-1"></i>Apply</button>');
        applyBtn.on('click', function() { applyOpFilter(cat); });
        opRow.append(applyBtn);
        body.append(opRow);
        body.on('change', '#ab_opsel_' + cat.id, function() {
            $('#ab_op2wrap_' + cat.id).toggle($(this).val() === 'between');
        });
    }
}

/* ─── Chip Logic ─────────────────────────────── */
function getChipSelection(fieldId) {
    var f = state.filters.find(function(x) { return x.field === fieldId && !x._qf; });
    return f ? (f.values || []) : [];
}

function toggleChipValue(cat, opt) {
    var idx = -1;
    for (var i = 0; i < state.filters.length; i++) {
        if (state.filters[i].field === cat.id && !state.filters[i]._qf) { idx = i; break; }
    }
    var values = idx >= 0 ? state.filters[idx].values.slice() : [];
    var vi = values.indexOf(opt.v);
    if (vi >= 0) values.splice(vi, 1); else values.push(opt.v);
    if (!values.length && idx >= 0) {
        state.filters.splice(idx, 1);
    } else if (values.length) {
        var labels = values.map(function(v) { var o = cat.options.find(function(x) { return x.v === v; }); return o ? o.l : v; });
        var filter = { field: cat.id, op: 'in', values: values, catLabel: cat.label, catColor: cat.color, _labels: labels };
        if (idx >= 0) state.filters[idx] = filter; else state.filters.push(filter);
    }
    recompute();
}

function applyOpFilter(cat) {
    var op = cat.opType === 'warranty' ? 'lte' : ($('#ab_opsel_' + cat.id).val() || 'eq');
    var v1 = parseFloat($('#ab_op_' + cat.id).val());
    if (isNaN(v1)) return;
    var v2 = op === 'between' ? parseFloat($('#ab_op2_' + cat.id).val()) : null;
    if (op === 'between' && isNaN(v2)) return;
    state.filters = state.filters.filter(function(f) { return f.field !== cat.id || f._qf; });
    var sym = { eq: '=', gte: '≥', lte: '≤', between: '' };
    var label = (cat.opType === 'warranty' ? '≤ ' + v1 + ' days' :
        (sym[op] || '') + ' ' + v1 + (op === 'between' ? ' – ' + v2 : '') + (cat.opType === 'days' ? ' days' : ''));
    state.filters.push({ field: cat.id, op: op, value: v1, values: op === 'between' ? [v1, v2] : [], catLabel: cat.label, catColor: cat.color, _labels: [label] });
    recompute();
}

/* ─── Filter Builder ─────────────────────────── */
function renderBuilder() {
    var wrap = $('#abBuilderRows').empty();
    if (!state.filters.length) {
        wrap.html('<p class="text-muted" style="font-size:13px;"><i class="fas fa-funnel-dollar mr-1"></i> No filters yet. Pick a quick filter or open advanced category.</p>');
        return;
    }
    state.filters.forEach(function(f, i) {
        if (i > 0) wrap.append('<div class="ab-and-divider">AND</div>');
        var labels = f._labels || f.values || [f.value];
        var valHtml = labels.map(function(l, j) {
            return (j > 0 ? '<span class="ab-v-or">OR</span>' : '') + '<span class="ab-v-chip">' + esc(l) + '</span>';
        }).join('');
        var row = $('<div class="ab-b-row">' +
            '<span class="ab-b-cat" style="color:' + (f.catColor || '#666') + '">' + esc(f.catLabel || f.field) + '</span>' +
            '<span class="ab-b-op">' + esc(f.op === 'in' ? (labels.length > 1 ? 'Is any of' : 'Is') : f.op) + '</span>' +
            '<span class="ab-b-val">' + valHtml + '</span>' +
            '<button class="ab-b-remove" title="Remove"><i class="fas fa-times"></i></button></div>');
        (function(index, filter) {
            row.find('.ab-b-remove').on('click', function() {
                state.filters.splice(index, 1);
                if (filter._qf) state.quickFilterId = null;
                recompute();
            });
        })(i, f);
        wrap.append(row);
    });
}

/* ─── Summary ────────────────────────────────── */
function renderSummary(data) {
    $('#abSummaryType').text(state.type === 'leads' ? 'Leads' : 'Customers');
    if (!data) { $('#abSummaryCount').text('0'); $('#abValidPhones,#abMissingPhones,#abReadyToSend').text('0'); updateNextBtn(0); return; }
    $('#abSummaryCount').text(data.stats.total);
    $('#abValidPhones').text(data.stats.valid_phones);
    $('#abMissingPhones').text(data.stats.missing_phones);
    $('#abReadyToSend').text(data.stats.ready_to_send);
    state.results = data;
    updateNextBtn(data.stats.ready_to_send);
}

function updateNextBtn(readyCount) {
    $('#abNextStep2').prop('disabled', readyCount < 1);
}

/* ─── Recompute ──────────────────────────────── */
var recomputeTimer = null;
function recompute() {
    renderQuickFilters();
    renderAccordion();
    renderBuilder();
    clearTimeout(recomputeTimer);
    recomputeTimer = setTimeout(function() {
        ajaxPost('sms/audience-filter', {
            type: state.type,
            filters: state.filters.map(function(f) { return { field: f.field, op: f.op, values: f.values, value: f.value }; })
        }, function(data) { renderSummary(data); });
    }, 200);
}

/* ─── Saved Groups ───────────────────────────── */
function renderSavedGroups() {
    ajaxGet('sms/filter-groups', { type: state.type }, function(data) {
        var wrap = $('#abSavedGroups').empty();
        if (!data.groups || !data.groups.length) {
            wrap.html('<p class="ab-empty-text">No saved groups yet.</p>');
            return;
        }
        data.groups.forEach(function(g) {
            var criteria = g.filter_criteria || [];
            var item = $('<div class="ab-saved-item"><div style="min-width:0;flex:1;"><span class="ab-saved-name">' + esc(g.name) + '</span><span class="ab-saved-meta">' + criteria.length + ' filter(s)</span></div><button class="ab-saved-del" title="Delete"><i class="fas fa-times"></i></button></div>');
            item.on('click', function(e) {
                if ($(e.target).closest('.ab-saved-del').length) return;
                state.filters = criteria.map(function(f) { return $.extend({}, f, { catLabel: f.catLabel || f.field, catColor: f.catColor || '#666', _labels: f._labels || f.values || [f.value] }); });
                state.quickFilterId = null;
                recompute();
            });
            item.find('.ab-saved-del').on('click', function() {
                if (!confirm('Delete this group?')) return;
                ajaxDel('sms/filter-groups/' + g.id, function() { renderSavedGroups(); });
            });
            wrap.append(item);
        });
    });
}

function renderAll() {
    renderQuickFilters();
    renderAccordion();
    renderBuilder();
    renderSavedGroups();
    recompute();
}

/* ═══════════════════════════════════════════════
   VIEW RECIPIENTS MODAL (fixed — explicit AJAX call)
   ═══════════════════════════════════════════════ */
$('#abViewRecipients').on('click', function() {
    $('#abRecipientRows').empty();
    $('#abRecipientMore').text('');
    $('#abRecipientLoading').show();
    $('#abViewModal').modal('show');

    // Make a fresh backend call to get recipients
    ajaxPost('sms/audience-filter', {
        type: state.type,
        filters: state.filters.map(function(f) { return { field: f.field, op: f.op, values: f.values, value: f.value }; })
    }, function(data) {
        $('#abRecipientLoading').hide();
        if (!data.preview || !data.preview.length) {
            $('#abRecipientRows').html('<tr><td colspan="4" class="text-center text-muted py-3">No recipients match current filters.</td></tr>');
            return;
        }
        var tbody = $('#abRecipientRows');
        data.preview.forEach(function(r) {
            var badge = r.status === 'ok'
                ? '<span class="badge badge-success" style="font-size:10px;">Sendable</span>'
                : '<span class="badge badge-danger" style="font-size:10px;">No Mobile</span>';
            tbody.append('<tr><td style="font-weight:600;">' + esc(r.name) + '</td><td style="font-family:monospace;font-size:12px;">' + esc(r.phone || '—') + '</td><td>' + esc(r.segment) + '</td><td>' + badge + '</td></tr>');
        });
        $('#abViewModalTitle').html('<i class="fas fa-users mr-2"></i>' + data.total + ' Recipients');
        if (data.total > 50) $('#abRecipientMore').text('+ ' + (data.total - 50) + ' more not shown in preview');
    });
});

/* ═══════════════════════════════════════════════
   SAVE FILTER GROUP
   ═══════════════════════════════════════════════ */
$('#abSaveGroup').on('click', function() {
    if (!state.filters.length) { if (typeof toastr !== 'undefined') toastr.warning('Add at least one filter first'); return; }
    $('#abGroupName').val('');
    $('#abSavePreview').text(state.filters.map(function(f) { return (f.catLabel || f.field) + ' ' + f.op + ' ' + (f._labels || f.values || [f.value]).join(', '); }).join(' • '));
    $('#abSaveModal').modal('show');
    setTimeout(function() { $('#abGroupName').focus(); }, 200);
});

$('#abConfirmSave').on('click', function() {
    var name = $.trim($('#abGroupName').val());
    if (!name) { $('#abGroupName').focus(); return; }
    ajaxPost('sms/filter-groups', { name: name, recipient_type: state.type, filter_criteria: state.filters }, function() {
        $('#abSaveModal').modal('hide');
        renderSavedGroups();
        if (typeof toastr !== 'undefined') toastr.success('Saved "' + name + '"');
    });
});

/* ─── Clear ──────────────────────────────────── */
$('#abClearAll').on('click', function() {
    state.filters = [];
    state.quickFilterId = null;
    recompute();
});

/* ─── Helpers ────────────────────────────────── */
function esc(s) {
    if (s == null) return '';
    var d = document.createElement('div'); d.textContent = String(s); return d.innerHTML;
}

/* ─── Init ───────────────────────────────────── */
$(function() {
    loadConfig(function() { renderAll(); });
});

})();
