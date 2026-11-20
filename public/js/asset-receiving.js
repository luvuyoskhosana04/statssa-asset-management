(function () {
    'use strict';
    var root = document.getElementById('receiving-workspace');
    if (!root) return;
    var copy = JSON.parse(root.dataset.copy), asset = null, batch = null, busy = false, submitted = false;
    var items = [], key = crypto.randomUUID(), controls = [];
    var el = function (name) { return document.getElementById('receive-' + name); };
    var replace = function (text, values) { return Object.keys(values).reduce(function (out, key) { return out.replace(':' + key, values[key]); }, text); };
    function message(text, failed) {
        el('message').textContent = text;
        el('message').className = 'alert alert-' + (failed ? 'danger' : 'info');
        el('message').hidden = !text;
        el('message').setAttribute('role', failed ? 'alert' : 'status');
    }
    function syncControls() {
        root.querySelectorAll('.receive-save-batch').forEach(function (button) {
            button.disabled = busy || !batch || !!asset || !items.length;
            button.textContent = replace(copy.receive_save_count, {count: items.length});
        });
        el('count').textContent = replace(copy.receive_draft_count, {count: items.length});
    }
    function setBusy(state, saving) {
        busy = state;
        if (state) {
            controls = Array.from(root.querySelectorAll('input,select,textarea,button')).map(function (node) { var entry = {node: node, disabled: node.disabled}; node.disabled = true; return entry; });
        } else controls.forEach(function (entry) { entry.node.disabled = entry.disabled; });
        root.setAttribute('aria-busy', String(state));
        el('progress').textContent = state ? (saving ? copy.receive_saving_batch : copy.receive_busy) : '';
        syncControls();
    }
    function step(index) {
        root.querySelectorAll('[data-receive-step]').forEach(function (node) {
            var n = Number(node.dataset.receiveStep);
            node.classList.toggle('is-current', n === index); node.classList.toggle('is-complete', n < index);
            if (n === index) node.setAttribute('aria-current', 'step'); else node.removeAttribute('aria-current');
        });
    }
    function noteRequired(condition) { return el('note').dataset.alwaysRequired === '1' || ['damaged', 'faulty', 'missing_parts'].indexOf(condition) !== -1; }
    function reset() {
        asset = null; el('serial-form').hidden = false; el('asset-form').hidden = true;
        el('serial').value = ''; el('tag').value = ''; el('note').value = '';
        if (batch) el('condition').value = batch.condition;
        el('note').required = noteRequired(el('condition').value); step(1); syncControls();
        el('serial').focus();
    }
    function draftRows() {
        el('log').replaceChildren();
        items.forEach(function (item, index) {
            var row = document.createElement('tr'); row.dataset.index = index;
            var identity = document.createElement('th'); identity.scope = 'row'; var record = document.createElement('a'); record.href = root.dataset.record + '/' + item.asset_id; record.target = '_blank'; record.rel = 'noopener'; record.textContent = item.model || item.asset_tag; identity.appendChild(record);
            var serial = document.createElement('small'); serial.className = 'receipt-subtext'; serial.textContent = item.serial; identity.appendChild(serial); row.appendChild(identity);
            var tag = document.createElement('input'); tag.className = 'form-control'; tag.value = item.asset_tag; tag.required = true; tag.maxLength = 255; tag.dataset.field = 'asset_tag'; tag.setAttribute('aria-label', replace(copy.receive_barcode_for, {serial: item.serial}));
            row.insertCell().appendChild(tag);
            var condition = document.createElement('select'); condition.className = 'form-control'; condition.dataset.field = 'condition'; condition.setAttribute('aria-label', replace(copy.receive_condition_for, {serial: item.serial}));
            Array.from(el('condition').options).forEach(function (option) { condition.appendChild(option.cloneNode(true)); }); condition.value = item.condition;
            row.insertCell().appendChild(condition);
            var note = document.createElement('textarea'); note.className = 'form-control'; note.rows = 1; note.maxLength = 5000; note.value = item.note; note.dataset.field = 'note'; note.placeholder = el('note').placeholder; note.required = noteRequired(item.condition); note.setAttribute('aria-label', replace(copy.receive_note_for, {serial: item.serial}));
            row.insertCell().appendChild(note);
            var remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn btn-danger btn-sm'; remove.dataset.remove = index; remove.textContent = copy.receive_remove_item;
            row.insertCell().appendChild(remove); el('log').appendChild(row);
        });
        el('empty').hidden = !!items.length; el('results').hidden = !items.length; syncControls();
    }
    async function post(url, body) {
        var response, result;
        try {
            response = await fetch(url, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body: JSON.stringify(body)});
            result = await response.json();
        } catch (error) { throw new Error(copy.receive_error); }
        if (!response.ok || result.status !== 'success') {
            var fields = result.errors || result.messages;
            var error = new Error(fields && typeof fields === 'object' ? Object.values(fields).flat().join(' ') : result.message || fields || copy.receive_error);
            error.fields = fields; throw error;
        }
        return result.payload;
    }
    el('batch').addEventListener('submit', function (event) {
        event.preventDefault(); if (busy || !this.reportValidity()) return;
        batch = {order_number: el('order').value, status_id: el('status').value, location_id: el('location').value || null, condition: el('batch-condition').value};
        this.querySelectorAll('select').forEach(function (node) { node.disabled = true; });
        el('scan-fields').disabled = false; el('setup-help').hidden = true; el('start').hidden = true; el('change').hidden = false; reset();
    });
    el('change').addEventListener('click', function () {
        if (busy) return;
        asset = null; batch = null; el('batch').querySelectorAll('select').forEach(function (node) { node.disabled = false; });
        el('order').disabled = items.length > 0;
        el('scan-fields').disabled = true; el('setup-help').hidden = false; el('start').hidden = false; el('change').hidden = true; reset(); el('status').focus();
    });
    el('serial-form').addEventListener('submit', async function (event) {
        event.preventDefault(); if (busy || !batch || !this.reportValidity()) return;
        if (items.length >= 200) { message(copy.receive_batch_limit, true); return; }
        setBusy(true);
        try {
            var match = await post(root.dataset.lookup, {serial: el('serial').value.trim(), order_number: batch.order_number, for_batch: true});
            if (items.some(function (item) { return item.asset_id === match.id; })) throw new Error(copy.receive_duplicate_draft);
            asset = match; el('match').textContent = asset.serial + ' · ' + (asset.model || '') + ' · ' + asset.asset_tag;
            el('serial-form').hidden = true; el('asset-form').hidden = false; el('condition').value = batch.condition; el('note').required = noteRequired(batch.condition); step(2); message(copy.receive_matched, false);
        } catch (error) { message(error.message || copy.receive_error, true); }
        finally { setBusy(false); if (asset) el('tag').focus(); else { el('serial').focus(); el('serial').select(); } }
    });
    el('asset-form').addEventListener('submit', function (event) {
        event.preventDefault(); el('note').required = noteRequired(el('condition').value);
        if (busy || !asset || !this.reportValidity()) return;
        var tag = el('tag').value.trim();
        if (!tag) { el('tag').value = ''; el('tag').reportValidity(); return; }
        if (items.some(function (item) { return item.asset_id === asset.id || item.asset_tag.toLocaleLowerCase() === tag.toLocaleLowerCase(); })) { message(copy.receive_duplicate_draft, true); return; }
        items.push({asset_id: asset.id, serial: asset.serial, model: asset.model, previous_tag: asset.asset_tag, asset_tag: tag, condition: el('condition').value, note: el('note').value.trim(), receipt_token: asset.receipt_token});
        draftRows(); reset(); message(copy.receive_added_draft, false);
    });
    el('log').addEventListener('input', function (event) {
        var field = event.target.dataset.field, row = event.target.closest('tr'); if (!field || !row || busy) return;
        var item = items[Number(row.dataset.index)]; item[field] = event.target.value;
        if (field === 'condition') row.querySelector('[data-field="note"]').required = noteRequired(item.condition);
        event.target.removeAttribute('aria-invalid'); row.classList.remove('has-error'); row.querySelectorAll('.receipt-row-error').forEach(function (node) { node.remove(); });
    });
    el('log').addEventListener('click', function (event) {
        var button = event.target.closest('[data-remove]'); if (!button || busy) return;
        items.splice(Number(button.dataset.remove), 1); draftRows(); message('', false);
    });
    el('batch-save-form').addEventListener('keydown', function (event) { if (event.key === 'Enter' && event.target.tagName === 'INPUT') event.preventDefault(); });
    el('batch-save-form').addEventListener('submit', async function (event) {
        event.preventDefault(); if (busy || !batch || asset || !items.length || !this.reportValidity()) return;
        var tags = items.map(function (item) { return item.asset_tag.trim().toLocaleLowerCase(); });
        if (new Set(tags).size !== tags.length) { message(copy.receive_duplicate_draft, true); return; }
        setBusy(true, true);
        try {
            var result = await post(root.dataset.store, {request_key: key, order_number: batch.order_number, status_id: batch.status_id, location_id: batch.location_id, items: items.map(function (item) { return {asset_id: item.asset_id, asset_tag: item.asset_tag.trim(), condition: item.condition, note: item.note.trim(), receipt_token: item.receipt_token}; })});
            items = []; asset = null; key = crypto.randomUUID(); submitted = false; setBusy(false); draftRows(); window.location.assign(result.history_url);
        } catch (error) {
            message(error.fields ? error.message : copy.receive_retry_save, true);
            if (error.fields && typeof error.fields === 'object') Object.keys(error.fields).forEach(function (path) {
                var match = path.match(/^items\.(\d+)\.(\w+)/); if (!match) return;
                var row = el('log').querySelector('[data-index="' + match[1] + '"]'); if (!row) return;
                row.classList.add('has-error'); var input = row.querySelector('[data-field="' + match[2] + '"]'); if (input) input.setAttribute('aria-invalid', 'true');
                var note = document.createElement('p'); note.className = 'receipt-row-error text-danger'; note.textContent = [].concat(error.fields[path]).join(' '); row.lastElementChild.appendChild(note);
            });
            setBusy(false);
        }
    });
    el('condition').addEventListener('change', function () { el('note').required = noteRequired(this.value); });
    el('tag').addEventListener('input', function () { step(this.value.trim() ? 3 : 2); });
    el('skip').addEventListener('click', function () { if (!busy) { message('', false); reset(); } });
    el('cancel').addEventListener('click', function (event) { if (busy) { event.preventDefault(); return; } if (items.length) { event.preventDefault(); $('#receive-discard-modal').modal('show'); } });
    el('discard').addEventListener('click', function () { submitted = true; });
    window.addEventListener('beforeunload', function (event) { if (items.length && !submitted) { event.preventDefault(); event.returnValue = ''; } });
    draftRows();
})();
