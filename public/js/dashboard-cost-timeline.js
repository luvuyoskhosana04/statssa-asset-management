(function () {
    'use strict';
    function monthIndex(period) {
        var match = typeof period === 'string' && period.match(/^(\d{4})-(\d{2})-01$/);
        if (!match || Number(match[1]) < 1 || Number(match[2]) < 1 || Number(match[2]) > 12) return null;
        return Number(match[1]) * 12 + Number(match[2]) - 1;
    }
    function buildBuckets(rows, currency) {
        var dated = rows.filter(function (row) { return monthIndex(row.period) !== null; });
        var selected = rows.filter(function (row) { return row.currency === currency; });
        var undated = selected.filter(function (row) { return monthIndex(row.period) === null; }).reduce(function (sum, row) {
            return {amount: sum.amount + Number(row.amount), priced: sum.priced + Number(row.priced)};
        }, {amount: 0, priced: 0});
        if (!dated.length || !selected.some(function (row) { return monthIndex(row.period) !== null; })) return {step: 1, buckets: [], undated: undated};
        var first = Infinity, last = -Infinity;
        dated.forEach(function (row) { var index = monthIndex(row.period); first = Math.min(first, index); last = Math.max(last, index); });
        var span = last - first + 1;
        var step = span <= 24 ? 1 : span <= 72 ? 3 : span <= 288 ? 12 : Math.ceil(span / 288) * 12;
        first = Math.floor(first / step) * step;
        var buckets = [];
        for (var start = first; start <= last; start += step) buckets.push({start: start, end: start + step - 1, amount: 0, priced: 0});
        selected.forEach(function (row) {
            var index = monthIndex(row.period); if (index === null) return;
            var bucket = buckets[Math.floor((index - first) / step)];
            bucket.amount += Number(row.amount); bucket.priced += Number(row.priced);
        });
        return {step: step, buckets: buckets, undated: undated};
    }
    if (typeof module !== 'undefined' && module.exports) { module.exports = {buildBuckets: buildBuckets}; return; }
    var observer = window.IntersectionObserver ? new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) { if (entry.isIntersecting) { entry.target.classList.add('is-revealed'); observer.unobserve(entry.target); } });
    }, {threshold: 0.1}) : null;
    var ns = 'http://www.w3.org/2000/svg';
    var cachedRows = [], cachedCurrency = '', activeCurrency = '', activePeriod = null, lastWidth = 0, resizeObserver = null;
    function svgNode(tag, attributes) {
        var node = document.createElementNS(ns, tag);
        Object.keys(attributes || {}).forEach(function (name) { node.setAttribute(name, attributes[name]); });
        return node;
    }
    function render(rows, currency) {
        var root = document.getElementById('dashboard-insights'), plot = document.getElementById('cost-timeline-plot');
        if (!root || !plot) return;
        var copy = JSON.parse(root.dataset.copy), locale = document.documentElement.lang || 'en';
        var money = new Intl.NumberFormat(locale, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        var compact = new Intl.NumberFormat(locale, {notation: 'compact', maximumFractionDigits: 1});
        var number = new Intl.NumberFormat(locale), month = new Intl.DateTimeFormat(locale, {month: 'short', year: 'numeric'});
        function interpolate(text, values) { return Object.keys(values).reduce(function (out, key) { return out.replace(':' + key, values[key]); }, text); }
        function amount(value) { return currency + ' ' + money.format(value); }
        var data = buildBuckets(rows, currency), buckets = data.buckets;
        var remembered = activeCurrency === currency ? activePeriod : null; activeCurrency = currency; cachedRows = rows; cachedCurrency = currency;
        var heading = document.getElementById('cost-timeline-heading'), detail = document.getElementById('cost-timeline-detail');
        var table = document.getElementById('cost-timeline-table'), buttons = document.getElementById('cost-timeline-periods');
        var graphic = document.getElementById('cost-timeline-svg'), empty = document.getElementById('cost-timeline-empty');
        var undated = document.getElementById('cost-timeline-undated'), axis = document.querySelector('.cost-timeline-axis');
        graphic.replaceChildren(); buttons.replaceChildren(); table.replaceChildren(); detail.textContent = ''; plot.classList.remove('is-revealed');
        heading.textContent = data.step === 1 ? copy.chart_cost_monthly : data.step === 3 ? copy.chart_cost_quarterly : data.step === 12 ? copy.chart_cost_yearly : interpolate(copy.chart_cost_multi_year, {count: data.step / 12});
        undated.hidden = !data.undated.priced;
        undated.textContent = data.undated.priced ? interpolate(copy.chart_cost_undated, {amount: amount(data.undated.amount), count: number.format(data.undated.priced)}) : '';
        empty.hidden = buckets.length > 0; empty.textContent = copy.chart_cost_no_dates;
        axis.hidden = !buckets.length; plot.hidden = !buckets.length;
        function label(bucket) {
            var year = Math.floor(bucket.start / 12), m = bucket.start % 12;
            if (data.step >= 12) return data.step === 12 ? String(year) : year + ' to ' + Math.floor(bucket.end / 12);
            if (data.step === 3) return interpolate(copy.chart_cost_quarter, {quarter: Math.floor(m / 3) + 1, year: year});
            var date = new Date(0); date.setFullYear(year, m, 1); date.setHours(12);
            return month.format(date);
        }
        function tableRow(period, value, count) {
            var row = document.createElement('tr'), name = document.createElement('th'); name.scope = 'row'; name.textContent = period; row.appendChild(name);
            [amount(value), number.format(count)].forEach(function (value) { var cell = row.insertCell(); cell.textContent = value; }); table.appendChild(row);
        }
        buckets.forEach(function (bucket) { tableRow(label(bucket), bucket.amount, bucket.priced); });
        if (data.undated.priced) tableRow(copy.chart_cost_undated_label, data.undated.amount, data.undated.priced);
        if (!buckets.length) return;
        var width = Math.max(600, buckets.length * 64, plot.parentElement.clientWidth), height = 194, left = 14, right = width - 14, baseline = 174, top = 18;
        var max = Math.max.apply(null, buckets.map(function (bucket) { return bucket.amount; }).concat([0]));
        var dx = (right - left) / buckets.length;
        var points = buckets.map(function (bucket, index) { return {x: left + dx * (index + 0.5), y: baseline - (max ? bucket.amount / max : 0) * (baseline - top)}; });
        plot.style.minWidth = width + 'px'; buttons.style.gridTemplateColumns = 'repeat(' + buckets.length + ', minmax(0, 1fr))';
        document.getElementById('cost-timeline-max').textContent = compact.format(max);
        var svg = svgNode('svg', {viewBox: '0 0 ' + width + ' ' + height, preserveAspectRatio: 'none'});
        var defs = svgNode('defs'), pattern = svgNode('pattern', {id: 'cost-dot-pattern', width: 8, height: 8, patternUnits: 'userSpaceOnUse'});
        pattern.appendChild(svgNode('circle', {cx: 3, cy: 3, r: 1.65, class: 'cost-dot'})); defs.appendChild(pattern); svg.appendChild(defs);
        [top, baseline].forEach(function (y) { svg.appendChild(svgNode('line', {x1: left, x2: right, y1: y, y2: y, class: 'cost-grid-line'})); });
        var line = points.map(function (point, index) { return (index ? 'L' : 'M') + point.x + ' ' + point.y; }).join(' ');
        var area = points.length === 1 ? 'M' + (points[0].x - 10) + ' ' + baseline + ' V' + points[0].y + ' H' + (points[0].x + 10) + ' V' + baseline + ' Z'
            : 'M' + points[0].x + ' ' + baseline + ' L' + points[0].x + ' ' + points[0].y + ' ' + points.slice(1).map(function (point) { return 'L' + point.x + ' ' + point.y; }).join(' ') + ' L' + points[points.length - 1].x + ' ' + baseline + ' Z';
        var dots = svgNode('path', {d: area, fill: 'url(#cost-dot-pattern)', class: 'cost-dot-area'}); svg.appendChild(dots);
        svg.appendChild(svgNode('path', {d: line, class: 'cost-trend-line'}));
        var guide = svgNode('line', {y1: top, y2: baseline, class: 'cost-selected-guide'}); svg.appendChild(guide);
        var markers = points.map(function (point, index) { var circle = svgNode('circle', {cx: point.x, cy: point.y, r: 3, class: 'cost-point'}); svg.appendChild(circle); return circle; });
        graphic.appendChild(svg);
        var selected = -1, controls = [];
        function describe(index) { var bucket = buckets[index]; return interpolate(copy.chart_cost_period_detail, {period: label(bucket), amount: amount(bucket.amount), count: number.format(bucket.priced)}); }
        function select(index) {
            if (selected === index) return; selected = index; activePeriod = buckets[index].start;
            detail.textContent = describe(index); guide.setAttribute('x1', points[index].x); guide.setAttribute('x2', points[index].x);
            controls.forEach(function (button, i) { button.setAttribute('aria-pressed', String(i === index)); button.tabIndex = i === index ? 0 : -1; markers[i].classList.toggle('is-selected', i === index); markers[i].setAttribute('r', i === index ? 5 : 3); });
        }
        buckets.forEach(function (bucket, index) {
            var button = document.createElement('button'); button.type = 'button'; button.className = 'cost-period'; button.textContent = label(bucket);
            button.setAttribute('aria-label', describe(index)); button.title = describe(index);
            button.addEventListener('focus', function () { select(index); }); button.addEventListener('click', function () { select(index); }); button.addEventListener('pointerenter', function () { select(index); });
            button.addEventListener('keydown', function (event) {
                var next = event.key === 'ArrowRight' ? Math.min(index + 1, buckets.length - 1) : event.key === 'ArrowLeft' ? Math.max(index - 1, 0) : event.key === 'Home' ? 0 : event.key === 'End' ? buckets.length - 1 : null;
                if (next !== null) { event.preventDefault(); controls[next].focus(); }
            });
            controls.push(button); buttons.appendChild(button);
        });
        svg.addEventListener('pointermove', function (event) {
            var x = (event.clientX - svg.getBoundingClientRect().left) / svg.getBoundingClientRect().width * width;
            select(Math.max(0, Math.min(buckets.length - 1, Math.floor((x - left) / dx))));
        });
        var latest = buckets.reduce(function (last, bucket, index) { return bucket.priced ? index : last; }, 0);
        var restored = buckets.findIndex(function (bucket) { return bucket.start === remembered; }); select(restored >= 0 ? restored : latest);
        lastWidth = plot.parentElement.clientWidth;
        if (window.ResizeObserver && !resizeObserver) { resizeObserver = new ResizeObserver(function () { if (plot.parentElement.clientWidth !== lastWidth) render(cachedRows, cachedCurrency); }); resizeObserver.observe(plot.parentElement); }
        if (observer && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) observer.observe(plot); else plot.classList.add('is-revealed');
    }
    window.StatsPurchaseCost = {render: render};
})();
