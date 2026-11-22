(function () {
    'use strict';
    function buildLedger(rows, currency) {
        var months = new Map(), undated = {amount: 0, priced: 0}, total = 0, priced = 0;
        rows.filter(function (row) { return row.currency === currency; }).forEach(function (row) {
            var amount = Number(row.amount), count = Number(row.priced);
            total += amount; priced += count;
            var match = typeof row.period === 'string' && row.period.match(/^(\d{4})-(\d{2})-01$/);
            if (!match || Number(match[1]) < 1 || Number(match[2]) < 1 || Number(match[2]) > 12) { undated.amount += amount; undated.priced += count; return; }
            var entry = months.get(row.period) || {period: row.period, amount: 0, priced: 0};
            entry.amount += amount; entry.priced += count; months.set(row.period, entry);
        });
        return {total: total, priced: priced, average: priced ? total / priced : 0, undated: undated,
            entries: Array.from(months.values()).sort(function (a, b) { return b.period.localeCompare(a.period); })};
    }
    function yearBins(entries, year) {
        return Array.from({length: 12}, function (_, month) {
            var period = String(year) + '-' + String(month + 1).padStart(2, '0') + '-01';
            var matches = entries.filter(function (row) { return row.period === period; });
            return {period: period, amount: matches.reduce(function (sum, row) { return sum + row.amount; }, 0), priced: matches.reduce(function (sum, row) { return sum + row.priced; }, 0)};
        });
    }
    if (typeof module !== 'undefined' && module.exports) { module.exports = {buildLedger: buildLedger, yearBins: yearBins}; return; }
    var previousCurrency = '', selectedYear = '';
    function render(rows, currency, coverage) {
        var root = document.getElementById('dashboard-insights'), list = document.getElementById('purchase-ledger-list');
        if (!root || !list) return;
        var copy = JSON.parse(root.dataset.copy), data = buildLedger(rows, currency), locale = document.documentElement.lang || 'en';
        var money = new Intl.NumberFormat(locale, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        var numbers = new Intl.NumberFormat(locale), percentage = new Intl.NumberFormat(locale, {style: 'percent', maximumFractionDigits: 1});
        function amount(value) { return currency + ' ' + money.format(value); }
        function interpolate(text, values) { return Object.keys(values).reduce(function (out, key) { return out.replace(':' + key, values[key]); }, text); }
        function dateLabel(period, options) { var date = new Date(0); date.setFullYear(Number(period.slice(0, 4)), Number(period.slice(5, 7)) - 1, 1); date.setHours(12); return new Intl.DateTimeFormat(locale, options).format(date); }
        if (data.priced) window.StatsDashboardNumbers.set(document.getElementById('purchase-average-value'), data.average, amount);
        else document.getElementById('purchase-average-value').textContent = copy.chart_empty;
        document.getElementById('purchase-latest-value').textContent = data.entries.length ? dateLabel(data.entries[0].period, {month: 'short', year: 'numeric'}) : copy.chart_cost_undated_label;
        var ratio = coverage && coverage.assetTotal ? coverage.pricedTotal / coverage.assetTotal : 0;
        window.StatsDashboardNumbers.set(document.getElementById('purchase-coverage-value'), ratio, percentage.format);
        document.getElementById('purchase-coverage-count').textContent = interpolate(copy.chart_cost_coverage_count, {count: numbers.format(coverage ? coverage.pricedTotal : 0), total: numbers.format(coverage ? coverage.assetTotal : 0)});
        var year = document.getElementById('purchase-ledger-year');
        var years = Array.from(new Set(data.entries.map(function (entry) { return entry.period.slice(0,4); })));
        if (previousCurrency !== currency) selectedYear = ''; previousCurrency = currency;
        if (selectedYear && years.indexOf(selectedYear) === -1) selectedYear = '';
        year.replaceChildren(); var all = document.createElement('option'); all.value = ''; all.textContent = copy.chart_cost_all_years; year.appendChild(all);
        years.forEach(function (value) { var option = document.createElement('option'); option.value = value; option.textContent = value; year.appendChild(option); });
        year.value = selectedYear; year.disabled = !years.length;
        var expanded = false, expand = document.getElementById('purchase-ledger-expand');
        function draw() {
            list.replaceChildren();
            var chart = document.getElementById('purchase-history-chart');
            var chartYear = selectedYear || years[0];
            if (chart) {
                chart.replaceChildren();
                document.getElementById('purchase-history-year').textContent = chartYear || '';
                if (chartYear && window.StatsDashboardCharts) {
                    window.StatsDashboardCharts.columns(chart, yearBins(data.entries, chartYear).map(function (entry) {
                        return {label: dateLabel(entry.period, {month: 'short'}), value: entry.amount};
                    }), {format: amount});
                } else {
                    chart.textContent = copy.chart_cost_no_dates;
                }
            }

            var entries = data.entries.filter(function (entry) { return !selectedYear || entry.period.startsWith(selectedYear + '-'); });
            (expanded ? entries : entries.slice(0,4)).forEach(function (entry) {
                var item = document.createElement('li'), date = document.createElement('time'); date.dateTime = entry.period;
                var largest = Math.max.apply(null, entries.map(function (row) { return row.amount; }).concat([0]));
                item.style.setProperty('--purchase-share', (largest > 0 ? Math.max(0, entry.amount / largest * 100) : 0) + '%');
                date.className = 'purchase-ledger-date';
                var month = document.createElement('strong'); month.textContent = dateLabel(entry.period, {month: 'short'});
                var yearLabel = document.createElement('span'); yearLabel.textContent = entry.period.slice(0,4); date.append(month, yearLabel);
                var info = document.createElement('div'), title = document.createElement('strong'), count = document.createElement('span');
                title.textContent = dateLabel(entry.period, {month: 'long', year: 'numeric'}); count.textContent = interpolate(copy.chart_priced, {count: numbers.format(entry.priced)}); info.append(title,count);
                var value = document.createElement('strong'); value.className = 'purchase-ledger-amount'; value.textContent = amount(entry.amount);
                item.append(date,info,value); list.appendChild(item);
            });
            if (data.undated.priced && !selectedYear) {
                var undated = document.createElement('li'); undated.className = 'purchase-ledger-undated';
                var label = document.createElement('div'); label.textContent = copy.chart_cost_undated_label;
                var count = document.createElement('span'); count.textContent = interpolate(copy.chart_priced, {count: numbers.format(data.undated.priced)}); label.appendChild(count);
                var value = document.createElement('strong'); value.className = 'purchase-ledger-amount'; value.textContent = amount(data.undated.amount); undated.append(label,value); list.appendChild(undated);
            }
            document.getElementById('purchase-ledger-empty').hidden = !!list.children.length;
            expand.hidden = entries.length <= 4; expand.setAttribute('aria-expanded', String(expanded));
            expand.textContent = expanded ? copy.chart_show_less : interpolate(copy.chart_show_all,{count:numbers.format(entries.length)});
        }
        year.onchange = function () { selectedYear = year.value; expanded = false; draw(); };
        expand.onclick = function () { expanded = !expanded; draw(); }; draw();
    }
    window.StatsPurchaseCost = {render: render};
})();
