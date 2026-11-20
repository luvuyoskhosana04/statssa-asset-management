(function () {
    'use strict';
    var root = document.getElementById('project-issue-preview');
    if (!root) return;
    var copy = JSON.parse(root.dataset.copy), form = document.getElementById('issue-preview-form');
    var panels = Array.from(root.querySelectorAll('[data-issue-panel]'));
    var steps = Array.from(root.querySelectorAll('.stepper-item'));
    var summary = document.getElementById('issue-error-summary');
    var back = document.getElementById('issue-back'), cancel = document.getElementById('issue-cancel');
    var scan = document.getElementById('issue-asset'), worker = document.getElementById('issue-worker');
    var returnDate = document.getElementById('issue-return');
    var accepted = document.getElementById('issue-accepted'), handedOver = document.getElementById('issue-handed-over');
    var success = document.getElementById('issue-preview-success');
    var step = 0, scanned = '', assetType = '';
    var types = ['type_laptop', 'type_tablet', 'type_monitor', 'type_phone', 'type_desktop'];
    function clearErrors() {
        summary.hidden = true; summary.querySelector('ul').replaceChildren();
        root.querySelectorAll('.project-field-error').forEach(function (node) { node.hidden = true; node.textContent = ''; });
        root.querySelectorAll('[aria-invalid]').forEach(function (node) { node.removeAttribute('aria-invalid'); });
    }
    function showErrors(errors) {
        clearErrors();
        errors.forEach(function (error) {
            var field = document.getElementById(error.id), message = document.getElementById(error.id + '-error');
            field.setAttribute('aria-invalid', 'true'); message.textContent = error.message; message.hidden = false;
            var item = document.createElement('li'), link = document.createElement('a');
            link.href = '#' + error.id; link.textContent = error.message;
            link.addEventListener('click', function (event) { event.preventDefault(); field.focus(); });
            item.appendChild(link); summary.querySelector('ul').appendChild(item);
        });
        summary.hidden = false; summary.focus();
    }
    function updateValues() {
        var date = returnDate.value ? new Date(returnDate.value + 'T12:00:00') : null;
        var values = {worker: worker.value.trim(), asset: scanned, type: assetType,
            returnDate: date && !Number.isNaN(date.getTime()) ? new Intl.DateTimeFormat(document.documentElement.lang || 'en', {dateStyle: 'long'}).format(date) : returnDate.value};
        root.querySelectorAll('[data-issue-value]').forEach(function (node) { node.textContent = values[node.dataset.issueValue] || ''; });
    }
    function addScan() {
        var value = scan.value.trim();
        if (!value) return false;
        if (scanned !== value) {
            scanned = value; assetType = copy[types[Math.floor(Math.random() * types.length)]];
            accepted.checked = false; handedOver.checked = false;
        }
        updateValues();
        document.getElementById('issue-scan-result').hidden = false;
        document.getElementById('issue-scan-status').textContent = copy.scan_added;
        return true;
    }
    function displayStep(index) {
        step = index; clearErrors(); updateValues();
        panels.forEach(function (panel, i) { panel.hidden = i !== step; });
        steps.forEach(function (item, i) {
            item.classList.toggle('is-current', i === step); item.classList.toggle('is-complete', i < step);
            if (i === step) item.setAttribute('aria-current', 'step'); else item.removeAttribute('aria-current');
        });
        back.hidden = step === 0; cancel.hidden = step !== 0;
        if (step === 1) scan.focus(); else panels[step].querySelector('h3').focus();
    }
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var errors = [];
        if (step === 0) {
            if (!worker.value.trim()) errors.push({id: 'issue-worker', message: copy.worker_required});
            if (!returnDate.value) errors.push({id: 'issue-return', message: copy.return_required});
            else if (returnDate.value < returnDate.min || returnDate.value > returnDate.max) errors.push({id: 'issue-return', message: copy.return_within_project});
        }
        if (step === 1 && !addScan()) errors.push({id: 'issue-asset', message: copy.asset_required});
        if (step === 2 && !accepted.checked) errors.push({id: 'issue-accepted', message: copy.acceptance_required});
        if (step === 3 && !handedOver.checked) errors.push({id: 'issue-handed-over', message: copy.handover_required});
        if (errors.length) { showErrors(errors); return; }
        if (step < panels.length - 1) { displayStep(step + 1); return; }
        clearErrors(); updateValues(); form.hidden = true; success.hidden = false;
        steps.forEach(function (item) { item.classList.remove('is-current'); item.classList.add('is-complete'); item.removeAttribute('aria-current'); });
        success.focus();
    });
    scan.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            if (!addScan()) showErrors([{id: 'issue-asset', message: copy.asset_required}]);
            else clearErrors();
        }
    });
    scan.addEventListener('change', addScan);
    scan.addEventListener('input', function () {
        if (scan.value.trim() !== scanned) {
            document.getElementById('issue-scan-result').hidden = true;
            document.getElementById('issue-scan-status').textContent = copy.scan_ready;
        }
    });
    [worker, returnDate].forEach(function (field) {
        field.addEventListener('input', function () { accepted.checked = false; handedOver.checked = false; });
    });
    back.addEventListener('click', function () { if (step > 0) displayStep(step - 1); });
    document.getElementById('issue-restart').addEventListener('click', function () {
        form.reset(); scan.value = ''; worker.value = ''; scanned = ''; assetType = '';
        document.getElementById('issue-scan-result').hidden = true;
        document.getElementById('issue-scan-status').textContent = copy.scan_ready;
        success.hidden = true; form.hidden = false; displayStep(0);
    });
    if (scan.value.trim()) addScan();
})();
