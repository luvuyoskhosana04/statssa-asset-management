(function () {
    'use strict';
    const root = document.getElementById('bulk-session');
    if (!root) return;
    const copy = JSON.parse(root.dataset.copy);
    const form = root.querySelector('form');
    const el = id => document.getElementById(id);
    const val = id => el(id)?.value || null;
    const selectedText = id => el(id)?.selectedOptions?.[0]?.textContent?.trim() || '';
    const states = Object.fromEntries(['user', 'location', 'project'].map(mode => [mode, {items: [], source: 'any', office: '', order: '', supplier: ''}]));
    let mode = 'user', picker = null, stock = [], busy = false, scanning = false, completed = false, generation = 0, timer;
    const items = () => states[mode].items;
    const message = data => Object.values(data.errors || (typeof data.messages === 'object' && data.messages) || {}).flat().join(' ') || (typeof data.messages === 'string' ? data.messages : '') || copy.save_error;
    const key = row => `${row.kind}:${row.id}`;
    const feedback = (message, error = false) => {
        el('bulk-feedback').textContent = message || '';
        el('bulk-feedback').hidden = !message;
        el('bulk-feedback').classList.toggle('is-error', error);
    };
    const targetId = () => mode === 'user' ? form.querySelector('[name="assigned_user"]')?.value : val(mode === 'location' ? 'bulk-destination' : 'bulk-project');
    const targetName = () => mode === 'user' ? form.querySelector('[name="assigned_user"]')?.selectedOptions?.[0]?.textContent?.trim() : selectedText(mode === 'location' ? 'bulk-destination' : 'bulk-project');
    const sourceReady = () => (val('bulk-source-type') !== 'order' || val('bulk-source-order')?.trim()) && (val('bulk-source-type') !== 'supplier' || val('bulk-source-supplier')) && (mode !== 'location' || val('bulk-source-office'));
    function sourceParams() {
        return {source_type: val('bulk-source-type'), source_location_id: val('bulk-source-office'), order_number: val('bulk-source-order')?.trim(), supplier_id: val('bulk-source-supplier')};
    }
    function summary() {
        const ready = !!(targetId() && sourceReady() && items().length && el('bulk-reviewed').checked);
        el('bulk-review-state').textContent = ready ? copy.ready_to_confirm : items().length ? copy.review_items : copy.draft;
        el('bulk-review-state').classList.toggle('is-ready', ready);
        root.querySelector('.bulk-recipient-card').classList.toggle('has-recipient', !!targetId());
        el('bulk-summary-action').textContent = copy.actions[mode];
        el('bulk-summary-target').textContent = targetId() ? targetName() : copy.empty_targets[mode];
        el('bulk-assets-count').textContent = items().filter(row => row.kind === 'asset').length;
        el('bulk-accessories-count').textContent = items().filter(row => row.kind === 'accessory').reduce((sum, row) => sum + row.quantity, 0);
        el('bulk-session-count').textContent = items().length;
        el('bulk-session-empty').hidden = items().length > 0;
        el('bulk-current-assignments').hidden = !targetId();
        el('bulk-reviewed').closest('label').hidden = !items().length;
        el('bulk-clear').hidden = !items().length;
        el('bulk-finalize').textContent = busy ? copy.saving : copy.actions[mode];
        el('bulk-next-action').textContent = !targetId() ? copy.next_recipient : !sourceReady() ? copy.next_source : !items().length ? copy.next_items : !el('bulk-reviewed').checked ? copy.next_review : copy.next_ready;
        el('bulk-reviewed').disabled = busy || !targetId() || !sourceReady() || !items().length;
        el('bulk-clear').disabled = busy || !items().length;
        el('bulk-finalize').disabled = busy || !items().length || !targetId() || !sourceReady() || !el('bulk-reviewed').checked;
        scheduleReviewFit();
    }
    let contextGeneration = 0;
    async function assignments() {
        const current = ++contextGeneration;
        const container = el('bulk-assignment-rows'); container.replaceChildren(node('p', targetId() ? copy.loading : copy.choose_target));
        if (!targetId()) return;
        const url = new URL(root.dataset.context, location.href); url.searchParams.set('mode', mode); url.searchParams.set('target_id', targetId());
        try {
            const response = await fetch(url, {headers: {Accept: 'application/json'}}); const data = await response.json();
            if (current !== contextGeneration) return;
            if (!response.ok || data.status !== 'success') throw new Error(message(data));
            container.replaceChildren();
            if (!data.payload.rows.length) container.append(node('p', copy.none_assigned));
            data.payload.rows.forEach(row => { const line = node('div', null, 'bulk-assignment'); line.append(node('span', copy[row.kind]), node('strong', [row.name, row.tag].filter(Boolean).join(' · ')), node('span', row.quantity)); container.append(line); });
        } catch (error) { if (current === contextGeneration) container.replaceChildren(node('p', error.message)); }
    }
    el('bulk-current-assignments').addEventListener('toggle', () => { if (el('bulk-current-assignments').open) assignments(); });
    function changed() { el('bulk-reviewed').checked = false; summary(); }
    function node(tag, text, className) {
        const element = document.createElement(tag);
        if (text !== undefined && text !== null) element.textContent = text;
        if (className) element.className = className;
        return element;
    }
    function itemSelect(row, name, options, labelText) {
        const label = node('label', labelText);
        const select = node('select', null, 'form-control');
        select.setAttribute('data-no-field-ui', '');
        select.setAttribute('aria-label', `${labelText}: ${row.name}`);
        select.append(new Option(copy.inherit, ''));
        Object.entries(options).forEach(([value, text]) => { if (value) select.append(new Option(text, value)); });
        select.value = row[name] || '';
        select.addEventListener('change', () => { row[name] = select.value || null; changed(); });
        label.append(select);
        return label;
    }
    function renderQueue() {
        const list = el('bulk-session-list');
        list.replaceChildren();
        items().forEach(row => {
            const li = node('li', null, 'bulk-session-item');
            const main = node('div', null, 'bulk-item-main');
            const description = node('div', null, 'bulk-item-description');
            description.append(node('strong', row.name), node('small', [row.tag, row.location].filter(Boolean).join(' · ')));
            main.append(node('span', copy[row.kind], 'bulk-item-kind'), description);
            const controls = node('div', null, 'bulk-item-actions');
            if (row.kind === 'accessory') {
                const label = node('label', copy.quantity);
                const input = node('input', null, 'form-control');
                Object.assign(input, {type: 'number', min: '1', max: String(Math.min(row.remaining, 500)), step: '1', value: String(row.quantity)});
                input.setAttribute('data-no-field-ui', '');
                input.setAttribute('aria-label', `${copy.quantity}: ${row.name}`);
                input.addEventListener('input', () => {
                    const amount = Number(input.value);
                    if (Number.isInteger(amount) && amount >= 1 && amount <= row.remaining && amount <= 500) {
                        row.quantity = amount; changed(); renderStock();
                    }
                });
                input.addEventListener('change', () => {
                    const amount = Number(input.value);
                    if (!Number.isInteger(amount) || amount < 1 || amount > row.remaining || amount > 500) { input.value = row.quantity; feedback(copy.quantity_exceeded, true); return; }
                    row.quantity = amount; changed(); renderStock();
                });
                label.append(input); controls.append(label);
            } else controls.append(node('span', copy.quantity + ': 1', 'bulk-unit-count'));
            const remove = node('button', copy.remove, 'btn btn-default');
            remove.type = 'button'; remove.setAttribute('aria-label', `${copy.remove}: ${row.name}`);
            remove.addEventListener('click', () => { states[mode].items = items().filter(item => key(item) !== key(row)); changed(); renderQueue(); renderStock(); });
            controls.append(remove); main.append(controls); li.append(main);
            const overrides = node('details', null, 'bulk-item-overrides');
            overrides.append(node('summary', copy.item_defaults));
            const fields = node('div', null, 'bulk-item-defaults');
            if (mode === 'user' && row.kind === 'asset') fields.append(itemSelect(row, 'status_id', Object.fromEntries(Array.from(el('bulk-status').options).map(option => [option.value, option.text])), copy.status));
            fields.append(itemSelect(row, 'condition', copy.conditions, copy.condition));
            if (row.serial) overrides.append(node('p', copy.serial_number + ': ' + row.serial, 'bulk-item-serial'));
            overrides.append(fields); li.append(overrides); list.append(li);
        });
        summary();
    }
    function add(row, quantity = 1) {
        const existing = items().find(item => key(item) === key(row));
        if (row.kind === 'asset' && existing) { feedback(copy.duplicate, true); return; }
        const amount = (existing?.quantity || 0) + quantity;
        if (!Number.isInteger(quantity) || quantity < 1 || amount > row.remaining || amount > 500 || (!existing && items().length >= 200)) { feedback(copy.quantity_exceeded, true); return; }
        if (existing) existing.quantity = amount;
        else items().push({...row, quantity});
        feedback(copy.added_item.replace(':name', row.name)); changed(); renderQueue(); renderStock();
    }
    function renderStock() {
        const body = el('bulk-stock-rows'); body.replaceChildren();
        if (!stock.length) { const tr = node('tr'); const td = node('td', copy.no_matches); td.colSpan = 4; tr.append(td); body.append(tr); return; }
        stock.forEach(row => {
            const tr = node('tr'); const cell = node('td');
            cell.append(node('strong', row.name), node('small', [row.tag, row.category, row.location].filter(Boolean).join(' · '))); tr.append(cell);
            const reserved = items().find(item => key(item) === key(row))?.quantity || 0;
            const availability = node('td', null, 'bulk-stock-availability');
            availability.append(node('strong', copy.available_units.replace(':count', row.remaining)), node('small', copy.stock_totals.replace(':total', row.total).replace(':allocated', row.checked_out)));
            if (reserved) availability.append(node('small', copy.after_selected.replace(':count', Math.max(0, row.remaining - reserved))));
            tr.append(availability);
            const quantityCell = node('td'); const quantity = node('input', null, 'form-control');
            Object.assign(quantity, {type: 'number', min: '1', max: String(Math.min(500, row.remaining - reserved)), value: '1', step: '1'});
            quantity.disabled = row.kind === 'asset' || row.remaining <= reserved;
            quantity.setAttribute('data-no-field-ui', '');
            quantity.setAttribute('aria-label', `${copy.quantity}: ${row.name}`); quantityCell.append(quantity);
            const actionCell = node('td'); const button = node('button', row.kind === 'asset' && reserved ? copy.selected : copy.add, 'btn btn-default'); button.type = 'button'; button.disabled = row.remaining <= reserved;
            tr.classList.toggle('is-selected', reserved > 0);
            button.setAttribute('aria-label', `${copy.add}: ${row.name}`); button.addEventListener('click', () => add(row, Number(quantity.value)));
            actionCell.append(button); tr.append(quantityCell, actionCell); body.append(tr);
        });
    }
    async function request(params) {
        const url = new URL(root.dataset.catalog, location.href);
        Object.entries({...sourceParams(), ...params}).forEach(([name, value]) => { if (value !== null && value !== '') url.searchParams.set(name, value); });
        const response = await fetch(url, {headers: {Accept: 'application/json'}, credentials: 'same-origin'});
        const data = await response.json();
        if (!response.ok || data.status !== 'success') throw new Error(message(data));
        return data.payload.rows;
    }
    async function loadStock() {
        const current = ++generation;
        if (!picker || !sourceReady()) { stock = []; renderStock(); if (picker) feedback(copy.choose_source, true); return; }
        feedback(copy.loading);
        try { const rows = await request({kind: picker, q: val('bulk-stock-search')}); if (current !== generation) return; stock = rows; renderStock(); feedback(null); }
        catch (error) { if (current === generation) { stock = []; renderStock(); feedback(error.message, true); } }
    }
    async function scan() {
        if (busy || scanning || !val('bulk-scan')?.trim()) return;
        if (!sourceReady()) { feedback(copy.choose_source, true); return; }
        const scanMode = mode, source = JSON.stringify(sourceParams());
        const button = el('bulk-scan-add'); scanning = true; button.disabled = true;
        el('bulk-scan').setAttribute('aria-busy', 'true'); feedback(copy.scan_checking);
        try { const rows = await request({scan: val('bulk-scan').trim()}); if (scanMode !== mode || source !== JSON.stringify(sourceParams())) return; add(rows[0]); el('bulk-scan').value = ''; }
        catch (error) { feedback(error.message, true); }
        finally { scanning = false; button.disabled = busy; el('bulk-scan').removeAttribute('aria-busy'); el('bulk-scan').focus(); }
    }
    function sourceUI() {
        el('bulk-order-wrap').hidden = val('bulk-source-type') !== 'order';
        el('bulk-supplier-wrap').hidden = val('bulk-source-type') !== 'supplier';
        const sourceName = val('bulk-source-type') === 'order' ? val('bulk-source-order') || copy.sources.order : val('bulk-source-type') === 'supplier' ? (val('bulk-source-supplier') ? selectedText('bulk-source-supplier') : copy.sources.supplier) : copy.sources.any;
        el('bulk-source-summary').textContent = sourceName + (val('bulk-source-office') ? ' · ' + selectedText('bulk-source-office') : '');
    }
    function switchMode(next) {
        if (busy || next === mode || !Object.hasOwn(states, next)) return;
        Object.assign(states[mode], {source: val('bulk-source-type'), office: val('bulk-source-office'), order: val('bulk-source-order'), supplier: val('bulk-source-supplier')});
        mode = next; generation++;
        const state = states[mode]; if (mode === 'project' && state.source === 'any') state.source = 'order';
        el('bulk-source-type').value = state.source; el('bulk-source-office').value = state.office || ''; el('bulk-source-order').value = state.order || ''; el('bulk-source-supplier').value = state.supplier || '';
        el('bulk-source-type').querySelector('[value="any"]').disabled = mode === 'project';
        el('bulk-source-office').required = mode === 'location';
        el('bulk-stock-scope').open = mode !== 'user';
        el('bulk-recipient-type').value = mode;
        root.querySelectorAll('[data-target]').forEach(section => {
            const active = section.dataset.target === mode; section.hidden = !active;
            section.querySelectorAll('select,input').forEach(control => { control.disabled = !active; control.required = active; });
        });
        el('bulk-user-project').hidden = mode !== 'user'; el('bulk-status-wrap').hidden = mode !== 'user'; el('bulk-fixed-status').hidden = mode === 'user'; el('bulk-return-wrap').hidden = mode !== 'user';
        el('bulk-recipient-heading').textContent = copy.headings[mode]; el('bulk-mode-hint').textContent = copy.hints[mode];
        sourceUI(); changed(); renderQueue(); feedback(null); assignments(); if (picker) loadStock();
    }
    el('bulk-recipient-type').addEventListener('change', event => switchMode(event.target.value));
    root.querySelectorAll('[data-picker]').forEach(button => button.addEventListener('click', () => {
        clearTimeout(timer);
        picker = picker === button.dataset.picker ? null : button.dataset.picker;
        el('bulk-stock-picker').hidden = !picker;
        root.querySelector('.bulk-picker-heading h3').textContent = picker === 'accessory' ? copy.accessory_stock : copy.asset_stock;
        root.querySelectorAll('[data-picker]').forEach(item => { const active = item.dataset.picker === picker; item.setAttribute('aria-expanded', String(active)); item.classList.toggle('is-open', active); });
        sourceUI(); if (picker) loadStock();
    }));
    el('bulk-stock-search').addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(loadStock, 250); });
    ['bulk-source-type', 'bulk-source-office', 'bulk-source-order', 'bulk-source-supplier'].forEach(id => el(id).addEventListener('change', () => { sourceUI(); changed(); if (items().length) feedback(copy.changed_source); if (picker) loadStock(); }));
    el('bulk-scan-add').addEventListener('click', scan);
    form.addEventListener('keydown', event => { if (event.key === 'Enter' && event.target.tagName === 'INPUT') { event.preventDefault(); if (event.target.id === 'bulk-scan') scan(); } });
    form.addEventListener('input', event => { if (event.target.id !== 'bulk-reviewed') changed(); });
    form.addEventListener('change', event => { if (event.target.id !== 'bulk-reviewed') changed(); else summary(); });
    if (window.jQuery) window.jQuery(form).on('change', '[name="assigned_user"]', () => { changed(); assignments(); });
    ['bulk-destination', 'bulk-project'].forEach(id => el(id).addEventListener('change', assignments));
    el('bulk-clear').addEventListener('click', () => {
        if (busy || !items().length) return;
        feedback(copy.clear_confirm);
        const actions = node('div', null, 'bulk-picker-actions');
        const confirm = node('button', copy.confirm_clear, 'btn btn-default'); confirm.type = 'button';
        const cancel = node('button', copy.cancel, 'btn btn-default'); cancel.type = 'button';
        confirm.addEventListener('click', () => { states[mode].items = []; changed(); renderQueue(); renderStock(); feedback(null); el('bulk-scan').focus(); });
        cancel.addEventListener('click', () => { feedback(null); el('bulk-clear').focus(); });
        actions.append(confirm, cancel); el('bulk-feedback').append(actions); confirm.focus();
    });
    form.addEventListener('submit', async event => {
        event.preventDefault(); if (el('bulk-finalize').disabled || busy) return;
        const payload = {...sourceParams(), mode, target_id: Number(targetId()), project_id: mode === 'user' ? val('bulk-allocation') : null,
            items: items().map(row => ({kind: row.kind, id: row.id, quantity: row.quantity, status_id: row.status_id || null, condition: row.condition || null})),
            status_id: mode === 'user' ? val('bulk-status') : null, condition: val('bulk-condition'), checkout_at: form.querySelector('[name="checkout_at"]')?.value || null,
            expected_checkin: mode === 'user' ? form.querySelector('[name="expected_checkin"]')?.value || null : null, note: form.querySelector('[name="note"]')?.value || null,
            reviewed: true, idempotency_key: form.querySelector('[name="idempotency_key"]').value};
        busy = true; summary(); feedback(copy.saving);
        const controls = Array.from(form.querySelectorAll('input,select,textarea,button')).filter(control => !control.disabled); controls.forEach(control => { control.disabled = true; });
        try {
            const response = await fetch(root.dataset.submit, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value}, body: JSON.stringify(payload)});
            const data = await response.json();
            if (!response.ok || data.status !== 'success') throw new Error(message(data));
            completed = true; root.querySelectorAll('.bulk-recipient-card,.bulk-items-card,.bulk-review-card').forEach(card => { card.hidden = true; });
            el('bulk-completed').hidden = false; el('bulk-success-message').textContent = data.messages || copy.saved[mode]; el('bulk-success-target').textContent = data.payload.target;
            el('bulk-receipt-link').href = data.payload.receipt_url; el('bulk-success-message').tabIndex = -1; el('bulk-success-message').focus();
            form.dataset.dirty = 'false'; feedback(null);
        } catch (error) { feedback(error.message || copy.save_error, true); el('bulk-feedback').scrollIntoView({block: 'center', behavior: 'smooth'}); }
        finally { busy = false; controls.forEach(control => { control.disabled = false; }); summary(); }
    });
    window.addEventListener('beforeunload', event => { if (!completed && Object.values(states).some(state => state.items.length)) { event.preventDefault(); event.returnValue = ''; } });
    let reviewFrame = null;
    function scheduleReviewFit() {
        if (reviewFrame !== null) return;
        reviewFrame = requestAnimationFrame(() => {
            reviewFrame = null;
            const card = root.querySelector('.bulk-review-card');
            if (getComputedStyle(card).position !== 'sticky') { card.style.maxHeight = ''; return; }
            const top = Math.max(82, card.getBoundingClientRect().top);
            card.style.maxHeight = Math.max(320, window.innerHeight - top - 20) + 'px';
        });
    }
    window.addEventListener('scroll', scheduleReviewFit, {passive: true});
    window.addEventListener('resize', scheduleReviewFit, {passive: true});
    new ResizeObserver(scheduleReviewFit).observe(root);
    sourceUI(); summary();
})();
