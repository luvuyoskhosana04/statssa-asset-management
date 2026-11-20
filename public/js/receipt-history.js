(function () {
    'use strict';
    var formatter = new Intl.DateTimeFormat(document.documentElement.lang || 'en', {year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', timeZoneName: 'short'});
    document.querySelectorAll('time.receipt-local-time').forEach(function (node) {
        var date = new Date(node.dateTime);
        if (!Number.isNaN(date.getTime())) node.textContent = formatter.format(date);
    });
})();
