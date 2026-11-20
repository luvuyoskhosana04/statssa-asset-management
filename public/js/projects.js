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
