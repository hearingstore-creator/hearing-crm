/**
 * CRM Print Paper Size
 * ---------------------------------------------------------------
 * Adds a paper-size selector next to every print button in the CRM
 * (.print-btn, [onclick*="window.print"], #btnPrint, [data-print-size]).
 * The chosen size is applied through a dynamic @page rule and is
 * remembered in localStorage. Default: A4 portrait.
 *
 * Opt-out on a specific button with: data-no-print-size
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'crm.printPaperSize';
    var SIZES = {
        'A4':     { label: 'A4',            size: 'A4 portrait',  margin: '10mm' },
        'A4L':    { label: 'A4 Landscape',  size: 'A4 landscape', margin: '10mm' },
        'A5':     { label: 'A5',            size: 'A5 portrait',  margin: '8mm'  },
        'Letter': { label: 'Letter',        size: 'letter',       margin: '10mm' },
        'Legal':  { label: 'Legal',         size: 'legal',        margin: '10mm' },
        'R80':    { label: '80mm Receipt',  size: '80mm auto',    margin: '3mm'  }
    };
    var TRIGGERS = '.print-btn, [onclick*="window.print"], #btnPrint, [data-print-size]';

    function current() {
        var v = null;
        try { v = localStorage.getItem(STORAGE_KEY); } catch (e) { /* private mode */ }
        return SIZES[v] ? v : 'A4';
    }

    function styleTag() {
        var el = document.getElementById('crm-print-page-size');
        if (!el) {
            el = document.createElement('style');
            el.id = 'crm-print-page-size';
        }
        // Always keep it as the LAST stylesheet so it overrides any earlier @page rule.
        document.head.appendChild(el);
        return el;
    }

    function apply(key) {
        var s = SIZES[key] || SIZES.A4;
        styleTag().textContent =
            '@page { size: ' + s.size + ' !important; margin: ' + s.margin + ' !important; }\n' +
            '@media print { .print-size-wrap { display: none !important; } }\n' +
            (key === 'R80'
                ? '@media print { html, body { width: 80mm !important; } }\n'
                : '');
        document.documentElement.setAttribute('data-print-size', key);
    }

    function setSize(key) {
        if (!SIZES[key]) key = 'A4';
        try { localStorage.setItem(STORAGE_KEY, key); } catch (e) { /* ignore */ }
        apply(key);
        // keep every selector in sync (and persist "selected" so innerHTML swaps keep it)
        document.querySelectorAll('select.print-size-select').forEach(function (sel) {
            sel.value = key;
            Array.prototype.forEach.call(sel.options, function (o) {
                if (o.value === key) o.setAttribute('selected', 'selected'); else o.removeAttribute('selected');
            });
        });
    }

    function buildSelector() {
        var key = current();
        var wrap = document.createElement('span');
        wrap.className = 'print-size-wrap';
        wrap.title = 'Paper size for printing';
        var html = '<i class="fas fa-file-alt" aria-hidden="true"></i><select class="print-size-select" aria-label="Paper size">';
        Object.keys(SIZES).forEach(function (k) {
            html += '<option value="' + k + '"' + (k === key ? ' selected="selected"' : '') + '>' + SIZES[k].label + '</option>';
        });
        wrap.innerHTML = html + '</select>';
        return wrap;
    }

    function injectSelectors(root) {
        (root || document).querySelectorAll(TRIGGERS).forEach(function (btn) {
            if (btn.hasAttribute('data-no-print-size') || btn.dataset.printSizeReady) return;
            btn.dataset.printSizeReady = '1';
            var sel = buildSelector();
            if (btn.tagName === 'DIV') {
                // Legacy markup: <div class="text-right print-btn"><a>Print</a></div>
                if (btn.querySelector('.print-size-wrap')) return;
                btn.insertBefore(sel, btn.firstChild);
                return;
            }
            var parent = btn.parentNode;
            if (!parent || parent.querySelector(':scope > .print-size-wrap')) return;
            parent.insertBefore(sel, btn);
        });
    }

    function injectCss() {
        if (document.getElementById('crm-print-size-ui')) return;
        var css = document.createElement('style');
        css.id = 'crm-print-size-ui';
        css.textContent =
            '.print-size-wrap{display:inline-flex;align-items:center;gap:6px;height:31px;padding:0 4px 0 10px;margin:0 6px 0 0;' +
            'border:1px solid var(--card-border,#e2e8f0);border-radius:8px;background:var(--card-bg,#fff);vertical-align:middle;' +
            'font-size:12px;color:var(--page-text-secondary,#64748b);}' +
            '.print-size-wrap i{font-size:11px;opacity:.8;}' +
            '.print-size-select{border:0;background:transparent;font-size:12px;font-weight:600;color:var(--page-text,#1e293b);' +
            'padding:0 2px;height:28px;cursor:pointer;outline:none;}' +
            '.print-size-select option{color:#1e293b;}' +
            '@media print{.print-size-wrap{display:none!important;}}';
        document.head.appendChild(css);
    }

    // Delegated so it survives printDiv()'s body.innerHTML swap.
    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('print-size-select')) {
            setSize(e.target.value);
        }
    });

    // Clicking the selector must never reach a print handler (window capture runs first,
    // and keeps working after printDiv() rebuilds the DOM).
    window.addEventListener('click', function (e) {
        if (e.target && e.target.closest && e.target.closest('.print-size-wrap')) {
            e.stopPropagation();
        }
    }, true);

    // Re-assert right before printing (other scripts may have appended styles).
    window.addEventListener('beforeprint', function () { apply(current()); });

    function init() {
        injectCss();
        apply(current());
        injectSelectors();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.CRMPrint = { setSize: setSize, current: current, sizes: SIZES, refresh: injectSelectors };
})();
