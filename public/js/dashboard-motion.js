(function () {
    'use strict';
    var dashboard = document.querySelector('.dashboard-modern');
    if (!dashboard || !('IntersectionObserver' in window)) return;
    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    var jumps = Array.from(dashboard.querySelectorAll('.dashboard-jump-nav a'));
    function markSection(id) {
        jumps.forEach(function (link) {
            var active = link.hash === '#' + id;
            link.classList.toggle('is-current', active);
            if (active) link.setAttribute('aria-current', 'location'); else link.removeAttribute('aria-current');
        });
    }
    if (jumps.length) {
        markSection('dashboard-overview');
        var sections = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) { if (entry.isIntersecting) markSection(entry.target.id); });
        }, {rootMargin: '-10% 0px -65% 0px', threshold: 0});
        jumps.forEach(function (link) {
            var section = document.getElementById(link.hash.slice(1));
            if (section) sections.observe(section);
            link.addEventListener('click', function () { markSection(link.hash.slice(1)); });
        });
    }

    // Observe the actual chart, including charts inserted after an API response.
    // Chart entrances run independently of the accessible number counters.
    var targets = 'section.box, .status-composition-track, .insight-donut-graphic, .utilisation-orbit, .dc-plot, .insight-composition-strip, .insight-variant-list';
    var registered = new WeakSet(), running = new Set();
    var ease = 'cubic-bezier(.25,.65,.25,1)';
    function animate(element, frames, duration, delay) {
        if (reduced.matches || !element.animate) return;
        var animation = element.animate(frames, {duration: duration, delay: delay || 0, easing: ease, fill: 'backwards'});
        running.add(animation);
        animation.onfinish = animation.oncancel = function () { running.delete(animation); };
    }
    function reveal(target, index) {
        if (target.matches('section.box')) {
            animate(target, [{opacity: .65, translate: '0 14px'}, {opacity: 1, translate: '0 0'}], 1000, Math.min(index, 2) * 110);
        } else if (target.matches('.dc-plot')) {
            target.querySelectorAll('.dc-bar').forEach(function (bar, i) {
                bar.style.transformOrigin = bar.classList.contains('is-negative') ? 'top' : 'bottom';
                animate(bar, [{transform: 'scaleY(0)'}, {transform: 'scaleY(1)'}], 1850, Math.min(i, 11) * 80);
            });
        } else if (target.matches('.insight-donut-graphic')) {
            target.querySelectorAll('circle[stroke-dasharray]').forEach(function (arc, i) {
                var dash = arc.getAttribute('stroke-dasharray'), parts = dash.split(' ').map(Number);
                animate(arc, [{strokeDasharray: '0 ' + (parts[0] + parts[1])}, {strokeDasharray: dash}], 2200, Math.min(i, 7) * 100);
            });
        } else if (target.matches('.utilisation-orbit')) {
            var arc = target.querySelector('.utilisation-arc');
            if (arc) animate(arc, [{strokeDasharray: '0 100'}, {strokeDasharray: getComputedStyle(arc).strokeDasharray}], 2400, 120);
        } else if (target.matches('.status-composition-track, .insight-composition-strip')) {
            target.querySelectorAll('span').forEach(function (segment, i) {
                segment.style.transformOrigin = 'left';
                animate(segment, [{transform: 'scaleX(0)', opacity: .4}, {transform: 'scaleX(1)', opacity: 1}], 1800, Math.min(i, 7) * 130);
            });
        } else {
            target.querySelectorAll(':scope > li').forEach(function (row, i) {
                animate(row, [{opacity: .45, translate: '0 10px'}, {opacity: 1, translate: '0 0'}], 1100, Math.min(i, 5) * 90);
            });
        }
    }
    var observer = new IntersectionObserver(function (entries) {
        var visible = entries.filter(function (entry) { return entry.isIntersecting && entry.intersectionRatio >= .25; });
        visible.forEach(function (entry, index) {
            observer.unobserve(entry.target);
            reveal(entry.target, index);
        });
    }, {threshold: .25, rootMargin: '0px 0px -32px 0px'});
    function register(root) {
        if (!(root instanceof Element)) return;
        var elements = Array.from(root.querySelectorAll(targets));
        if (root.matches(targets)) elements.unshift(root);
        elements.forEach(function (element) {
            if (registered.has(element)) return;
            if (element.matches('.utilisation-orbit') && !element.querySelector('.utilisation-arc')?.style.getPropertyValue('--utilisation')) return;
            registered.add(element);
            // Very tall cards remain readable and do not wait for a quarter of their height.
            if (element.matches('section.box') && element.offsetHeight > innerHeight * 2) return;
            observer.observe(element);
        });
    }
    register(dashboard);
    new MutationObserver(function (records) {
        records.forEach(function (record) {
            record.addedNodes.forEach(register);
            if (record.type === 'attributes') {
                var orbit = record.target.closest('.utilisation-orbit');
                if (orbit) register(orbit);
            }
        });
        running.forEach(function (animation) {
            var target = animation.effect && animation.effect.target;
            if (target && !target.isConnected) animation.cancel();
        });
    }).observe(dashboard, {childList: true, subtree: true, attributes: true, attributeFilter: ['style']});
    function finishMotion() { running.forEach(function (animation) { animation.finish(); }); }
    reduced.addEventListener('change', function (event) { if (event.matches) finishMotion(); });
    window.addEventListener('beforeprint', finishMotion);
})();
