(function () {
    'use strict';

    var panel = document.querySelector('.status-overview');
    if (!panel) return;

    var body = panel.querySelector('[data-status-url]');
    var ring = panel.querySelector('.status-ring');
    var message = panel.querySelector('.status-chart-message');
    var totalElement = panel.querySelector('.status-total-value');
    var legend = panel.querySelector('.status-breakdown');
    var chart;
    var locale = document.documentElement.lang || 'en';
    var integers = new Intl.NumberFormat(locale);
    var percentages = new Intl.NumberFormat(locale, {maximumFractionDigits: 1});

    function normalize(value) {
        return String(value).trim().toLocaleLowerCase(locale);
    }

    function statusColor(label, fallback, index) {
        var styles = getComputedStyle(document.body);
        function token(name) { return styles.getPropertyValue('--ui-status-' + name).trim(); }
        var name = normalize(label);
        if (name === normalize(body.dataset.statusReady)) return token('ready') || '#657e6e';
        if (name === normalize(body.dataset.statusDeployed)) return token('deployed') || '#9c8965';
        if (name.indexOf(normalize(body.dataset.statusPending)) === 0) {
            return name === normalize(body.dataset.statusPending) ? token('pending') || '#947d44' : token('validation') || '#938f84';
        }
        if (name === normalize(body.dataset.statusArchived) || name === normalize(body.dataset.statusUndeployable)) {
            return token('archived') || '#767268';
        }
        return styles.getPropertyValue('--ui-chart-series-' + (index % 6 + 1)).trim() || '#5c5952';
    }

    function applyTheme() {
        if (!chart) return;
        var styles = getComputedStyle(document.body);
        chart.data.datasets[0].borderColor = styles.getPropertyValue('--ui-surface').trim();
        chart.data.datasets[0].backgroundColor = chart.data.labels.map(function (label, index) {
            var color = statusColor(label, null, index);
            var swatch = legend.querySelectorAll('.status-swatch')[index];
            if (swatch) swatch.style.backgroundColor = color;
            return color;
        });
        chart.update(0);
    }

    $.ajax({
        type: 'GET',
        url: body.dataset.statusUrl,
        dataType: 'json',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function (data) {
            var dataset = data.datasets && data.datasets[0];
            if (!dataset || !Array.isArray(dataset.data)) {
                message.textContent = message.dataset.error;
                return;
            }

            var rows = dataset.data.map(function (value, index) {
                var label = String(data.labels[index] || '').replace(/\s\(\d+\)$/, '');
                return {
                    label: label,
                    count: Number(value),
                    color: statusColor(label, (dataset.backgroundColor || [])[index], index)
                };
            }).filter(function (row) {
                return Number.isFinite(row.count) && row.count > 0;
            }).sort(function (a, b) { return b.count - a.count; });

            var total = rows.reduce(function (sum, row) { return sum + row.count; }, 0);
            totalElement.textContent = integers.format(total);
            message.hidden = total > 0;
            ring.hidden = total === 0;
            if (!total) {
                message.textContent = message.dataset.empty;
                return;
            }

            rows.forEach(function (row) {
                var entry = document.createElement('li');
                var swatch = document.createElement('span');
                swatch.className = 'status-swatch';
                swatch.style.backgroundColor = row.color;
                swatch.setAttribute('aria-hidden', 'true');
                var label = document.createElement('span');
                label.className = 'status-name';
                label.textContent = row.label;
                var count = document.createElement('strong');
                count.textContent = integers.format(row.count);
                var percentage = document.createElement('span');
                percentage.className = 'status-percentage';
                percentage.textContent = percentages.format(row.count / total * 100) + '%';
                entry.append(swatch, label, count, percentage);
                legend.appendChild(entry);
            });

            chart = new Chart(panel.querySelector('canvas'), {
                type: 'doughnut',
                data: {
                    labels: rows.map(function (row) { return row.label; }),
                    datasets: [{
                        data: rows.map(function (row) { return row.count; }),
                        backgroundColor: rows.map(function (row) { return row.color; }),
                        borderColor: getComputedStyle(document.body).getPropertyValue('--ui-surface').trim(),
                        borderWidth: 3,
                        hoverBorderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutoutPercentage: 80,
                    legend: {display: false},
                    animation: {duration: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 1400, easing: 'easeInOutCubic'},
                    tooltips: {
                        displayColors: false,
                        bodyFontFamily: 'Arial, sans-serif',
                        titleFontFamily: 'Arial, sans-serif',
                        cornerRadius: 8,
                        xPadding: 12,
                        yPadding: 10,
                        callbacks: {
                            label: function (tooltip) {
                                var row = rows[tooltip.index];
                                return row.label + ': ' + integers.format(row.count) + ' (' + percentages.format(row.count / total * 100) + '%)';
                            }
                        }
                    }
                }
            });
        },
        error: function () {
            message.textContent = message.dataset.error;
        }
    });

    new MutationObserver(applyTheme).observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });
})();
