/* Keep the original pagination controls reachable while reading long listings. */
(function ($) {
    'use strict';
    const entries = new Map();
    let scheduled = false;
    const resize = new ResizeObserver(() => schedule());
    function schedule() {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(update);
    }
    function release(entry) {
        entry.footer.classList.remove('table-pagination-floating');
        entry.footer.style.removeProperty('left');
        entry.footer.style.removeProperty('width');
        entry.space.hidden = true;
        entry.space.style.height = '';
    }
    function update() {
        scheduled = false;
        const viewport = window.visualViewport;
        const bottom = viewport ? viewport.offsetTop + viewport.height : window.innerHeight;
        const headerBottom = document.querySelector('.main-header')?.getBoundingClientRect().bottom || 0;
        for (const [footer, entry] of entries) {
            const {table, wrap, space} = entry;
            if (!footer.isConnected || !table.isConnected) {
                release(entry); resize.unobserve(footer); resize.unobserve(wrap); space.remove(); entries.delete(footer); continue;
            }
            if (!footer.getClientRects().length || !table.getClientRects().length || getComputedStyle(footer).display === 'none' || table.closest('.modal') || window.matchMedia('print').matches) {
                release(entry); continue;
            }
            const bounds = wrap.getBoundingClientRect();
            const height = footer.getBoundingClientRect().height;
            // The spacer preserves the footer's natural position while it is docked.
            const natural = footer.classList.contains('table-pagination-floating') ? space.getBoundingClientRect() : footer.getBoundingClientRect();
            const long = table.getBoundingClientRect().height > bottom - Math.max(0, headerBottom) - height;
            const dock = long && bounds.width > 0 && bounds.top < bottom - height - 80 && natural.bottom > bottom + 1 && bounds.bottom > headerBottom + height;
            if (!dock) { release(entry); continue; }
            space.style.height = height + 'px'; space.hidden = false;
            footer.style.left = bounds.left + 'px'; footer.style.width = bounds.width + 'px';
            footer.classList.add('table-pagination-floating');
        }
    }
    function scan() {
        document.querySelectorAll('table.snipe-table').forEach(table => {
            const wrap = table.closest('.bootstrap-table');
            const footer = wrap?.querySelector(':scope > .fixed-table-pagination');
            if (!footer || entries.has(footer)) return;
            const space = document.createElement('div');
            space.className = 'table-pagination-space'; space.hidden = true; space.setAttribute('aria-hidden', 'true');
            footer.before(space); entries.set(footer, {table, wrap, footer, space});
            resize.observe(footer); resize.observe(wrap);
        });
        schedule();
    }
    window.addEventListener('scroll', schedule, {capture: true, passive: true});
    window.addEventListener('resize', schedule);
    window.visualViewport?.addEventListener('resize', schedule);
    window.visualViewport?.addEventListener('scroll', schedule);
    window.addEventListener('beforeprint', () => entries.forEach(release));
    window.addEventListener('afterprint', schedule);
    $(document).on('post-body.bs.table reset-view.bs.table post-header.bs.table page-change.bs.table column-switch.bs.table', '.snipe-table', scan);
    $(document).on('shown.bs.tab shown.bs.modal hidden.bs.modal expanded.boxwidget collapsed.boxwidget', scan);
    $(scan);
})(window.jQuery);
