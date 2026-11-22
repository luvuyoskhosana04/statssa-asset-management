(function () {
    'use strict';
    var form = document.getElementById('header-asset-lookup');
    if (!form) return;
    var field = document.getElementById('tagSearch');
    var button = document.getElementById('topSearchButton');
    var open = false;
    function size() {
        var right = button.getBoundingClientRect().right;
        form.style.setProperty('--lookup-open-width', Math.max(44, Math.min(300, right - 12)) + 'px');
    }
    function setOpen(value, restoreFocus) {
        open = value; size();
        form.classList.toggle('is-collapsed', !open);
        button.setAttribute('aria-expanded', String(open));
        field.disabled = !open; field.tabIndex = open ? 0 : -1;
        if (open) field.focus({preventScroll: true});
        else if (restoreFocus) button.focus({preventScroll: true});
    }
    button.addEventListener('click', function (event) {
        if (!open) { event.preventDefault(); setOpen(true); }
    });
    form.addEventListener('submit', function (event) {
        if (!open) { event.preventDefault(); setOpen(true); }
    });
    form.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && open) { event.preventDefault(); event.stopPropagation(); setOpen(false, true); }
    });
    form.addEventListener('focusout', function () {
        setTimeout(function () { if (open && !form.contains(document.activeElement)) setOpen(false); }, 0);
    });
    document.addEventListener('pointerdown', function (event) {
        if (open && !form.contains(event.target)) setOpen(false);
    });
    window.addEventListener('resize', size);
    setOpen(false);
})();
