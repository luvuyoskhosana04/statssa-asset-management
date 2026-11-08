/* Column menus: values within a column use OR; separate columns use AND. */
(function ($) {
    'use strict';
    const t = key => (window.SnipePresentation || {})[key] || key;
    const node = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text !== undefined) n.textContent = text; return n; };
    const states = new WeakMap();
    let opened = null;
    const readURL = () => { try { return JSON.parse(new URL(location.href).searchParams.get('column_filters') || '{}'); } catch (_) { return {}; } };
    const initial = readURL();
    const restored = new Set();
    // Set state before Bootstrap Table's first request, including browser reloads.
    document.querySelectorAll('table.snipe-table[id]').forEach(table => {
        if (initial[table.id] && typeof initial[table.id] === 'object') $(table).data('column-filters', initial[table.id]);
    });
    const text = value => {
        if (value === undefined || value === null) return '';
        if (typeof value === 'object') value = value.name ?? value.formatted ?? value.date ?? '';
        const decoded = node('textarea'); decoded.innerHTML = String(value); return decoded.value;
    };
    const fieldValue = (row, field) => text(field.split('.').reduce((value, part) => value?.[part], row));
    function close(restore = false) {
        if (!opened) return;
        const {panel, trigger, request, timer} = opened;
        clearTimeout(timer); request?.abort(); panel.remove(); trigger.setAttribute('aria-expanded', 'false'); opened = null;
        if (restore && trigger.isConnected) trigger.focus();
    }
    function saveURL(table, filters) {
        const url = new URL(location.href), all = readURL();
        if (Object.keys(filters).length) all[table.id] = filters; else delete all[table.id];
        if (Object.keys(all).length) url.searchParams.set('column_filters', JSON.stringify(all)); else url.searchParams.delete('column_filters');
        if (url.href !== location.href) history.replaceState(history.state, '', url);
    }
    function params(table, extras = {}) {
        const instance = $(table).data('bootstrap.table'), opts = instance.options;
        const base = {limit: 1, offset: 0, search: opts.searchText || '', sort: opts.sortName, order: opts.sortOrder};
        if (instance.filterColumnsPartial && Object.keys(instance.filterColumnsPartial).length) base.filter = JSON.stringify(instance.filterColumnsPartial);
        return Object.assign({}, typeof opts.queryParams === 'function' ? opts.queryParams(base) : base, extras);
    }
    function request(table, extras) {
        const opts = $(table).bootstrapTable('getOptions');
        return $.ajax(Object.assign({}, opts.ajaxOptions, {url: opts.url, method: 'GET', dataType: 'json', data: params(table, extras)}));
    }
    function refresh(table) {
        const options = $(table).bootstrapTable('getOptions');
        if (options.pageNumber !== 1) $(table).bootstrapTable('selectPage', 1);
        else $(table).bootstrapTable('refresh', {pageNumber: 1});
    }
    function apply(table, field, filter) {
        const state = states.get(table), filters = Object.assign({}, $(table).data('column-filters') || {});
        if (filter) filters[field] = filter; else delete filters[field];
        $(table).data('column-filters', filters); saveURL(table, filters); close(true); decorate(table);
        if (state.client) {
            $(table).bootstrapTable('filterBy', {}, {filterAlgorithm: row => Object.entries(filters).every(([key, f]) => matches(fieldValue(row, key), f, state.columns[key].type))});
        } else {
            refresh(table);
        }
    }
    function matches(value, filter, type) {
        if (filter.op === 'in') return filter.values.includes(value);
        if (filter.op === 'not_in') return !filter.values.includes(value);
        if (filter.op === 'blank') return value === '';
        if (filter.op === 'not_blank') return value !== '';
        if (filter.op === 'contains') return value.toLocaleLowerCase().includes(filter.value.toLocaleLowerCase());
        if (filter.op === 'not_contains') return !value.toLocaleLowerCase().includes(filter.value.toLocaleLowerCase());
        const a = type === 'number' ? Number(value) : type === 'date' ? value.slice(0, 10) : value.toLocaleLowerCase();
        const b = type === 'number' ? Number(filter.value) : type === 'date' ? filter.value : filter.value.toLocaleLowerCase();
        if (value === '') return false;
        return {eq: () => a === b, neq: () => a !== b, gt: () => a > b, gte: () => a >= b, lt: () => a < b, lte: () => a <= b}[filter.op]?.() || false;
    }
    function decorate(table) {
        const state = states.get(table); if (!state?.columns) return;
        const instance = $(table).data('bootstrap.table'); if (!instance) return;
        const wrap = table.closest('.bootstrap-table'), filters = $(table).data('column-filters') || {};
        // Include fixed/sticky header copies. Event delegation survives header rebuilds.
        wrap.querySelectorAll('thead th[data-field]').forEach(header => {
            const field = header.dataset.field, column = state.columns[field];
            if (!column || header.querySelector('input[type=checkbox]')) return;
            const spec = instance.options.columns.flat().find(c => c.field === field);
            const label = text(spec?.title).replace(/<[^>]*>/g, '').trim();
            let button = header.querySelector('.column-filter-trigger');
            if (!button) {
                button = node('button', 'column-filter-trigger'); button.type = 'button';
                button.innerHTML = '<i class="fa fa-filter" aria-hidden="true"></i>';
                button.dataset.columnField = field; button.setAttribute('aria-haspopup', 'dialog'); button.setAttribute('aria-expanded', 'false');
                header.append(button); header.classList.add('has-column-filter');
            }
            button.dataset.columnTable = table.id;
            button.classList.toggle('is-filtered', !!filters[field]);
            button.setAttribute('aria-label', t('column_filter_for').replace(':column', label) + (filters[field] ? ' · ' + t('column_filter_active') : ''));
            button.title = button.getAttribute('aria-label');
        });
        let picker = wrap.querySelector(':scope > .column-filter-picker');
        if (!picker) {
            picker = node('div', 'column-filter-picker');
            const label = node('label', '', t('column_filter_pick_column')), select = node('select', 'form-control');
            select.id = table.id + '-filter-column'; label.htmlFor = select.id;
            select.setAttribute('aria-haspopup', 'dialog'); select.setAttribute('aria-expanded', 'false');
            select.addEventListener('change', () => { if (!select.value) return; close(); select.dataset.columnField = select.value; show(table, select); select.value = ''; });
            picker.append(label, select); const anchor = wrap.querySelector(':scope > .fixed-table-container'); if (anchor) wrap.insertBefore(picker, anchor); else wrap.append(picker);
        }
        picker.hidden = !instance.options.cardView;
        const select = picker.querySelector('select'), previous = select.value;
        select.replaceChildren(); const placeholder = node('option', '', t('column_filter_pick_column')); placeholder.value = ''; select.append(placeholder);
        instance.options.columns.flat().filter(c => state.columns[c.field] && c.visible !== false).forEach(c => { const option = node('option', '', text(c.title).replace(/<[^>]*>/g, '')); option.value = c.field; select.append(option); }); select.value = previous || '';
        let summary = wrap.querySelector(':scope > .column-filter-summary');
        if (!summary) {
            summary = node('div', 'column-filter-summary'); summary.setAttribute('aria-label', t('column_filter_active'));
            const anchor = wrap.querySelector(':scope > .fixed-table-container'); if (anchor) wrap.insertBefore(summary, anchor); else wrap.append(summary);
        }
        summary.replaceChildren(); summary.hidden = !Object.keys(filters).length;
        for (const [field, filter] of Object.entries(filters)) {
            const spec = instance.options.columns.flat().find(c => c.field === field);
            const title = text(spec?.title || field).replace(/<[^>]*>/g, '').trim();
            const value = ['in', 'not_in'].includes(filter.op)
                ? (filter.op === 'not_in' ? t('column_filter_except') + ' ' : '') + filter.values.map(v => v || t('column_filter_blanks')).join(', ')
                : t('column_filter_' + filter.op + (['gt','gte','lt','lte'].includes(filter.op) ? '_' + state.columns[field]?.type : '')) + (filter.value ? ' ' + filter.value : '');
            const chip = node('button', 'column-filter-chip', title + ': ' + value + ' ×'); chip.type = 'button'; chip.title = chip.textContent;
            chip.setAttribute('aria-label', t('column_filter_remove').replace(':column', title));
            chip.addEventListener('click', () => apply(table, field, null)); summary.append(chip);
        }
        const clear = node('button', 'btn btn-link', t('column_filter_clear_all')); clear.type = 'button';
        clear.addEventListener('click', () => { $(table).data('column-filters', {}); saveURL(table, {}); close(); decorate(table); state.client ? $(table).bootstrapTable('filterBy', {}) : refresh(table); }); summary.append(clear);
    }
    function position() {
        if (!opened) return;
        const {panel, trigger} = opened, box = trigger.getBoundingClientRect();
        if (box.bottom < 0 || box.top > innerHeight) { close(); return; }
        const width = Math.min(328, innerWidth - 24), height = Math.min(panel.scrollHeight, innerHeight - 24);
        panel.style.width = width + 'px';
        panel.style.left = Math.max(12, Math.min(box.right - width, innerWidth - width - 12)) + 'px';
        panel.style.top = Math.max(12, Math.min(box.bottom + 6, innerHeight - height - 12)) + 'px';
    }
    function show(table, trigger) {
        if (opened?.trigger === trigger) { close(true); return; }
        close();
        const state = states.get(table), field = trigger.dataset.columnField, spec = $(table).bootstrapTable('getOptions').columns.flat().find(c => c.field === field);
        const type = state.columns[field].type, active = ($(table).data('column-filters') || {})[field];
        let draft = active && ['in', 'not_in'].includes(active.op) ? structuredClone(active) : {op: 'not_in', values: []};
        const panel = node('section', 'column-filter-panel'); panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-label', t('column_filter_for').replace(':column', text(spec.title).replace(/<[^>]*>/g, '')));
        const heading = node('div', 'column-filter-heading', text(spec.title).replace(/<[^>]*>/g, ''));
        const x = node('button', 'column-filter-close', '×'); x.type = 'button'; x.setAttribute('aria-label', t('column_filter_close')); x.addEventListener('click', () => close(true)); heading.append(x); panel.append(heading);
        if (spec.sortable) {
            const sorting = node('div', 'column-filter-sorting');
            ['asc', 'desc'].forEach(order => {
                const label = t('column_filter_sort_' + (type === 'text' ? 'text_' : type === 'date' ? 'date_' : 'number_') + order);
                const b = node('button', 'btn btn-default', label); b.type = 'button'; b.addEventListener('click', () => { close(true); $(table).bootstrapTable('refreshOptions', {sortName: field, sortOrder: order, pageNumber: 1}); }); sorting.append(b);
            }); panel.append(sorting);
        }
        const clear = node('button', 'column-filter-clear btn btn-link', t('column_filter_clear')); clear.type = 'button'; clear.disabled = !active; clear.addEventListener('click', () => apply(table, field, null)); panel.append(clear);
        const modeLabel = node('label', 'column-filter-label', t('column_filter_method'));
        const mode = node('select', 'form-control'); mode.id = 'column-filter-method'; modeLabel.htmlFor = mode.id;
        const ops = ['values', 'eq', 'neq', ...(type === 'text' ? ['contains', 'not_contains'] : ['gt', 'gte', 'lt', 'lte']), 'blank', 'not_blank'];
        ops.forEach(op => { const option = node('option', '', t('column_filter_' + op + (['gt','gte','lt','lte'].includes(op) ? '_' + type : ''))); option.value = op; mode.append(option); });
        mode.value = active && !['in', 'not_in'].includes(active.op) ? active.op : 'values'; panel.append(modeLabel, mode);
        const condition = node('input', 'form-control column-filter-condition'); condition.type = type === 'number' ? 'number' : type === 'date' ? 'date' : 'text'; condition.step = 'any'; condition.value = active?.value || ''; condition.setAttribute('aria-label', t('column_filter_value')); condition.placeholder = t('column_filter_enter_value'); panel.append(condition);
        const choices = node('div', 'column-filter-choices');
        const search = node('input', 'form-control'); search.type = 'search'; search.placeholder = t('column_filter_search'); search.setAttribute('aria-label', t('column_filter_search'));
        const allLabel = node('label', 'column-filter-choice column-filter-select-all'); const all = node('input'); all.type = 'checkbox'; allLabel.append(all, node('span', '', t('column_filter_select_all')));
        const list = node('div', 'column-filter-values'); list.setAttribute('aria-label', t('column_filter_values')); list.setAttribute('role', 'group');
        const message = node('p', 'column-filter-message'); message.setAttribute('role', 'status');
        const more = node('button', 'btn btn-link', t('column_filter_more')); more.type = 'button'; more.hidden = true;
        choices.append(search, allLabel, list, message, more); panel.append(choices);
        const error = node('p', 'column-filter-error'); error.hidden = true; error.setAttribute('role', 'alert'); panel.append(error);
        const footer = node('div', 'column-filter-footer'); const cancel = node('button', 'btn btn-default', t('column_filter_cancel')), submit = node('button', 'btn btn-primary', t('column_filter_apply'));
        cancel.type = submit.type = 'button'; cancel.addEventListener('click', () => close(true)); footer.append(cancel, submit); panel.append(footer);
        opened = {panel, trigger, request: null, timer: null}; trigger.setAttribute('aria-expanded', 'true'); document.body.append(panel);
        let available = [], offset = 0, generation = 0;
        function selected(value) { return draft.op === 'not_in' ? !draft.values.includes(value) : draft.values.includes(value); }
        function paint() {
            list.replaceChildren();
            available.forEach(value => {
                const label = node('label', 'column-filter-choice'), box = node('input'); box.type = 'checkbox'; box.checked = selected(value);
                box.addEventListener('change', () => { const values = new Set(draft.values); if ((draft.op === 'in') === box.checked) values.add(value); else values.delete(value); draft.values = [...values]; updateAll(); });
                const name = node('span', '', value === '' ? t('column_filter_blanks') : value); name.title = name.textContent; label.append(box, name); list.append(label);
            }); updateAll(); position();
        }
        function updateAll() { all.checked = draft.op === 'not_in' && !draft.values.length; all.indeterminate = draft.values.length > 0; submit.disabled = draft.op === 'in' && !draft.values.length && mode.value === 'values'; }
        all.addEventListener('change', () => { draft = {op: all.checked ? 'not_in' : 'in', values: []}; paint(); });
        function load(append = false) {
            const serial = ++generation; opened?.request?.abort();
            if (!append) { available = []; offset = 0; list.replaceChildren(); }
            message.textContent = t('column_filter_loading'); more.hidden = true; message.hidden = false;
            const done = data => {
                if (serial !== generation || opened?.panel !== panel) return;
                if (!Array.isArray(data.values)) { message.textContent = t('column_filter_failed'); more.textContent = t('column_filter_retry'); more.hidden = false; position(); return; }
                more.textContent = t('column_filter_more');
                available.push(...data.values); offset += data.values.length; more.hidden = !data.more;
                message.textContent = available.length ? (data.more ? t('column_filter_search_more') : '') : t('column_filter_no_values'); message.hidden = !message.textContent;
                paint();
            };
            if (state.client) {
                const values = [...new Set($(table).bootstrapTable('getOptions').data.filter(row => Object.entries($(table).data('column-filters') || {}).every(([key, f]) => key === field || matches(fieldValue(row, key), f, state.columns[key].type))).map(row => fieldValue(row, field)))].filter(value => value.toLocaleLowerCase().includes(search.value.toLocaleLowerCase())).sort((a, b) => a.localeCompare(b, undefined, {numeric: type === 'number'}));
                done({values: values.slice(offset, offset + 100), more: values.length > offset + 100});
            } else {
                opened.request = request(table, {column_filter_field: field, column_filter_search: search.value, column_filter_offset: offset});
                opened.request.done(done).fail((_, status) => {
                    if (status === 'abort' || opened?.panel !== panel) return;
                    message.textContent = t('column_filter_failed'); more.textContent = t('column_filter_retry'); more.hidden = false; position();
                });
            }
        }
        more.addEventListener('click', () => load(true)); search.addEventListener('input', () => { clearTimeout(opened?.timer); if (opened) opened.timer = setTimeout(() => load(), 250); });
        function changeMode() { choices.hidden = mode.value !== 'values'; condition.hidden = ['values', 'blank', 'not_blank'].includes(mode.value); error.hidden = true; submit.disabled = false; if (mode.value === 'values') updateAll(); position(); }
        mode.addEventListener('change', changeMode); changeMode();
        submit.addEventListener('click', () => {
            let filter;
            if (mode.value === 'values') filter = draft.op === 'not_in' && !draft.values.length ? null : draft;
            else {
                if (!condition.hidden && (!condition.value.trim() || !condition.checkValidity())) { error.textContent = t('column_filter_required'); error.hidden = false; condition.focus(); position(); return; }
                filter = {op: mode.value}; if (!condition.hidden) filter.value = condition.value.trim();
            }
            apply(table, field, filter);
        });
        panel.addEventListener('keydown', event => {
            if (event.key === 'Escape') { event.preventDefault(); close(true); }
            if (event.key === 'Enter' && event.target === condition) { event.preventDefault(); submit.click(); }
            if (event.key === 'Tab') {
                const controls = [...panel.querySelectorAll('button,input,select')].filter(c => !c.disabled && c.getClientRects().length);
                if (event.shiftKey && event.target === controls[0]) { event.preventDefault(); controls.at(-1).focus(); }
                else if (!event.shiftKey && event.target === controls.at(-1)) { event.preventDefault(); controls[0].focus(); }
            }
        });
        load(); position(); (mode.value === 'values' ? search : condition.hidden ? mode : condition).focus();
    }
    function init(table) {
        if (table.tagName !== 'TABLE' || !table.id) return;
        const instance = $(table).data('bootstrap.table'); if (!instance) return;
        let state = states.get(table);
        if (state) { decorate(table); return; }
        state = {columns: null, client: instance.options.sidePagination === 'client'}; states.set(table, state);
        // The address-bar extension caches its own URL parameters. Keep them in sync.
        if (typeof instance.updateHistoryState === 'function') {
            const original = instance.updateHistoryState;
            instance.updateHistoryState = function () {
                const all = readURL(), filters = $(table).data('column-filters') || {};
                if (Object.keys(filters).length) all[table.id] = filters; else delete all[table.id];
                if (this.searchParams) {
                    if (Object.keys(all).length) this.searchParams.set('column_filters', JSON.stringify(all)); else this.searchParams.delete('column_filters');
                }
                return original.apply(this, arguments);
            };
        }
        const wrap = table.closest('.bootstrap-table');
        if (state.client) {
            state.columns = {};
            instance.options.columns.flat().forEach(c => {
                if (!c.field || c.checkbox || c.searchable === false || /actions|image|checkin|checkout|available_actions/.test(c.field)) return;
                const values = instance.options.data.map(row => fieldValue(row, c.field)).filter(Boolean);
                state.columns[c.field] = {type: /(_date|_at)$/.test(c.field) ? 'date' : values.length && values.every(v => Number.isFinite(Number(v))) ? 'number' : 'text'};
            }); decorate(table);
            const filters = $(table).data('column-filters') || {};
            if (Object.keys(filters).length) $(table).bootstrapTable('filterBy', {}, {filterAlgorithm: row => Object.entries(filters).every(([key, f]) => !state.columns[key] || matches(fieldValue(row, key), f, state.columns[key].type))});
        } else if (instance.options.url && new URL(instance.options.url, location.href).origin === location.origin) {
            request(table, {column_filter_meta: 1}).done(data => { state.columns = data.columns || {}; decorate(table); });
        }
    }
    document.addEventListener('click', event => {
        const trigger = event.target.closest('.column-filter-trigger');
        if (!trigger) return;
        const table = document.getElementById(trigger.dataset.columnTable);
        if (!table || !states.get(table)?.columns) return;
        event.preventDefault(); event.stopImmediatePropagation(); show(table, trigger);
    }, true);
    document.addEventListener('pointerdown', event => { if (opened && !opened.panel.contains(event.target) && !opened.trigger.contains(event.target)) close(); });
    window.addEventListener('resize', position); window.addEventListener('scroll', position, true);
    $(document).on('load-success.bs.table', '.snipe-table', function () {
        const table = this;
        if (table.tagName !== 'TABLE' || !initial[table.id] || restored.has(table.id)) return;
        restored.add(table.id);
        if ($(table).bootstrapTable('getOptions').pageNumber !== 1) setTimeout(() => $(table).bootstrapTable('selectPage', 1), 0);
    });
    $(document).on('post-header.bs.table post-body.bs.table reset-view.bs.table column-switch.bs.table', '.snipe-table', function () { init(this); });
    $(function () { document.querySelectorAll('table.snipe-table').forEach(init); });
})(window.jQuery);
