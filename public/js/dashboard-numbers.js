(function () {
    'use strict';
    function frameValue(value, progress) {
        var fraction = Math.max(0, Math.min(1, progress));
        var eased = fraction * fraction * (3 - 2 * fraction);
        var current = value * eased;
        return value >= 0 ? Math.min(value, Math.max(0, current)) : Math.max(value, Math.min(0, current));
    }
    if (typeof module !== 'undefined' && module.exports) { module.exports = {frameValue: frameValue}; return; }
    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    var states = new WeakMap(), records = new Set(), active = new Set(), frame = null;
    var observer = 'IntersectionObserver' in window ? new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            var record = states.get(entry.target);
            if (!record) return;
            if (!entry.target.isConnected) { finish(record); return; }
            if (!entry.isIntersecting || entry.intersectionRatio < .25) return;
            observer.unobserve(entry.target);
            if (reduced.matches) { finish(record); return; }
            record.started = performance.now(); active.add(record);
            if (frame === null) frame = requestAnimationFrame(tick);
        });
    }, {threshold: .25, rootMargin: '0px 0px -32px 0px'}) : null;
    function finish(record) {
        active.delete(record); records.delete(record);
        if (observer) observer.unobserve(record.element);
        if (states.get(record.element) !== record) return;
        record.element.textContent = record.exact;
        // Keep tabular digits and the same display mode after settling.
        states.delete(record.element);
    }
    function tick(now) {
        frame = null;
        active.forEach(function (record) {
            if (!record.element.isConnected || states.get(record.element) !== record) {
                active.delete(record); records.delete(record); return;
            }
            var progress = Math.min(1, Math.max(0, (now - record.started) / 2600));
            if (progress === 1) { finish(record); return; }
            var current = frameValue(record.value, progress);
            var text = record.format(record.whole ? Math.trunc(current) : current);
            // Updating an existing Text node avoids triggering the application's
            // child-list observers and rebuilding unrelated controls every frame.
            if (record.visual.firstChild.nodeValue !== text) record.visual.firstChild.nodeValue = text;
        });
        if (active.size) frame = requestAnimationFrame(tick);
    }
    function set(element, value, format, whole) {
        if (!element) return;
        var previous = states.get(element), exact = format(value);
        if (previous && previous.value === value && previous.exact === exact && previous.whole === Boolean(whole)) return;
        if (previous) finish(previous);
        if (!previous && element.textContent === exact) return;
        element.textContent = exact;
        if (!observer || reduced.matches || !Number.isFinite(value) || value === 0) return;
        var visual = document.createElement('span'), measure = document.createElement('span'), accessible = document.createElement('span');
        visual.className = 'dashboard-number-value'; visual.textContent = format(0); visual.setAttribute('aria-hidden', 'true');
        measure.className = 'dashboard-number-measure'; measure.textContent = exact; measure.setAttribute('aria-hidden', 'true');
        accessible.className = 'sr-only'; accessible.textContent = exact;
        element.replaceChildren(visual, measure, accessible); element.classList.add('dashboard-number');
        var record = {element: element, value: value, format: format, exact: exact, visual: visual, whole: Boolean(whole)};
        states.set(element, record); records.add(record); observer.observe(element);
    }
    function finishAll() {
        records.forEach(finish);
        if (frame !== null) { cancelAnimationFrame(frame); frame = null; }
    }
    reduced.addEventListener('change', function (event) { if (event.matches) finishAll(); });
    window.addEventListener('beforeprint', finishAll);
    document.addEventListener('visibilitychange', function () { if (document.hidden) finishAll(); });
    window.StatsDashboardNumbers = {set: set, count: function (element, value, format) { set(element, value, format, true); }};
})();
