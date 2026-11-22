/* Presentation for standalone searches. Existing input handlers and form actions
   remain responsible for filtering and submitting searches. */
(function () {
    'use strict';
    var words = window.SnipePresentation || {}, records = new WeakMap(), entries = [], serial = 0, queued = false;
    var selector = '.fixed-table-toolbar .search input, input[type="search"], form[role="search"] input:not([type="hidden"]), input[name="search"], input[name="q"]';
    function enhance(input) {
        if (records.has(input) || input.matches('[type="hidden"],#tagSearch,.select2-search__field,[role="combobox"]') || input.closest('.select2-container,.select2-dropdown,.main-header,[data-search-disclosure="off"]')) return;
        if (input.closest('.ui-search-disclosure')) return;
        input.setAttribute('data-no-field-ui', '');
        var group = input.closest('.input-group,.btn-group');
        if (group && group.querySelectorAll('input:not([type="hidden"])').length !== 1) group = null;
        var form = input.closest('form[role="search"]');
        var submit = form && form.querySelector('button[type="submit"]:not([disabled])');
        var root = group || (submit && input.parentElement === form ? form : null);
        if (!root) { root = document.createElement('span'); input.before(root); root.appendChild(input); }
        root.classList.add('ui-search-disclosure');
        var clear = root.querySelector('[name="clearSearch"],#searchclear,.search-clear');
        var button = submit && root.contains(submit) ? submit : document.createElement('button');
        var nativeSubmit = button === submit;
        if (!clear && nativeSubmit) clear = root.querySelector('a[href]');
        if (!nativeSubmit) { button.type = 'button'; root.appendChild(button); }
        root.classList.toggle('has-search-clear', !!clear);
        if (clear && !clear.matches('button,a')) {
            clear.removeAttribute('aria-hidden'); clear.setAttribute('role', 'button'); clear.tabIndex = 0;
            clear.addEventListener('keydown', function (event) { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); clear.click(); } });
        }
        root.appendChild(button);
        button.classList.add('ui-search-toggle');
        // Use the existing product icon family without a second clear/reset action.
        button.replaceChildren(); var icon = document.createElement('i'); icon.className = 'fas fa-search'; icon.setAttribute('aria-hidden', 'true'); button.appendChild(icon);
        if (!input.id) input.id = 'ui-search-field-' + (++serial);
        var label = input.getAttribute('aria-label') || input.labels?.[0]?.textContent.trim() || input.placeholder || words.search_open;
        button.setAttribute('aria-label', label); button.title = label; button.setAttribute('aria-controls', input.id);
        var originalTabIndex = input.getAttribute('tabindex'), open = false;
        function sync() {
            var hasValue = !!input.value.trim(); root.classList.toggle('has-search-query', hasValue);
            if (clear) {
                clear.hidden = !open || !hasValue;
                if ('disabled' in clear) clear.disabled = !hasValue;
                clear.setAttribute('aria-label', words.search_clear);
                clear.setAttribute('title', words.search_clear);
            }
        }
        function setOpen(value, focus) {
            open = value; root.classList.toggle('is-search-open', value);
            button.setAttribute('aria-expanded', String(value));
            input.inert = !value;
            if (value && originalTabIndex === null) input.removeAttribute('tabindex');
            else input.setAttribute('tabindex', value ? originalTabIndex : '-1');
            sync();
            if (focus) (value ? input : button).focus({preventScroll: true});
        }
        button.addEventListener('click', function (event) {
            if (!open) { event.preventDefault(); event.stopImmediatePropagation(); setOpen(true, true); }
            else if (!nativeSubmit) { event.preventDefault(); setOpen(false, true); }
        }, true);
        root.addEventListener('click', function (event) {
            if (clear && clear.contains(event.target) && !input.value.trim()) {
                event.preventDefault(); event.stopImmediatePropagation();
            } else if (clear && clear.contains(event.target)) {
                setTimeout(sync, 0);
            }
        }, true);
        root.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && open) { event.preventDefault(); event.stopPropagation(); setOpen(false, true); }
        });
        root.addEventListener('focusout', function () {
            setTimeout(function () { if (open && !root.contains(document.activeElement)) setOpen(false); }, 0);
        });
        input.addEventListener('input', sync); input.addEventListener('change', sync);
        records.set(input, {sync: sync}); entries.push({root: root, close: function (target) { if (open && !root.contains(target)) setOpen(false); }});
        setOpen(!!input.value.trim(), false);
    }
    function scan() {
        queued = false;
        document.querySelectorAll(selector).forEach(function (input) { if (records.has(input)) records.get(input).sync(); else enhance(input); });
        entries = entries.filter(function (entry) { return entry.root.isConnected; });
    }
    function schedule() { if (!queued) { queued = true; requestAnimationFrame(scan); } }
    document.addEventListener('pointerdown', function (event) { entries.forEach(function (entry) { entry.close(event.target); }); });
    new MutationObserver(function (changes) { if (changes.some(function (change) { return change.addedNodes.length || change.removedNodes.length; })) schedule(); }).observe(document.body, {childList: true, subtree: true});
    if (window.jQuery) window.jQuery(document).on('post-body.bs.table search.bs.table load-success.bs.table reset-view.bs.table', schedule);
    scan();
})();
