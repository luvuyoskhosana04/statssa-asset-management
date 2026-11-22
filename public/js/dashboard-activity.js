(function () {
    'use strict';
    var root = document.getElementById('dashboard-activity');
    if (!root) return;
    var copy = JSON.parse(root.dataset.copy), list = root.querySelector('ol');
    var search = document.getElementById('dashboard-activity-search');
    var refresh = document.getElementById('dashboard-activity-refresh');
    var more = document.getElementById('dashboard-activity-more');
    var retry = document.getElementById('dashboard-activity-retry');
    var message = document.getElementById('dashboard-activity-message');
    var number = new Intl.NumberFormat(document.documentElement.lang || 'en');
    var shown = 0, request = null, sequence = 0, debounce, lastReset = true;
    var destinations = {asset: 'hardware', accessory: 'accessories', component: 'components', consumable: 'consumables', license: 'licenses', user: 'users', location: 'locations', maintenance: 'maintenances', model: 'models', supplier: 'suppliers', department: 'departments', company: 'companies'};
    function plain(value) {
        var parsed = new DOMParser().parseFromString(String(value || ''), 'text/html');
        parsed.querySelectorAll('br').forEach(function (br) { br.replaceWith('\n'); });
        return (parsed.body.textContent || '').trim();
    }
    function record(value, type, fallback) {
        var destination = value && destinations[type || value.type];
        var canLink = destination && value.viewable === true && Number.isInteger(value.id) && value.id > 0;
        var element = document.createElement(canLink ? 'a' : 'span');
        element.textContent = plain(value && value.name) || fallback;
        if (canLink) element.href = root.dataset.baseUrl + '/' + destination + '/' + value.id;
        return element;
    }
    function eventRow(row) {
        var item = document.createElement('li'); item.className = 'dashboard-activity-event'; item.tabIndex = -1;
        var avatar = document.createElement('span'); avatar.className = 'activity-avatar'; avatar.setAttribute('aria-hidden', 'true');
        var actorName = plain(row.admin && row.admin.name) || copy.activity_unknown_actor;
        avatar.textContent = actorName.split(/\s+/).slice(0, 2).map(function (word) { return word.charAt(0); }).join('').toLocaleUpperCase();
        var content = document.createElement('div'); content.className = 'activity-event-content';
        var context = document.createElement('div'); context.className = 'activity-event-context';
        var action = document.createElement('span'); action.className = 'activity-event-action'; action.textContent = plain(row.action_type);
        context.append(action, record(row.admin, 'user', copy.activity_unknown_actor));
        var subject = document.createElement('div'); subject.className = 'activity-event-subject'; subject.appendChild(record(row.item, null, copy.activity_unknown_item));
        content.append(context, subject);
        if (row.target) {
            var target = document.createElement('p'), label = document.createElement('span'); target.className = 'activity-event-target'; label.textContent = copy.activity_target + ' ';
            target.append(label, record(row.target, null, copy.activity_unknown_item)); content.appendChild(target);
        }
        var note = plain(row.note);
        if (note) { var notes = document.createElement('p'); notes.className = 'activity-event-note'; notes.textContent = note; content.appendChild(notes); }
        var time = document.createElement('time'); time.className = 'activity-event-time'; time.textContent = row.created_at && row.created_at.formatted || '';
        item.append(avatar, content, time);
        return item;
    }
    async function load(reset, focusNew) {
        if (request) request.abort();
        request = new AbortController(); var current = ++sequence; lastReset = reset;
        root.setAttribute('aria-busy', 'true'); more.disabled = true; refresh.disabled = true; retry.hidden = true;
        message.textContent = copy.activity_loading;
        var url = new URL(root.dataset.url, location.href);
        url.searchParams.set('limit', '8'); url.searchParams.set('offset', reset ? '0' : String(shown));
        if (search.value.trim()) url.searchParams.set('search', search.value.trim());
        try {
            var response = await fetch(url, {signal: request.signal, credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}});
            if (!response.ok) throw new Error();
            var data = await response.json();
            if (!Array.isArray(data.rows) || !Number.isFinite(Number(data.total))) throw new Error();
            if (current !== sequence) return;
            if (reset) { list.replaceChildren(); shown = 0; }
            var firstNew;
            data.rows.forEach(function (row) { var element = eventRow(row); if (!firstNew) firstNew = element; list.appendChild(element); });
            shown += data.rows.length;
            message.textContent = shown ? copy.activity_count.replace(':shown', number.format(shown)).replace(':total', number.format(data.total)) : (search.value.trim() ? copy.activity_search_empty : copy.activity_empty);
            more.hidden = shown >= Number(data.total) || !data.rows.length;
            if (focusNew && firstNew) firstNew.focus({preventScroll: false});
        } catch (error) {
            if (error.name === 'AbortError' || current !== sequence) return;
            message.textContent = copy.activity_error; retry.hidden = false; more.hidden = true;
        } finally {
            if (current === sequence) { root.removeAttribute('aria-busy'); more.disabled = false; refresh.disabled = false; }
        }
    }
    search.addEventListener('input', function () { clearTimeout(debounce); if (request) request.abort(); ++sequence; debounce = setTimeout(function () { load(true); }, 300); });
    refresh.addEventListener('click', function () { clearTimeout(debounce); load(true); });
    retry.addEventListener('click', function () { load(lastReset); });
    more.addEventListener('click', function () { load(false, true); });
    load(true);
})();
