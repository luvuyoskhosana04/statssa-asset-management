(function () {
    'use strict';
    document.querySelectorAll('[data-project-time]').forEach(function (element) {
        var date = new Date(element.dateTime);
        if (!Number.isNaN(date.getTime())) element.textContent = new Intl.DateTimeFormat(document.documentElement.lang || 'en', {dateStyle: 'medium', timeStyle: 'short'}).format(date);
    });
    if (document.getElementById('project-issue-preview')) return;
    var worker = document.getElementById('issue-worker'), asset = document.getElementById('issue-asset');
    if (worker && asset) worker.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') { event.preventDefault(); asset.focus(); }
    });
    if (asset) asset.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') { event.preventDefault(); document.getElementById('issue-return').focus(); }
    });
})();

/* Display text is inserted through DOM nodes, never interpolated into markup. */
(function () {
    function link(row, label, cls) {
        var a = document.createElement('a'); a.href = row.view_url; a.textContent = label || ''; if (cls) a.className = cls; return a;
    }
    window.projectNameFormatter = function (value, row) {
        return link(row, value).outerHTML;
    };
    window.projectStatusFormatter = function (value, row) {
        var span = document.createElement('span'); span.className = 'project-status';
        if (['active','planned','suspended','ended'].includes(row.status_key)) span.classList.add('project-status-'+row.status_key);
        span.textContent = value || ''; return span.outerHTML;
    };
    window.projectTextFormatter = function (value) { var span = document.createElement('span'); span.textContent = value || ''; return span.innerHTML; };
    window.projectViewFormatter = function (_, row) { return link(row,(window.SnipePresentation || {}).project_view || 'View project','btn btn-default btn-sm').outerHTML; };
})();
