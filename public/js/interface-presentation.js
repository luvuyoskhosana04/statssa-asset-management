/* Shared presentation only. Server policies, queries and form payloads remain authoritative. */
(function ($) {
    'use strict';
    const words = window.SnipePresentation || {};
    const t = key => words[key] || key;
    const el = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text !== undefined) n.textContent = text; return n; };
    const escape = value => { const n = el('span', '', value == null ? '' : String(value)); return n.innerHTML; };
    const number = value => value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value)) ? Number(value) : null;
    const date = value => {
        const raw = value && typeof value === 'object' ? (value.datetime || value.date) : value;
        if (!raw) return null;
        const d = new Date(String(raw).replace(/^(\d{4}-\d{2}-\d{2}) /, '$1T').replace(/^(\d{4}-\d{2}-\d{2})$/, '$1T00:00:00'));
        return Number.isNaN(d.getTime()) ? null : d;
    };
    const badge = (state, label) => '<span class="presentation-state state-' + state + '">' + escape(label) + '</span>';
    window.presentationAssignedSeatsFormatter = (_, row) => {
        const total = number(row.seats), free = number(row.free_seats_count);
        return total === null || free === null ? '-' : escape(Math.max(0, total - free).toLocaleString());
    };
    window.presentationStockStateFormatter = (_, row) => {
        const remaining = number(row.remaining), minimum = number(row.min_amt);
        if (remaining === null) return '-';
        return remaining <= 0 ? badge('danger', t('out_of_stock')) : minimum !== null && remaining <= minimum ? badge('warning', t('low_stock')) : badge('neutral', t('in_stock'));
    };
    window.presentationJobStateFormatter = (_, row) => {
        if (date(row.completed_at)) return badge('neutral', t('completed'));
        const due = date(row.expected_completion_date), today = new Date(); today.setHours(0, 0, 0, 0);
        return !due ? badge('neutral', t('unscheduled')) : due < today ? badge('danger', t('overdue')) : badge('neutral', t('scheduled'));
    };
    window.presentationRequestAgeFormatter = (_, row) => {
        const since = date(row.requested_at);
        if (!since) return '-';
        const hours = Math.max(0, Math.floor((Date.now() - since.getTime()) / 3600000));
        const days = Math.floor(hours / 24);
        const text = new Intl.RelativeTimeFormat(document.documentElement.lang || 'en', { numeric: 'auto' }).format(-(days || hours), days ? 'day' : 'hour');
        return '<span title="' + escape(since.toLocaleString()) + '">' + escape(text) + '</span>';
    };
    window.presentationAvailabilityFormatter = (_, row) => {
        const item = row.requestable || {}, remaining = number(item.remaining);
        if (item.available === false) return badge('warning', t('unavailable'));
        if (remaining !== null) return remaining > 0 ? badge('neutral', t('available') + ' · ' + remaining.toLocaleString()) : badge('warning', t('unavailable'));
        if (typeof item.available === 'boolean') return badge(item.available ? 'neutral' : 'warning', t(item.available ? 'available' : 'unavailable'));
        return badge('neutral', t('check_availability'));
    };
    window.presentationFileTypeFormatter = (value, row) => escape(value ? String(value).toUpperCase() : '-');
    window.presentationFileSizeFormatter = value => {
        const bytes = number(value);
        if (bytes === null) return '<span class="text-muted">' + escape(t('size_unavailable')) + '</span>';
        return escape(bytes < 1024 ? bytes + ' B' : bytes < 1048576 ? (bytes / 1024).toFixed(1) + ' KB' : (bytes / 1048576).toFixed(1) + ' MB');
    };
    // API labels are HTML-escaped. Read them as text before building new markup.
    const apiText = value => { const node = document.createElement('textarea'); node.innerHTML = value == null ? '' : String(value); return node.value; };
    const missingAssetValue = key => '<span class="asset-empty-value">' + escape(t(key)) + '</span>';
    window.presentationAssetNameFormatter = (value, row) => {
        const name = String(value || '').trim() ? value : row.model?.name;
        return name ? window.hardwareLinkFormatter(name, row) : missingAssetValue('not_recorded');
    };
    window.presentationAssetOptionalLinkFormatter = (value, row) => value ? window.hardwareLinkFormatter(value, row) : missingAssetValue('not_recorded');
    window.presentationAssetModelFormatter = (value, row) => value?.name ? window.modelsLinkObjFormatter(value, row) : missingAssetValue('not_recorded');
    window.presentationAssetLocationFormatter = value => {
        if (!value?.name) return missingAssetValue('not_recorded');
        const label = el(value.viewable === false ? 'span' : 'a', 'asset-location-label', apiText(value.name));
        if (label.tagName === 'A') label.href = window.SnipeAssetBase + '/locations/' + encodeURIComponent(value.id);
        return label.outerHTML;
    };
    window.presentationAssetAssignmentFormatter = value => {
        if (!value?.name) return missingAssetValue('unassigned');
        const destinations = {user: 'users', location: 'locations', asset: 'hardware'};
        const kinds = {user: 'assignment_person', location: 'assignment_location', asset: 'assignment_asset'};
        const wrap = el('span', 'asset-assignment');
        const canLink = destinations[value.type] && value.viewable !== false && (value.type !== 'user' || Object.hasOwn(value, 'username'));
        const name = el(canLink ? 'a' : 'span', '', apiText(value.name));
        if (canLink) name.href = window.SnipeAssetBase + '/' + destinations[value.type] + '/' + encodeURIComponent(value.id);
        wrap.append(el('small', 'asset-assignment-kind', t(kinds[value.type] || 'assigned_to')), name);
        return wrap.outerHTML;
    };
    window.presentationAssetActionsFormatter = (value, row) => {
        const original = el('div'); original.innerHTML = window.hardwareActionsFormatter(value, row);
        const actions = el('span', 'asset-row-actions'), menu = el('div', 'asset-row-menu');
        menu.hidden = true; menu.setAttribute('role', 'menu');
        menu.id = 'asset-more-' + row.id;
        const label = t('asset_more').replace(':tag', apiText(row.asset_tag || row.id));
        menu.setAttribute('aria-label', label);
        original.querySelectorAll('a, form').forEach(item => {
            if (item.closest('form') && item.tagName !== 'FORM') return;
            if (item.classList.contains('disabled')) return;
            if (item.matches('a[href$="/edit"]')) { actions.append(item); return; }
            const control = item.tagName === 'FORM' ? item.querySelector('button') : item;
            if (!control) return;
            control.querySelectorAll('.sr-only').forEach(n => n.classList.remove('sr-only'));
            control.className = 'asset-menu-item' + (control.classList.contains('delete-asset') ? ' delete-asset asset-menu-delete' : '');
            control.removeAttribute('data-tooltip'); control.setAttribute('role', 'menuitem');
            menu.append(item);
        });
        if (menu.childElementCount) {
            const button = el('button', 'btn btn-default asset-more-toggle', t('more'));
            button.type = 'button'; button.setAttribute('aria-haspopup', 'menu'); button.setAttribute('aria-expanded', 'false'); button.setAttribute('aria-controls', menu.id); button.setAttribute('aria-label', label);
            actions.append(button, menu);
        }
        return actions.childElementCount ? actions.outerHTML : missingAssetValue('no_actions');
    };
    let assetMenu = null;
    function closeAssetMenu(focus) {
        if (!assetMenu) return;
        const {menu, trigger, holder} = assetMenu;
        menu.hidden = true; trigger.setAttribute('aria-expanded', 'false');
        if (holder.isConnected) holder.append(menu); else menu.remove();
        assetMenu = null;
        if (focus && trigger.isConnected) trigger.focus();
    }
    function openAssetMenu(trigger, last) {
        closeAssetMenu(false);
        const holder = trigger.parentElement, menu = holder.querySelector('.asset-row-menu');
        if (!menu) return;
        document.body.append(menu); menu.hidden = false; trigger.setAttribute('aria-expanded', 'true');
        const bounds = trigger.getBoundingClientRect(), rect = menu.getBoundingClientRect();
        menu.style.left = Math.max(8, Math.min(innerWidth - rect.width - 8, bounds.right - rect.width)) + 'px';
        menu.style.top = (bounds.bottom + rect.height + 8 <= innerHeight ? bounds.bottom + 6 : Math.max(8, bounds.top - rect.height - 6)) + 'px';
        assetMenu = {menu, trigger, holder};
        const items = menu.querySelectorAll('[role=menuitem]'); items[last ? items.length - 1 : 0]?.focus();
    }
    $(document).on('click', '.asset-more-toggle', function (event) {
        event.preventDefault(); event.stopPropagation();
        if (assetMenu?.trigger === this) closeAssetMenu(true); else openAssetMenu(this, false);
    });
    document.addEventListener('click', event => { document.querySelectorAll('.asset-table-tools[open]').forEach(tools => { if (!tools.contains(event.target)) tools.open = false; }); });
    document.addEventListener('click', event => { if (assetMenu && !assetMenu.menu.contains(event.target) && !assetMenu.trigger.contains(event.target)) closeAssetMenu(false); });
    $(document).on('click', '.asset-row-menu [role=menuitem]', function () { setTimeout(() => closeAssetMenu(false), 0); });
    document.addEventListener('keydown', event => {
        const trigger = event.target.closest('.asset-more-toggle');
        if (trigger && ['ArrowDown', 'ArrowUp'].includes(event.key)) { event.preventDefault(); openAssetMenu(trigger, event.key === 'ArrowUp'); return; }
        if (!assetMenu) {
            if (event.key === 'Escape') document.querySelectorAll('.asset-table-tools[open]').forEach(tools => { tools.open = false; tools.querySelector('summary').focus(); });
            return;
        }
        if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); closeAssetMenu(true); }
        if (event.key === 'Tab') { event.preventDefault(); const trigger = assetMenu.trigger; closeAssetMenu(true); if (!event.shiftKey) { const next = Array.from(document.querySelectorAll('a[href],button,input,select')).filter(n => n.offsetParent && !n.disabled); next[next.indexOf(trigger) + 1]?.focus(); } }
        if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
            event.preventDefault(); const items = Array.from(assetMenu.menu.querySelectorAll('[role=menuitem]'));
            const index = items.indexOf(document.activeElement), next = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
            items[next]?.focus();
        }
    }, true);
    window.addEventListener('scroll', event => { if (assetMenu && !assetMenu.menu.contains(event.target)) closeAssetMenu(false); }, true);
    window.addEventListener('resize', () => closeAssetMenu(false));

    function tableCreateControls(table) {
        const create = table.closest('.bootstrap-table')?.querySelector('.fixed-table-toolbar [name=btnAdd]');
        if (!create) return;
        create.classList.add('table-create-button');
        const label = create.getAttribute('data-original-title') || create.getAttribute('title') || create.getAttribute('aria-label');
        const text = Array.from(create.childNodes).filter(node => node.nodeType === Node.TEXT_NODE ||
            (node.nodeType === Node.ELEMENT_NODE && !node.matches('i,svg,.sr-only')))
            .map(node => node.textContent.trim()).join('').trim();
        if (label && !text) create.append(el('span', 'table-create-label', label));
        if (label) create.setAttribute('aria-label', label);
    }

    function assetTableControls(table) {
        if (table.id !== 'assetsListingTable' || profileFor(table) !== 'assets') return;
        const wrap = table.closest('.bootstrap-table'), toolbar = wrap?.querySelector('.fixed-table-toolbar');
        if (!toolbar) return;
        wrap.classList.add('asset-inventory-table');
        const instance = $(table).data('bootstrap.table');
        if (instance) instance.options.stickyHeaderOffsetY = wrap.classList.contains('fullscreen') ? 0 : Math.ceil(document.querySelector('.main-header')?.getBoundingClientRect().bottom || 0);
        const filters = toolbar.querySelector('[name=advancedSearch]');
        if (filters && !filters.querySelector('.asset-control-label')) {
            filters.append(el('span', 'asset-control-label', t('filters'))); filters.setAttribute('aria-label', t('filters'));
        }
        const create = toolbar.querySelector('[name=btnAdd]');
        if (create) create.classList.add('asset-create-button');
        const columns = toolbar.querySelector('.keep-open > button');
        if (columns && !columns.querySelector('.asset-control-label')) columns.append(el('span', 'asset-control-label', columns.getAttribute('aria-label')));
        const group = toolbar.querySelector('.columns');
        if (!group || group.querySelector('.asset-table-tools')) return;
        const tools = el('details', 'asset-table-tools'), summary = el('summary', 'btn btn-default', t('table_tools')), panel = el('div', 'asset-tools-panel');
        tools.append(summary, panel);
        ['btnAddMaintenance', 'btnShowDeleted', 'btnExport', 'print', 'fullscreen'].forEach(name => {
            const button = group.querySelector('[name="' + name + '"]');
            if (!button) return;
            button.append(el('span', '', button.getAttribute('data-original-title') || button.getAttribute('title') || button.getAttribute('aria-label')));
            panel.append(button);
        });
        if (panel.childElementCount) group.append(tools);
        panel.addEventListener('click', () => { tools.open = false; });
    }
    const profiles = { assetsListingTable: 'assets', licensesListingTable: 'licenses', accessoriesListingTable: 'accessories', consumablesListingTable: 'consumables', componentsListingTable: 'components', usersListingTable: 'users', maintenanceListingTable: 'maintenance', checkoutRequestsListingTable: 'requests' };
    function profileFor(table) {
        const profile = profiles[table.id];
        const paths = {assets: '/hardware', licenses: '/licenses', accessories: '/accessories', consumables: '/consumables', components: '/components', users: '/users', maintenance: '/maintenances', requests: '/requests'};
        return location.pathname.replace(/\/$/, '') === paths[profile] ? profile : null;
    }
    function pinTable(table) {
        if (location.pathname.startsWith('/account/')) return;
        const profile = profileFor(table);
        if (!profile || !$(table).data('bootstrap.table')) return;
        table.classList.add('presentation-table');
        ['snipe-table--sticky-left-1','snipe-table--sticky-left-2','snipe-table--sticky-left-3','snipe-table--sticky-left-4'].forEach(cls => table.classList.remove(cls));
        const wrapper = table.closest('.fixed-table-container');
        if (!wrapper) return;
        wrapper.querySelectorAll('[data-presentation-pin]').forEach(cell => { cell.removeAttribute('data-presentation-pin'); cell.style.removeProperty('--pin-left'); });
        if ($(table).bootstrapTable('getOptions').cardView || window.innerWidth < 768) return;
        let left = 0;
        const headers = Array.from(table.querySelectorAll('thead tr:first-child > th')).filter(h => h.offsetWidth > 0);
        const fields = profiles[table.id] === 'requests' ? ['checkbox', 'user', 'requestable.item_name'] : ['checkbox', 'name', 'asset_tag'];
        headers.forEach((header, index) => {
            if (!fields.includes(header.dataset.field) || left > wrapper.clientWidth * .48) return;
            const cells = [header, ...Array.from(table.tBodies[0]?.rows || []).map(row => row.cells[index]).filter(Boolean)];
            cells.forEach(cell => { cell.dataset.presentationPin = 'true'; cell.style.setProperty('--pin-left', left + 'px'); });
            left += header.getBoundingClientRect().width;
        });
    }
    const cardControlLocations = new WeakMap();
    function cardTableControls(table) {
        const instance = $(table).data('bootstrap.table');
        const wrap = table.closest('.bootstrap-table');
        const box = table.closest('.box');
        const header = box?.querySelector(':scope > .box-header');
        const title = header?.querySelector(':scope > .box-title');
        const tools = header?.querySelector(':scope > .box-tools');
        const toolbar = instance?.$toolbar?.[0];
        if (!wrap || !title || !tools?.querySelector('[data-widget="collapse"]') || !toolbar || !toolbar.querySelector('.search input,.columns button')) return;
        // Multiple tables and transaction controls retain their own contextual toolbars.
        let location = cardControlLocations.get(table);
        if (!location && (box.querySelectorAll('table.snipe-table[id]').length !== 1 || toolbar.querySelector('.bs-bars input,.bs-bars select,.bs-bars button,.bs-bars a'))) return;
        if (!location) {
            const anchor = el('div', 'table-toolbar-anchor');
            toolbar.before(anchor);
            location = {anchor, toolbar}; cardControlLocations.set(table, location);
            new ResizeObserver(entries => {
                header.classList.toggle('table-header-compact', entries[0].contentRect.width < 480);
            }).observe(box);
            new MutationObserver(() => cardTableControls(table)).observe(wrap, {attributes: true, attributeFilter: ['class']});
        }
        if (wrap.classList.contains('fullscreen')) {
            if (toolbar.parentElement !== wrap) location.anchor.before(toolbar);
            header.classList.remove('has-table-controls');
            toolbar.classList.remove('card-header-toolbar');
        } else {
            if (toolbar.parentElement !== header) header.insertBefore(toolbar, tools);
            header.classList.add('has-table-controls');
            toolbar.classList.add('card-header-toolbar');
        }
    }
    function tableTools(table) {
        tableCreateControls(table);
        assetTableControls(table);
        cardTableControls(table);
        const key = profileFor(table), views = (window.SnipeTableViews || {})[key];
        if (!views || !$(table).data('bootstrap.table')) return;
        const wrap = table.closest('.bootstrap-table');
        if (!wrap || wrap.querySelector('.presentation-views')) { pinTable(table); return; }
        const toolbar = el('div', 'presentation-views');
        const label = el('label', '', t('view')); label.htmlFor = table.id + '-presentation';
        const select = el('select', 'form-control'); select.id = label.htmlFor;
        Object.keys(views).concat('all_columns').forEach(view => { const opt = el('option', '', t(view)); opt.value = view; select.append(opt); });
        const custom = el('option', '', t('custom')); custom.value = 'custom'; custom.disabled = true; select.append(custom);
        toolbar.append(label, select); wrap.prepend(toolbar);
        let applying = false;
        select.addEventListener('change', () => {
            const picked = select.value;
            const fields = views[picked];
            const columns = $(table).bootstrapTable('getOptions').columns.flat();
            applying = true;
            columns.forEach(column => {
                const visible = column.field === 'checkbox' || (fields ? fields.includes(column.field) : column.switchable !== false || column.visible);
                if (column.visible !== visible) $(table).bootstrapTable(visible ? 'showColumn' : 'hideColumn', column.field);
            });
            applying = false;
            select.value = picked;
            pinTable(table);
        });
        $(table).on('column-switch.bs.table', () => { if (!applying) select.value = 'custom'; });
        pinTable(table);
    }
    function permissions(editor) {
        const search = editor.querySelector('[data-permission-search]'), changed = editor.querySelector('[data-permission-changed]');
        const rows = Array.from(editor.querySelectorAll('[data-permission-row]'));
        const original = new Map(Array.from(editor.querySelectorAll('input[type=radio][name^="permission["]')).map(input => [input, input.checked]));
        function filter() {
            const query = search.value.trim().toLowerCase(); let count = 0, dirty = false;
            rows.forEach(row => {
                const different = Array.from(row.querySelectorAll('input[type=radio]')).some(input => original.get(input) !== input.checked);
                dirty = dirty || different;
                row.hidden = !(row.dataset.permissionKey + ' ' + row.querySelector('th').textContent).toLowerCase().includes(query) || (changed.checked && !different);
                if (!row.hidden && row.closest('.permission-section').style.display !== 'none') count++;
            });
            editor.querySelectorAll('.permission-section').forEach(section => {
                const visible = Array.from(section.querySelectorAll('[data-permission-row]')).filter(row => !row.hidden).length;
                section.hidden = visible === 0;
                const counter = section.querySelector('[data-permission-count]'); if (counter) counter.textContent = visible;
                if (query || changed.checked) section.open = true;
            });
            editor.querySelector('[data-permission-empty]').hidden = count !== 0;
            editor.querySelector('[data-permission-dirty]').hidden = !dirty;
        }
        search.addEventListener('input', filter); changed.addEventListener('change', filter);
        editor.addEventListener('change', () => setTimeout(filter, 0));
        editor.querySelector('[data-permission-expand]').addEventListener('click', () => editor.querySelectorAll('.permission-section').forEach(s => { s.open = true; }));
        editor.querySelector('[data-permission-collapse]').addEventListener('click', () => editor.querySelectorAll('.permission-section').forEach(s => { s.open = false; }));
        filter();
    }
    function recordDetails() {
        if (!/^\/(hardware|users)\/\d+\/?$/.test(location.pathname)) return;
        const list = document.querySelector('#details dl.table-display'); if (!list) return;
        const groups = new Map();
        const isUser = location.pathname.startsWith('/users/');
        Array.from(list.querySelectorAll(':scope > dt')).forEach(term => {
            const value = term.nextElementSibling; if (!value || value.tagName !== 'DD') return;
            const key = value.querySelector('[class*="js-copy-"]:not(.js-copy-link)')?.className || '';
            const category = /js-copy-current_value/.test(key) ? 'finance' : /js-copy-(audit_date|next_audit_date|eol_date)/.test(key) ? 'lifecycle' : /js-copy-external_/.test(key) ? 'technical' : isUser && /js-copy-(employee_num|jobtitle|groups)/.test(key) ? 'assignments' : 'record_details';
            if (!groups.has(category)) {
                const section = el('section', 'presentation-detail-group'); section.append(el('h3', '', t(category)));
                const dl = el('dl', 'table-display'); section.append(dl); groups.set(category, {section, dl});
            }
            groups.get(category).dl.append(term, value);
        });
        if (!groups.size) return;
        const container = el('div', 'presentation-details');
        groups.forEach(({section}) => container.append(section)); list.replaceWith(container);
    }
    function recordNavigation() {
        if (!/^\/(hardware|users)\/\d+\/?$/.test(location.pathname)) return;
        const nav = document.querySelector('.nav-tabs'); if (!nav) return;
        const primary = ['details', 'assets', 'maintenances', 'history', 'files'];
        const secondary = Array.from(nav.children).filter(item => {
            const a = item.querySelector('a[data-toggle=tab]'); return a && !primary.includes(a.hash.slice(1));
        });
        const overview = nav.querySelector('a[href="#details"] .tab-text'); if (overview) overview.textContent = t('overview');
        const maintenance = nav.querySelector('a[href="#maintenances"] .tab-text'); if (maintenance) maintenance.textContent = t('maintenance');
        if (!secondary.length) return;
        const more = el('li', 'dropdown presentation-more');
        const toggle = el('a', 'dropdown-toggle', t('more') + ' '); toggle.href = '#'; toggle.dataset.toggle = 'dropdown'; toggle.setAttribute('aria-haspopup', 'true'); toggle.setAttribute('aria-expanded', 'false'); toggle.append(el('span', 'caret'));
        const menu = el('ul', 'dropdown-menu dropdown-menu-right');
        secondary.forEach(item => { item.classList.remove('snipetab'); menu.append(item); }); more.append(toggle, menu); nav.append(more);
        const linked = menu.querySelector('a[href="#model-files"] .tab-text'); if (linked) linked.textContent = t('other_files');
    }
    function reportBuilder(form) {
        const fields = form.querySelector('#included_fields_wrapper');
        const body = form.querySelector('.box-body'); if (!fields || !body) return;
        const filters = fields.nextElementSibling; if (!filters) return;
        filters.classList.add('presentation-report-filters');
        const heading = el('h3', 'presentation-report-heading'); heading.tabIndex = -1;
        const nav = el('nav', 'presentation-workflow'); nav.setAttribute('aria-label', t('report_review'));
        const review = el('section', 'presentation-report-review');
        const summary = el('p', 'presentation-config-summary'); summary.setAttribute('aria-live', 'polite');
        const searchLabel = el('label', 'presentation-column-search', t('search_columns'));
        const search = el('input', 'form-control'); search.type = 'search'; searchLabel.append(search); fields.prepend(searchLabel);
        const error = el('p', 'text-danger', t('report_column_error')); error.hidden = true; error.setAttribute('role', 'alert');
        const buttons = [t('report_filters'), t('report_columns'), t('report_review')].map((text, i) => {
            const button = el('button', 'btn btn-default', (i + 1) + '. ' + text); button.type = 'button'; button.addEventListener('click', () => show(i)); nav.append(button); return button;
        });
        const controls = el('div', 'presentation-workflow-actions');
        const back = el('button', 'btn btn-default', t('back')), next = el('button', 'btn btn-primary', t('next')); back.type = next.type = 'button'; controls.append(back, next);
        let current = 0;
        const footer = form.querySelector('.box-footer');
        form.classList.add('presentation-report-builder'); body.prepend(nav, summary, heading, error); body.append(review, controls);
        const selected = () => Array.from(fields.querySelectorAll('input[type=checkbox][name]:checked'));
        function update() {
            summary.textContent = t('columns_selected').replace(':count', selected().length);
            if (selected().length) error.hidden = true;
        }
        function show(index) {
            const previous = current;
            if (index === 2 && !selected().length) { error.hidden = false; index = 1; }
            current = index; fields.hidden = index !== 1; filters.hidden = index !== 0; review.hidden = index !== 2;
            heading.textContent = [t('report_filters'), t('report_columns'), t('report_review')][index];
            buttons.forEach((button, i) => { button.classList.toggle('btn-primary', i === index); if (i === index) button.setAttribute('aria-current', 'step'); else button.removeAttribute('aria-current'); });
            back.hidden = index === 0; next.hidden = index === 2; if (footer) footer.hidden = index !== 2;
            if (index === 2) {
                review.replaceChildren(el('p', 'help-block', t('report_preview_help')));
                review.append(el('h4', '', t('report_columns')));
                const cols = el('div', 'presentation-column-summary'); selected().forEach(input => cols.append(el('span', 'presentation-state', input.closest('label')?.textContent.trim() || input.name))); review.append(cols, el('h4', '', t('filters_summary')));
                const list = el('dl', 'stepper-summary');
                filters.querySelectorAll('select[name], input[name]:not([type=hidden]):not([type=checkbox]):not([type=radio]), input[type=radio]:checked, input[type=checkbox][name]:checked').forEach(input => {
                    if (!input.value || input.disabled) return;
                    const text = input.tagName === 'SELECT' ? Array.from(input.selectedOptions).filter(o => o.value).map(o => o.textContent.trim()).join(', ') : input.value;
                    if (!text) return;
                    const label = input.closest('.form-group')?.querySelector('label')?.textContent.trim() || input.name;
                    list.append(el('dt', '', label), el('dd', '', text));
                });
                review.append(list.children.length ? list : el('p', '', t('all_records')));
            }
            update();
            if (previous !== index) form.dispatchEvent(new CustomEvent('interface:stepchange', {bubbles: true, detail: {form: form, from: previous, to: index, targets: [heading, [filters, fields, review][index]]}}));
        }
        fields.addEventListener('change', update);
        search.addEventListener('input', () => fields.querySelectorAll('label.form-control').forEach(label => { label.hidden = !label.textContent.toLowerCase().includes(search.value.toLowerCase()); }));
        back.addEventListener('click', () => show(Math.max(0, current - 1))); next.addEventListener('click', () => show(Math.min(2, current + 1)));
        form.addEventListener('submit', event => { if (!selected().length) { event.preventDefault(); show(1); search.focus(); } });
        show(0);
    }
    function settingsCategories() {
        if (location.pathname !== '/admin') return;
        const list = document.querySelector('#setting-list .list'); if (!list) return;
        const cards = Array.from(list.children).filter(card => card.querySelector('.admin a'));
        const categories = {};
        ['identity', 'access', 'inventory', 'integrations', 'operations'].forEach(key => {
            const section = el('section', 'presentation-settings-category'); section.append(el('h2', '', t(key)));
            const grid = el('div', 'presentation-settings-grid'); section.append(grid); list.append(section); categories[key] = { section, grid };
        });
        cards.forEach(card => {
            const link = card.querySelector('.settings_button, .admin a');
            const path = link.pathname;
            const key = /branding|localization|settings$/.test(path) ? 'identity' : /security|groups|logins/.test(path) ? 'access' : /asset_tags|labels/.test(path) ? 'inventory' : /slack|ldap|google|adapters|saml|oauth/.test(path) ? 'integrations' : 'operations';
            const body = card.querySelector('.box-body');
            const icon = link.querySelector('i,svg');
            const name = link.querySelector('.name');
            if (body && icon && name) {
                const visual = el('span', 'settings-card-visual');
                visual.setAttribute('aria-hidden', 'true'); visual.append(icon);
                const content = el('span', 'settings-card-content');
                const heading = el('h3', 'settings-card-title'); heading.append(name);
                content.append(heading);
                const description = body.querySelector('.index-block');
                if (description) content.append(description);
                content.append(el('span', 'settings-card-action', t('settings_open')));
                const keywords = link.querySelector('.keywords');
                link.replaceChildren(visual, content);
                if (keywords) link.append(keywords);
                link.classList.add('settings-card-link');
                card.dataset.settingsCategory = key;
                body.replaceChildren(link);
            }
            categories[key].grid.append(card);
        });
        const landing = el('div', 'presentation-settings-grid settings-category-landing');
        const navigation = el('div', 'settings-category-navigation');
        const back = el('a', 'btn btn-default', t('settings_all_categories')); back.href = '#categories';
        navigation.append(back);
        const empty = el('p', 'help-block settings-search-empty', t('settings_no_results'));
        empty.setAttribute('role', 'status');
        list.prepend(navigation, landing); list.append(empty);
        Object.entries(categories).forEach(([key, {grid}]) => {
            if (!grid.children.length) return;
            const box = el('div', 'admin box box-default'); box.dataset.settingsCategory = key;
            const body = el('div', 'box-body');
            const link = el('a', 'settings-card-link settings-category-link'); link.href = '#category/' + key;
            const visual = grid.querySelector('.settings-card-visual').cloneNode(true);
            const content = el('span', 'settings-card-content');
            content.append(el('h2', 'settings-card-title', t(key)));
            const names = Array.from(grid.querySelectorAll('.name')).map(name => name.textContent.trim());
            content.append(el('span', 'index-block', names.join(' · ')));
            const footer = el('span', 'settings-category-footer');
            footer.append(el('span', 'settings-category-count', t('settings_category_count').replace(':count', names.length)), el('span', 'settings-card-action', t('settings_open')));
            content.append(footer); link.append(visual, content); body.append(link); box.append(body); landing.append(box);
        });
        const system = document.querySelector('#settings-system-information');
        if (system) {
            const box = el('div', 'admin box box-default');
            const body = el('div', 'box-body');
            const link = el('a', 'settings-card-link settings-category-link'); link.href = '#category/system';
            const visual = el('span', 'settings-card-visual'); visual.setAttribute('aria-hidden', 'true');
            visual.append(el('i', 'fas fa-info-circle'));
            const content = el('span', 'settings-card-content');
            content.append(el('h2', 'settings-card-title', system.querySelector('.box-title').textContent.trim()), el('span', 'index-block', t('settings_system_summary')), el('span', 'settings-card-action', t('settings_open')));
            link.append(visual, content); body.append(link); box.append(body); landing.prepend(box);
            system.classList.add('settings-system-details'); list.append(system);
            categories.system = {section: system, grid: null};
        }
        const input = document.querySelector('#searchinput');
        const activeCategory = () => {
            const key = location.hash.replace(/^#category\//, '');
            return Object.hasOwn(categories, key) ? key : null;
        };
        const filter = () => {
            const query = input.value.toLowerCase().trim();
            const selected = activeCategory();
            landing.hidden = !!query || !!selected;
            navigation.hidden = !query && !selected;
            let matches = 0;
            cards.forEach(card => { card.style.display = !query || card.textContent.toLowerCase().includes(query) ? '' : 'none'; });
            Object.entries(categories).forEach(([key, {section, grid}]) => {
                if (!grid) {
                    section.hidden = query ? !section.textContent.toLowerCase().includes(query) && !t('settings_system_summary').toLowerCase().includes(query) : selected !== key;
                    if (!section.hidden) matches++;
                    return;
                }
                const visible = (query || selected === key) && Array.from(grid.children).some(card => card.style.display !== 'none');
                section.hidden = !visible;
                if (visible) matches += Array.from(grid.children).filter(card => card.style.display !== 'none').length;
            });
            empty.hidden = !query || matches > 0;
        };
        back.addEventListener('click', () => { input.value = ''; filter(); });
        window.addEventListener('hashchange', () => {
            input.value = ''; filter();
            const target = activeCategory() ? categories[activeCategory()].section.querySelector('h2') : landing.querySelector('a');
            if (target) { if (target.tagName === 'H2') target.setAttribute('tabindex', '-1'); target.focus({preventScroll: true}); }
        });
        input.addEventListener('input', () => setTimeout(filter, 0)); document.querySelector('#searchclear')?.addEventListener('click', () => setTimeout(filter, 0)); filter();
    }
    function catalogue(table) {
        if (!location.pathname.startsWith('/account/requestable') || !$(table).data('bootstrap.table')) return;
        if ($(table).bootstrapTable('getOptions').cardView) { $(table).bootstrapTable('refreshOptions', {cardView: false, mobileResponsive: false}); return; }
        const wrap = table.closest('.bootstrap-table'); if (!wrap) return;
        if (!wrap.querySelector('.presentation-catalogue-toggle')) {
            const toggle = el('button', 'btn btn-default presentation-catalogue-toggle', t('list_view')); toggle.type = 'button'; toggle.setAttribute('aria-pressed', 'false');
            wrap.prepend(el('p', 'help-block', t('catalogue_help')), toggle); wrap.classList.add('presentation-catalogue');
            toggle.addEventListener('click', () => { const list = wrap.classList.toggle('presentation-catalogue-list'); toggle.textContent = t(list ? 'request_catalogue' : 'list_view'); toggle.setAttribute('aria-pressed', String(list)); });
        }
        const columns = $(table).bootstrapTable('getVisibleColumns');
        table.querySelectorAll('tbody tr').forEach(row => {
            Array.from(row.cells).forEach((cell, i) => { if (columns[i]) { cell.dataset.catalogueField = columns[i].field; cell.dataset.catalogueLabel = columns[i].title + ': '; } });
        });
    }

    function alignFormFooters(form) {
        const labels = (words.cancel_actions || ['Cancel']).map(label => label.trim().toLowerCase());
        form.querySelectorAll('.box-footer').forEach(footer => {
            const cancel = Array.from(footer.querySelectorAll('a,button')).find(action => action.hasAttribute('data-form-cancel') || labels.includes(action.textContent.replace(/\s+/g, ' ').trim().toLowerCase()));
            const submit = footer.querySelector('[type="submit"]');
            if (!cancel || !submit) return;
            footer.classList.add('mobile-form-actions');
            cancel.classList.add('form-footer-cancel-action');
            submit.classList.add('form-footer-primary-action');
        });
    }

    function cancellationGuard() {
        const baselines = new WeakMap(), touched = new WeakSet(), restored = new WeakSet(), bypass = new WeakSet();
        const labels = (words.cancel_actions || ['Cancel']).map(label => label.trim().toLowerCase());
        let dialog, pending;
        const eligible = form => form && !['get', 'delete'].includes((form.querySelector('input[name="_method"]')?.value || form.method).toLowerCase()) && !form.hasAttribute('data-no-cancel-confirm');
        function snapshot(form) {
            return JSON.stringify(Array.from(form.querySelectorAll('input,select,textarea,[contenteditable=true]')).filter(control => {
                return (control.name || control.id || control.isContentEditable) && !['_token', '_method', 'redirect_option'].includes(control.name) && control.id !== 'available-select' && !['submit', 'button', 'reset'].includes(control.type);
            }).map(control => {
                let value;
                if (control.type === 'file') value = Array.from(control.files).map(file => [file.name, file.size, file.lastModified]);
                else if (control.type === 'checkbox' || control.type === 'radio') value = [control.value, control.checked];
                else if (control.tagName === 'SELECT') value = Array.from(control.id === 'selected-select' ? control.options : control.selectedOptions).map(option => option.value);
                else value = control.isContentEditable ? control.innerHTML : control.value;
                return [control.name || control.id, value];
            }));
        }
        function register(form) {
            if (!eligible(form) || baselines.has(form)) return;
            baselines.set(form, snapshot(form));
            if (words.restored_form_input && form.matches('.form-horizontal')) restored.add(form);
        }
        function scanForms() { document.querySelectorAll('form').forEach(form => { register(form); alignFormFooters(form); }); }
        function begin(event) {
            const field = event.target.closest('input,select,textarea,label,button,[contenteditable=true],.select2-selection,.iCheck-helper');
            const form = field && field.closest('form');
            if (!eligible(form) || touched.has(form)) return;
            // Capture after initial AJAX/widget setup, before the first user edit.
            baselines.set(form, snapshot(form)); touched.add(form);
        }
        document.addEventListener('focusin', begin, true);
        document.addEventListener('pointerdown', begin, true);
        ['input', 'change'].forEach(type => document.addEventListener(type, event => {
            const form = event.target.closest('form');
            if (eligible(form)) { register(form); touched.add(form); }
        }, true));
        document.addEventListener('reset', event => { if (eligible(event.target)) setTimeout(() => { baselines.set(event.target, snapshot(event.target)); touched.delete(event.target); }, 0); });
        function ensureDialog() {
            if (dialog) return;
            dialog = el('div', 'modal fade'); dialog.id = 'unsaved-changes-dialog'; dialog.tabIndex = -1;
            dialog.setAttribute('role', 'dialog'); dialog.setAttribute('aria-modal', 'true'); dialog.setAttribute('aria-labelledby', 'unsaved-changes-title'); dialog.setAttribute('aria-describedby', 'unsaved-changes-description');
            const frame = el('div', 'modal-dialog'), content = el('div', 'modal-content'), header = el('div', 'modal-header');
            const title = el('h2', 'modal-title', t('discard_title')); title.id = 'unsaved-changes-title'; header.append(title);
            const body = el('div', 'modal-body'), description = el('p', '', t('discard_description')); description.id = 'unsaved-changes-description'; body.append(description);
            const footer = el('div', 'modal-footer'), discard = el('button', 'btn btn-danger', t('discard_changes')), keep = el('button', 'btn btn-primary', t('keep_editing'));
            discard.type = keep.type = 'button'; discard.dataset.discardChanges = ''; keep.dataset.dismiss = 'modal'; keep.dataset.keepEditing = '';
            footer.append(discard, keep); content.append(header, body, footer); frame.append(content); dialog.append(frame); document.body.append(dialog);
            $(dialog).on('shown.bs.modal', () => keep.focus());
            $(dialog).on('hidden.bs.modal', () => {
                const active = document.querySelector('.modal.in');
                if (active) { document.body.classList.add('modal-open'); const instance = $(active).data('bs.modal'); if (instance) instance.enforceFocus(); }
                if (pending) { const action = pending.action; pending = null; if (action && action.isConnected) action.focus({preventScroll: true}); }
            });
            discard.addEventListener('click', () => {
                if (!pending) return;
                const action = pending.action, callback = pending.onDiscard; pending = null;
                $(dialog).one('hidden.bs.modal', () => { if (callback) callback(); else if (action && action.isConnected) { bypass.add(action); action.click(); } });
                $(dialog).modal('hide');
            });
        }
        window.InterfaceForms = Object.assign(window.InterfaceForms || {}, {confirmDiscard: function (onDiscard, trigger) {
            ensureDialog(); pending = {action: trigger, onDiscard: onDiscard}; $(dialog).modal({backdrop: 'static', keyboard: true, show: true});
        }});
        document.addEventListener('click', event => {
            const action = event.target.closest('a,button,[role=button]');
            if (!action || action.closest('#unsaved-changes-dialog') || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || action.target === '_blank') return;
            if (bypass.has(action)) { bypass.delete(action); return; }
            if (!action.hasAttribute('data-form-cancel') && !labels.includes(action.textContent.replace(/\s+/g, ' ').trim().toLowerCase())) return;
            const form = action.closest('form') || action.closest('.modal')?.querySelector('form');
            if (!eligible(form) || (!restored.has(form) && (!touched.has(form) || snapshot(form) === baselines.get(form)))) return;
            event.preventDefault(); event.stopImmediatePropagation();
            ensureDialog(); pending = {action: action}; $(dialog).modal({backdrop: 'static', keyboard: true, show: true});
        }, true);
        scanForms();
        // Modal forms and additional fields may arrive after page initialization.
        new MutationObserver(scanForms).observe(document.body, {childList: true, subtree: true});
    }

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        const open = document.querySelector('.tasks-menu.open:has(.stock-alert-panel)');
        if (!open) return;
        event.preventDefault(); event.stopPropagation();
        const trigger = open.querySelector(':scope > .dropdown-toggle');
        $(trigger).dropdown('toggle'); trigger.focus();
    });

    function systemBanners() {
        let scheduled = false;
        function scan() {
            scheduled = false;
            document.querySelectorAll('.ui-refresh .alert,.ui-refresh .callout:not(.callout-legend),.ui-refresh .maintenance-mode-alert,.ui-refresh .table-load-error .well[role="alert"]').forEach(banner => {
                if (banner.querySelector(':scope > .banner-status')) return;
                const type = banner.matches('.alert-success,.callout-success') ? 'success' : banner.matches('.alert-warning,.callout-warning,.maintenance-mode-alert') ? 'warning' : banner.matches('.alert-danger,.alert-error,.callout-danger,.callout-error,.table-load-error .well') ? 'error' : banner.matches('.alert-info,.callout-info') ? 'info' : 'notice';
                const body = el(banner.tagName === 'P' ? 'span' : 'div', 'banner-content');
                const label = el('span', 'banner-status', t('banner_' + type));
                const children = Array.from(banner.childNodes);
                children.forEach(child => {
                    if (child.nodeType === 1 && child.matches('.close,[data-dismiss="alert"]')) return;
                    body.append(child);
                });
                banner.classList.add('system-banner', 'banner-' + type);
                if (!banner.hasAttribute('role')) banner.setAttribute('role', ['warning', 'error'].includes(type) ? 'alert' : 'status');
                if (!banner.hasAttribute('aria-atomic')) banner.setAttribute('aria-atomic', 'true');
                banner.querySelectorAll('.close,[data-dismiss="alert"]').forEach(close => {
                    if (!close.hasAttribute('aria-label')) close.setAttribute('aria-label', t('banner_dismiss'));
                    if (close.tagName === 'BUTTON' && !close.hasAttribute('type')) close.type = 'button';
                });
                banner.prepend(label); banner.append(body);
            });
        }
        scan();
        new MutationObserver(() => { if (!scheduled) { scheduled = true; requestAnimationFrame(scan); } }).observe(document.body, {childList: true, subtree: true});
    }

    function formExperience() {
        const states = new WeakMap();
        let generatedId = 0, scheduled = false;
        function labelText(control) {
            const row = control.closest('.form-group');
            const label = Array.from(row?.querySelectorAll('label') || []).find(n => n.htmlFor === control.id) || row?.querySelector(':scope > label');
            if (!label) return control.name;
            const copy = label.cloneNode(true); copy.querySelectorAll('.form-field-status,.sr-only').forEach(n => n.remove());
            return copy.textContent.replace(/\s+/g, ' ').trim();
        }
        function fieldFocus(form, control) {
            if (form.classList.contains('form-stepper-ready')) {
                form.dispatchEvent(new CustomEvent('form:reveal-field', {detail: {control}}));
            } else {
                const target = control.classList.contains('select2-hidden-accessible') ? control.parentElement.querySelector('.select2-selection') : control;
                target?.focus(); control.closest('.form-group')?.scrollIntoView({block: 'start', behavior: 'auto'});
            }
        }
        function reportErrors(form, supplied, options = {}) {
            if (!form?.matches('.form-ui-ready')) return;
            const state = states.get(form), validator = $(form).data('validator');
            if (!state) return;
            const errors = new Map();
            if (validator) Object.keys(validator.invalid).forEach(name => {
                if (!validator.invalid[name]) return;
                const control = Array.from(form.elements).find(n => n.name === name);
                const message = validator.errorMap[name] || (control && validator.errorsFor(control).text());
                if (control && message) errors.set(control, message);
            });
            (supplied || []).forEach(error => { if (error.element && error.message) errors.set(error.element, error.message); });
            form.querySelectorAll('.form-group.has-error').forEach(row => {
                const control = row.querySelector('input:not([type=hidden]),select,textarea');
                const message = Array.from(row.querySelectorAll('.alert-msg')).find(n => n.textContent.trim() && !n.hidden && getComputedStyle(n).display !== 'none');
                if (control && message && !errors.has(control)) errors.set(control, message.textContent.trim());
            });
            if (!state.summary && !errors.size) return;
            if (!state.summary) {
                const summary = el('section', 'form-error-summary'); summary.tabIndex = -1;
                const heading = el('h2', '', t('form_errors_title')); heading.id = 'form-errors-' + (++generatedId);
                summary.setAttribute('aria-labelledby', heading.id);
                summary.append(heading, el('ul')); state.summary = summary;
                const nav = form.querySelector(':scope > .form-stepper');
                if (nav) nav.after(summary); else form.prepend(summary);
            }
            const summary = state.summary, list = summary.querySelector('ul'); list.replaceChildren();
            errors.forEach((message, control) => {
                const item = el('li'), link = el('a', '', labelText(control) + ': ' + message);
                if (!control.id) control.id = 'form-field-' + (++generatedId);
                link.href = '#' + control.id;
                link.addEventListener('click', event => { event.preventDefault(); fieldFocus(form, control); });
                item.append(link); list.append(item);
            });
            summary.hidden = !errors.size;
            if (errors.size && options.focus !== false) {
                summary.focus({preventScroll: true}); summary.scrollIntoView({block: 'start', behavior: 'auto'});
            }
        }
        window.InterfaceForms = Object.assign(window.InterfaceForms || {}, {reportErrors});
        function describe(control, node) {
            if (!node.id) node.id = 'form-help-' + (++generatedId);
            const ids = new Set((control.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
            ids.add(node.id); control.setAttribute('aria-describedby', Array.from(ids).join(' '));
            if (control.classList.contains('select2-hidden-accessible')) control.parentElement.querySelector('.select2-selection')?.setAttribute('aria-describedby', Array.from(ids).join(' '));
        }
        const examples = words.form_examples || {};
        function guideExample(control) {
            const name = (control.name || '').replace(/\[[^\]]*\]/g, '').toLowerCase();
            const label = labelText(control).toLowerCase();
            const kind = control.form?.dataset.stepper || new URL(control.form?.action || location.href).pathname.split('/').filter(Boolean).find(part => ['hardware','licenses','users','accessories','consumables','components','companies','models','statuslabels','depreciations','locations','categories','suppliers','manufacturers','departments','groups','maintenances','kits'].includes(part));
            const kinds = {companies:'company',models:'model',statuslabels:'status',depreciations:'depreciation',asset:'asset',hardware:'asset',license:'license',licenses:'license',user:'person',users:'person',accessory:'accessory',accessories:'accessory',consumable:'consumable',consumables:'consumable',component:'component',components:'component',location:'location',locations:'location',categories:'category',suppliers:'supplier',manufacturers:'manufacturer',departments:'department',group:'group',groups:'group',maintenance:'maintenance',maintenances:'maintenance',kit:'kit',kits:'kit'};
            const exact = {first_name:'first_name',last_name:'last_name',username:'username',email:'email',license_email:'email',license_name:'person',phone:'phone',mobile:'phone',fax:'phone',asset_tag:'tag',asset_tags:'tag',serial:'serial',serials:'serial',model_number:'model_number',modelno:'model_number',employee_num:'employee',employee_number:'employee',jobtitle:'title',address:'address',address2:'address',city:'city',state:'state',zip:'zip',postal_code:'zip',country:'country',country_code:'country',url:'url',website:'url',order_number:'order',purchase_order:'order',notes:'notes',checkout_note:'notes',checkin_note:'notes',description:'description',qty:'number',quantity:'number',seats:'seats',min_amt:'minimum',purchase_cost:'cost',cost:'cost',warranty_months:'months',eol:'months',currency:'currency',locale:'language',timezone:'timezone',company_id:'company',company_ids:'company',department_id:'department',location_id:'location',rtd_location_id:'location',assigned_location:'location',parent_id:'location',supplier_id:'supplier',default_supplier_id:'supplier',manufacturer_id:'manufacturer',category_id:'category',model_id:'model',status_id:'status',depreciation_id:'depreciation',asset_maintenance_type:'maintenance_type',asset_id:'asset',license_id:'license',accessory_id:'accessory',component_id:'component',consumable_id:'consumable',assigned_to:'person',responsible_user_id:'person',assigned_user:'person',user_id:'person',manager_id:'person',groups:'group',assigned_asset:'asset'};
            if (name === 'name') return examples[kinds[kind] || 'general_name'];
            if (name === 'serial' && ['license','licenses'].includes(kind)) return examples.key;
            if (name === 'category_id') return examples['category_' + ({licenses:'license',accessories:'accessory',consumables:'consumable',components:'component'}[kind] || kind)] || examples.category;
            if (control.type === 'email') return examples.email;
            if (control.type === 'url') return examples.url;
            if (control.type === 'tel') return examples.phone;
            if (control.closest('.input-group.date[data-format="YYYY-MM-DD"]') || control.type === 'date') return examples.date;
            if (control.closest('.input-group.date')) return examples.datetime;
            if (exact[name]) return examples[exact[name]];
            if (/license.*key|product.*key/.test(name)) return examples.key;
            if (/mac.?address|ethernet.*mac|bluetooth.*mac/.test(name + ' ' + label)) return examples.mac;
            if (/ip.?address/.test(name + ' ' + label)) return examples.ip;
            if (/ram|memory/.test(label)) return examples.ram;
            if (/storage|disk.*size/.test(label)) return examples.storage;
            if (/operating.system|os_name/.test(name + ' ' + label)) return examples.os;
            if (/version/.test(name)) return examples.version;
            if (/date|expected_checkin/.test(name)) return examples.date;
            if (/days|duration/.test(name)) return examples.days;
            if (control.type === 'number') return examples.number;
            if (control.tagName === 'TEXTAREA') return examples.notes;
            return null;
        }
        function guideControl(control) {
            if (['hidden','file','checkbox','radio','submit','button','reset','color','range'].includes(control.type) || control.classList.contains('select2-search__field')) return;
            const label = labelText(control) || control.name.replace(/_/g, ' ');
            const existing = control.getAttribute('placeholder') || '';
            let example = guideExample(control);
            const password = control.type === 'password' || /password/.test(control.name);
            if (password) {
                const min = control.minLength > 0 ? control.minLength : Number(window.SnipeFieldGuidance?.passwordMin || 8);
                example = /confirm/.test(control.name) ? t('form_password_confirm_hint') : t('form_password_hint').replace(':min', min);
            } else if (/token|secret|private_key|api_key/.test(control.name)) example = t('form_token_hint');
            const generic = !existing || existing.toLowerCase() === label.toLowerCase() || /^(select|choose|search|enter|yyyy-mm-dd)/i.test(existing);
            if (!generic && !control.dataset.formExamplePlaceholder) return;
            if (!example) {
                if (existing) return;
                example = t('form_enter_example').replace(':label', label);
            }
            if (control.type === 'number') {
                let value = Number(example);
                if (Number.isFinite(value)) {
                    if (control.min !== '') value = Math.max(value, Number(control.min));
                    if (control.max !== '') value = Math.min(value, Number(control.max));
                    if (value !== Number(example)) example = String(value);
                }
            }
            if (control.tagName !== 'SELECT') {
                if (control.getAttribute('placeholder') !== example) control.setAttribute('placeholder', example);
                control.dataset.formExamplePlaceholder = 'true';
                return;
            }
            const prompt = t(control.classList.contains('js-data-ajax') ? 'form_search_example' : 'form_select_example').replace(':label', label.toLowerCase()).replace(':example', example);
            const values = $(control).val();
            const empty = values === null || values === '' || (Array.isArray(values) && !values.filter(Boolean).length);
            const instance = $(control).data('select2');
            if (control.dataset.formExamplePlaceholder !== prompt) {
                control.dataset.placeholder = prompt;
                const option = Array.from(control.options).find(o => o.value === '');
                if (option && option.textContent !== prompt) option.textContent = prompt;
                if (instance) {
                    const placeholder = {id:'', text:prompt};
                    instance.options.set('placeholder', placeholder);
                    if (instance.selection.placeholder) instance.selection.placeholder = placeholder;
                    if (empty) instance.dataAdapter.current(data => instance.selection.update(data));
                }
                control.dataset.formExamplePlaceholder = prompt;
            }
            const selection = control.parentElement.querySelector('.select2-selection');
            selection?.querySelector('.select2-selection__rendered')?.classList.toggle('is-example-placeholder', empty && !control.multiple);
            const search = selection?.querySelector('.select2-search__field');
            if (search && empty && search.placeholder !== prompt) search.placeholder = prompt;
        }
        function guideUploads() {
            document.querySelectorAll('input[type=file]').forEach(control => {
                const region = control.closest('.form-group,#import-upload-zone,.modal-body') || control.parentElement.parentElement;
                const guidance = region?.querySelector('[data-upload-guidance]');
                if (guidance) {
                    describe(control, guidance);
                    const trigger = control.closest('label.btn');
                    if (trigger) {
                        const disabled = control.disabled || trigger.classList.contains('disabled');
                        trigger.removeAttribute('aria-hidden');
                        trigger.setAttribute('role', 'button'); trigger.tabIndex = disabled ? -1 : 0;
                        trigger.setAttribute('aria-disabled', String(disabled));
                        trigger.setAttribute('aria-describedby', guidance.id);
                        if (!trigger.dataset.uploadKeyboard) {
                            trigger.dataset.uploadKeyboard = 'true';
                            trigger.addEventListener('keydown', event => {
                                if (['Enter', ' '].includes(event.key)) {
                                    event.preventDefault();
                                    if (!control.disabled && !trigger.classList.contains('disabled')) control.click();
                                }
                            });
                        }
                    }
                }
            });
        }
        function enhance(form) {
            if (form.matches('[data-permission-editor]') || !form.querySelector('.form-group')) return;
            if (!states.has(form)) {
                states.set(form, {serverErrors: new Map()}); form.classList.add('form-ui-ready');
                const guide = el('p', 'form-field-guidance', t('form_guidance'));
                const panel = form.querySelector('.box-body, .tab-content');
                if (panel) panel.prepend(guide);
            }
            form.querySelectorAll('.form-group').forEach(row => {
                const controls = Array.from(row.querySelectorAll('input:not([type=hidden]):not([type=submit]),select,textarea')).filter(c => c.closest('.form-group') === row && c.name);
                if (!controls.length || row.closest('[data-permission-editor]')) return;
                const label = row.querySelector(':scope > label.control-label, :scope > label[class*="col-"]');
                if (label && !label.querySelector('input')) {
                    row.classList.add('form-ui-row');
                    const control = controls.find(c => c.id === label.htmlFor) || controls[0];
                    if (!control.id) control.id = 'form-field-' + (++generatedId);
                    if (!document.getElementById(label.htmlFor) || !row.contains(document.getElementById(label.htmlFor))) label.htmlFor = control.id;
                    let wrapper = control;
                    while (wrapper.parentElement !== row && wrapper.parentElement) wrapper = wrapper.parentElement;
                    wrapper.classList.add('form-ui-input');
                    const selection = wrapper.querySelector('.select2-selection');
                    if (selection) {
                        if (!label.id) label.id = 'form-label-' + (++generatedId);
                        const ids = new Set((selection.getAttribute('aria-labelledby') || '').split(/\s+/).filter(Boolean));
                        ids.add(label.id); selection.setAttribute('aria-labelledby', Array.from(ids).join(' '));
                    }
                    Array.from(row.children).forEach(child => {
                        if (child === wrapper || child === label) return;
                        if (child.matches('div.text-left') && child.querySelector('.btn')) child.classList.add('form-ui-aside');
                        else if (child.matches('div[class*="col-"]')) child.classList.add('form-ui-help');
                    });
                    const fields = controls.filter(c => !['checkbox', 'radio', 'file'].includes(c.type));
                    if (fields.length) {
                        const required = fields.some(c => c.required || c.getAttribute('aria-required') === 'true' || ($(form).data('validator') && $(c).rules().required === true));
                        let status = label.querySelector('.form-field-status');
                        if (!status) { status = el('span', 'form-field-status'); label.append(status); }
                        const text = t(required ? 'form_required' : 'form_optional');
                        if (status.textContent !== text) status.textContent = text;
                        status.dataset.required = String(required);
                    }
                } else if (!label && row.querySelector(':scope > div[class*="offset"]')) row.classList.add('form-ui-checkrow');
                controls.forEach(control => {
                    if (control.getAttribute('aria-label') === control.name && control.labels?.length) control.removeAttribute('aria-label');
                    row.querySelectorAll('.help-block,.alert-msg').forEach(help => { if (help.textContent.trim() && getComputedStyle(help).display !== 'none') describe(control, help); });
                    if (control.type === 'number') control.inputMode = control.step && control.step !== '1' ? 'decimal' : 'numeric';
                    if (['purchase_cost', 'cost'].includes(control.name)) control.inputMode = 'decimal';
                    const date = control.closest('.input-group.date[data-format="YYYY-MM-DD"]');
                    if (date && !row.querySelector('.form-date-guidance')) {
                        const help = el('p', 'help-block form-date-guidance', t('form_date_format')); date.after(help); describe(control, help);
                    }
                });
                if (row.classList.contains('has-error')) {
                    controls[0].setAttribute('aria-invalid', 'true');
                    const serverErrors = Array.from(row.querySelectorAll('[data-server-error]')).filter(n => !n.hidden);
                    const saved = states.get(form).serverErrors;
                    if (serverErrors.length && !saved.has(row)) saved.set(row, {nodes: serverErrors, controls, value: JSON.stringify(controls.map(c => $(c).val()))});
                }
            });
            const nouns = {hardware: 'asset', licenses: 'license', users: 'user', accessories: 'accessory', consumables: 'consumable', components: 'component', locations: 'location', categories: 'category', suppliers: 'supplier', manufacturers: 'manufacturer', departments: 'department', groups: 'group', maintenances: 'maintenance', kits: 'kit'};
            const route = new URL(form.action, location.href).pathname.match(/^\/(?:admin\/|api\/v1\/)?(\w+)(?:\/(\d+))?\/?$/);
            if (route && nouns[route[1]]) {
                const action = t(route[2] ? 'form_save_changes' : 'form_create_' + nouns[route[1]]);
                form.querySelectorAll('button[type=submit]').forEach(button => {
                    if (button.textContent.replace(/\s+/g, ' ').trim() !== t('form_save')) return;
                    Array.from(button.childNodes).filter(n => n.nodeType === 3).forEach(n => n.remove());
                    button.append(el('span', 'form-action-label', action));
                });
            }
            const state = states.get(form);
            if (!state.checkedServerErrors) {
                state.checkedServerErrors = true;
                if (form.querySelector('.form-group.has-error .alert-msg')) requestAnimationFrame(() => reportErrors(form));
            }
        }
        function scan() {
            scheduled = false;
            document.querySelectorAll('form.form-horizontal').forEach(enhance);
            document.querySelectorAll('form.form-horizontal,form[data-stepper],.modal form').forEach(form => form.querySelectorAll('input[name],textarea[name],select[name]').forEach(guideControl));
            guideUploads();
        }
        function schedule() { if (!scheduled) { scheduled = true; requestAnimationFrame(scan); } }
        function clearChangedServerError(control) {
            const form = control.closest('form.form-ui-ready'), state = form && states.get(form);
            if (!state) return;
            state.serverErrors.forEach((record, row) => {
                if (!row.contains(control) || JSON.stringify(record.controls.map(c => $(c).val())) === record.value) return;
                record.nodes.forEach(n => { n.hidden = true; n.style.display = 'none'; });
                state.serverErrors.delete(row);
                if (!Array.from(row.querySelectorAll('.alert-msg')).some(n => n.textContent.trim() && getComputedStyle(n).display !== 'none')) {
                    row.classList.remove('has-error'); record.controls.forEach(c => c.setAttribute('aria-invalid', 'false'));
                }
                if (state.summary) requestAnimationFrame(() => reportErrors(form, null, {focus: false}));
            });
        }
        document.addEventListener('input', event => { if (event.target.matches('input,textarea')) clearChangedServerError(event.target); });
        document.addEventListener('change', event => { if (event.target.matches('input,select,textarea')) clearChangedServerError(event.target); });
        document.addEventListener('focusout', event => {
            const form = event.target.closest('form.form-ui-ready');
            if (form && states.get(form)?.summary) requestAnimationFrame(() => reportErrors(form, null, {focus: false}));
        });
        $(document).on('select2:select select2:clear', 'form.form-ui-ready select', function () {
            clearChangedServerError(this);
            const validator = $(this.form).data('validator');
            if (validator) { validator.element(this); if (states.get(this.form)?.summary) reportErrors(this.form, null, {focus: false}); }
        });
        scan();
        new MutationObserver(schedule).observe(document.body, {childList: true, subtree: true, attributes: true, attributeFilter: ['required', 'disabled']});
    }

    $(document).on('post-body.bs.table post-header.bs.table reset-view.bs.table', '.snipe-table', function () { if (this.tagName !== 'TABLE') return; tableTools(this); catalogue(this); });
    $(function () {
        document.querySelectorAll('.main-header a[href]').forEach(link => {
            const path = new URL(link.href, location.href).pathname.replace(/\/$/, '');
            if (path && path !== '/' && (location.pathname === path || location.pathname.startsWith(path + '/')) && link.querySelector('i')) link.setAttribute('aria-current', 'page');
        });
        document.querySelectorAll('.snipe-table').forEach(tableTools);
        document.querySelectorAll('[data-permission-editor]').forEach(permissions);
        cancellationGuard();
        formExperience();
        systemBanners();
        recordNavigation();
        recordDetails();
        document.querySelectorAll('form#custom-report-form').forEach(reportBuilder);
        settingsCategories();
        document.querySelectorAll('table.snipe-table').forEach(catalogue);
    });
    window.addEventListener('resize', () => document.querySelectorAll('.presentation-table').forEach(pinTable));
})(window.jQuery);
