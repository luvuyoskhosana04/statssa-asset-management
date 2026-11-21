(function () {
    'use strict';
    var root = document.getElementById('receiving-workspace');
    if (!root || !root.classList.contains('barcode-intake')) return;
    var copy = JSON.parse(root.dataset.copy), modelIds = JSON.parse(root.dataset.modelIds);
    var items = [], activeId = null, key = crypto.randomUUID(), busy = false, leaving = false, idle = null, rapid = 0, lastInput = 0, controls = [], savedSnapshot = '', storageBlocked = false, pending = 0, epoch = 0, scanQueue = Promise.resolve();
    var el = function (name) { return document.getElementById('receive-' + name); };
    var replace = function (text, values) { return Object.keys(values).reduce(function (out, name) { return out.replace(':' + name, values[name]); }, text); };
    var scannerInput = el('barcode');
    function focusScanner() { scannerInput.focus({preventScroll: !active()}); }
    function active() { return items.find(function (item) { return item.id === activeId && item.stage === 'pending'; }); }
    function message(text, failed) {
        el('message').textContent = text; el('message').hidden = !text;
        el('message').className = 'alert alert-' + (failed ? 'danger' : 'info');
        el('message').setAttribute('role', failed ? 'alert' : 'status');
    }
    function draft() { return {version: 2, request_key: key, company_id: el('company').value, items: items}; }
    function dirty() { return !!el('barcode').value.trim() || (items.length > 0 && JSON.stringify(draft()) !== savedSnapshot); }
    function sync() {
        root.querySelectorAll('.receive-save-batch').forEach(function (button) { button.disabled = busy || pending > 0 || !items.length; button.textContent = copy.save; });
        document.querySelectorAll('.receive-save-later').forEach(function (button) { button.disabled = busy || pending > 0 || (!items.length && !el('barcode').value.trim()); });
        root.querySelectorAll('[data-item-action], #receive-manual-open, #receive-pending-log select, #receive-pending-log input:not(#receive-barcode)').forEach(function (node) { node.disabled = busy || pending > 0; });
        el('count').textContent = replace(copy.received_count, {count: items.filter(function (item) { return item.stage === 'received'; }).length});
        el('pending-count').textContent = replace(copy.pending_count, {count: items.filter(function (item) { return item.stage === 'pending'; }).length});
        el('scan-label').textContent = active() ? replace(copy.tag_prompt, {barcode: active().serial}) : copy.original_prompt;
        scannerInput.placeholder = active() ? copy.tag_scan_placeholder : '';
        scannerInput.setAttribute('aria-label', active() ? replace(copy.tag_prompt, {barcode: active().serial}) : copy.original_prompt);
    }
    function button(cell, item, action, label, danger) {
        var node = document.createElement('button'); node.type = 'button'; node.className = 'btn btn-' + (danger ? 'danger' : 'default') + ' btn-sm';
        node.dataset.itemAction = action; node.dataset.id = item.id; node.textContent = label;
        node.setAttribute('aria-label', label + ': ' + item.serial); cell.appendChild(node);
    }
    function draw() {
        if (!active()) activeId = (items.find(function (item) { return item.stage === 'pending'; }) || {}).id || null;
        el('scanner-dock').appendChild(scannerInput); scannerInput.className = 'sr-only';
        el('log').replaceChildren(); el('pending-log').replaceChildren();
        items.forEach(function (item, index) {
            var waiting = item.stage === 'pending', row = document.createElement('tr'); row.dataset.index = index; row.dataset.id = item.id;
            if (waiting && item.id === activeId) row.className = 'intake-active-item';
            var cell = document.createElement('th'); cell.scope = 'row'; cell.textContent = item.serial; row.appendChild(cell);
            if (waiting) {
                cell.textContent = '';
                var serialInput = document.createElement('input'); serialInput.className = 'form-control'; serialInput.maxLength = 255;
                serialInput.value = item.serial; serialInput.dataset.field = 'serial'; serialInput.setAttribute('aria-label', copy.barcode + ': ' + item.serial); cell.appendChild(serialInput);
                if (item.asset_tag) { var hint = document.createElement('small'); hint.className = 'help-block'; hint.textContent = copy.tag_label + ': ' + item.asset_tag + '. ' + copy.replace_tag; cell.appendChild(hint); }
                var tagCell = row.insertCell();
                if (item.id === activeId) { scannerInput.className = 'form-control intake-row-tag'; tagCell.appendChild(scannerInput); }
                else {
                    var tagInput = document.createElement('input'); tagInput.className = 'form-control intake-row-tag'; tagInput.readOnly = true;
                    tagInput.placeholder = copy.tag_scan_placeholder; tagInput.dataset.tagTarget = item.id;
                    tagInput.setAttribute('aria-label', replace(copy.tag_prompt, {barcode: item.serial})); tagCell.appendChild(tagInput);
                }
                var actions = row.insertCell();
                if (item.id !== activeId) button(actions, item, 'select', copy.select_tag);
                if (item.asset_tag) button(actions, item, 'complete', copy.update_received);
                button(actions, item, 'remove', copy.receive_remove_item, true);
                el('pending-log').appendChild(row);
            } else {
                row.insertCell().textContent = item.asset_tag;
                button(row.insertCell(), item, 'review', copy.review);
                el('log').appendChild(row);
            }
        });
        var waiting = items.filter(function (item) { return item.stage === 'pending'; }).length;
        el('pending-empty').hidden = waiting > 0; el('pending-results').hidden = !waiting;
        el('empty').hidden = items.length > waiting; el('results').hidden = items.length === waiting; sync();
    }
    function barcodeError(barcode, target) {
        if (!barcode) return copy.empty_barcode;
        if (barcode.length > 255 || /[\u0000-\u001f\u007f]/.test(barcode)) return copy.invalid_barcode;
        if (target && target.serial.toLowerCase() === barcode.toLowerCase()) return copy.same_barcode;
        if (items.some(function (item) { return item.serial.toLowerCase() === barcode.toLowerCase() || (item.id !== (target || {}).id && item.asset_tag.toLowerCase() === barcode.toLowerCase()); })) return replace(copy.duplicate, {barcode: barcode});
        return '';
    }
    function scanFailure(error, manual) {
        epoch++; message(error, true); scannerInput.setAttribute('aria-invalid', 'true');
        if (manual) { el('manual-error').textContent = error; el('manual-error').hidden = false; el('manual-barcode').setAttribute('aria-invalid', 'true'); el('manual-barcode').focus(); }
        else if (!document.querySelector('.modal.in')) focusScanner();
    }
    function addBarcode(raw, modelId, manual) {
        if (busy) return Promise.resolve(false);
        clearTimeout(idle); idle = null; rapid = 0; lastInput = 0;
        var barcode = raw.trim(), queuedEpoch = epoch;
        if (!manual) el('barcode').value = '';
        pending++; sync();
        var task = scanQueue.then(async function () {
            if (queuedEpoch !== epoch) return false;
            var target = manual ? null : active(), error = barcodeError(barcode, target);
            if (!target && items.length >= 200) error = copy.receive_batch_limit;
            if (error) { scanFailure(error, manual); return false; }
            message(copy.checking, false);
            try {
                var response = await fetch(root.dataset.check, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body: JSON.stringify({barcode: barcode})});
                var result = await response.json();
                if (!response.ok || result.status !== 'success' || typeof result.payload.available !== 'boolean') throw new Error('check');
                if (!result.payload.available) { scanFailure(replace(copy.exists, {barcode: barcode}), manual); return false; }
                if (target) {
                    target.asset_tag = barcode; target.stage = 'received'; activeId = null;
                    message(replace(copy.tagged, {barcode: barcode}), false);
                } else {
                    var item = {id: crypto.randomUUID(), serial: barcode, asset_tag: '', model_id: modelId || null, stage: 'pending'};
                    items.push(item); activeId = item.id;
                    message(replace(manual ? copy.manual_added : copy.added, {barcode: barcode}), false);
                }
                scannerInput.removeAttribute('aria-invalid'); draw();
                if (!manual && !document.querySelector('.modal.in')) focusScanner();
                return true;
            } catch (error) { scanFailure(copy.check_failed, manual); return false; }
        });
        scanQueue = task.finally(function () { pending--; sync(); }); return scanQueue;
    }
    el('barcode').addEventListener('keydown', function (event) {
        if ((event.key === 'Enter' || event.key === 'Tab') && this.value.trim()) { event.preventDefault(); addBarcode(this.value); }
    });
    el('barcode').addEventListener('input', function (event) {
        clearTimeout(idle); sync();
        var now = performance.now(); rapid = now - lastInput < 100 ? rapid + 1 : 1; lastInput = now;
        if (rapid >= 3 || (event.data && event.data.length > 1) || event.inputType === 'insertFromPaste') idle = setTimeout(function () { addBarcode(el('barcode').value); }, 350);
    });
    el('barcode').addEventListener('paste', function (event) {
        var text = event.clipboardData && event.clipboardData.getData('text'); if (!text) return;
        event.preventDefault(); text.split(/\r?\n/).filter(function (line) { return line.trim(); }).forEach(function (line) { addBarcode(line); });
    });
    el('barcode').addEventListener('focus', function () { root.classList.add('is-scanning'); });
    el('barcode').addEventListener('blur', function () { root.classList.remove('is-scanning'); });
    el('resume').addEventListener('click', function () { if (!busy) focusScanner(); });
    async function updatePendingField(event) {
        if (busy || pending || !event.target.dataset.field) return;
        var input = event.target, item = items.find(function (item) { return item.id === input.closest('tr').dataset.id; });
        var barcode = input.value.trim();
        if (barcode === item.serial) return;
        var error = !barcode || barcode.length > 255 || /[\u0000-\u001f\u007f]/.test(barcode) ? copy.invalid_barcode : '';
        if (!error && items.some(function (other) { return (other.id !== item.id && other.serial.toLowerCase() === barcode.toLowerCase()) || other.asset_tag.toLowerCase() === barcode.toLowerCase(); })) error = replace(copy.duplicate, {barcode: barcode});
        if (error) { input.value = item.serial; message(error, true); return; }
        setBusy(true);
        try {
            var response = await fetch(root.dataset.check, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body: JSON.stringify({barcode: barcode})});
            var result = await response.json();
            if (!response.ok || result.status !== 'success' || typeof result.payload.available !== 'boolean') throw new Error('check');
            if (!result.payload.available) { input.value = item.serial; message(replace(copy.exists, {barcode: barcode}), true); }
            else { item.serial = barcode; message('', false); }
        } catch (error) { input.value = item.serial; message(copy.check_failed, true); }
        setBusy(false); draw(); focusScanner();
    }
    el('pending-log').addEventListener('focusin', function (event) {
        if (!event.target.dataset.tagTarget || busy || pending) return;
        activeId = event.target.dataset.tagTarget; scannerInput.value = ''; draw(); focusScanner();
    });
    el('pending-log').addEventListener('change', updatePendingField);
    el('pending-log').addEventListener('focusout', function (event) {
        if (event.target.dataset.field === 'serial') updatePendingField(event);
    });
    function itemAction(event) {
        var node = event.target.closest('[data-item-action]'); if (!node || busy || pending) return;
        var item = items.find(function (item) { return item.id === node.dataset.id; }); if (!item) return;
        if (node.dataset.itemAction === 'remove') items.splice(items.indexOf(item), 1);
        else if (node.dataset.itemAction === 'complete') { item.stage = 'received'; activeId = null; }
        else { item.stage = 'pending'; activeId = item.id; }
        draw(); message('', false); focusScanner();
    }
    el('pending-log').addEventListener('click', itemAction); el('log').addEventListener('click', itemAction);
    el('manual-open').addEventListener('click', function () {
        if (busy || pending) return;
        el('manual-form').reset(); el('manual-error').hidden = true; el('manual-barcode').removeAttribute('aria-invalid');
        $('#receive-manual-modal').modal('show');
    });
    $('#receive-manual-modal').on('shown.bs.modal', function () { el('manual-barcode').focus(); }).on('hidden.bs.modal', function () { focusScanner(); });
    el('manual-form').addEventListener('submit', async function (event) {
        event.preventDefault(); if (pending) return;
        if (await addBarcode(el('manual-barcode').value, null, true)) $('#receive-manual-modal').modal('hide');
    });
    function setBusy(state) {
        busy = state; root.setAttribute('aria-busy', String(state));
        if (state) controls = Array.from(root.querySelectorAll('input,select,textarea,button')).map(function (node) { var original = {node: node, disabled: node.disabled}; node.disabled = true; return original; });
        else controls.forEach(function (original) { original.node.disabled = original.disabled; });
        sync();
    }
    function rowError(index, field, text) {
        var row = el('log').querySelector('[data-index="' + index + '"]'); if (!row) return;
        row.classList.add('has-error');
        var input = row.querySelector('[data-field="' + field + '"]'); if (input) input.setAttribute('aria-invalid', 'true');
        var note = document.createElement('p'); note.id = 'receive-row-error-' + index + '-' + field; note.className = 'receipt-row-error text-danger'; note.textContent = text; row.lastElementChild.appendChild(note);
        if (input) input.setAttribute('aria-describedby', note.id);
    }
    async function capturePending() { await scanQueue; return !el('barcode').value.trim() || await addBarcode(el('barcode').value); }
    el('intake-form').addEventListener('keydown', function (event) { if (event.key === 'Enter' && event.target.tagName === 'INPUT') event.preventDefault(); });
    el('intake-form').addEventListener('submit', async function (event) {
        event.preventDefault(); if (busy || !await capturePending() || !items.length) return;
        if (items.some(function (item) { return item.stage !== 'received'; })) { message(copy.unfinished, true); focusScanner(); return; }
        if (!el('status').value) { message(copy.missing_status, true); return; }
        if (!this.reportValidity()) return;
        var body = {intake_mode: 'two_scan', request_key: key, status_id: el('status').value, company_id: el('company').value || null,
            items: items.map(function (item) { return {serial: item.serial, asset_tag: item.asset_tag, model_id: item.model_id}; })};
        setBusy(true); message(copy.saving, false);
        try {
            var response = await fetch(root.dataset.store, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body: JSON.stringify(body)});
            var result = await response.json();
            if (!response.ok || result.status !== 'success') {
                var fields = result.errors || result.messages;
                if (fields && typeof fields === 'object') {
                    message(Object.values(fields).flat().join(' '), true);
                    Object.keys(fields).forEach(function (path) {
                        var match = path.match(/^items\.(\d+)\.(\w+)/);
                        if (match) rowError(Number(match[1]), match[2], [].concat(fields[path]).join(' '));
                        else if (path === 'company_id') el('company').setAttribute('aria-invalid', 'true');
                    });
                } else message(typeof fields === 'string' ? fields : result.message || copy.receive_error, true);
                setBusy(false); return;
            }
            try { localStorage.removeItem(root.dataset.draftKey); } catch (error) { /* A confirmed receipt must still open if browser storage is unavailable. */ }
            leaving = true; items = []; window.location.assign(result.payload.history_url);
        } catch (error) { message(copy.retry, true); setBusy(false); }
    });
    async function saveLater() {
        if (busy || !await capturePending()) return;
        if (storageBlocked) { message(copy.draft_corrupt, true); return; }
        setBusy(true);
        try {
            var response = await fetch(root.dataset.draftStore, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body: JSON.stringify(draft())});
            var result = await response.json();
            if (!response.ok || result.status !== 'success') throw new Error('save');
            savedSnapshot = JSON.stringify(draft());
            try { localStorage.removeItem(root.dataset.draftKey); } catch (error) { /* The account copy is saved. */ }
            leaving = true; window.location.assign(result.payload.history_url);
        } catch (error) {
            message(copy.draft_storage_error, true); el('draft-save-error').textContent = copy.draft_storage_error; el('draft-save-error').hidden = false; setBusy(false);
        }
    }
    document.querySelectorAll('.receive-save-later').forEach(function (button) { button.addEventListener('click', saveLater); });
    el('cancel').addEventListener('click', function (event) {
        if (busy || pending) { event.preventDefault(); return; }
        if (items.length || el('barcode').value.trim() || savedSnapshot || storageBlocked) { event.preventDefault(); el('draft-save-error').hidden = true; $('#receive-discard-modal').modal('show'); }
    });
    el('discard').addEventListener('click', async function () {
        try {
            localStorage.removeItem(root.dataset.draftKey);
        }
        catch (error) { el('draft-save-error').textContent = copy.draft_storage_error; el('draft-save-error').hidden = false; return; }
        leaving = true; window.location.assign(root.dataset.return);
    });
    window.addEventListener('beforeunload', function (event) {
        if (!leaving && (pending > 0 || dirty() || (savedSnapshot && !items.length))) { event.preventDefault(); event.returnValue = ''; }
    });
    try {
        var serverDraft = JSON.parse(root.dataset.savedDraft || 'null');
        var stored = serverDraft ? JSON.stringify(serverDraft) : localStorage.getItem(root.dataset.draftKey);
        if (stored) {
            var restored = JSON.parse(stored);
            if (![1, 2].includes(restored.version) || !Array.isArray(restored.items) || restored.items.length > 200 || !/^[0-9a-f-]{36}$/i.test(restored.request_key) || restored.items.some(function (item) { return !item || typeof item.asset_tag !== 'string' || item.asset_tag.length > 255 || (item.model_id !== null && !Number.isInteger(item.model_id)); })) throw new Error('draft');
            key = restored.request_key; items = restored.items.map(function (item) {
                if (restored.version === 1) return {id: crypto.randomUUID(), serial: item.asset_tag, asset_tag: '', model_id: item.model_id, stage: 'pending'};
                if (typeof item.serial !== 'string' || !item.serial || item.serial.length > 255 || !['pending', 'received'].includes(item.stage) || (item.stage === 'received' && !item.asset_tag)) throw new Error('draft');
                return {id: crypto.randomUUID(), serial: item.serial, asset_tag: item.asset_tag, model_id: item.model_id, stage: item.stage};
            }); el('company').value = restored.company_id || '';
            items.forEach(function (item) { if (item.model_id && !modelIds.includes(item.model_id)) item.model_id = null; });
            savedSnapshot = JSON.stringify(draft()); message(restored.version === 1 ? copy.legacy_restored : copy.draft_restored, false);
        }
    } catch (error) { if (stored) { storageBlocked = true; message(copy.draft_corrupt, true); } }
    draw(); focusScanner();
})();
