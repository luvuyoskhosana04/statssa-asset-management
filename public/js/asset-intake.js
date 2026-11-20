(function () {
    'use strict';
    var root = document.getElementById('receiving-workspace');
    if (!root || !root.classList.contains('barcode-intake')) return;
    var copy = JSON.parse(root.dataset.copy), models = JSON.parse(root.dataset.models);
    var items = [], key = crypto.randomUUID(), busy = false, leaving = false, idle = null, rapid = 0, lastInput = 0, controls = [], savedSnapshot = '', storageBlocked = false;
    var el = function (name) { return document.getElementById('receive-' + name); };
    var replace = function (text, values) { return Object.keys(values).reduce(function (out, name) { return out.replace(':' + name, values[name]); }, text); };
    function message(text, failed) {
        el('message').textContent = text; el('message').hidden = !text;
        el('message').className = 'alert alert-' + (failed ? 'danger' : 'info');
        el('message').setAttribute('role', failed ? 'alert' : 'status');
    }
    function draft() { return {version: 1, request_key: key, company_id: el('company').value, items: items.map(function (item) { return {asset_tag: item.asset_tag, model_id: item.model_id}; })}; }
    function dirty() { return !!el('barcode').value.trim() || (items.length > 0 && JSON.stringify(draft()) !== savedSnapshot); }
    function sync() {
        root.querySelectorAll('.receive-save-batch').forEach(function (button) { button.disabled = busy || !items.length; button.textContent = copy.save; });
        document.querySelectorAll('.receive-save-later').forEach(function (button) { button.disabled = busy || (!items.length && !el('barcode').value.trim()); });
        el('count').textContent = replace(copy.receive_draft_count, {count: items.length});
    }
    function rowModelSelect(item) {
        var select = document.createElement('select'); select.className = 'form-control'; select.dataset.field = 'model_id'; select.required = true;
        select.setAttribute('aria-label', replace(copy.model_for, {barcode: item.asset_tag}));
        var option = document.createElement('option'); option.value = ''; option.textContent = copy.choose_model; select.appendChild(option);
        models.forEach(function (model) { var option = document.createElement('option'); option.value = model.id; option.textContent = model.name; select.appendChild(option); });
        select.value = item.model_id || ''; return select;
    }
    function draw() {
        el('log').replaceChildren();
        items.forEach(function (item, index) {
            var row = document.createElement('tr'); row.dataset.index = index;
            var cell = document.createElement('th'); cell.scope = 'row';
            var tag = document.createElement('input'); tag.className = 'form-control'; tag.value = item.asset_tag; tag.dataset.field = 'asset_tag'; tag.maxLength = 255; tag.required = true;
            tag.setAttribute('aria-label', replace(copy.barcode_for, {number: index + 1})); cell.appendChild(tag); row.appendChild(cell);
            row.insertCell().appendChild(rowModelSelect(item));
            var remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn btn-danger btn-sm';
            remove.dataset.remove = index; remove.textContent = copy.receive_remove_item; remove.setAttribute('aria-label', replace(copy.remove_for, {barcode: item.asset_tag}));
            row.insertCell().appendChild(remove); el('log').appendChild(row);
        });
        el('empty').hidden = items.length > 0; el('results').hidden = !items.length; sync();
    }
    function barcodeError(barcode, ignoreIndex) {
        if (!barcode) return copy.empty_barcode;
        if (barcode.length > 255 || /[\u0000-\u001f\u007f]/.test(barcode)) return copy.invalid_barcode;
        if (items.some(function (item, index) { return index !== ignoreIndex && item.asset_tag.trim().toLocaleLowerCase() === barcode.toLocaleLowerCase(); })) return replace(copy.duplicate, {barcode: barcode});
        return '';
    }
    function addBarcode(raw, modelId, manual) {
        if (busy) return false;
        clearTimeout(idle); idle = null; rapid = 0; lastInput = 0;
        var barcode = raw.trim(), error = barcodeError(barcode);
        if (items.length >= 200) error = copy.receive_batch_limit;
        if (error) {
            if (manual) { el('manual-error').textContent = error; el('manual-error').hidden = false; el('manual-barcode').setAttribute('aria-invalid', 'true'); el('manual-barcode').focus(); }
            else { message(error, true); el('barcode').value = ''; el('barcode').focus(); }
            return false;
        }
        items.push({asset_tag: barcode, model_id: modelId || null});
        if (!manual) el('barcode').value = '';
        draw(); message(replace(manual ? copy.manual_added : copy.added, {barcode: barcode}), false);
        if (!manual) el('barcode').focus();
        return true;
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
    function scannerState(ready) { el('resume').textContent = ready ? copy.scan_ready : copy.scan_resume; root.classList.toggle('is-scanning', ready); }
    el('barcode').addEventListener('focus', function () { scannerState(true); });
    el('barcode').addEventListener('blur', function () { scannerState(false); });
    el('resume').addEventListener('click', function () { if (!busy) el('barcode').focus(); });
    el('log').addEventListener('input', function (event) {
        var row = event.target.closest('tr'), field = event.target.dataset.field; if (!row || busy || !field) return;
        items[Number(row.dataset.index)][field] = field === 'model_id' ? (event.target.value ? Number(event.target.value) : null) : event.target.value;
        row.classList.remove('has-error'); row.querySelectorAll('.receipt-row-error').forEach(function (node) { node.remove(); }); event.target.removeAttribute('aria-invalid'); event.target.removeAttribute('aria-describedby');
        if (field === 'asset_tag') {
            var item = items[Number(row.dataset.index)];
            row.querySelector('select').setAttribute('aria-label', replace(copy.model_for, {barcode: item.asset_tag}));
            row.querySelector('[data-remove]').setAttribute('aria-label', replace(copy.remove_for, {barcode: item.asset_tag}));
        }
        message('', false); sync();
    });
    el('log').addEventListener('change', function (event) {
        var row = event.target.closest('tr'); if (!row || busy) return;
        var item = items[Number(row.dataset.index)];
        if (event.target.dataset.field === 'model_id') {
            item.model_id = event.target.value ? Number(event.target.value) : null;
            row.classList.remove('has-error'); row.querySelectorAll('.receipt-row-error').forEach(function (node) { node.remove(); });
            event.target.removeAttribute('aria-invalid'); event.target.removeAttribute('aria-describedby'); message('', false);
        }
        if (event.target.dataset.field === 'asset_tag') {
            item.asset_tag = event.target.value.trim(); event.target.value = item.asset_tag;
            row.querySelector('select').setAttribute('aria-label', replace(copy.model_for, {barcode: item.asset_tag}));
            row.querySelector('[data-remove]').setAttribute('aria-label', replace(copy.remove_for, {barcode: item.asset_tag}));
        }
    });
    el('log').addEventListener('click', function (event) {
        var button = event.target.closest('[data-remove]'); if (!button || busy) return;
        var index = Number(button.dataset.remove), barcode = items[index].asset_tag;
        items.splice(index, 1); draw(); message(replace(copy.removed, {barcode: barcode}), false);
        var next = el('log').querySelector('[data-index="' + Math.min(index, items.length - 1) + '"] input');
        (next || el('barcode')).focus();
    });
    el('manual-open').addEventListener('click', function () {
        if (busy) return;
        el('manual-form').reset(); el('manual-error').hidden = true; el('manual-barcode').removeAttribute('aria-invalid');
        $('#receive-manual-modal').modal('show');
    });
    $('#receive-manual-modal').on('shown.bs.modal', function () { el('manual-barcode').focus(); }).on('hidden.bs.modal', function () { el('barcode').focus(); });
    el('manual-form').addEventListener('submit', function (event) {
        event.preventDefault();
        if (addBarcode(el('manual-barcode').value, el('manual-model').value ? Number(el('manual-model').value) : null, true)) $('#receive-manual-modal').modal('hide');
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
    function capturePending() { return !el('barcode').value.trim() || addBarcode(el('barcode').value); }
    el('intake-form').addEventListener('keydown', function (event) { if (event.key === 'Enter' && event.target.tagName === 'INPUT') event.preventDefault(); });
    el('intake-form').addEventListener('submit', async function (event) {
        event.preventDefault(); if (busy || !capturePending() || !items.length) return;
        root.querySelectorAll('.receipt-row-error').forEach(function (node) { node.remove(); });
        el('log').querySelectorAll('[aria-invalid]').forEach(function (node) { node.removeAttribute('aria-invalid'); });
        el('log').querySelectorAll('.has-error').forEach(function (node) { node.classList.remove('has-error'); });
        var first = null;
        items.forEach(function (item, index) {
            var error = barcodeError(item.asset_tag.trim(), index), field = 'asset_tag';
            if (!error && !item.model_id) { error = copy.model_required; field = 'model_id'; }
            if (error) { rowError(index, field, error); if (!first) first = {index: index, field: field, error: error}; }
        });
        if (first) { message(first.error, true); el('log').querySelector('[data-index="' + first.index + '"] [data-field="' + first.field + '"]').focus(); return; }
        if (!el('status').value) { message(copy.missing_status, true); return; }
        if (!this.reportValidity()) return;
        var body = {request_key: key, status_id: el('status').value, company_id: el('company').value || null,
            items: items.map(function (item) { return {asset_tag: item.asset_tag.trim(), model_id: item.model_id}; })};
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
    function saveLater() {
        if (busy || !capturePending()) return;
        if (storageBlocked) { message(copy.draft_corrupt, true); return; }
        try {
            var stored = JSON.stringify(draft()); localStorage.setItem(root.dataset.draftKey, stored); savedSnapshot = stored;
            leaving = true; window.location.assign(root.dataset.return);
        } catch (error) {
            message(copy.draft_storage_error, true); el('draft-save-error').textContent = copy.draft_storage_error; el('draft-save-error').hidden = false;
        }
    }
    document.querySelectorAll('.receive-save-later').forEach(function (button) { button.addEventListener('click', saveLater); });
    el('cancel').addEventListener('click', function (event) {
        if (busy) { event.preventDefault(); return; }
        if (items.length || el('barcode').value.trim() || savedSnapshot || storageBlocked) { event.preventDefault(); el('draft-save-error').hidden = true; $('#receive-discard-modal').modal('show'); }
    });
    el('discard').addEventListener('click', function () {
        try { localStorage.removeItem(root.dataset.draftKey); }
        catch (error) { el('draft-save-error').textContent = copy.draft_storage_error; el('draft-save-error').hidden = false; return; }
        leaving = true; window.location.assign(root.dataset.return);
    });
    window.addEventListener('beforeunload', function (event) {
        if (!leaving && (dirty() || (savedSnapshot && !items.length))) { event.preventDefault(); event.returnValue = ''; }
    });
    try {
        var stored = localStorage.getItem(root.dataset.draftKey);
        if (stored) {
            var restored = JSON.parse(stored);
            if (restored.version !== 1 || !Array.isArray(restored.items) || restored.items.length > 200 || !/^[0-9a-f-]{36}$/i.test(restored.request_key) || restored.items.some(function (item) { return !item || typeof item.asset_tag !== 'string' || item.asset_tag.length > 255 || (item.model_id !== null && !Number.isInteger(item.model_id)); })) throw new Error('draft');
            key = restored.request_key; items = restored.items; el('company').value = restored.company_id || '';
            items.forEach(function (item) { if (item.model_id && !models.some(function (model) { return model.id === item.model_id; })) item.model_id = null; });
            savedSnapshot = JSON.stringify(draft()); message(copy.draft_restored, false);
        }
    } catch (error) { storageBlocked = true; message(copy.draft_corrupt, true); }
    draw(); el('barcode').focus();
})();
