/**
 * Deals board: drag & drop (SortableJS), stage gates, Won / Lost / Transfer, "Move to" menu, load more.
 * List view: rows open the same drawer; tick rows for bulk transfer / owner / SMS / lost.
 * Server rules live in DealController; this file only collects input and keeps the board in sync.
 */
(function ($) {
    'use strict';

    var B = window.DealBoard;
    if (!B) { return; }

    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': B.csrf, 'Accept': 'application/json' } });

    var $body = $('body');
    var pending = null;      // { card, from, oldIndex } – where to put the card back if the action is cancelled
    var submitted = false;   // the open modal finished successfully (do not revert on close)

    // ------------------------------------------------------------------ helpers
    function stageById(id) {
        id = +id;
        for (var i = 0; i < B.stages.length; i++) { if (B.stages[i].id === id) { return B.stages[i]; } }
        return null;
    }
    function money(v) { return '৳' + Math.round(+v || 0).toLocaleString('en-US'); }
    function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }

    function toast(msg, type) {
        var icon = type === 'err' ? 'fa-exclamation-circle' : (type === 'info' ? 'fa-info-circle' : 'fa-check-circle');
        var $t = $('<div class="deal-toast ' + (type || 'ok') + '"><i class="fas ' + icon + '" style="margin-top:2px"></i><span>' + esc(msg) + '</span></div>');
        $('#deal-toasts').append($t);
        setTimeout(function () { $t.fadeOut(250, function () { $t.remove(); }); }, type === 'err' ? 5000 : 3200);
    }

    function bump(stageId, dCount, dValue) {
        var $col = $('#stage-col-' + stageId);
        if (!$col.length) { return; }
        var $c = $col.find('.deal-col-head [data-count]');
        $c.text(Math.max(0, (+$c.text() || 0) + dCount));
        var $list = $col.find('.deal-col-body');
        var total = (+$list.attr('data-total') || 0) + dValue;
        $list.attr('data-total', total);
        $col.find('.deal-col-head [data-value]').text(money(total)); // header only: cards carry data-value too
    }

    function revert() {
        if (!pending) { return; }
        var p = pending;
        pending = null;
        if (p.from && p.card) {
            p.from.insertBefore(p.card, p.from.children[p.oldIndex] || null);
            $(p.card).removeClass('is-busy');
        }
    }

    function remember(card) {
        var from = card.parentNode;
        pending = { card: card, from: from, oldIndex: Array.prototype.indexOf.call(from.children, card) };
    }

    function errorsOf(xhr) {
        var r = xhr.responseJSON || {};
        if (r.errors) {
            return $.map(r.errors, function (list) { return list; });
        }
        return [r.message || 'Something went wrong. Please try again.'];
    }

    function openModal(id, card) {
        submitted = false;
        var $m = $(id);
        $m.find('form')[0].reset();
        $m.find('.deal-errors').empty();
        $m.find('[data-deal-name]').text(card ? card.getAttribute('data-name') : '');
        $m.data('card', card).modal('show');
        return $m;
    }

    function done($modal) {
        submitted = true;
        pending = null;
        if ($modal) { $modal.modal('hide'); }
    }

    function fail(xhr, $modal, $card) {
        if ($card) { $card.removeClass('is-busy'); }
        if (xhr.status === 409) {
            toast(errorsOf(xhr)[0], 'info');
            setTimeout(function () { window.location.reload(); }, 1400);
            return;
        }
        if ($modal && $modal.hasClass('show') && xhr.status === 422) {
            $modal.find('.deal-errors').html(errorsOf(xhr).map(function (e) { return '<div>' + esc(e) + '</div>'; }).join(''));
            return;
        }
        if ($modal) { $modal.modal('hide'); }
        revert();
        toast(errorsOf(xhr)[0], 'err');
    }

    function busy($btn, on) {
        $btn.prop('disabled', on);
        if (on) { $btn.data('html', $btn.html()).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving…'); }
        else if ($btn.data('html')) { $btn.html($btn.data('html')); }
    }

    // Cancelled modal = put the card back where it was
    $('.deal-modal').on('hidden.bs.modal', function () {
        if (!submitted) { revert(); }
        submitted = false;
    });

    // ------------------------------------------------------------------ move
    function sendMove(card, stageId, extra, $modal, $btn) {
        var $card = $(card);
        var oldStage = +card.getAttribute('data-stage');
        var oldValue = +card.getAttribute('data-value') || 0;
        var order = $card.parent().children('.deal-card').map(function () { return +this.getAttribute('data-id'); }).get();

        $card.addClass('is-busy');
        if ($btn) { busy($btn, true); }

        return $.post(B.base + '/' + card.getAttribute('data-id') + '/move', $.param($.extend({
            stage_id: stageId, updated_at: card.getAttribute('data-updated'), order: order
        }, extra || {})))
            .done(function (res) {
                var $new = $(res.html);
                $card.replaceWith($new);
                $new.addClass('is-flash');
                if (oldStage !== +stageId) {
                    bump(oldStage, -1, -oldValue);
                    bump(stageId, 1, +res.value || 0);
                    toast('Moved to ' + (stageById(stageId) || {}).name);
                } else if ((+res.value || 0) !== oldValue) {
                    bump(stageId, 0, (+res.value || 0) - oldValue);
                }
                done($modal);
            })
            .fail(function (xhr) { fail(xhr, $modal, $card); })
            .always(function () { if ($btn) { busy($btn, false); } });
    }

    /** Decide whether the target stage needs input (gate) or a "why back?" note, else move directly. */
    function requestMove(card, toStageId) {
        var from = stageById(card.getAttribute('data-stage'));
        var to = stageById(toStageId);
        if (!from || !to) { revert(); return; }

        if (to.order <= from.order) { openGate(card, to, 'back'); return; }

        var hasInquiry = (JSON.parse(card.getAttribute('data-inquiry') || '[]') || []).length > 0;
        var hasValue = (+card.getAttribute('data-value') || 0) > 0;
        var needsInput = (to.required || []).some(function (f) {
            if (f === 'inquiry_for') { return !hasInquiry; }
            if (f === 'value') { return !hasValue; }
            return true;
        });

        if (needsInput) { openGate(card, to, 'forward'); } else { sendMove(card, to.id, {}); }
    }

    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function isoDate(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }

    function openGate(card, to, mode) {
        var $m = openModal('#gateModal', card);
        var req = mode === 'back' ? ['note'] : (to.required || []);
        $m.data({ stage: to.id, mode: mode });

        $m.find('[data-gate]').each(function () {
            var f = this.getAttribute('data-gate');
            $(this).toggle(req.indexOf(f) !== -1 || (f === 'note' && mode === 'forward'));
        });

        var noteRequired = mode === 'back' || req.indexOf('note') !== -1;
        $('#gateNoteLabel').text(mode === 'back' ? 'Why is this deal moving back?' : (noteRequired ? 'Counselling note' : 'Note (optional)'));
        $('#gateNoteReq').toggle(noteRequired);
        $('#gateTitle').text((mode === 'back' ? 'Move back to ' : 'Move to ') + to.name);
        $('#gateSub').text(card.getAttribute('data-name'));
        $('#gateIcon').css('background', to.color).html('<i class="fas ' + (mode === 'back' ? 'fa-undo' : 'fa-arrow-right') + '"></i>');

        // Prefill from the deal
        var interests = JSON.parse(card.getAttribute('data-inquiry') || '[]') || [];
        $m.find('input[name="inquiry_for[]"]').each(function () { this.checked = interests.indexOf(this.value) !== -1; });
        var v = +card.getAttribute('data-value') || 0;
        $('#gate-value').val(v > 0 ? Math.round(v) : '');
        var week = new Date(); week.setDate(week.getDate() + 7);
        $('#gate-trial-return').val(isoDate(week)).attr('min', isoDate(new Date()));
        var tomorrow = new Date(); tomorrow.setDate(tomorrow.getDate() + 1);
        $('#gate-appointment-at').val(isoDate(tomorrow) + 'T11:00').prop('disabled', false);
        setTimeout(function () { $m.find('[data-gate]:visible').find('input:visible,textarea:visible').first().trigger('focus'); }, 350);
    }

    $('#gateModal').on('change', 'input[name="visit_type"]', function () {
        $('#gate-appointment-at').prop('disabled', this.value === 'walk_in');
    });

    $('#gateForm').on('submit', function (e) {
        e.preventDefault();
        var $m = $('#gateModal');
        var card = $m.data('card');
        var data = {};
        $m.find('[data-gate]:visible').each(function () {
            var f = this.getAttribute('data-gate');
            if (f === 'inquiry_for') {
                data.inquiry_for = $(this).find('input:checked').map(function () { return this.value; }).get();
            } else if (f === 'appointment_or_walkin') {
                if ($m.find('input[name="visit_type"]:checked').val() === 'walk_in') { data.walk_in = 1; }
                else { data.appointment_at = $('#gate-appointment-at').val(); }
            } else {
                var $input = $(this).find('input,textarea').first();
                data[$input.attr('name')] = $input.val();
            }
        });
        sendMove(card, $m.data('stage'), data, $m, $('#gateSubmit'));
    });

    // ------------------------------------------------------------------ won / lost / transfer
    function closeCard(card, res, $modal) {
        var stageId = +card.getAttribute('data-stage');
        bump(stageId, -1, -(+card.getAttribute('data-value') || 0));
        $(card).remove();
        done($modal);
        toast(res.message || 'Saved');
    }

    function openWon(card) { openModal('#wonModal', card); $('#won-create-sale').prop('checked', true); }
    function openLost(card) { openModal('#lostModal', card); }
    function openTransfer(card) {
        var $m = openModal('#transferModal', card);
        $('#transfer-branch').val(card.getAttribute('data-vendor') || '').trigger('change');
        $m.find('#transfer-owner').val(card.getAttribute('data-owner'));
    }

    $('#transfer-branch').on('change', function () {
        var list = B.owners[this.value || 'hq'] || [];
        var $o = $('#transfer-owner').empty();
        if (!list.length) { $o.append('<option value="">No active staff in this branch</option>'); }
        list.forEach(function (u) { $o.append($('<option>').val(u.id).text(u.name)); });
    });

    function postAction(formId, url, $btn, onDone) {
        var $m = $(formId).closest('.modal');
        var card = $m.data('card');
        busy($btn, true);
        $.post(B.base + '/' + card.getAttribute('data-id') + '/' + url, $(formId).serialize())
            .done(function (res) { onDone(card, res, $m); })
            .fail(function (xhr) { fail(xhr, $m, null); })
            .always(function () { busy($btn, false); });
    }

    $('#wonForm').on('submit', function (e) {
        e.preventDefault();
        postAction('#wonForm', 'won', $('#wonSubmit'), function (card, res, $m) {
            closeCard(card, res, $m);
            if (res.redirect) { setTimeout(function () { window.location = res.redirect; }, 700); }
        });
    });

    $('#lostForm').on('submit', function (e) {
        e.preventDefault();
        postAction('#lostForm', 'lost', $('#lostSubmit'), closeCard);
    });

    $('#transferForm').on('submit', function (e) {
        e.preventDefault();
        postAction('#transferForm', 'transfer', $('#transferSubmit'), function (card, res, $m) {
            revert(); // the card stays in its stage
            done($m);
            toast(res.message);
            if (isRow(card)) { reloadSoon(); return; }
            if (res.html) { var $n = $(res.html); $(card).replaceWith($n); $n.addClass('is-flash'); }
        });
    });

    // ------------------------------------------------------------------ drag & drop
    var canDrag = B.can.move || B.can.won || B.can.lost || B.can.transfer;
    if (canDrag && window.Sortable) {
        document.querySelectorAll('.deal-col-body').forEach(function (list) {
            Sortable.create(list, {
                group: { name: 'deals', pull: true, put: !!B.can.move },
                sort: !!B.can.move,
                draggable: '.deal-card',
                filter: '.dc-menu-btn',
                preventOnFilter: false,
                animation: 160,
                delay: 150,
                delayOnTouchOnly: true,
                ghostClass: 'deal-ghost',
                chosenClass: 'deal-chosen',
                onStart: function () { $body.addClass('is-dragging-deal'); closeMenu(); },
                onMove: function (evt) {
                    $('.deal-col').removeClass('is-target');
                    $(evt.to).closest('.deal-col').addClass('is-target');
                    $('.deal-drop').removeClass('is-over');
                    $(evt.to).closest('.deal-drop').addClass('is-over');
                },
                onEnd: function (evt) {
                    lastDrag = Date.now(); // the click that follows a drop must not open the drawer
                    $body.removeClass('is-dragging-deal');
                    $('.deal-col').removeClass('is-target');
                    $('.deal-drop').removeClass('is-over');
                    handleDrop(evt);
                }
            });
        });
        document.querySelectorAll('.deal-drop-list').forEach(function (zone) {
            Sortable.create(zone, { group: { name: 'deals', pull: false, put: true }, sort: false });
        });
    }

    function handleDrop(evt) {
        var card = evt.item;
        if (evt.to === evt.from && evt.oldIndex === evt.newIndex) { return; }
        pending = { card: card, from: evt.from, oldIndex: evt.oldIndex };

        var zone = evt.to.getAttribute('data-drop');
        if (zone === 'won') { openWon(card); return; }
        if (zone === 'lost') { openLost(card); return; }
        if (zone === 'transfer') { openTransfer(card); return; }

        var toStage = +evt.to.getAttribute('data-stage');
        if (toStage === +card.getAttribute('data-stage')) {
            pending = null;
            sendMove(card, toStage, {}); // new position in the same column
            return;
        }
        requestMove(card, toStage);
    }

    // ------------------------------------------------------------------ "Move to" menu (also the mobile way to move)
    var $menu = $('#deal-menu');
    function closeMenu() { $menu.removeClass('show').empty(); }

    $(document).on('click', '.dc-menu-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var card = $(this).closest('.deal-card')[0];
        if ($menu.hasClass('show') && $menu.data('card') === card) { closeMenu(); return; }

        var current = +card.getAttribute('data-stage');
        var html = '';
        if (B.can.move) {
            html += '<h6>Move to</h6>';
            B.stages.forEach(function (s) {
                html += '<button type="button" data-act="move" data-stage="' + s.id + '"' + (s.id === current ? ' disabled' : '') + '>' +
                    '<span class="m-dot" style="background:' + esc(s.color) + '"></span>' + esc(s.name) + (s.id === current ? ' <small>(current)</small>' : '') + '</button>';
            });
        }
        if (B.can.won || B.can.lost || B.can.transfer) { html += B.can.move ? '<hr>' : ''; }
        if (B.can.won) { html += '<button type="button" class="m-won" data-act="won"><i class="fas fa-trophy"></i>Mark won</button>'; }
        if (B.can.lost) { html += '<button type="button" class="m-lost" data-act="lost"><i class="fas fa-times-circle"></i>Mark lost</button>'; }
        if (B.can.transfer) { html += '<button type="button" data-act="transfer"><i class="fas fa-share-square"></i>Transfer to branch</button>'; }

        var off = $(this).offset();
        $menu.html(html).data('card', card).addClass('show');
        var left = Math.min(off.left - $menu.outerWidth() + 26, $(window).width() - $menu.outerWidth() - 12);
        $menu.css({ top: off.top + 30, left: Math.max(12, left) });
    });

    $menu.on('click', 'button[data-act]', function () {
        var card = $menu.data('card');
        var act = this.getAttribute('data-act');
        var stageId = +this.getAttribute('data-stage');
        closeMenu();
        if (!card) { return; }
        remember(card);
        if (act === 'move') {
            $('#stage-col-' + stageId).find('.deal-col-body').prepend(card);
            requestMove(card, stageId);
        } else if (act === 'won') { openWon(card); }
        else if (act === 'lost') { openLost(card); }
        else if (act === 'transfer') { openTransfer(card); }
    });

    $(document).on('click', function (e) { if (!$(e.target).closest('#deal-menu').length) { closeMenu(); } });
    $(document).on('keydown', function (e) { if (e.key === 'Escape') { closeMenu(); } });
    $('#deal-board').on('scroll', closeMenu);

    // ------------------------------------------------------------------ load more
    $(document).on('click', '.deal-col-more', function () {
        var $btn = $(this);
        var stageId = $btn.data('stage');
        var $list = $('#stage-col-' + stageId).find('.deal-col-body');
        busy($btn, true);
        $.get(B.base + '/column/' + stageId, $.extend({ offset: $list.children('.deal-card').length }, B.query))
            .done(function (res) {
                $list.append(res.html);
                if (!res.has_more) { $btn.remove(); return; }
                busy($btn, false);
                var total = +$('#stage-col-' + stageId).find('.deal-col-head [data-count]').text() || 0;
                $btn.text('Show more (' + Math.max(0, total - $list.children('.deal-card').length) + ')');
            })
            .fail(function (xhr) { busy($btn, false); toast(errorsOf(xhr)[0], 'err'); });
    });

    // ------------------------------------------------------------------ reopen (Won / Lost lists)
    $(document).on('click', '.js-reopen', function () {
        var $btn = $(this);
        if (!window.confirm('Reopen this deal? It goes back to its last stage on the board.')) { return; }
        busy($btn, true);
        $.post(B.base + '/' + $btn.data('id') + '/reopen')
            .done(function (res) {
                toast(res.message);
                $btn.closest('tr').fadeOut(250, function () { $(this).remove(); });
            })
            .fail(function (xhr) { busy($btn, false); toast(errorsOf(xhr)[0], 'err'); });
    });

    // ------------------------------------------------------------------ deal drawer
    var $drawer = $('#deal-drawer');
    var $backdrop = $('#deal-drawer-backdrop');
    var drawerId = null;
    var lastDrag = 0;

    var listDirty = false;   // list view: something changed in the drawer → reload when it closes

    function cardOf(id) { return document.querySelector('.deal-card[data-id="' + id + '"], .deal-row[data-id="' + id + '"]'); }
    function isRow(el) { return el && el.classList.contains('deal-row'); }
    function reloadSoon() { setTimeout(function () { window.location.reload(); }, 900); }

    function openDrawer(id, tab) {
        drawerId = +id;
        if (!$drawer.hasClass('show')) { $drawer.html('<div class="dd-loading"><i class="fas fa-spinner fa-spin"></i> Loading…</div>'); }
        $drawer.addClass('show');
        $backdrop.addClass('show');
        return $.ajax({ url: B.base + '/' + id, headers: { Accept: 'text/html' } })
            .done(function (html) {
                $drawer.html(html);
                if (tab) { $drawer.find('[data-tab="' + tab + '"]').trigger('click'); }
                syncActivityForm();
            })
            .fail(function (xhr) { closeDrawer(); toast(errorsOf(xhr)[0], 'err'); });
    }

    function closeDrawer() {
        drawerId = null;
        $drawer.removeClass('show');
        $backdrop.removeClass('show');
        if (listDirty) { window.location.reload(); }
    }

    /** Replace the board card with fresh HTML from the server (chip, value, etc.). List rows reload instead. */
    function refreshCard(res) {
        var card = cardOf(drawerId);
        if (isRow(card)) { listDirty = true; return; }
        if (card && res.html) { var $n = $(res.html); $(card).replaceWith($n); $n.addClass('is-flash'); }
    }

    $(document).on('click', '.deal-card, .deal-row', function (e) {
        if (Date.now() - lastDrag < 300 || $(e.target).closest('a, button, input, .dc-menu-btn, .dl-check').length) { return; }
        openDrawer(this.getAttribute('data-id'));
    });
    $(document).on('keydown', '.deal-card, .deal-row', function (e) {
        if (e.key === 'Enter' && e.target === this) { openDrawer(this.getAttribute('data-id')); }
    });
    $backdrop.on('click', closeDrawer);
    $drawer.on('click', '#dd-close', closeDrawer);
    $(document).on('keydown', function (e) { if (e.key === 'Escape' && drawerId && !$('.modal.show').length) { closeDrawer(); } });

    // Deep link: ?deal=ID opens that deal's drawer
    var linked = new URLSearchParams(window.location.search).get('deal');
    if (linked && /^\d+$/.test(linked)) { openDrawer(linked); }

    // Click-to-call / WhatsApp: the link opens the app; the contact is logged on the deal in the background
    $(document).on('click', '.js-contact', function () {
        var id = this.getAttribute('data-deal');
        var channel = this.getAttribute('data-channel');
        $.post(B.base + '/' + id + '/contact', { channel: channel })
            .done(function (res) {
                toast(res.message);
                var card = cardOf(id);
                if (isRow(card)) { listDirty = true; } else if (card && res.html) { $(card).replaceWith(res.html); }
            });
    });

    $drawer.on('click', '[data-tab]', function () {
        var tab = this.getAttribute('data-tab');
        $drawer.find('[data-tab]').removeClass('active');
        $(this).addClass('active');
        $drawer.find('[data-pane]').removeClass('active').filter('[data-pane="' + tab + '"]').addClass('active');
    });

    // Won / Lost from the drawer reuse the board modals with the matching card
    $drawer.on('click', '[data-act]', function () {
        var card = cardOf(drawerId);
        if (!card) { toast('Open this deal on the board to close it.', 'info'); return; }
        listDirty = false;
        closeDrawer();
        if (this.getAttribute('data-act') === 'won') { openWon(card); } else { openLost(card); }
    });

    // Activity form: notes have no date/assignee; "Already done" needs no date
    function syncActivityForm() {
        var $f = $('#dd-activity-form');
        if (!$f.length) { return; }
        var isNote = $f.find('input[name="activity_type"]:checked').val() === 'note';
        $f.find('[data-when]').toggle(!isNote);
        $('#dd-act-due').toggle(!$('#dd-act-done').is(':checked'));
        $('#dd-act-note').attr('placeholder', isNote ? 'Write the note…' : 'What is it about? (e.g. confirm trial fitting)');
    }
    $drawer.on('change', 'input[name="activity_type"], #dd-act-done', syncActivityForm);

    $drawer.on('submit', '#dd-activity-form', function (e) {
        e.preventDefault();
        var $f = $(this);
        var $btn = $('#dd-act-submit');
        var isNote = $f.find('input[name="activity_type"]:checked').val() === 'note';
        var data = $f.serializeArray().filter(function (p) {
            if (isNote) { return p.name === 'activity_type' || p.name === 'note'; }
            return !(p.name === 'due_at' && $('#dd-act-done').is(':checked'));
        });
        $f.find('.deal-errors').empty();
        busy($btn, true);
        $.post(B.base + '/' + $f.data('deal') + '/activities', $.param(data))
            .done(function (res) { toast(res.message); refreshCard(res); openDrawer(drawerId, isNote ? 'timeline' : null); })
            .fail(function (xhr) {
                busy($btn, false);
                $f.find('.deal-errors').html(errorsOf(xhr).map(function (m) { return '<div>' + esc(m) + '</div>'; }).join(''));
            });
    });

    $drawer.on('click', '.js-act-done', function () {
        var $btn = $(this);
        if ($btn.data('contact')) { // calls / WhatsApp: pick a result instead of typing
            var $p = $('#dd-results-' + $btn.data('id'));
            $p.prop('hidden', !$p.prop('hidden'));
            return;
        }
        var outcome = window.prompt('Outcome (optional):', '');
        if (outcome === null) { return; }
        $btn.prop('disabled', true);
        $.post(B.base + '/activities/' + $btn.data('id') + '/complete', { outcome: outcome })
            .done(function (res) { toast(res.message); refreshCard(res); openDrawer(drawerId); })
            .fail(function (xhr) { $btn.prop('disabled', false); toast(errorsOf(xhr)[0], 'err'); });
    });

    $drawer.on('click', '.js-dd-result', function () {
        var $chip = $(this);
        var data = { result: $chip.data('result') };
        if (data.result === 'call_back') {
            var t = new Date(); t.setDate(t.getDate() + 1);
            var when = window.prompt('Call back when? (YYYY-MM-DD HH:MM)', isoDate(t) + ' 11:00');
            if (!when) { return; }
            data.callback_at = when;
        }
        $chip.closest('.dd-results').find('button').prop('disabled', true);
        $.post(B.base + '/activities/' + $chip.data('id') + '/complete', data)
            .done(function (res) {
                toast(res.message + (res.suggest_lost ? ' Consider marking this deal Lost.' : ''), res.suggest_lost ? 'info' : 'ok');
                refreshCard(res);
                openDrawer(drawerId);
            })
            .fail(function (xhr) { $chip.closest('.dd-results').find('button').prop('disabled', false); toast(errorsOf(xhr)[0], 'err'); });
    });

    function showErrors($f, xhr) {
        $f.find('.deal-errors').html(errorsOf(xhr).map(function (m) { return '<div>' + esc(m) + '</div>'; }).join(''));
    }

    // ------------------------------------------------------------------ drawer: notes
    $drawer.on('submit', '#dd-note-form', function (e) {
        e.preventDefault();
        var $f = $(this);
        var $btn = $('#dd-note-submit');
        $f.find('.deal-errors').empty();
        busy($btn, true);
        $.post(B.base + '/' + $f.data('deal') + '/notes', $f.serialize())
            .done(function (res) { toast(res.message); openDrawer(drawerId, 'notes'); })
            .fail(function (xhr) { busy($btn, false); showErrors($f, xhr); });
    });

    $drawer.on('click', '.js-note-pin, .js-note-del', function () {
        var $btn = $(this);
        var del = $btn.hasClass('js-note-del');
        if (del && !window.confirm('Delete this note?')) { return; }
        $btn.prop('disabled', true);
        $.ajax({ url: B.base + '/notes/' + $btn.data('id') + (del ? '' : '/pin'), type: del ? 'DELETE' : 'POST' })
            .done(function (res) { toast(res.message); openDrawer(drawerId, 'notes'); })
            .fail(function (xhr) { $btn.prop('disabled', false); toast(errorsOf(xhr)[0], 'err'); });
    });

    // ------------------------------------------------------------------ drawer: move to another pipeline
    $drawer.on('click', '.js-dd-pipeline', function () {
        var $btn = $(this);
        var name = $('#dd-pipeline-to option:selected').text();
        if (!window.confirm('Move this deal to "' + name + '"? It starts again in that pipeline\'s first stage.')) { return; }
        busy($btn, true);
        $.post(B.base + '/' + $btn.data('id') + '/pipeline', { pipeline_id: $('#dd-pipeline-to').val() })
            .done(function (res) { toast(res.message); reloadSoon(); })
            .fail(function (xhr) { busy($btn, false); toast(errorsOf(xhr)[0], 'err'); });
    });

    // ------------------------------------------------------------------ drawer: book appointment
    var apptUrl = B.base.replace(/\/deals$/, '/appointments');

    function apptSlotsHint(text) { $('#dd-appt-slots').html('<span class="dd-meta">' + esc(text) + '</span>'); $('#dd-appt-slot').val(''); }

    function loadSlots() {
        var pro = $('#dd-appt-pro').val();
        var date = $('#dd-appt-date').val();
        if (!pro || !date) { apptSlotsHint('Choose a service and professional to see free slots.'); return; }
        apptSlotsHint('Loading slots…');
        $.getJSON(apptUrl + '/professional-slots/' + pro + '/' + date)
            .done(function (slots) {
                if (!slots.length) { apptSlotsHint('Not working on this day. Try another date.'); return; }
                $('#dd-appt-slots').html(slots.map(function (s) {
                    return '<button type="button" class="dd-slot" data-slot="' + s.professional_time_slot_id + '"' + (s.available ? '' : ' disabled title="Booked"') + '>'
                        + esc(s.start_time) + '</button>';
                }).join(''));
            })
            .fail(function (xhr) { apptSlotsHint(errorsOf(xhr)[0]); });
    }

    $drawer.on('change', '#dd-appt-service', function () {
        var $pro = $('#dd-appt-pro').prop('disabled', true).html('<option value="">Professional…</option>');
        apptSlotsHint('Choose a service and professional to see free slots.');
        if (!this.value) { return; }
        $.getJSON(apptUrl + '/professionals-by-service/' + this.value).done(function (list) {
            if (!list.length) { $pro.html('<option value="">No professional for this service</option>'); return; }
            $pro.append(list.map(function (p) { return '<option value="' + p.id + '">' + esc(p.name) + '</option>'; }).join('')).prop('disabled', false);
            if (list.length === 1) { $pro.val(list[0].id); loadSlots(); }
        });
    });
    $drawer.on('change', '#dd-appt-pro, #dd-appt-date', loadSlots);
    $drawer.on('click', '.dd-slot:not(:disabled)', function () {
        $('.dd-slot').removeClass('is-picked');
        $(this).addClass('is-picked');
        $('#dd-appt-slot').val(this.getAttribute('data-slot'));
    });

    $drawer.on('submit', '#dd-appt-form', function (e) {
        e.preventDefault();
        var $f = $(this);
        var $btn = $('#dd-appt-submit');
        $f.find('.deal-errors').empty();
        busy($btn, true);
        $.post(B.base + '/' + $f.data('deal') + '/appointments', $f.serialize())
            .done(function (res) { toast(res.message); refreshCard(res); openDrawer(drawerId, 'appointments'); })
            .fail(function (xhr) { busy($btn, false); showErrors($f, xhr); if (xhr.status === 422) { loadSlots(); } });
    });

    // ------------------------------------------------------------------ quick add
    var $qa = $('#quickAddModal');
    var qaTimer = null;
    var qaXhr = null;

    function unpick() {
        $('#qa-existing').val('');
        $('#qa-picked').hide();
        $qa.find('[data-new-person]').show();
        $qa.find('.qa-match').removeClass('is-picked');
    }

    $('#btn-quick-add').on('click', function () {
        submitted = true; // nothing to revert for this modal
        $('#quickAddForm')[0].reset();
        $qa.find('.deal-errors').empty();
        $('#qa-matches').empty();
        unpick();
        $qa.modal('show');
        setTimeout(function () { $('#qa-phone').trigger('focus'); }, 350);
    });

    function renderMatches(list) {
        var $box = $('#qa-matches').empty();
        if (!list.length) { return; }
        $box.append('<h6><i class="fas fa-user-friends mr-1"></i>Already in the CRM – add the deal to this person instead</h6>');
        list.forEach(function (m) {
            var meta = [m.lead_uid, m.phone, m.branch].filter(Boolean).map(esc).join(' · ');
            var extra = m.customer ? '<i class="fas fa-crown" style="color:#f59e0b"></i> ' + esc(m.tier || 'Customer') + (m.card ? ' · #' + esc(m.card) : '') : '';
            var open = m.open_deals.length ? '<div class="dd-meta">Open: ' + m.open_deals.map(esc).join(', ') + '</div>' : '';
            $box.append(
                '<div class="qa-match" data-id="' + m.id + '"><div class="qa-info"><b>' + esc(m.name) + '</b> ' + extra +
                '<div class="dd-meta">' + meta + '</div>' + open + '</div>' +
                '<button type="button" class="btn btn-primary btn-sm js-qa-pick" id="qa-pick-' + m.id + '" data-id="' + m.id + '" data-name="' + esc(m.name) + '">Add new deal</button></div>'
            );
        });
    }

    $('#qa-phone, #qa-card').on('input', function () {
        clearTimeout(qaTimer);
        qaTimer = setTimeout(function () {
            if (qaXhr) { qaXhr.abort(); }
            qaXhr = $.get(B.base + '/duplicate-check', { phone: $('#qa-phone').val(), card: $('#qa-card').val() })
                .done(function (res) { renderMatches(res.matches || []); });
        }, 400);
    });

    $qa.on('click', '.js-qa-pick', function () {
        $('#qa-existing').val(this.getAttribute('data-id'));
        $('#qa-picked').css('display', 'flex').find('[data-picked-name]').text(this.getAttribute('data-name'));
        $qa.find('[data-new-person]').hide();
        $qa.find('.deal-errors').empty();
    });
    $('#qa-unpick').on('click', unpick);
    $qa.on('change', '.qa-ear select', function () {
        if (this.value) { $(this).closest('.qa-ear').find('input[type=checkbox]').prop('checked', true); }
    });

    $('#quickAddForm').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#quickAddSubmit');
        var data = $(this).serializeArray().filter(function (p) {
            return !($('#qa-existing').val() && (p.name === 'full_name' || p.name === 'phone_number'));
        });
        $qa.find('.deal-errors').empty();
        busy($btn, true);
        $.post(B.base, $.param(data))
            .done(function (res) {
                $qa.modal('hide');
                toast(res.message);
                if (B.view === 'list') { reloadSoon(); return; }
                var $list = $('#stage-col-' + res.stage_id).find('.deal-col-body');
                if ($list.length && res.html) {
                    var $n = $(res.html);
                    $list.prepend($n);
                    $n.addClass('is-flash');
                    bump(res.stage_id, 1, +res.value || 0);
                }
            })
            .fail(function (xhr) { fail(xhr, $qa, null); })
            .always(function () { busy($btn, false); });
    });

    // ------------------------------------------------------------------ list view: selection + bulk actions
    var $bar = $('#dl-bulkbar');
    var $bulk = $('#bulkModal');
    var BULK = {
        transfer: { title: 'Transfer to branch', icon: 'fa-share-square', color: 'var(--info-500)', btn: 'Transfer', note: 'Note for the branch', noteReq: false },
        owner:    { title: 'Change owner', icon: 'fa-user-tag', color: 'var(--accent-500)', btn: 'Reassign', note: 'Note for the new owner', noteReq: false },
        sms:      { title: 'Send SMS', icon: 'fa-comment-dots', color: 'var(--success-500)', btn: 'Send SMS', note: '', noteReq: false },
        lost:     { title: 'Mark as lost', icon: 'fa-times', color: 'var(--danger-500)', btn: 'Mark lost', note: 'Note', noteReq: true }
    };

    function selectedIds() { return $('.dl-check-row:checked').map(function () { return +this.value; }).get(); }

    function syncSelection() {
        var ids = selectedIds();
        var all = $('.dl-check-row').length;
        $('#dl-selected').text(ids.length);
        $bar.toggleClass('show', ids.length > 0);
        $('#dl-check-all').prop({ checked: all > 0 && ids.length === all, indeterminate: ids.length > 0 && ids.length < all });
        $('.dl-check-row').each(function () { $(this).closest('tr').toggleClass('is-selected', this.checked); });
    }

    $('#dl-check-all').on('change', function () { $('.dl-check-row').prop('checked', this.checked); syncSelection(); });
    $(document).on('change', '.dl-check-row', syncSelection);
    $('#dl-clear').on('click', function () { $('.dl-check-row, #dl-check-all').prop('checked', false); syncSelection(); });

    function fillOwners(list) {
        var $o = $('#bulk-owner-select').empty();
        if (!list.length) { $o.append('<option value="">No active staff</option>'); }
        list.forEach(function (u) { $o.append($('<option>').val(u.id).text(u.name)); });
    }

    /** Owner choices for "Change owner": staff of the branches of the selected deals. */
    function ownersForSelection() {
        var keys = {};
        $('.dl-check-row:checked').each(function () { keys[$(this).closest('tr').attr('data-vendor') || 'hq'] = true; });
        var seen = {}, list = [];
        Object.keys(keys).forEach(function (k) {
            (B.owners[k] || []).forEach(function (u) { if (!seen[u.id]) { seen[u.id] = true; list.push(u); } });
        });
        return list.sort(function (a, b) { return a.name.localeCompare(b.name); });
    }

    $bar.on('click', '[data-bulk]', function () {
        var act = this.getAttribute('data-bulk');
        var cfg = BULK[act];
        var n = selectedIds().length;
        if (!n) { return; }
        submitted = true; // nothing to revert for this modal
        $('#bulkForm')[0].reset();
        $bulk.find('.deal-errors').empty();
        $bulk.data('action', act);
        $bulk.find('[data-for]').each(function () { $(this).toggle(this.getAttribute('data-for').split(' ').indexOf(act) !== -1); });
        $('#bulkTitle').text(cfg.title);
        $('#bulkSub').text(n + ' deal' + (n > 1 ? 's' : '') + ' selected');
        $('#bulkIcon').css('background', cfg.color).html('<i class="fas ' + cfg.icon + '"></i>');
        $('#bulkSubmit').toggleClass('btn-danger', act === 'lost').toggleClass('btn-primary', act !== 'lost').find('span').text(cfg.btn);
        $('#bulk-note-label').text(cfg.note);
        $('#bulk-note-req').toggle(cfg.noteReq);
        $('#bulk-chars').text('0');
        if (act === 'transfer') { $('#bulk-branch').val('').trigger('change'); }
        if (act === 'owner') { fillOwners(ownersForSelection()); }
        $bulk.modal('show');
    });

    $('#bulk-branch').on('change', function () { fillOwners(B.owners[this.value || 'hq'] || []); });

    $('#bulk-template').on('change', function () {
        var body = $(this).find(':selected').attr('data-body');
        if (body) { $('#bulk-message').val(body).trigger('input'); }
    });
    $('#bulk-message').on('input', function () {
        var len = this.value.length;
        // Unicode (Bangla) SMS = 70 chars per part, plain = 160
        var per = /[^\u0000-\u007F]/.test(this.value) ? 70 : 160;
        $('#bulk-chars').text(len + ' chars · ' + Math.max(1, Math.ceil(len / per)) + ' SMS');
    });

    $('#bulkForm').on('submit', function (e) {
        e.preventDefault();
        var act = $bulk.data('action');
        var $btn = $('#bulkSubmit');
        var data = [{ name: 'action', value: act }];
        selectedIds().forEach(function (id) { data.push({ name: 'ids[]', value: id }); });
        $bulk.find('[data-for]:visible').find('input, select, textarea').each(function () {
            if (this.name) { data.push({ name: this.name, value: $(this).val() || '' }); }
        });
        if (act === 'lost' && !window.confirm('Mark ' + selectedIds().length + ' deal(s) as lost?')) { return; }
        $bulk.find('.deal-errors').empty();
        busy($btn, true);
        $.post(B.base + '/bulk', $.param(data))
            .done(function (res) { $bulk.modal('hide'); toast(res.message); if (act !== 'sms') { reloadSoon(); } else { $('#dl-clear').trigger('click'); } })
            .fail(function (xhr) {
                busy($btn, false);
                $bulk.find('.deal-errors').html(errorsOf(xhr).map(function (m) { return '<div>' + esc(m) + '</div>'; }).join(''));
            })
            .always(function () { if (!$bulk.hasClass('show')) { busy($btn, false); } });
    });
})(jQuery);
