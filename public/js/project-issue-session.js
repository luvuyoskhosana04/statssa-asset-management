(function () {
    'use strict';
    var root = document.getElementById('project-issue-preview'); if (!root) return;
    var copy = JSON.parse(root.dataset.copy), form = document.getElementById('issue-preview-form');
    var worker = document.getElementById('issue-worker'), asset = document.getElementById('issue-asset'), date = document.getElementById('issue-return');
    var next = document.getElementById('issue-next'), back = document.getElementById('issue-back'), cancel = document.getElementById('issue-cancel');
    var summary = document.getElementById('issue-error-summary'), status = document.getElementById('issue-live-status');
    var panels = Array.from(root.querySelectorAll('[data-issue-panel]')), markers = Array.from(root.querySelectorAll('.stepper-item'));
    var step = 0, person = null, equipment = null, busy = false, timers = {};
    var checkedWorker = '', checkedAsset = '';
    var picker = document.getElementById('issue-employee-picker'), workerValue = document.getElementById('issue-worker-value');
    var employeeDirectory = JSON.parse(picker.dataset.employees), employeeMenu = document.getElementById('issue-worker-menu');
    var employeeList = document.getElementById('issue-worker-list'), employeeToggle = document.getElementById('issue-worker-toggle');
    var employeeMatches = [], employeeIndex = -1;
    function normal(value) { return String(value || '').trim().toLocaleLowerCase(); }
    function exactEmployees(query) {
        query = normal(query);
        return employeeDirectory.filter(function (employee) { return [employee.barcode,employee.number,employee.username].some(function (value) { return value && normal(value) === query; }); });
    }
    function closeEmployeePicker() {
        employeeMenu.hidden = true; worker.setAttribute('aria-expanded','false'); employeeToggle.setAttribute('aria-expanded','false');
        worker.removeAttribute('aria-activedescendant'); employeeIndex = -1;
    }
    function highlightEmployee(index) {
        employeeIndex = index;
        Array.from(employeeList.children).forEach(function (node,i) { node.setAttribute('aria-selected',String(i === index)); });
        if (index >= 0 && employeeList.children[index]) {
            worker.setAttribute('aria-activedescendant',employeeList.children[index].id);
            employeeList.children[index].scrollIntoView({block:'nearest'});
        } else worker.removeAttribute('aria-activedescendant');
    }
    function openEmployeePicker() {
        if (busy || step !== 0) return;
        var query = person && checkedWorker === worker.value.trim() ? '' : normal(worker.value);
        var matches = employeeDirectory.filter(function (employee) { return !query || [employee.name,employee.number,employee.barcode,employee.username].some(function (value) { return normal(value).includes(query); }); });
        employeeMatches = matches.slice(0,15); employeeIndex = -1; employeeList.replaceChildren(); worker.removeAttribute('aria-activedescendant');
        employeeMatches.forEach(function (employee,index) {
            var option = document.createElement('li'); option.id = 'issue-worker-option-'+index; option.role = 'option'; option.setAttribute('aria-selected','false'); option.dataset.employeeIndex = index;
            var name = document.createElement('span'); name.textContent = employee.name;
            var number = document.createElement('small'); number.textContent = employee.number || employee.barcode;
            option.append(name,number); employeeList.appendChild(option);
        });
        document.getElementById('issue-worker-results').textContent = matches.length ? copy.employee_picker_count.replace(':count',matches.length) : copy.employee_picker_empty;
        employeeMenu.hidden = false; worker.setAttribute('aria-expanded','true'); employeeToggle.setAttribute('aria-expanded','true');
    }
    function selectEmployee(employee) {
        if (!employee || busy) return;
        clearTimeout(timers.worker); worker.value = employee.barcode; workerValue.value = ''; person = null;
        document.getElementById('issue-accepted').checked = false; document.getElementById('issue-handed-over').checked = false;
        closeEmployeePicker(); lookup('worker');
    }
    worker.addEventListener('focus',openEmployeePicker);
    worker.addEventListener('keydown',function (event) {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault(); if (employeeMenu.hidden) openEmployeePicker();
            if (employeeMatches.length) highlightEmployee(event.key === 'ArrowDown' ? (employeeIndex+1)%employeeMatches.length : (employeeIndex <= 0 ? employeeMatches.length-1 : employeeIndex-1));
        } else if (event.key === 'Escape') { event.preventDefault(); closeEmployeePicker(); }
        else if (event.key === 'Enter' || event.key === 'Tab') {
            if (event.key === 'Tab' && employeeIndex < 0 && exactEmployees(worker.value).length !== 1) { closeEmployeePicker(); return; }
            event.preventDefault();
            if (employeeIndex >= 0) selectEmployee(employeeMatches[employeeIndex]);
            else if (worker.value.trim()) lookup('worker');
        }
    });
    worker.addEventListener('input',function () {
        clearTimeout(timers.worker); person = null; workerValue.value = ''; worker.dataset.fieldVerification = 'pending'; errors([]);
        document.getElementById('issue-accepted').checked = false; document.getElementById('issue-handed-over').checked = false;
        openEmployeePicker();
        timers.worker = setTimeout(function () { if (exactEmployees(worker.value).length === 1) lookup('worker'); },350);
    });
    employeeToggle.addEventListener('mousedown',function (event) { event.preventDefault(); });
    employeeToggle.addEventListener('click',function () { if (employeeMenu.hidden) { worker.focus(); openEmployeePicker(); } else closeEmployeePicker(); });
    employeeList.addEventListener('mousedown',function (event) { event.preventDefault(); });
    employeeList.addEventListener('click',function (event) { var option = event.target.closest('[data-employee-index]'); if (option) selectEmployee(employeeMatches[Number(option.dataset.employeeIndex)]); });
    document.addEventListener('pointerdown',function (event) { if (!picker.contains(event.target)) closeEmployeePicker(); });
    picker.addEventListener('focusout',function (event) { if (!picker.contains(event.relatedTarget)) closeEmployeePicker(); });

    function errors(messages) {
        summary.hidden = !messages.length; summary.querySelector('ul').replaceChildren();
        root.querySelectorAll('[aria-invalid]').forEach(function (node) { node.removeAttribute('aria-invalid'); });
        root.querySelectorAll('.project-field-error').forEach(function (node) { node.hidden = true; });
        messages.forEach(function (entry) {
            var li = document.createElement('li'); li.textContent = entry.text; summary.querySelector('ul').appendChild(li);
            var input = document.getElementById(entry.id), help = document.getElementById(entry.id + '-error');
            if (input) input.setAttribute('aria-invalid', 'true'); if (help) { help.textContent = entry.text; help.hidden = false; }
        });
        if (messages.length) summary.focus();
    }
    function values() {
        var info = Object.assign({}, equipment || {}, {worker: person ? person.name : '', asset: equipment ? equipment.asset_tag : '', returnDate: date.value});
        root.querySelectorAll('[data-issue-value]').forEach(function (node) { var value = info[node.dataset.issueValue]; node.textContent = value === 'not_recorded' || !value ? copy.not_recorded : value; });
    }
    function display(index) {
        if (index !== 0) closeEmployeePicker();
        if (index !== 1) closeAssetPicker();
        step = index; errors([]); values(); status.textContent = index === 1 ? (equipment ? copy.asset_identified : copy.employee_identified) : '';
        panels.forEach(function (panel, i) { panel.hidden = i !== step; });
        markers.forEach(function (marker, i) {
            marker.classList.toggle('is-current', i === step); marker.classList.toggle('is-complete', i < step);
            var indicator = marker.querySelector('.stepper-marker');
            indicator.textContent = i < step ? '✓' : String(i + 1);
            if (i < step) indicator.setAttribute('aria-label', copy.step_completed); else indicator.removeAttribute('aria-label');
            if (i === step) marker.setAttribute('aria-current', 'step'); else marker.removeAttribute('aria-current');
        });
        back.hidden = step === 0; cancel.hidden = step !== 0; next.textContent = step === 3 ? next.dataset.finish : next.dataset.next;
        if (step === 0) worker.focus(); else if (step === 1) asset.focus(); else panels[step].querySelector('h3').focus();
    }
    async function lookup(kind) {
        if (busy) return false;
        clearTimeout(timers[kind]);
        var input = kind === 'worker' ? worker : asset, barcode = input.value.trim();
        if (!barcode) { errors([{id: input.id, text: copy[kind === 'worker' ? 'worker_required' : 'asset_required']}]); return false; }
        if (kind === 'worker' && person && checkedWorker === barcode) return true;
        if (kind === 'asset' && equipment && checkedAsset === barcode) return true;
        if (kind === 'asset') closeAssetPicker();
        busy = true; next.disabled = true; back.disabled = true; employeeToggle.disabled = true; worker.readOnly = true; assetToggle.disabled = true; asset.readOnly = true;
        status.textContent = copy.lookup_busy; errors([]);
        try {
            var response = await fetch(kind === 'worker' ? root.dataset.workerUrl : root.dataset.assetUrl, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json', Accept:'application/json', 'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}, body:JSON.stringify({barcode:barcode})});
            var body = await response.json();
            if (!response.ok || body.status === 'error' || (kind === 'worker' ? !body.name : !body.asset_tag)) {
                var messages = body.errors || body.messages;
                throw new Error(messages && typeof messages === 'object' ? Object.values(messages).flat().join(' ') : body.message || copy.lookup_failed);
            }
            if (input.value.trim() !== barcode) { status.textContent = ''; return false; }
            if (kind === 'worker') { person = body; workerValue.value = body.barcode; worker.value = body.name + ' · ' + (body.employee_number || body.barcode); checkedWorker = worker.value.trim(); status.textContent = copy.employee_identified; display(1); }
            else { equipment = body; checkedAsset = barcode; document.getElementById('issue-scan-result').hidden = false; values(); status.textContent = copy.asset_identified; date.focus(); }
            input.dataset.fieldVerification = 'valid'; input.dispatchEvent(new CustomEvent('field:verified',{bubbles:true}));
            return true;
        } catch (error) { input.dataset.fieldVerification = 'invalid'; errors([{id: input.id, text: error.message || copy.lookup_failed}]); status.textContent = ''; return false; }
        finally { busy = false; next.disabled = false; back.disabled = false; employeeToggle.disabled = false; worker.readOnly = false; assetToggle.disabled = false; asset.readOnly = false; if (kind === 'worker' && step === 1 && !equipment) searchAssets(); }
    }
    var assetPicker = document.getElementById('issue-asset-picker'), assetMenu = document.getElementById('issue-asset-menu');
    var assetList = document.getElementById('issue-asset-list'), assetToggle = document.getElementById('issue-asset-toggle');
    var assetMatches = [], assetIndex = -1, searchSequence = 0, searchTimer;
    function closeAssetPicker() {
        assetMenu.hidden = true; asset.setAttribute('aria-expanded','false'); assetToggle.setAttribute('aria-expanded','false');
        asset.removeAttribute('aria-activedescendant'); assetIndex = -1; searchSequence++; clearTimeout(searchTimer);
    }
    function highlightAsset(index) {
        assetIndex = index;
        Array.from(assetList.children).forEach(function (node,i) { node.setAttribute('aria-selected',String(i === index)); });
        if (index >= 0 && assetList.children[index]) {
            asset.setAttribute('aria-activedescendant',assetList.children[index].id); assetList.children[index].scrollIntoView({block:'nearest'});
        }
    }
    function chooseAsset(item) { if (!item || busy) return; asset.value = item.asset_tag; closeAssetPicker(); lookup('asset'); }
    async function searchAssets() {
        if (busy || step !== 1) return;
        var sequence = ++searchSequence, query = asset.value.trim();
        assetMenu.hidden = false; asset.setAttribute('aria-expanded','true'); assetToggle.setAttribute('aria-expanded','true');
        assetList.replaceChildren(); assetMatches = []; assetIndex = -1; asset.removeAttribute('aria-activedescendant');
        var resultStatus = document.getElementById('issue-asset-results'); resultStatus.textContent = copy.asset_picker_loading;
        try {
            var url = new URL(root.dataset.assetSearchUrl); url.searchParams.set('q', query);
            var response = await fetch(url, {credentials:'same-origin',headers:{Accept:'application/json'}});
            if (!response.ok) throw new Error(copy.lookup_failed);
            var body = await response.json();
            if (body.status === 'error') throw new Error(copy.lookup_failed);
            if (sequence !== searchSequence || step !== 1 || busy || asset.value.trim() !== query) return;
            assetMatches = body.results || [];
            assetMatches.forEach(function (item,index) {
                var option = document.createElement('li'); option.id = 'issue-asset-option-'+index; option.role = 'option'; option.setAttribute('aria-selected','false'); option.dataset.assetIndex = index;
                var name = document.createElement('span'); name.textContent = item.name || item.model || item.asset_tag;
                var identity = document.createElement('small'); identity.textContent = item.asset_tag + (item.serial ? ' · '+item.serial : '');
                option.append(name,identity); assetList.appendChild(option);
            });
            resultStatus.textContent = assetMatches.length ? copy.asset_picker_results : copy.asset_picker_empty;
            var exact = assetMatches.filter(function (item) { return [item.asset_tag,item.serial].some(function (value) { return value && normal(value) === normal(query); }); });
            if (query && exact.length === 1 && !equipment) chooseAsset(exact[0]);
        } catch (error) { if (sequence === searchSequence) resultStatus.textContent = copy.lookup_failed; }
    }
    asset.addEventListener('focus',searchAssets);
    asset.addEventListener('input',function () {
        equipment = null; asset.dataset.fieldVerification = 'pending'; document.getElementById('issue-scan-result').hidden = true; errors([]); status.textContent = '';
        document.getElementById('issue-accepted').checked = false; document.getElementById('issue-handed-over').checked = false;
        clearTimeout(searchTimer); searchSequence++; assetMatches = []; assetIndex = -1; assetList.replaceChildren();
        assetMenu.hidden = false; asset.setAttribute('aria-expanded','true'); assetToggle.setAttribute('aria-expanded','true');
        document.getElementById('issue-asset-results').textContent = copy.asset_picker_loading;
        searchTimer = setTimeout(searchAssets,200);
    });
    asset.addEventListener('keydown',function (event) {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault(); if (assetMenu.hidden) { searchAssets(); return; }
            if (assetMatches.length) highlightAsset(event.key === 'ArrowDown' ? (assetIndex+1)%assetMatches.length : (assetIndex <= 0 ? assetMatches.length-1 : assetIndex-1));
        } else if (event.key === 'Escape') { event.preventDefault(); closeAssetPicker(); }
        else if (event.key === 'Enter' || (event.key === 'Tab' && assetIndex >= 0)) {
            event.preventDefault(); if (assetIndex >= 0) chooseAsset(assetMatches[assetIndex]); else { closeAssetPicker(); lookup('asset'); }
        }
    });
    assetToggle.addEventListener('mousedown',function (event) { event.preventDefault(); });
    assetToggle.addEventListener('click',function () { if (assetMenu.hidden) { asset.focus(); searchAssets(); } else closeAssetPicker(); });
    assetList.addEventListener('mousedown',function (event) { event.preventDefault(); });
    assetList.addEventListener('click',function (event) { var option = event.target.closest('[data-asset-index]'); if (option) chooseAsset(assetMatches[Number(option.dataset.assetIndex)]); });
    document.addEventListener('pointerdown',function (event) { if (!assetPicker.contains(event.target)) closeAssetPicker(); });
    assetPicker.addEventListener('focusout',function (event) { if (!assetPicker.contains(event.relatedTarget)) closeAssetPicker(); });
    date.addEventListener('input', function () { document.getElementById('issue-accepted').checked = false; document.getElementById('issue-handed-over').checked = false; });
    form.addEventListener('submit', async function (event) {
        event.preventDefault(); if (busy) return;
        if (step === 0) { if (await lookup('worker') && step === 0) display(1); return; }
        if (step === 1) {
            if (!await lookup('asset')) return;
            if (!date.value || date.value < date.min || date.value > date.max) { errors([{id:'issue-return', text:date.value ? copy.return_within_project : copy.return_required}]); return; }
        }
        if (step === 2 && !document.getElementById('issue-accepted').checked) { errors([{id:'issue-accepted',text:copy.acceptance_required}]); return; }
        if (step === 3 && !document.getElementById('issue-handed-over').checked) { errors([{id:'issue-handed-over',text:copy.handover_required}]); return; }
        if (step < 3) display(step + 1);
        else { next.disabled = true; back.disabled = true; busy = true; form.submit(); }
    });
    back.addEventListener('click', function () { if (!busy && step > 0) display(step - 1); });
    worker.focus();
})();
