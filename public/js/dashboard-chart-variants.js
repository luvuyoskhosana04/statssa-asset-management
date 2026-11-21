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
    var colors = ['var(--ui-chart-series-2)', 'var(--ui-chart-series-1)', 'var(--ui-chart-series-3)', 'var(--ui-chart-series-4)', 'var(--ui-chart-series-5)', 'var(--ui-chart-series-6)', 'var(--stats-sand-400)', 'var(--stats-stone-300)'];
    function node(tag, className, text) { var element = document.createElement(tag); if (className) element.className = className; if (text !== undefined) element.textContent = text; return element; }
    function svgNode(tag, attributes) { var element = document.createElementNS(ns, tag); Object.keys(attributes).forEach(function (key) { element.setAttribute(key, attributes[key]); }); return element; }
    function render(name, source, financial, copy) {
        var type = {categories: 'donut', locations: 'dots', cost_location: 'ranked', cost_manufacturer: 'composition'}[name];
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
                graphic.appendChild(svg); var center = node('div', 'insight-donut-center'); center.append(node('strong', '', numbers.format(data.total)), node('span', '', copy.chart_active_assets)); graphic.appendChild(center); layout.appendChild(graphic);
            }
            if (type === 'composition') {
                var strip = node('div', 'insight-composition-strip'); strip.setAttribute('aria-hidden', 'true');
                data.rows.forEach(function (row, index) { var segment = node('span'); segment.style.width = row.share * 100 + '%'; segment.style.backgroundColor = colors[index % colors.length]; strip.appendChild(segment); });
                layout.appendChild(strip);
                layout.appendChild(node('p', 'insight-composition-caption', copy.chart_cost_composition));
            }
            var list = node('ul', 'insight-variant-list');
            (expanded ? data.rows : data.rows.slice(0, 6)).forEach(function (row, index) {
                var li = node('li'), label = node('span', 'insight-variant-label', row.label), value = node('strong', 'insight-variant-value', numbers.format(row.value));
                if (type === 'dots') {
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
    window.StatsDashboardCharts = {render: render};
})();
