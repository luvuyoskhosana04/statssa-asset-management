(function () {
    'use strict';
    var root = document.getElementById('header-quick-actions');
    if (!root) return;
    var trigger = document.getElementById('quick-actions-trigger');
    var panel = document.getElementById('quick-actions-panel');
    var closeTimer, open = false, exitPoint = null;
    function setOpen(value, focusFirst) {
        clearTimeout(closeTimer); open = value;
        root.classList.toggle('is-open', open);
        trigger.setAttribute('aria-expanded', String(open));
        panel.setAttribute('aria-hidden', String(!open)); panel.inert = !open;
        exitPoint = null;
        if (open && focusFirst) panel.querySelector('a')?.focus();
    }
    root.addEventListener('pointerenter', function (event) {
        if (event.pointerType !== 'touch') setOpen(true);
    });
    function scheduleClose(delay) {
        clearTimeout(closeTimer);
        closeTimer = setTimeout(function () {
            if (!panel.contains(document.activeElement)) setOpen(false);
        }, delay);
    }
    root.addEventListener('pointerleave', function (event) {
        exitPoint = {x: event.clientX, y: event.clientY};
        scheduleClose(400);
    });
    panel.addEventListener('pointerenter', function () { exitPoint = null; clearTimeout(closeTimer); });
    document.addEventListener('pointermove', function (event) {
        if (!open || !exitPoint || event.pointerType === 'touch') return;
        var bounds = panel.getBoundingClientRect();
        // Preserve the diagonal path from the trigger to either column, rather
        // than requiring users to move straight down through a narrow gap.
        var top = bounds.top + 12, origin = exitPoint;
        if (origin.y >= top || event.clientY < origin.y - 8 || event.clientY > top) return;
        var progress = Math.max(0, Math.min(1, (event.clientY - origin.y) / (top - origin.y)));
        var left = origin.x + (bounds.left - 16 - origin.x) * progress - 12;
        var right = origin.x + (bounds.right + 16 - origin.x) * progress + 12;
        if (event.clientX >= left && event.clientX <= right) scheduleClose(600);
    });
    trigger.addEventListener('click', function (event) {
        // A mouse hover already opens the flyout. A click keeps it available;
        // touch and keyboard activation toggle it without requiring hover.
        if (event.detail > 0 && window.matchMedia('(hover: hover)').matches && root.matches(':hover')) setOpen(true);
        else setOpen(!open);
    });
    root.addEventListener('focusout', function () {
        setTimeout(function () { if (!root.contains(document.activeElement)) setOpen(false); }, 0);
    });
    root.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && open) {
            event.preventDefault(); event.stopPropagation(); setOpen(false); trigger.focus();
        } else if (event.key === 'ArrowDown' && event.target === trigger) {
            event.preventDefault(); setOpen(true, true);
        }
    });
    document.addEventListener('pointerdown', function (event) {
        if (open && !root.contains(event.target)) setOpen(false);
    });
})();
