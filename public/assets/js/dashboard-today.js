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

    function post(url, data) {
        var token = root.dataset.token || '';
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token
            },
            body: JSON.stringify(data || {})
        }).then(function (r) {
            if (!r.ok) {
                return r.json().catch(function () { return { message: 'HTTP ' + r.status }; })
                    .then(function (err) { return Promise.reject(err); });
            }
            return r.json();
        });
    }

    function toast(msg, ok) {
        if (typeof ok === 'undefined') ok = true;
        if (typeof toastr !== 'undefined') {
            if (ok) toastr.success(msg); else toastr.error(msg);
            return;
        }
        var old = document.querySelector('.td-toast');
        if (old) old.remove();
        var t = document.createElement('div');
        t.className = 'td-toast' + (ok ? '' : ' error');
        t.innerHTML = (ok ? '<i class="fas fa-check-circle" style="color:#34d399;"></i>' : '<i class="fas fa-exclamation-circle"></i>') + ' <span>' + esc(msg) + '</span>';
        document.body.appendChild(t);
        setTimeout(function () { t.remove(); }, 3200);
    }

    var money = new Intl.NumberFormat('en-IN', {maximumFractionDigits: 0});
    function fmt(v, format) { return (format === 'money' ? (window.CRM_CURRENCY || '৳') : '') + money.format(v || 0); }

    var tileThemes = {
        sales_today:     { accent: '#10b981', halo: '#10b981' },
        todays_sales:    { accent: '#10b981', halo: '#10b981' },
        cash_in:         { accent: '#8b5cf6', halo: '#8b5cf6' },
        revenue:         { accent: '#8b5cf6', halo: '#8b5cf6' },
        total_sales:     { accent: '#8b5cf6', halo: '#8b5cf6' },
        bookings:        { accent: '#0ea5e9', halo: '#0ea5e9' },
        appointments:    { accent: '#0ea5e9', halo: '#0ea5e9' },
        open_repairs:    { accent: '#f59e0b', halo: '#f59e0b' },
        stock:           { accent: '#f59e0b', halo: '#f59e0b' },
        total_products:  { accent: '#f59e0b', halo: '#f59e0b' },
        patient_dues:    { accent: '#f43f5e', halo: '#f43f5e' },
        attention:       { accent: '#f43f5e', halo: '#f43f5e' },
        needs_attention: { accent: '#f43f5e', halo: '#f43f5e' },
        new_leads:       { accent: '#6366f1', halo: '#6366f1' },
        total_leads:     { accent: '#6366f1', halo: '#6366f1' },
        leads:           { accent: '#6366f1', halo: '#6366f1' },
        customers:       { accent: '#3b82f6', halo: '#3b82f6' },
        total_customers: { accent: '#3b82f6', halo: '#3b82f6' },
        pending_services:{ accent: '#0284c7', halo: '#0284c7' }
    };

    function spark(values, key) {
        if (!values || values.length < 2) return '';
        var theme = tileThemes[key] || { accent: '#6366f1' };
        var color = theme.accent;
        var w = 110, h = 42, pad = 4;
        var max = Math.max.apply(null, values), min = Math.min.apply(null, values);
        var span = max - min || 1, step = w / (values.length - 1);
        var pts = values.map(function (v, i) {
            var y = h - pad - ((v - min) / span * (h - 2 * pad));
            return (i * step).toFixed(1) + ',' + y.toFixed(1);
        });
        var last = pts[pts.length - 1].split(',');
        var polyPts = pts.join(' ');
        var areaPts = pts.join(' ') + ' ' + w + ',' + h + ' 0,' + h;
        var gradId = 'spk-g-' + esc(key || Math.random().toString(36).substr(2, 6));

        return '<svg class="t-spark" viewBox="0 0 ' + w + ' ' + h + '" aria-hidden="true">' +
            '<defs>' +
                '<linearGradient id="' + gradId + '" x1="0" y1="0" x2="0" y2="1">' +
                    '<stop offset="0%" stop-color="' + color + '" stop-opacity="0.35"/>' +
                    '<stop offset="100%" stop-color="' + color + '" stop-opacity="0.0"/>' +
                '</linearGradient>' +
            '</defs>' +
            '<polygon points="' + areaPts + '" fill="url(#' + gradId + ')"/>' +
            '<polyline points="' + polyPts + '" fill="none" stroke="' + color + '" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>' +
            '<circle cx="' + last[0] + '" cy="' + last[1] + '" r="3.2" fill="' + color + '" stroke="#ffffff" stroke-width="1.8"/>' +
        '</svg>';
    }

    function change(t) {
        if (!t.change || !t.vs) return '';
        var d = t.change.diff, cls = d > 0 ? 'up' : (d < 0 ? 'down' : 'flat');
        var arrow = d > 0 ? '▲' : (d < 0 ? '▼' : '•');
        var txt = t.change.pct !== null && t.change.pct !== undefined ? Math.abs(t.change.pct) + '%' : fmt(Math.abs(d), t.format);
        if (t.key === 'open_repairs') { cls = 'flat'; arrow = '+'; txt = fmt(d, 'count'); }
        return '<span class="t-chg ' + cls + '">' + arrow + ' ' + esc(txt) + ' ' + esc(t.vs) + '</span>';
    }

    function renderTiles(tiles) {
        var box = document.getElementById('td-tiles');
        if (!tiles.length) { box.innerHTML = '<div class="td-empty">No tiles are switched on for your role.</div>'; return; }
        box.innerHTML = tiles.map(function (t) {
            if (t.error) return '<div class="td-tile"><div class="td-err">This tile could not be loaded.</div></div>';
            var themeClass = 'td-tile-' + esc(t.key);
            var changeBadge = change(t);
            var subText = t.sub ? '<span class="t-sub" title="' + esc(t.sub) + '">' + esc(t.sub) + '</span>' : '';
            return '<a class="td-tile ' + themeClass + '" id="td-tile-' + esc(t.key) + '" href="' + esc(t.url) + '">' +
                '<div class="t-top">' +
                    '<span class="t-halo"><i class="' + esc(t.icon) + '"></i></span>' +
                    '<span class="t-top-text">' + esc(t.label) + '</span>' +
                '</div>' +
                '<div class="t-body">' +
                    '<div class="t-val">' + esc(fmt(t.value, t.format)) + '</div>' +
                    '<div class="t-footer">' +
                        changeBadge + subText +
                    '</div>' +
                '</div>' +
                spark(t.spark, t.key) +
            '</a>';
        }).join('');
    }

    get(root.dataset.tiles + query).then(function (d) { renderTiles(d.tiles || []); })
        .catch(function () { document.getElementById('td-tiles').innerHTML = '<div class="td-err">Tiles could not be loaded. Refresh to try again.</div>'; });

    document.querySelectorAll('[data-block-name]').forEach(function (el) {
        get(root.dataset.block + '/' + el.dataset.blockName + query, true)
            .then(function (html) { el.innerHTML = html; })
            .catch(function () { el.innerHTML = '<div class="td-err">This section could not be loaded.</div>'; });
    });

    function decrementMyDayCounts(cat) {
        var todoEl = document.getElementById('md-cnt-todo');
        if (todoEl) {
            var n = parseInt(todoEl.textContent, 10);
            if (!isNaN(n) && n > 0) todoEl.textContent = n - 1;
        }
        if (cat) {
            var chip = document.querySelector('.md-chip[data-cat="' + cat + '"] .chip-n');
            if (chip) {
                var cn = parseInt(chip.textContent, 10);
                if (!isNaN(cn) && cn > 0) chip.textContent = cn - 1;
            }
            var allChip = document.querySelector('.md-chip[data-cat="all"] .chip-n');
            if (allChip) {
                var an = parseInt(allChip.textContent, 10);
                if (!isNaN(an) && an > 0) allChip.textContent = an - 1;
            }
        }
    }

    // ── Interactive My Day Actions (Filter chips, 1-click Done, WhatsApp/Call logging) ──
    document.addEventListener('click', function (e) {
        // 1. Category filter chips
        var chip = e.target.closest('.md-chip');
        if (chip && chip.closest('#td-my-day')) {
            var cat = chip.dataset.cat;
            var box = chip.closest('#td-my-day');
            box.querySelectorAll('.md-chip').forEach(function (c) { c.classList.remove('active'); });
            chip.classList.add('active');
            box.querySelectorAll('.md-item').forEach(function (item) {
                if (cat === 'all' || item.dataset.cat === cat) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
            return;
        }

        // 2. Deal activity one-click complete
        var doneBtn = e.target.closest('.js-md-done');
        if (doneBtn && doneBtn.closest('#td-my-day')) {
            var row = doneBtn.closest('.md-item');
            var id = doneBtn.dataset.id;
            var dealsUrl = root.dataset.deals || (window.location.origin + '/admin/deals');
            doneBtn.disabled = true;
            doneBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            post(dealsUrl + '/activities/' + id + '/complete', {})
                .then(function (res) {
                    toast(res && res.message ? res.message : 'Marked as done');
                    row.classList.add('done');
                    decrementMyDayCounts(row.dataset.cat);
                    setTimeout(function () {
                        row.remove();
                        var list = document.getElementById('mdItemList');
                        if (list && !list.querySelector('.md-item:not(.done)')) {
                            list.innerHTML = '<div class="td-empty"><i class="fas fa-check-circle mr-2" style="color:#059669; font-size:18px;"></i>All caught up! Nothing waiting right now.</div>';
                        }
                    }, 320);
                })
                .catch(function (err) {
                    toast(err && err.message ? err.message : 'Could not complete activity', false);
                    doneBtn.disabled = false;
                    doneBtn.innerHTML = '<i class="fas fa-check"></i>';
                });
            return;
        }

        // 3. After-sales care touch one-click complete
        var afterBtn = e.target.closest('.js-after-sales-done');
        if (afterBtn && afterBtn.closest('#td-my-day')) {
            var aRow = afterBtn.closest('.md-item');
            var custId = afterBtn.dataset.customerId;
            var type = afterBtn.dataset.type;
            var custUrl = root.dataset.customers || (window.location.origin + '/admin/customers');
            afterBtn.disabled = true;
            afterBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            post(custUrl + '/' + custId + '/after-sales-action', { type: type })
                .then(function (res) {
                    toast(res && res.message ? res.message : 'Care touch recorded');
                    aRow.classList.add('done');
                    decrementMyDayCounts(aRow.dataset.cat);
                    setTimeout(function () {
                        aRow.remove();
                        var aList = document.getElementById('mdItemList');
                        if (aList && !aList.querySelector('.md-item:not(.done)')) {
                            aList.innerHTML = '<div class="td-empty"><i class="fas fa-check-circle mr-2" style="color:#059669; font-size:18px;"></i>All caught up! Nothing waiting right now.</div>';
                        }
                    }, 320);
                })
                .catch(function (err) {
                    toast(err && err.message ? err.message : 'Could not record care touch', false);
                    afterBtn.disabled = false;
                    afterBtn.innerHTML = '<i class="fas fa-check"></i>';
                });
            return;
        }

        // 4. Contact logging (WhatsApp / Call on deals)
        var contactLink = e.target.closest('.js-contact');
        if (contactLink && contactLink.dataset.deal) {
            var cDealsUrl = root.dataset.deals || (window.location.origin + '/admin/deals');
            post(cDealsUrl + '/' + contactLink.dataset.deal + '/contact', { channel: contactLink.dataset.channel })
                .catch(function () {});
        }
    });

    // Charts
    var css = getComputedStyle(document.documentElement);
    var accent = (css.getPropertyValue('--accent-500') || '#4f46e5').trim();
    var muted = (css.getPropertyValue('--page-text-muted') || '#64748b').trim();
    var palette = ['#4f46e5', '#059669', '#d97706', '#0891b2', '#7c3aed', '#e11d48', '#94a3b8'];

    var builders = {
        revenue_month: function (d, canvas) {
            document.getElementById('td-rev-note').textContent = d.this_label + ' so far ' + fmt(d.total, 'money') +
                ' · same day last month ' + fmt(d.same_day_last, 'money') + ' · before returns';

            var ctx = canvas.getContext('2d');
            var grad = ctx.createLinearGradient(0, 0, 0, 240);
            grad.addColorStop(0, 'rgba(79, 70, 229, 0.22)');
            grad.addColorStop(0.65, 'rgba(79, 70, 229, 0.04)');
            grad.addColorStop(1, 'rgba(79, 70, 229, 0.0)');

            return {
                type: 'line',
                data: {
                    labels: d.labels,
                    datasets: [
                        {
                            label: d.this_label,
                            data: d.this_month,
                            borderColor: '#4f46e5',
                            borderWidth: 2.8,
                            backgroundColor: grad,
                            fill: true,
                            tension: 0.38,
                            pointRadius: 0,
                            pointHoverRadius: 6,
                            pointHoverBackgroundColor: '#ffffff',
                            pointHoverBorderColor: '#4f46e5',
                            pointHoverBorderWidth: 3,
                            spanGaps: false
                        },
                        {
                            label: d.last_label,
                            data: d.last_month,
                            borderColor: '#94a3b8',
                            borderWidth: 1.8,
                            borderDash: [5, 4],
                            fill: false,
                            tension: 0.38,
                            pointRadius: 0,
                            pointHoverRadius: 5,
                            pointHoverBackgroundColor: '#ffffff',
                            pointHoverBorderColor: '#94a3b8',
                            pointHoverBorderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle',
                                padding: 16,
                                font: { size: 12, weight: '600', family: 'Inter, system-ui, sans-serif' },
                                color: '#475569'
                            }
                        },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleColor: '#f8fafc',
                            bodyColor: '#e2e8f0',
                            borderColor: '#334155',
                            borderWidth: 1,
                            padding: 12,
                            cornerRadius: 10,
                            boxPadding: 6,
                            usePointStyle: true,
                            callbacks: {
                                title: function (items) {
                                    return items.length ? 'Day ' + items[0].label : '';
                                },
                                label: function (c) {
                                    return ' ' + c.dataset.label + ': ' + fmt(c.parsed.y, 'money');
                                },
                                afterBody: function (items) {
                                    if (items.length < 2) return '';
                                    var curr = items[0].parsed.y;
                                    var prev = items[1].parsed.y;
                                    if (curr === null || prev === null || prev === 0) return '';
                                    var diff = curr - prev;
                                    var pct = Math.round((diff / prev) * 100);
                                    var sign = diff > 0 ? '+' : '';
                                    return '\nComparison: ' + sign + fmt(diff, 'money') + ' (' + sign + pct + '%)';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(226, 232, 240, 0.7)' },
                            border: { dash: [4, 4] },
                            ticks: {
                                precision: 0,
                                font: { size: 11, family: 'Inter, system-ui, sans-serif' },
                                color: '#64748b',
                                callback: function (v) {
                                    if (v >= 100000) return (window.CRM_CURRENCY || '৳') + (v / 100000).toFixed(1) + 'L';
                                    if (v >= 1000) return (window.CRM_CURRENCY || '৳') + (v / 1000).toFixed(0) + 'k';
                                    return fmt(v, 'money');
                                }
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: {
                                font: { size: 11, family: 'Inter, system-ui, sans-serif' },
                                color: '#64748b',
                                maxTicksLimit: 12
                            }
                        }
                    }
                }
            };
        },
        sales_by_category: function (d) {
            document.getElementById('td-cat-note').textContent = d.period + ' · total ' + fmt(d.total, 'money') + ' · before returns';
            if (!d.values.length) return null;

            var catPalette = ['#4f46e5', '#059669', '#d97706', '#0891b2', '#7c3aed', '#e11d48', '#94a3b8'];

            return {
                type: 'doughnut',
                data: {
                    labels: d.labels,
                    datasets: [
                        {
                            data: d.values,
                            backgroundColor: catPalette.slice(0, d.values.length),
                            borderWidth: 2,
                            borderColor: '#ffffff',
                            hoverOffset: 6,
                            borderRadius: 4,
                            spacing: 3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle',
                                padding: 14,
                                font: { size: 12, weight: '600', family: 'Inter, system-ui, sans-serif' },
                                color: '#475569',
                                generateLabels: function (chart) {
                                    var data = chart.data;
                                    if (data.labels.length && data.datasets.length) {
                                        var total = d.total || 0;
                                        return data.labels.map(function (label, i) {
                                            var val = data.datasets[0].data[i] || 0;
                                            var pct = total > 0 ? Math.round((val / total) * 100) : 0;
                                            return {
                                                text: label + ' (' + pct + '%)',
                                                fillStyle: data.datasets[0].backgroundColor[i],
                                                strokeStyle: data.datasets[0].backgroundColor[i],
                                                lineWidth: 0,
                                                pointStyle: 'circle',
                                                hidden: isNaN(val) || val === 0,
                                                index: i
                                            };
                                        });
                                    }
                                    return [];
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleColor: '#f8fafc',
                            bodyColor: '#e2e8f0',
                            borderColor: '#334155',
                            borderWidth: 1,
                            padding: 12,
                            cornerRadius: 10,
                            boxPadding: 6,
                            usePointStyle: true,
                            callbacks: {
                                label: function (c) {
                                    var val = c.parsed;
                                    var pct = d.total > 0 ? ((val / d.total) * 100).toFixed(1) : 0;
                                    return ' ' + c.label + ': ' + fmt(val, 'money') + ' (' + pct + '%)';
                                }
                            }
                        }
                    }
                },
                plugins: [
                    {
                        id: 'doughnutCenterStats',
                        afterDraw: function (chart) {
                            var meta = chart.getDatasetMeta(0);
                            if (!meta || !meta.data || !meta.data.length) return;
                            var ctx = chart.ctx;
                            var x = meta.data[0].x;
                            var y = meta.data[0].y;
                            ctx.save();
                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'middle';

                            // Subtitle
                            ctx.font = '600 11px Inter, system-ui, sans-serif';
                            ctx.fillStyle = '#64748b';
                            ctx.fillText('TOTAL SALES', x, y - 11);

                            // Value
                            ctx.font = '800 17px Inter, system-ui, sans-serif';
                            ctx.fillStyle = '#0f172a';
                            var formattedTotal = fmt(d.total, 'money');
                            if (d.total >= 100000) {
                                formattedTotal = (window.CRM_CURRENCY || '৳') + (d.total / 100000).toFixed(2) + 'L';
                            }
                            ctx.fillText(formattedTotal, x, y + 10);
                            ctx.restore();
                        }
                    }
                ]
            };
        }
    };

    document.querySelectorAll('canvas[data-chart-name]').forEach(function (canvas) {
        var name = canvas.dataset.chartName, box = canvas.parentNode;
        get(root.dataset.chart + '/' + name + query).then(function (d) {
            if (d.error) { box.innerHTML = '<div class="td-err">This chart could not be loaded.</div>'; return; }
            var cfg = builders[name](d, canvas);
            if (!cfg) { box.innerHTML = '<div class="td-empty">No sales in this period yet.</div>'; return; }
            if (typeof Chart === 'undefined') { box.innerHTML = '<div class="td-err">The chart library did not load.</div>'; return; }
            new Chart(canvas, cfg);
        }).catch(function () { box.innerHTML = '<div class="td-err">This chart could not be loaded.</div>'; });
    });
    // ── Other tabs: each loads its HTML the first time it is opened ──
    var charts = [];
    var fmtNum = new Intl.NumberFormat('en-IN', {maximumFractionDigits: 1});
    var base = ['#4f46e5', '#059669', '#d97706', '#0891b2', '#7c3aed', '#e11d48', '#94a3b8'];

    function initCharts(scope) {
        if (typeof Chart === 'undefined') return;
        scope.querySelectorAll('canvas.td-cv').forEach(function (canvas) {
            if (canvas.dataset.ready) return;
            canvas.dataset.ready = '1';
            var d;
            try { d = JSON.parse(canvas.dataset.cv); } catch (e) { return; }
            var money = d.fmt === 'money';
            var f = function (v) { return money ? fmt(v, 'money') : fmtNum.format(v); };
            var round = d.kind === 'doughnut';
            var sets = d.sets.map(function (s, i) {
                var color = s.color || base[i % base.length];
                if (round) return {
                    label: s.label,
                    data: s.data,
                    backgroundColor: s.colors || base,
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    borderRadius: 4,
                    spacing: 2
                };
                if (d.kind === 'bar') return {
                    label: s.label,
                    data: s.data,
                    backgroundColor: s.colors || color,
                    borderRadius: 6
                };
                var bg = undefined;
                if (s.fill) {
                    var ctx = canvas.getContext('2d');
                    var grad = ctx.createLinearGradient(0, 0, 0, 220);
                    grad.addColorStop(0, /^#[0-9a-f]{6}$/i.test(color) ? color + '33' : 'rgba(79,70,229,0.2)');
                    grad.addColorStop(1, 'rgba(255,255,255,0)');
                    bg = grad;
                }
                return {
                    label: s.label,
                    data: s.data,
                    borderColor: color,
                    borderWidth: 2.5,
                    borderDash: s.dash ? [5, 4] : [],
                    tension: 0.38,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    fill: !!s.fill,
                    backgroundColor: bg
                };
            });
            var opts = {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        display: round || sets.length > 1,
                        position: round ? 'right' : 'bottom',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: { size: 12, weight: '600', family: 'Inter, system-ui, sans-serif' },
                            color: '#475569'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#f8fafc',
                        bodyColor: '#e2e8f0',
                        borderColor: '#334155',
                        borderWidth: 1,
                        padding: 10,
                        cornerRadius: 8,
                        boxPadding: 5,
                        usePointStyle: true,
                        callbacks: {
                            label: function (c) {
                                return ' ' + (c.dataset.label || c.label) + ': ' + f(round ? c.parsed : c.parsed.y);
                            }
                        }
                    }
                }
            };
            if (round) { opts.cutout = '70%'; opts.interaction = {}; }
            else opts.scales = {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(226, 232, 240, 0.7)' },
                    border: { dash: [4, 4] },
                    ticks: {
                        precision: 0,
                        font: { size: 11, family: 'Inter, system-ui, sans-serif' },
                        color: '#64748b',
                        callback: function (v) { return f(v); }
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        font: { size: 11, family: 'Inter, system-ui, sans-serif' },
                        color: '#64748b'
                    }
                }
            };
            charts.push(new Chart(canvas, { type: round ? 'doughnut' : d.kind, data: { labels: d.labels, datasets: sets }, options: opts }));
        });
    }

    var loaded = {}, tabs = document.querySelectorAll('.td-tab[data-tab]');

    function show(name) {
        var known = false;
        tabs.forEach(function (t) {
            var on = t.dataset.tab === name;
            known = known || on;
            t.classList.toggle('active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        if (!known) name = 'today';
        document.querySelectorAll('.td-panel').forEach(function (p) { p.hidden = p.id !== 'td-panel-' + name; });
        if (name === 'today' || loaded[name]) { if (name !== 'today') initCharts(document.getElementById('td-panel-' + name)); return; }
        loaded[name] = true;
        var panel = document.getElementById('td-panel-' + name);
        get(root.dataset.tab + '/' + name + query, true)
            .then(function (html) { panel.innerHTML = html; initCharts(panel); })
            .catch(function () { loaded[name] = false; panel.innerHTML = '<div class="td-err">This tab could not be loaded. Click it again to retry.</div>'; });
    }

    tabs.forEach(function (t) {
        t.addEventListener('click', function (e) {
            e.preventDefault();
            history.replaceState(null, '', '#' + t.dataset.tab);
            show(t.dataset.tab);
        });
    });
    // Start tab: a link with #tab wins, otherwise the one this user pinned (or their role's default)
    var active = 'today', pinned = root.dataset.start || 'today', pinBtn = document.getElementById('td-pin');

    function paintPin() {
        if (!pinBtn) return;
        var isStart = active === pinned;
        pinBtn.classList.toggle('on', isStart);
        pinBtn.querySelector('span').textContent = isStart ? 'This is your start tab' : 'Make this my start tab';
    }

    var baseShow = show;
    show = function (name) { baseShow(name); active = document.querySelector('.td-tab.active').dataset.tab; paintPin(); };

    if (pinBtn) {
        pinBtn.addEventListener('click', function () {
            fetch(root.dataset.pin, {method: 'POST', credentials: 'same-origin',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': root.dataset.token, 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify({tab: active})
            }).then(function (r) { if (r.ok) { pinned = active; paintPin(); } });
        });
    }

    show(location.hash ? location.hash.slice(1) : pinned);
})();