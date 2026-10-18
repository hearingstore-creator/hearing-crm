/**
 * Header search (Ctrl+K). Type 2+ characters: grouped results from /admin/global-search appear
 * under the box. Arrow keys move, Enter opens, Esc closes. Each result is a normal link.
 */
(function () {
    var wrap = document.querySelector('.navbar-search');
    if (!wrap) return;
    var input = wrap.querySelector('input');
    var endpoint = input.getAttribute('data-endpoint');
    if (!endpoint) return;

    var style = document.createElement('style');
    style.textContent =
        '.gs-panel{position:absolute;top:calc(100% + 6px);left:0;width:min(520px,90vw);max-height:70vh;overflow-y:auto;' +
        'background:var(--card-bg);border:1px solid var(--card-border);border-radius:12px;box-shadow:0 12px 32px rgba(15,23,42,.18);z-index:1080;padding:6px 0;display:none}' +
        '.gs-panel.open{display:block}' +
        '.gs-group{padding:6px 14px 2px;font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:var(--page-text-muted)}' +
        '.gs-item{display:flex;align-items:center;gap:10px;padding:7px 14px;color:var(--page-text);text-decoration:none}' +
        '.gs-item:hover,.gs-item.active{background:var(--accent-50);color:var(--page-text);text-decoration:none}' +
        '[data-theme="dark"] .gs-item:hover,[data-theme="dark"] .gs-item.active{background:rgba(99,102,241,.16)}' +
        '.gs-item i{width:16px;text-align:center;color:var(--accent-500);font-size:13px}' +
        '.gs-title{font-size:13px;font-weight:500;line-height:1.3}' +
        '.gs-sub{font-size:11.5px;color:var(--page-text-muted);line-height:1.3}' +
        '.gs-empty{padding:12px 14px;font-size:13px;color:var(--page-text-muted)}';
    document.head.appendChild(style);

    var panel = document.createElement('div');
    panel.className = 'gs-panel';
    panel.setAttribute('role', 'listbox');
    wrap.appendChild(panel);
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-expanded', 'false');

    var timer = null, seq = 0, active = -1;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    }

    function links() { return panel.querySelectorAll('.gs-item'); }

    function setActive(i) {
        var items = links();
        if (!items.length) return;
        active = (i + items.length) % items.length;
        items.forEach(function (el, n) { el.classList.toggle('active', n === active); });
        items[active].scrollIntoView({block: 'nearest'});
    }

    function open(html) {
        panel.innerHTML = html;
        panel.classList.add('open');
        input.setAttribute('aria-expanded', 'true');
        active = -1;
    }

    function close() {
        panel.classList.remove('open');
        input.setAttribute('aria-expanded', 'false');
        active = -1;
    }

    function render(data) {
        if (!data.groups || !data.groups.length) {
            open('<div class="gs-empty">No matches for "' + esc(data.query) + '"</div>');
            return;
        }
        var html = '';
        data.groups.forEach(function (g) {
            html += '<div class="gs-group">' + esc(g.label) + '</div>';
            g.items.forEach(function (it) {
                html += '<a class="gs-item" role="option" href="' + esc(it.url) + '"><i class="fas ' + esc(g.icon) + '"></i>' +
                    '<span><div class="gs-title">' + esc(it.title) + '</div>' +
                    (it.subtitle ? '<div class="gs-sub">' + esc(it.subtitle) + '</div>' : '') + '</span></a>';
            });
        });
        open(html);
    }

    function search() {
        var q = input.value.trim();
        if (q.length < 2) { close(); return; }
        var mine = ++seq;
        fetch(endpoint + '?q=' + encodeURIComponent(q), {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, credentials: 'same-origin'})
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
            .then(function (data) { if (mine === seq) render(data); })
            .catch(function () { if (mine === seq) open('<div class="gs-empty">Search is not available right now.</div>'); });
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(search, 250);
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); setActive(active + 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(active - 1); }
        else if (e.key === 'Enter') {
            var items = links();
            var target = items[active >= 0 ? active : 0];
            if (target) { e.preventDefault(); window.location.href = target.href; }
        } else if (e.key === 'Escape') { close(); input.blur(); }
    });

    input.addEventListener('focus', function () { if (input.value.trim().length >= 2 && panel.innerHTML) panel.classList.add('open'); });

    document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) close(); });

    // Ctrl+K / Cmd+K from anywhere focuses the search
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
            e.preventDefault();
            input.focus();
            input.select();
        }
    });
})();
