(function () {
    'use strict';
    var root = document.getElementById('dashboard-insights');
    if (!root) return;
    var copy = JSON.parse(root.dataset.copy), payload = null;
    var locale = document.documentElement.lang || 'en';
    var numbers = new Intl.NumberFormat(locale), decimals = new Intl.NumberFormat(locale, {maximumFractionDigits: 1});
    var money = new Intl.NumberFormat(locale, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    var message = document.getElementById('insights-message'), retry = document.getElementById('insights-retry');
    function text(id, value) { var el = document.getElementById(id); if (el) el.textContent = value; }
    function interpolate(template, values) { return Object.keys(values).reduce(function (s, key) { return s.replace(':' + key, values[key]); }, template); }
    var reveal = window.IntersectionObserver ? new IntersectionObserver(function (entries) { entries.forEach(function (entry) { if (entry.isIntersecting) { entry.target.classList.add('is-revealed'); reveal.unobserve(entry.target); } }); }, {threshold: 0.05}) : null;
    function revealBars(body) {
        body.classList.remove('is-revealed');
        if (reveal && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) reveal.observe(body);
        else body.classList.add('is-revealed');
    }
    function barColor(chart, label) {
        if (chart !== 'statuses') return 'var(--ui-primary-button, var(--ui-accent))';
        if (label === root.dataset.ready) return 'var(--ui-status-ready)';
        if (label === root.dataset.deployed) return 'var(--ui-status-deployed)';
        if (label === root.dataset.pending) return 'var(--ui-status-pending)';
        return 'var(--ui-chart-series-3, var(--ui-muted))';
    }
    function chart(name, rows, financial) {
        var card = document.querySelector('[data-insight-chart="' + name + '"]');
        if (!card) return;
        var body = card.querySelector('.insight-bars'), button = card.querySelector('.insight-expand');
        rows = rows.slice().sort(function (a, b) { return (financial ? b.amount - a.amount : b.count - a.count) || a.label.localeCompare(b.label); });
        var max = Math.max.apply(null, rows.map(function (row) { return financial ? row.amount : row.count; }).concat([0]));
        body.style.setProperty('--insight-value-width', Math.max(40, (financial ? money.format(max) : numbers.format(max)).length * 7 + 6) + 'px');
        var expanded = false;
        function draw() {
            body.replaceChildren();
            if (!rows.length) { var empty = document.createElement('p'); empty.className = 'help-block'; empty.textContent = copy.chart_empty; body.appendChild(empty); return; }
            var list = document.createElement('ul'); list.className = 'insight-bar-list';
            (expanded ? rows : rows.slice(0, 6)).forEach(function (row) {
                var value = financial ? row.amount : row.count;
                var li = document.createElement('li'), label = document.createElement('span'), track = document.createElement('span'), bar = document.createElement('span'), count = document.createElement('strong');
                label.className = 'insight-bar-label'; label.textContent = row.label;
                track.className = 'insight-bar-track'; track.setAttribute('aria-hidden', 'true');
                bar.className = 'insight-bar-fill'; bar.style.width = (max > 0 ? Math.max(0, value / max * 100) : 0) + '%'; bar.style.backgroundColor = barColor(name, row.label);
                count.className = 'insight-bar-count'; count.textContent = financial ? money.format(value) : numbers.format(value);
                track.appendChild(bar); li.append(label, track, count); list.appendChild(li);
            });
            body.appendChild(list);
            if (expanded && rows.length > 6) body.setAttribute('tabindex', '0'); else body.removeAttribute('tabindex');
            var axis = document.createElement('div'); axis.className = 'insight-bar-axis'; axis.setAttribute('aria-hidden', 'true');
            [0, max].forEach(function (n) { var tick = document.createElement('span'); tick.textContent = financial ? money.format(n) : numbers.format(n); axis.appendChild(tick); });
            body.appendChild(axis);
            revealBars(body);
            button.hidden = rows.length <= 6;
            button.textContent = expanded ? copy.chart_show_less : interpolate(copy.chart_show_all, {count: numbers.format(rows.length)});
            button.setAttribute('aria-expanded', String(expanded));
        }
        button.onclick = function () { expanded = !expanded; draw(); };
        draw();
    }
    function renderCosts() {
        if (!payload.assets) return;
        var costs = payload.assets.costs, selector = document.getElementById('insight-currency');
        var total = costs.totals.find(function (row) { return row.currency === selector.value; });
        if (window.StatsPurchaseCost) window.StatsPurchaseCost.render(costs.timeline || [], selector.value);
        text('insight-cost-total', total ? total.currency + ' ' + money.format(total.amount) : copy.chart_empty);
        text('insight-priced-count', total ? interpolate(copy.chart_priced, {count: numbers.format(total.priced)}) : '');
        var priced = costs.totals.reduce(function (sum, row) { return sum + row.priced; }, 0);
        text('insight-unknown-costs', interpolate(copy.chart_unknown_costs, {count: numbers.format(Math.max(0, payload.assets.total - priced))}));
        ['location', 'manufacturer', 'department'].forEach(function (group) {
            chart('cost_' + group, (costs[group] || []).filter(function (row) { return row.currency === selector.value; }), true);
            var card = document.querySelector('[data-insight-chart="cost_' + group + '"]');
            if (card) card.querySelector('.help-block').textContent = selector.value ? selector.value + ' · ' + card.dataset.description : card.dataset.description;
        });
    }
    function render(data) {
        payload = data;
        var start = new Date(data.start + 'T12:00:00'), end = new Date(data.end + 'T12:00:00');
        var dateFormat = new Intl.DateTimeFormat(locale, {day: 'numeric', month: 'short', year: 'numeric'});
        document.querySelectorAll('.insight-period').forEach(function (el) { el.textContent = interpolate(copy.chart_period, {start: dateFormat.format(start), end: dateFormat.format(end)}); });
        document.querySelectorAll('[data-insight-value]').forEach(function (el) { var key = el.dataset.insightValue; el.textContent = numbers.format(key === 'offboarding' ? data.offboarding : data.assets[key]); });
        if (!data.assets) return;
        var assets = data.assets;
        text('insights-overdue', interpolate(copy.chart_overdue, {count: numbers.format(assets.overdue)}));
        ['statuses', 'categories', 'locations'].forEach(function (key) { chart(key, assets[key], false); });
        var table = document.getElementById('insight-deployable-rows'); table.replaceChildren();
        assets.categories.forEach(function (row) {
            var tr = document.createElement('tr');
            [row.label, numbers.format(row.available), numbers.format(row.count), decimals.format(row.count ? row.available / row.count * 100 : 0) + '%'].forEach(function (value, index) { var cell = document.createElement(index ? 'td' : 'th'); if (!index) cell.scope = 'row'; cell.textContent = value; tr.appendChild(cell); });
            table.appendChild(tr);
        });
        if (!assets.categories.length) { var emptyRow = table.insertRow(); var emptyCell = emptyRow.insertCell(); emptyCell.colSpan = 4; emptyCell.textContent = copy.chart_empty; }
        var utilisation = assets.total ? assets.deployed / assets.total * 100 : 0;
        text('insight-utilisation-value', decimals.format(utilisation) + '%');
        text('insight-utilisation-context', interpolate(copy.chart_checked_out, {deployed: numbers.format(assets.deployed), total: numbers.format(assets.total)}));
        var meter = document.getElementById('insight-utilisation-meter'); meter.value = utilisation;
        var selector = document.getElementById('insight-currency'); selector.replaceChildren();
        assets.costs.totals.forEach(function (row) { var option = document.createElement('option'); option.value = row.currency; option.textContent = row.currency; selector.appendChild(option); });
        selector.disabled = !assets.costs.totals.length;
        if (assets.costs.totals.some(function (row) { return row.currency === 'ZAR'; })) selector.value = 'ZAR';
        selector.onchange = renderCosts; renderCosts();
    }
    async function load() {
        message.hidden = false; retry.hidden = true; message.textContent = root.dataset.loading; root.setAttribute('aria-busy', 'true');
        try {
            var response = await fetch(root.dataset.url, {credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}});
            if (!response.ok) throw new Error();
            var data = await response.json(); if (data.status !== 'success') throw new Error();
            render(data.payload); message.hidden = true;
        } catch (error) { message.textContent = copy.chart_error; retry.hidden = false; }
        finally { root.setAttribute('aria-busy', 'false'); }
    }
    retry.addEventListener('click', load);
    load();
})();
