(function () {
    'use strict';

    function currentHash() {
        try { return decodeURIComponent(location.hash.slice(1)); } catch (error) { return ''; }
    }

    function enhance(form, formIndex) {
        var body = form.querySelector('.box-body');
        if (!body || form.closest('.modal') || form.dataset.settingsTabbed) return;
        var fieldsets = Array.from(body.querySelectorAll('fieldset')).filter(function (fieldset) {
            return !fieldset.parentElement.closest('fieldset') && !fieldset.closest('.modal');
        });
        var panels = [];
        fieldsets.forEach(function (fieldset) {
            var legends = Array.from(fieldset.children).filter(function (child) { return child.tagName === 'LEGEND'; });
            if (!legends.length) return;
            if (legends.length === 1) {
                panels.push({element: fieldset, legend: legends[0]});
                return;
            }
            // Preserve the original fieldset, including its disabled state and selectors.
            var current;
            Array.from(fieldset.childNodes).forEach(function (child) {
                if (child.nodeType === 1 && child.tagName === 'LEGEND') {
                    current = document.createElement('section');
                    fieldset.insertBefore(current, child);
                    panels.push({element: current, legend: child});
                }
                if (current) current.appendChild(child);
            });
        });
        if (panels.length < 2) return;
        form.dataset.settingsTabbed = 'true';
        form.classList.add('settings-tabbed-form');
        var nav = document.createElement('div');
        nav.className = 'settings-section-tabs';
        nav.setAttribute('role', 'tablist');
        nav.setAttribute('aria-label', 'Settings sections');
        var buttons = [];
        var prefix = 'settings-' + formIndex + '-';
        var initialHash = currentHash();
        var selected = 0;
        var firstError = -1;
        panels.forEach(function (panel, index) {
            var heading = panel.legend.querySelector('h4') || panel.legend;
            var title = heading.textContent.trim().replace(/\s+/g, ' ');
            var slug = title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || String(index + 1);
            var id = prefix + slug + '-' + index;
            panel.element.id = panel.element.id || id;
            panel.element.classList.add('settings-section-panel');
            panel.element.setAttribute('role', 'tabpanel');
            panel.element.setAttribute('aria-labelledby', id + '-tab');
            var button = document.createElement('button');
            button.type = 'button';
            button.id = id + '-tab';
            button.setAttribute('role', 'tab');
            button.setAttribute('aria-controls', panel.element.id);
            button.textContent = title;
            var errors = panel.element.querySelectorAll('.has-error').length;
            if (errors) {
                if (firstError < 0) firstError = index;
                var badge = document.createElement('span');
                badge.className = 'settings-tab-error';
                badge.textContent = String(errors);
                badge.setAttribute('aria-label', errors + ' fields need attention');
                button.appendChild(badge);
            }
            if (initialHash === panel.element.id) selected = index;
            button.addEventListener('click', function () { activate(index, false, true); });
            button.addEventListener('keydown', function (event) {
                var next = index;
                var rtl = document.documentElement.dir === 'rtl';
                if (event.key === 'ArrowRight') next += rtl ? -1 : 1;
                else if (event.key === 'ArrowLeft') next += rtl ? 1 : -1;
                else if (event.key === 'Home') next = 0;
                else if (event.key === 'End') next = panels.length - 1;
                else return;
                event.preventDefault();
                activate((next + panels.length) % panels.length, true, true);
            });
            nav.appendChild(button);
            buttons.push(button);
        });
        body.parentNode.insertBefore(nav, body);
        function activate(index, focus, updateHash) {
            panels.forEach(function (panel, i) {
                panel.element.hidden = i !== index;
                buttons[i].setAttribute('aria-selected', String(i === index));
                buttons[i].tabIndex = i === index ? 0 : -1;
            });
            selected = index;
            if (focus) buttons[index].focus({preventScroll: true});
            if (focus || updateHash) buttons[index].scrollIntoView({block: 'nearest', inline: 'nearest', behavior: 'auto'});
            if (updateHash) history.replaceState(null, '', location.pathname + location.search + '#' + panels[index].element.id);
            // Recalculate Select2 and auto-sized controls after their panel becomes visible.
            window.dispatchEvent(new Event('resize'));
        }
        var validating = false;
        form.addEventListener('invalid', function (event) {
            if (validating) return;
            var index = panels.findIndex(function (panel) { return panel.element.contains(event.target); });
            if (index >= 0) {
                validating = true;
                activate(index, false, true);
                setTimeout(function () { validating = false; }, 0);
            }
        }, true);
        window.addEventListener('hashchange', function () {
            var hash = currentHash();
            var index = panels.findIndex(function (panel) { return panel.element.id === hash; });
            if (index >= 0 && index !== selected) activate(index, false, false);
        });
        var footer = form.querySelector('.box-footer');
        if (footer) {
            var note = document.createElement('p');
            note.className = 'settings-save-note';
            note.textContent = 'Save applies changes across all tabs.';
            footer.insertBefore(note, footer.firstChild);
        }
        activate(firstError >= 0 ? firstError : selected, false, false);
    }
    function init() {
        document.querySelectorAll('#main form').forEach(enhance);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
