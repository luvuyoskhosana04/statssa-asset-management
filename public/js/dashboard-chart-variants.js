(function () {
    'use strict';
    function composition(rows, financial) {
        var sorted = rows.slice().sort(function (a, b) { return Number(financial ? b.amount : b.count) - Number(financial ? a.amount : a.count) || a.label.localeCompare(b.label); });
        var total = sorted.reduce(function (sum, row) { return sum + Number(financial ? row.amount : row.count); }, 0);
        return {total: total, rows: sorted.map(function (row) { return {label: row.label, value: Number(financial ? row.amount : row.count), share: total > 0 ? Number(financial ? row.amount : row.count) / total : 0}; })};
    }
    function formatShare(share, financial, locale) {
        var formatter = new Intl.NumberFormat(locale || 'en', {style: 'percent', maximumFractionDigits: financial ? 2 : 1});
        var threshold = financial ? 0.0001 : 0.001;
        if (share > 0 && share < threshold) return '<' + formatter.format(threshold);
        if (share < 1 && share > 1 - threshold) return '>' + formatter.format(1 - threshold);
        return formatter.format(share);
    }
    if (typeof module !== 'undefined' && module.exports) { module.exports = {composition: composition, formatShare: formatShare}; return; }
    var ns = 'http://www.w3.org/2000/svg';
    var colors = ['var(--ui-chart-series-2)', 'var(--ui-chart-series-1)', 'var(--ui-chart-series-3)', 'var(--ui-chart-series-4)', 'var(--ui-chart-series-5)', 'var(--ui-chart-series-6)', 'var(--ui-chart-series-2)', 'var(--ui-chart-series-3)'];
    function node(tag, className, text) { var element = document.createElement(tag); if (className) element.className = className; if (text !== undefined) element.textContent = text; return element; }
    function svgNode(tag, attributes) { var element = document.createElementNS(ns, tag); Object.keys(attributes).forEach(function (key) { element.setAttribute(key, attributes[key]); }); return element; }
    var columnTooltipId = 0;
    function columns(container, rows, options) {
        container.replaceChildren(); container.classList.add('dc-chart');
        if (!rows.length) return;
        var max = Math.max.apply(null, rows.map(function (row) { return row.value; }).concat([0]));
        var min = Math.min.apply(null, rows.map(function (row) { return row.value; }).concat([0]));
        var range = max - min || 1, zero = -min / range * 100;
        var inspector = node('div', 'dc-inspector'); inspector.hidden = true;
        inspector.id = 'dc-tooltip-' + (++columnTooltipId); inspector.setAttribute('role', 'tooltip');
        var info = node('span', 'dc-inspector-label'), amount = node('strong', 'dc-inspector-value');
        inspector.append(info, amount); container.append(inspector);
        var plot = node('div', 'dc-plot');
        plot.style.setProperty('--dc-zero', zero + '%');
        var buttons = [], active = null, activeShow = null;
        function hide() {
            inspector.hidden = true;
            buttons.forEach(function (item) { item.classList.remove('is-selected'); item.removeAttribute('aria-describedby'); });
            active = null; activeShow = null;
        }
        rows.forEach(function (row, index) {
            var button = node('button', 'dc-column'); button.type = 'button';
            button.setAttribute('aria-label', row.label + ': ' + options.format(row.value));
            var measure = node('span', 'dc-column-measure'); measure.setAttribute('aria-hidden', 'true');
            var bar = node('span', 'dc-bar');
            bar.style.height = Math.abs(row.value) / range * 100 + '%';
            bar.style.bottom = (row.value < 0 ? zero - Math.abs(row.value) / range * 100 : zero) + '%';
            if (!row.value) bar.classList.add('is-zero');
            if (row.value < 0) bar.classList.add('is-negative');
            measure.appendChild(bar); button.append(measure, node('span', 'dc-column-label', row.label));
            function show() {
                buttons.forEach(function (item) { item.classList.toggle('is-selected', item === button); item.removeAttribute('aria-describedby'); });
                active = button; activeShow = show; button.setAttribute('aria-describedby', inspector.id);
                info.textContent = row.label; amount.textContent = options.format(row.value); inspector.hidden = false;
                var bounds = container.getBoundingClientRect(), mark = bar.getBoundingClientRect(), graph = measure.getBoundingClientRect();
                var width = inspector.offsetWidth, height = inspector.offsetHeight;
                var left = Math.max(4, Math.min(mark.left + mark.width / 2 - bounds.left - width / 2, bounds.width - width - 4));
                var top = Math.max(4, Math.min(mark.top - bounds.top + 8, graph.bottom - bounds.top - height - 8));
                inspector.style.left = left + 'px'; inspector.style.top = top + 'px';
            }
            button.addEventListener('mouseenter', show);
            button.addEventListener('focus', show);
            button.addEventListener('click', show);
            button.addEventListener('mouseleave', function () { if (!button.matches(':focus-visible')) hide(); });
            button.addEventListener('blur', function () { if (active === button) hide(); });
            button.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') { hide(); event.preventDefault(); return; }
                var target = event.key === 'ArrowRight' ? index + 1 : event.key === 'ArrowLeft' ? index - 1 : event.key === 'Home' ? 0 : event.key === 'End' ? rows.length - 1 : null;
                if (target !== null) { event.preventDefault(); buttons[(target + buttons.length) % buttons.length].focus(); }
            });
            buttons.push(button); plot.appendChild(button);
        });
        container.appendChild(plot);
        plot.addEventListener('scroll', function () { if (active && active.matches(':focus-visible')) activeShow(); else hide(); }, {passive: true});
        container.addEventListener('mouseleave', function () { if (!active || !active.matches(':focus-visible')) hide(); });
        var axis = node('div', 'dc-scale'); axis.setAttribute('aria-hidden', 'true');
        axis.append(node('span', '', options.format(min)), node('span', '', options.format(max))); container.appendChild(axis);
        requestAnimationFrame(function () {
            if (plot.clientWidth && plot.scrollWidth > plot.clientWidth) {
                var index = Math.max(0, rows.findIndex(function (row) { return row.value === max; }));
                var initial = buttons[index];
                plot.scrollLeft = Math.max(0, initial.offsetLeft - plot.clientWidth / 2 + initial.offsetWidth / 2);
            }
        });
    }

    function render(name, source, financial, copy) {
        var type = {categories: 'donut', locations: 'columns', cost_location: 'ranked', cost_manufacturer: 'composition', cost_department: 'tiles'}[name];
        if (!type) return false;
        var card = document.querySelector('[data-insight-chart="' + name + '"]'); if (!card) return true;
        var body = card.querySelector('.insight-bars'), expand = card.querySelector('.insight-expand'), data = composition(source, financial);
        if (data.rows.some(function (row) { return row.value < 0; })) type = 'ranked';
        var locale = document.documentElement.lang || 'en';
        var numbers = new Intl.NumberFormat(locale, financial ? {minimumFractionDigits: 2, maximumFractionDigits: 2} : {});
        var expanded = false, max = Math.max.apply(null, data.rows.map(function (row) { return row.value; }).concat([0]));
        function draw() {
            body.replaceChildren(); body.classList.add('insight-chart-variant'); body.dataset.visual = type;
            body.classList.toggle('is-expanded', expanded); if (expanded) body.tabIndex = 0; else body.removeAttribute('tabindex');
            expand.hidden = data.rows.length <= 6;
            expand.setAttribute('aria-expanded', String(expanded));
            expand.textContent = expanded ? copy.chart_show_less : copy.chart_show_all.replace(':count', data.rows.length.toLocaleString(locale));
            if (!data.rows.length) { body.appendChild(node('p', 'help-block', copy.chart_empty)); return; }
            var layout = node('div', type === 'donut' ? 'insight-donut-layout' : 'insight-variant-layout');
            if (type === 'columns') {
                columns(layout, expanded ? data.rows : data.rows.slice(0, 6), {format: function (value) { return numbers.format(value); }});
                body.appendChild(layout); return;
            }
            if (type === 'donut') {
                var graphic = node('div', 'insight-donut-graphic'); 
                var svg = svgNode('svg', {viewBox: '0 0 160 160', 'aria-hidden': 'true'}), radius = 62, circumference = 2 * Math.PI * radius, offset = 0;
                svg.appendChild(svgNode('circle', {cx: 80, cy: 80, r: radius, fill: 'none', stroke: 'var(--ui-line)', 'stroke-width': 18}));
                data.rows.forEach(function (row, index) {
                    if (row.share <= 0) return;
                    svg.appendChild(svgNode('circle', {cx: 80, cy: 80, r: radius, fill: 'none', stroke: colors[index % colors.length], 'stroke-width': 18,
                        'stroke-dasharray': (row.share * circumference) + ' ' + ((1 - row.share) * circumference), 'stroke-dashoffset': -offset, transform: 'rotate(-90 80 80)'}));
                    offset += row.share * circumference;
                });
                graphic.appendChild(svg); var center = node('div', 'insight-donut-center'); var totalNumber = node('strong'); window.StatsDashboardNumbers.count(totalNumber, data.total, numbers.format); center.append(totalNumber, node('span', '', copy.chart_active_assets)); graphic.appendChild(center); layout.appendChild(graphic);
            }
            if (type === 'composition') {
                var strip = node('div', 'insight-composition-strip'); strip.setAttribute('aria-hidden', 'true');
                data.rows.forEach(function (row, index) { var segment = node('span'); segment.style.width = row.share * 100 + '%'; segment.style.backgroundColor = colors[index % colors.length]; strip.appendChild(segment); });
                layout.appendChild(strip);
                layout.appendChild(node('p', 'insight-composition-caption', copy.chart_cost_composition));
            }
            var list = node('ul', 'insight-variant-list');
            (expanded ? data.rows : data.rows.slice(0, 6)).forEach(function (row, index) {
                var li = node('li'), label = node('span', 'insight-variant-label', row.label), value = node('strong', 'insight-variant-value');
                (financial ? window.StatsDashboardNumbers.set : window.StatsDashboardNumbers.count)(value, row.value, numbers.format);
                if (type === 'tiles') {
                    li.append(label, value, node('span', 'insight-variant-share', formatShare(row.share, financial, locale)));
                } else if (type === 'dots') {
                    var track = node('span', 'insight-dot-track'); track.setAttribute('aria-hidden', 'true');
                    var point = node('span', 'insight-dot-point'); point.style.left = (max > 0 ? row.value / max * 100 : 0) + '%'; track.appendChild(point);
                    li.append(label, track, value);
                } else if (type === 'ranked') {
                    li.append(node('span', 'insight-rank', (index + 1).toLocaleString(locale)), label, value);
                } else {
                    var swatch = node('span', 'insight-variant-swatch'); swatch.setAttribute('aria-hidden', 'true'); swatch.style.backgroundColor = colors[index % colors.length];
                    var measure = node('span', 'insight-variant-measure'); measure.append(value, node('span', 'insight-variant-share', formatShare(row.share, financial, locale)));
                    li.append(swatch, label, measure);
                }
                list.appendChild(li);
            });
            layout.appendChild(list); body.appendChild(layout);
            if (type === 'dots') { var axis = node('div', 'insight-dot-axis'); axis.setAttribute('aria-hidden', 'true'); axis.append(node('span', '', '0'), node('span', '', numbers.format(max))); body.appendChild(axis); }
        }
        expand.onclick = function () { expanded = !expanded; draw(); }; draw(); return true;
    }
    window.StatsDashboardCharts = {render: render, columns: columns};
})();
