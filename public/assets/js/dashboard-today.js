/**
 * Today dashboard: the page opens as a light shell, then tiles, alerts, My Day and the charts load
 * one by one from their own endpoints. One slow or failing block only affects its own card.
 */
(function () {
    var root = document.getElementById('dashboard-today');
    if (!root) return;

    var query = '?branch=' + encodeURIComponent(root.dataset.branch || '') + '&period=' + encodeURIComponent(root.dataset.period || '');
    var headers = {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'};

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    }

    function get(url, asText) {
        return fetch(url, {headers: headers, credentials: 'same-origin'}).then(function (r) {
            if (!r.ok) return Promise.reject(r.status);
            return asText ? r.text() : r.json();
        });
    }

    var money = new Intl.NumberFormat('en-IN', {maximumFractionDigits: 0});
    function fmt(v, format) { return (format === 'money' ? '৳' : '') + money.format(v || 0); }

    function spark(values) {
        if (!values || values.length < 2) return '';
        var w = 96, h = 32, max = Math.max.apply(null, values), min = Math.min.apply(null, values);
        var span = max - min || 1, step = w / (values.length - 1);
        var pts = values.map(function (v, i) { return (i * step).toFixed(1) + ',' + (h - (v - min) / span * h).toFixed(1); });
        var last = pts[pts.length - 1].split(',');
        return '<svg class="t-spark" viewBox="0 0 ' + w + ' ' + h + '" aria-hidden="true"><polyline points="' + pts.join(' ') + '"/>' +
            '<circle cx="' + last[0] + '" cy="' + last[1] + '" r="2.5"/></svg>';
    }

    function change(t) {
        if (!t.change || !t.vs) return '';
        var d = t.change.diff, cls = d > 0 ? 'up' : (d < 0 ? 'down' : 'flat');
        var arrow = d > 0 ? '▲' : (d < 0 ? '▼' : '•');
        var txt = t.change.pct !== null && t.change.pct !== undefined ? Math.abs(t.change.pct) + '%' : fmt(Math.abs(d), t.format);
        if (t.key === 'open_repairs') { cls = 'flat'; arrow = '+'; txt = fmt(d, 'count'); }
        return '<div class="t-chg ' + cls + '">' + arrow + ' ' + esc(txt) + ' ' + esc(t.vs) + '</div>';
    }

    function renderTiles(tiles) {
        var box = document.getElementById('td-tiles');
        if (!tiles.length) { box.innerHTML = '<div class="td-empty">No tiles are switched on for your role.</div>'; return; }
        box.innerHTML = tiles.map(function (t) {
            if (t.error) return '<div class="td-tile"><div class="td-err">This tile could not be loaded.</div></div>';
            return '<a class="td-tile" id="td-tile-' + esc(t.key) + '" href="' + esc(t.url) + '">' +
                '<div class="t-top"><i class="' + esc(t.icon) + '"></i>' + esc(t.label) + '</div>' +
                '<div class="t-val">' + esc(fmt(t.value, t.format)) + '</div>' + change(t) +
                '<div class="t-sub">' + esc(t.sub) + '</div>' + spark(t.spark) + '</a>';
        }).join('');
    }

    get(root.dataset.tiles + query).then(function (d) { renderTiles(d.tiles || []); })
        .catch(function () { document.getElementById('td-tiles').innerHTML = '<div class="td-err">Tiles could not be loaded. Refresh to try again.</div>'; });

    document.querySelectorAll('[data-block-name]').forEach(function (el) {
        get(root.dataset.block + '/' + el.dataset.blockName + query, true)
            .then(function (html) { el.innerHTML = html; })
            .catch(function () { el.innerHTML = '<div class="td-err">This section could not be loaded.</div>'; });
    });

    // Charts
    var css = getComputedStyle(document.documentElement);
    var accent = (css.getPropertyValue('--accent-500') || '#6366f1').trim();
    var muted = (css.getPropertyValue('--page-text-muted') || '#64748b').trim();
    var palette = [accent, '#10b981', '#f59e0b', '#0ea5e9', '#e11d48', '#8b5cf6', '#94a3b8'];

    var builders = {
        revenue_month: function (d) {
            document.getElementById('td-rev-note').textContent = d.this_label + ' so far ' + fmt(d.total, 'money') +
                ' · same day last month ' + fmt(d.same_day_last, 'money');
            return {
                type: 'line',
                data: {labels: d.labels, datasets: [
                    {label: d.this_label, data: d.this_month, borderColor: accent, backgroundColor: (/^#[0-9a-f]{6}$/i.test(accent) ? accent + '22' : 'rgba(99,102,241,.13)'), fill: true, tension: .3, pointRadius: 0, spanGaps: false},
                    {label: d.last_label, data: d.last_month, borderColor: muted, borderDash: [5, 4], fill: false, tension: .3, pointRadius: 0}
                ]},
                options: {responsive: true, maintainAspectRatio: false, interaction: {mode: 'index', intersect: false},
                    plugins: {legend: {position: 'bottom'}, tooltip: {callbacks: {label: function (c) { return c.dataset.label + ': ' + fmt(c.parsed.y, 'money'); }}}},
                    scales: {y: {beginAtZero: true, ticks: {precision: 0, callback: function (v) { return fmt(v, 'money'); }}}, x: {grid: {display: false}}}}
            };
        },
        sales_by_category: function (d) {
            document.getElementById('td-cat-note').textContent = d.period + ' · total ' + fmt(d.total, 'money');
            if (!d.values.length) return null;
            return {
                type: 'doughnut',
                data: {labels: d.labels, datasets: [{data: d.values, backgroundColor: palette, borderWidth: 0}]},
                options: {responsive: true, maintainAspectRatio: false, cutout: '62%',
                    plugins: {legend: {position: 'right'}, tooltip: {callbacks: {label: function (c) { return c.label + ': ' + fmt(c.parsed, 'money'); }}}}}
            };
        }
    };

    document.querySelectorAll('canvas[data-chart-name]').forEach(function (canvas) {
        var name = canvas.dataset.chartName, box = canvas.parentNode;
        get(root.dataset.chart + '/' + name + query).then(function (d) {
            if (d.error) { box.innerHTML = '<div class="td-err">This chart could not be loaded.</div>'; return; }
            var cfg = builders[name](d);
            if (!cfg) { box.innerHTML = '<div class="td-empty">No sales in this period yet.</div>'; return; }
            if (typeof Chart === 'undefined') { box.innerHTML = '<div class="td-err">The chart library did not load.</div>'; return; }
            new Chart(canvas, cfg);
        }).catch(function () { box.innerHTML = '<div class="td-err">This chart could not be loaded.</div>'; });
    });
})();
