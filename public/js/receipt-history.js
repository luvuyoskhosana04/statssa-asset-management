(function () {
    'use strict';
    var formatter = new Intl.DateTimeFormat(document.documentElement.lang || 'en', {year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', timeZoneName: 'short'});
    document.querySelectorAll('time.receipt-local-time').forEach(function (node) {
        var date = new Date(node.dateTime);
        if (!Number.isNaN(date.getTime())) node.textContent = formatter.format(date);
    });
    var hub = document.getElementById('receiving-drafts-hub'), recovery = document.getElementById('receiving-recovery');
    if (hub && recovery) {
        var stored = null;
        try { stored = localStorage.getItem(hub.dataset.localKey); } catch (error) { /* Server drafts remain available without browser storage. */ }
        if (stored) {
            recovery.hidden = false;
            document.getElementById('receiving-recover').addEventListener('click', async function () {
                this.disabled = true;
                try {
                    var draft = JSON.parse(stored);
                    var response = await fetch(hub.dataset.draftStore, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body: JSON.stringify(draft)});
                    var result = await response.json();
                    if (!response.ok || result.status !== 'success') throw new Error(result.messages ? Object.values(result.messages).flat().join(' ') : result.message);
                    try { localStorage.removeItem(hub.dataset.localKey); } catch (error) { /* The server copy is already saved. */ }
                    window.location.assign(result.payload.history_url);
                } catch (error) {
                    recovery.className = 'alert alert-danger';
                    document.getElementById('receiving-recovery-message').textContent = error.message;
                    this.disabled = false;
                }
            });
        }
    }
    var deleteUrl = '', deleting = false, deleteTrigger = null;
    document.querySelectorAll('.receiving-delete-draft').forEach(function (button) {
        button.addEventListener('click', function () {
            deleteUrl = button.dataset.deleteUrl; deleteTrigger = button;
            document.getElementById('receiving-delete-reference').textContent = button.dataset.reference;
            document.getElementById('receiving-delete-error').hidden = true;
            $('#receiving-delete-modal').modal('show');
        });
    });
    var confirmDelete = document.getElementById('receiving-delete-confirm');
    if (confirmDelete) confirmDelete.addEventListener('click', async function () {
        if (deleting || !deleteUrl) return;
        deleting = true; confirmDelete.disabled = true;
        try {
            var response = await fetch(deleteUrl, {method: 'DELETE', credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}});
            var result = await response.json();
            if (!response.ok || result.status !== 'success') throw new Error('delete');
            window.location.reload();
        } catch (error) {
            document.getElementById('receiving-delete-error').hidden = false;
            deleting = false; confirmDelete.disabled = false;
        }
    });
    $('#receiving-delete-modal').on('hide.bs.modal', function (event) { if (deleting) event.preventDefault(); })
        .on('hidden.bs.modal', function () { if (deleteTrigger) deleteTrigger.focus(); });
})();
