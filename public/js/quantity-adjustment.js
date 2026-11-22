(function () {
    'use strict';
    function preview(available, raw) {
        if (String(raw).trim() === '') return {error: 'empty', delta: null, after: null};
        var delta = Number(raw);
        if (!Number.isSafeInteger(delta)) return {error: 'invalid', delta: null, after: null};
        var known = available !== null && available !== '' && Number.isSafeInteger(Number(available));
        var after = known ? Number(available) + delta : null;
        return {error: after !== null && after < 0 ? 'exceeds' : null, delta: delta, after: after};
    }
    if (typeof module !== 'undefined' && module.exports) { module.exports = {preview: preview}; return; }
    var modal = document.getElementById('adjustQuantityModal');
    if (!modal) return;
    var amount = modal.querySelector('#adjustQuantityAmount'), form = modal.querySelector('form');
    var buttons = Array.from(modal.querySelectorAll('[data-adjust-mode]'));
    var after = modal.querySelector('#adjustQuantityAfter'), delta = modal.querySelector('#adjustQuantityDelta');
    var feedback = modal.querySelector('#adjustQuantityFeedback'), details = modal.querySelector('#adjustQuantityDetails');
    var copy = JSON.parse(modal.querySelector('[data-adjust-copy]').dataset.adjustCopy);
    modal.querySelector('.close').setAttribute('aria-label', copy.close);
    var numbers = new Intl.NumberFormat(document.documentElement.lang || 'en', {maximumFractionDigits: 0});
    var mode = 'add';
    function select(next) {
        mode = next; modal.dataset.adjustMode = mode;
        buttons.forEach(function (button) { button.setAttribute('aria-pressed', String(button.dataset.adjustMode === mode)); });
        amount.readOnly = mode === 'audit';
    }
    function update() {
        var current = modal.querySelector('.adjust-quantity-available').textContent.trim();
        var result = preview(current === '' ? null : Number(current), amount.value);
        after.textContent = result.after === null || result.error ? '...' : numbers.format(result.after);
        delta.textContent = result.delta === null ? '...' : (result.delta > 0 ? '+' : '') + numbers.format(result.delta);
        feedback.textContent = result.error && result.error !== 'empty' ? copy[result.error] : mode === 'audit' ? copy.audit : '';
        feedback.hidden = !feedback.textContent;
        feedback.classList.toggle('has-adjust-error', Boolean(result.error && result.error !== 'empty'));
        after.classList.toggle('has-adjust-error', Boolean(result.error && result.error !== 'empty'));
        form.querySelector('[type=submit]').disabled = Boolean(result.error);
        amount.setCustomValidity(result.error && result.error !== 'empty' ? copy[result.error] : '');
    }
    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            select(button.dataset.adjustMode);
            if (mode === 'audit') amount.value = '0';
            else if (amount.value !== '') amount.value = mode === 'remove' ? -Math.abs(Number(amount.value)) : Math.abs(Number(amount.value));
            amount.dispatchEvent(new Event('input', {bubbles: true}));
            if (mode !== 'audit') amount.focus();
        });
    });
    // Capture sign changes before the existing acquisition-field handler runs.
    form.addEventListener('input', function (event) {
        if (event.target !== amount) return;
        if (mode === 'remove' && Number(amount.value) > 0) amount.value = -Number(amount.value);
        if (mode === 'add' && Number(amount.value) < 0) select('remove');
        update();
    }, true);
    function prepareCalendar() {
        var wrapper = modal.querySelector('[data-provide="datetimepicker"]');
        if (!wrapper || wrapper.dataset.quantityCalendar) return;
        var picker = window.jQuery(wrapper).data('DateTimePicker');
        if (!picker) return;
        var content = modal.querySelector('.modal-content');
        picker.widgetParent(window.jQuery(content));
        window.jQuery(wrapper).on('dp.show.quantityAdjustment', function () {
            var widget = content.querySelector('.bootstrap-datetimepicker-widget');
            if (!widget) return;
            var field = wrapper.getBoundingClientRect(), bounds = content.getBoundingClientRect();
            var top = field.bottom - bounds.top + 4;
            if (top + widget.offsetHeight > bounds.height - 8) top = field.top - bounds.top - widget.offsetHeight - 4;
            widget.style.top = Math.max(8, Math.min(top, bounds.height - widget.offsetHeight - 8)) + 'px';
            widget.style.left = Math.max(8, Math.min(field.left - bounds.left, bounds.width - widget.offsetWidth - 8)) + 'px';
            widget.style.bottom = 'auto'; widget.style.right = 'auto';
        });
        modal.querySelector('.modal-body').addEventListener('scroll', function () { picker.hide(); }, {passive: true});
        wrapper.dataset.quantityCalendar = 'true';
    }
    window.jQuery(modal).on('shown.bs.modal', function () {
        select('add'); details.open = false; prepareCalendar();
        amount.setAttribute('step', '1'); amount.setAttribute('inputmode', 'numeric');
        delete amount.dataset.formExamplePlaceholder; amount.placeholder = copy.quantity;
        var note = modal.querySelector('#adjustQuantityNote');
        delete note.dataset.formExamplePlaceholder; note.placeholder = copy.reason;
        update(); amount.focus();
    });
})();
