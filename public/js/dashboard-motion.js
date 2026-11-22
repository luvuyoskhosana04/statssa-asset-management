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

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('dash-in-view');
                observer.unobserve(entry.target);
            }
        });
    }, {threshold: 0.08});
    dashboard.querySelectorAll('section.box').forEach(function (card) {
        if (reduced.matches) { card.classList.add('dash-in-view'); return; }
        card.classList.add('dash-motion-ready'); observer.observe(card);
    });
    reduced.addEventListener('change', function (event) {
        if (event.matches) {
            observer.disconnect();
            dashboard.querySelectorAll('.dash-motion-ready').forEach(function (card) { card.classList.add('dash-in-view'); });
        }
    });
})();
