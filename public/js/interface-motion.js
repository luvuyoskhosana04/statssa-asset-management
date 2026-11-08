/* Progressive motion enhancement. Server-rendered values remain the source of truth. */
(function () {
    'use strict';
    if (window.InterfaceMotion) return;

    var timing = {counter: 1800, chart: 1400, reveal: 900, press: 280, step: 620, progress: 1400};
    var preference = window.matchMedia('(prefers-reduced-motion: reduce)');
    var counters = new WeakMap();
    var progressBars = new WeakMap();
    var pending = new Map();
    var activeCounters = new Set();
    var animations = new Set();
    var revealed = new WeakSet();
    var running = new WeakMap();
    var stepFrames = new WeakMap();
    var chartLibrary;
    var observer;
    var scanQueued = false;
    var scrolled = false;

    function visible(element) {
        var rect = element.getBoundingClientRect();
        var top = Math.max(0, rect.top), bottom = Math.min(window.innerHeight, rect.bottom);
        var left = Math.max(0, rect.left), right = Math.min(window.innerWidth, rect.right);
        if (!rect.width || !rect.height || bottom <= top || right <= left) return false;
        for (var parent = element.parentElement; parent && parent !== document.body; parent = parent.parentElement) {
            var style = getComputedStyle(parent), clip = parent.getBoundingClientRect();
            if (/(auto|scroll|hidden|clip)/.test(style.overflowY)) { top = Math.max(top, clip.top); bottom = Math.min(bottom, clip.bottom); }
            if (/(auto|scroll|hidden|clip)/.test(style.overflowX)) { left = Math.max(left, clip.left); right = Math.min(right, clip.right); }
            if (bottom <= top || right <= left) return false;
        }
        return true;
    }

    function animate(element, frames, options) {
        if (preference.matches || !element.animate) return;
        var previous = running.get(element);
        if (previous) previous.cancel();
        var animation = element.animate(frames, options);
        running.set(element, animation);
        animations.add(animation);
        animation.onfinish = animation.oncancel = function () {
            animations.delete(animation);
            if (running.get(element) === animation) running.delete(element);
        };
        return animation;
    }

    function whenVisible(element, callback) {
        if (preference.matches || !observer || visible(element)) {
            callback();
            return;
        }
        var callbacks = pending.get(element) || [];
        callbacks.push(callback);
        pending.set(element, callbacks);
        observer.observe(element);
    }

    function finishCounter(state) {
        cancelAnimationFrame(state.frame);
        state.span.textContent = state.text;
        activeCounters.delete(state);
    }

    function prepareCounter(element, index) {
        var previous = counters.get(element);
        if (previous && element.contains(previous.span)) return;
        if (previous) finishCounter(previous);
        var textNode = Array.from(element.childNodes).find(function (node) {
            return node.nodeType === Node.TEXT_NODE && /^(?:\d{1,3}(?:,\d{3})+|\d+)$/.test(node.textContent.trim());
        });
        if (!textNode) return;
        var original = textNode.textContent;
        var text = original.trim();
        var target = Number(text.replace(/,/g, ''));
        if (!Number.isSafeInteger(target)) return;
        var span = document.createElement('span');
        span.className = 'motion-number';
        span.setAttribute('aria-hidden', 'true');
        span.textContent = text;
        var accessible = document.createElement('span');
        accessible.className = 'sr-only';
        accessible.textContent = text;
        textNode.replaceWith(document.createTextNode(original.match(/^\s*/)[0]), span, accessible,
            document.createTextNode(original.match(/\s*$/)[0]));
        var state = {span: span, text: text, frame: 0};
        counters.set(element, state);
        var formatter = new Intl.NumberFormat('en-US', {useGrouping: text.indexOf(',') !== -1});
        whenVisible(element, function () {
            if (preference.matches || !target || !element.isConnected) return;
            span.style.minWidth = span.getBoundingClientRect().width + 'px';
            var start;
            var delay = (index % 6) * 45;
            activeCounters.add(state);
            function tick(time) {
                if (preference.matches || document.hidden || !element.isConnected) {
                    finishCounter(state);
                    return;
                }
                if (start === undefined) start = time;
                var progress = Math.min(1, Math.max(0, (time - start - delay) / timing.counter));
                var eased = progress < 0.5 ? 4 * Math.pow(progress, 3) : 1 - Math.pow(-2 * progress + 2, 3) / 2;
                span.firstChild.nodeValue = formatter.format(Math.round(target * eased));
                if (progress < 1) state.frame = requestAnimationFrame(tick);
                else finishCounter(state);
            }
            state.frame = requestAnimationFrame(tick);
        });
    }

    function reveal(element) {
        if (pending.has(element) || element.closest('.fixed-table-body, .fixed-table-toolbar')) return;
        if (revealed.has(element)) {
            // Async tables can push an initially visible panel below the fold.
            if (!scrolled && !visible(element) && !running.get(element)) revealed.delete(element);
            else return;
        }
        // Form steps have their own directional transition. Review sections can reveal on scroll.
        if (element.matches('.box') && element.closest('.presentation-settings-grid')) return;
        if (element.closest('.form-stepper-ready') && !element.matches('.stepper-review-section, .form-stepper, .permission-section')) return;
        revealed.add(element);
        whenVisible(element, function () {
            if (!element.isConnected || preference.matches || element.contains(document.activeElement)) return;
            var parent = element.parentElement;
            var siblings = parent && Array.from(parent.children);
            var column = parent && parent.matches('[class*="col-"]') ? parent : element;
            if (column !== element) siblings = Array.from(parent.parentElement.children);
            var delay = siblings ? Math.min(3, Math.max(0, siblings.indexOf(column))) * 65 : 0;
            var animation = animate(element, [{opacity: 0, transform: 'translateY(18px)'}, {opacity: 1, transform: 'translateY(0)'}],
                {duration: timing.reveal, delay: delay, easing: 'cubic-bezier(.22,1,.36,1)', fill: 'backwards'});
            if (animation) animation.addEventListener('finish', function () {
                if (!scrolled && !visible(element)) { revealed.delete(element); reveal(element); }
            }, {once: true});
        });
    }

    function stepMotion(detail) {
        if (preference.matches || !detail || detail.from === detail.to || !detail.form) return;
        var form = detail.form;
        cancelAnimationFrame(stepFrames.get(form));
        stepFrames.set(form, requestAnimationFrame(function () {
            if (!form.isConnected || preference.matches) return;
            var direction = detail.to > detail.from ? 1 : -1;
            var targets = detail.targets || Array.from(form.querySelectorAll('.stepper-section-header, [data-form-step]:not([hidden]), .stepper-review:not([hidden])'))
                .filter(function (target) { return !target.parentElement.closest('[data-form-step]') && visible(target); });
            targets.forEach(function (target, index) {
                animate(target, [{opacity: 0, transform: 'translate(' + (direction * 12) + 'px, 5px)'}, {opacity: 1, transform: 'translate(0, 0)'}],
                    {duration: timing.step, delay: Math.min(index, 5) * 45, easing: 'cubic-bezier(.22,1,.36,1)', fill: 'backwards'});
            });
            var marker = form.querySelector('.stepper-item.is-current .stepper-marker');
            if (marker) animate(marker, [{transform: 'scale(.88)'}, {transform: 'scale(1)'}],
                {duration: timing.step, easing: 'cubic-bezier(.22,1,.36,1)'});
            queueScan();
        }));
    }

    function chartMotion() {
        var Chart = window.Chart;
        if (!Chart || !Chart.plugins || chartLibrary === Chart) return;
        chartLibrary = Chart;
        function prepare(chart) {
            if (chart.$interfaceMotion) return;
            chart.$interfaceMotion = {seen: visible(chart.canvas)};
            chart.options.animation = chart.options.animation || {};
            chart.options.animation.duration = preference.matches || !chart.$interfaceMotion.seen ? 0 : timing.chart;
            chart.options.animation.easing = 'easeInOutCubic';
            chart.options.hover = chart.options.hover || {};
            chart.options.hover.animationDuration = preference.matches ? 0 : 120;
            chart.options.responsiveAnimationDuration = 0;
            if (!chart.$interfaceMotion.seen && !preference.matches) whenVisible(chart.canvas, function () {
                if (!chart.canvas.isConnected) return;
                chart.$interfaceMotion.seen = true;
                if (preference.matches) chart.update(0);
                else {
                    chart.options.animation.duration = timing.chart;
                    chart.reset();
                    chart.update({duration: timing.chart, easing: 'easeInOutCubic'});
                }
            });
        }
        Chart.plugins.register({
            beforeInit: prepare,
            beforeUpdate: function (chart) {
                prepare(chart);
                if (preference.matches || !chart.$interfaceMotion.seen) chart.options.animation.duration = 0;
            },
            destroy: function (chart) {
                pending.delete(chart.canvas);
                if (observer) observer.unobserve(chart.canvas);
            }
        });
        Object.keys(Chart.instances).forEach(function (key) { prepare(Chart.instances[key]); });
    }

    function progressMotion(bar) {
        var value = bar.style.width || bar.getAttribute('aria-valuenow') || '';
        if (progressBars.get(bar) === value) return;
        if (progressBars.has(bar) && !bar.closest('.stock-alert-progress')) { progressBars.set(bar, value); return; }
        progressBars.set(bar, value);
        pending.delete(bar); if (observer) observer.unobserve(bar);
        whenVisible(bar, function () {
            if (!bar.isConnected) return;
            var rtl = getComputedStyle(bar).direction === 'rtl';
            animate(bar, [{clipPath: rtl ? 'inset(0 0 0 100%)' : 'inset(0 100% 0 0)'}, {clipPath: 'inset(0 0 0 0)'}],
                {duration: timing.progress, easing: 'cubic-bezier(.22,1,.36,1)'});
        });
    }

    function scan() {
        scanQueued = false;
        document.querySelectorAll('.small-box .inner h3, .info-box-number, .status-total-value, [data-motion-count]')
            .forEach(prepareCounter);
        document.querySelectorAll('.content .box, .content .small-box, .content .info-box, .content .form-stepper, .stepper-review-section, .presentation-detail-group, .presentation-settings-grid > div, .presentation-catalogue tbody > tr, .permission-section, .presentation-kit-summary, .content fieldset')
            .forEach(reveal);
        document.querySelectorAll('.chart svg, .morris-chart svg, [data-motion-chart]')
            .forEach(reveal);
        document.querySelectorAll('.progress-bar,[data-motion-progress]').forEach(progressMotion);
        chartMotion();
    }

    function queueScan() {
        if (scanQueued) return;
        scanQueued = true;
        requestAnimationFrame(scan);
    }

    function syncPreference() {
        if (preference.matches) {
            animations.forEach(function (animation) { animation.cancel(); });
            activeCounters.forEach(finishCounter);
        }
        if (chartLibrary) Object.keys(chartLibrary.instances).forEach(function (key) {
            var chart = chartLibrary.instances[key];
            chart.options.animation.duration = preference.matches ? 0 : timing.chart;
            chart.options.hover.animationDuration = preference.matches ? 0 : 120;
            if (preference.matches) { chart.stop(); chart.update(0); }
        });
    }

    function start() {
        if (!document.body.classList.contains('ui-refresh')) return;
        if ('IntersectionObserver' in window) observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var callbacks = pending.get(entry.target) || [];
                pending.delete(entry.target);
                observer.unobserve(entry.target);
                callbacks.forEach(function (callback) { callback(); });
            });
        }, {threshold: 0, rootMargin: '0px 0px -28px 0px'});
        scrolled = window.scrollY > 8;
        window.addEventListener('scroll', function () { if (window.scrollY > 8) scrolled = true; }, {passive: true});
        scan();
        if (preference.matches) syncPreference();
        document.addEventListener('interface:stepchange', function (event) { stepMotion(event.detail); });
        new MutationObserver(function (mutations) {
            mutations.filter(function (mutation) { return mutation.type === 'attributes' && mutation.attributeName === 'data-motion-wizard-step'; }).forEach(function (mutation) {
                stepMotion({form: mutation.target, from: Number(mutation.oldValue), to: Number(mutation.target.dataset.motionWizardStep), targets: [mutation.target.querySelector('.modal-body')].filter(Boolean)});
            });
            pending.forEach(function (_, element) {
                if (!element.isConnected) { pending.delete(element); if (observer) observer.unobserve(element); }
            });
            if (mutations.some(function (mutation) { return mutation.addedNodes.length || mutation.removedNodes.length || (mutation.type === 'attributes' && (mutation.target.matches('.dropdown,.tab-pane,.modal,.box') || (mutation.attributeName === 'style' && mutation.target.matches('.stock-alert-progress .progress-bar')))); })) queueScan();
        }).observe(document.body, {childList: true, subtree: true, attributes: true, attributeOldValue: true, attributeFilter: ['data-motion-wizard-step', 'class', 'style']});
        if (window.jQuery) window.jQuery(document).on('shown.bs.dropdown shown.bs.tab shown.bs.modal', function (event) {
            var root = event.target;
            if (root.tagName === 'A' && root.hash) root = document.getElementById(root.hash.slice(1)) || root;
            root.querySelectorAll('.progress-bar,[data-motion-progress]').forEach(function (bar) {
                progressBars.delete(bar); pending.delete(bar); if (observer) observer.unobserve(bar);
            });
            queueScan();
        });
        document.addEventListener('click', function (event) {
            var target = event.target.closest('button, .btn, [role="button"]');
            if (!target || target.disabled || target.getAttribute('aria-disabled') === 'true' || target.closest('.modal-backdrop')) return;
            animate(target, [{transform: 'scale(.985)'}, {transform: 'scale(1)'}],
                {duration: timing.press, easing: 'cubic-bezier(.22,1,.36,1)'});

        });
        document.addEventListener('focusin', function (event) {
            if (!event.target.matches('input, select, textarea')) return;
            var element = event.target;
            while (element && element !== document.body) {
                var animation = running.get(element);
                if (animation) animation.cancel();
                element = element.parentElement;
            }
        });
        window.addEventListener('beforeprint', function () {
            animations.forEach(function (animation) { animation.cancel(); });
            activeCounters.forEach(finishCounter);
        });
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) activeCounters.forEach(finishCounter);
        });
        document.addEventListener('livewire:navigated', queueScan);
        if (preference.addEventListener) preference.addEventListener('change', syncPreference);
        else preference.addListener(syncPreference);
    }

    window.InterfaceMotion = {refresh: queueScan};
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
