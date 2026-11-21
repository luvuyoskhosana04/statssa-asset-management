/* Shared field presentation. Existing names, rules and submit handlers are retained. */
(function () {
    'use strict';
    var words = window.SnipePresentation || {}, records = new Map(), sequence = 0, scheduled = false;
    var animationUrl = document.currentScript?.dataset.successAnimation, animationDataPromise;
    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    var text = function (key) { return words[key] || key; };
    var controlSelector = 'input:not([type=hidden]):not([type=submit]):not([type=button]):not([type=reset]),select,textarea';
    function node(tag, cls, content) { var n = document.createElement(tag); n.className = cls; if (content) n.textContent = content; return n; }
    function jqValidator(control) { return window.jQuery && control.form ? window.jQuery(control.form).data('validator') : null; }
    function rules(control) { try { return jqValidator(control) ? window.jQuery(control).rules() : {}; } catch (_) { return {}; } }
    function required(control) { var rule = rules(control).required, label = labelFor(control), marked = label && (label.classList.contains('required') || label.querySelector('[data-required="true"]')); return !!marked || control.required || control.getAttribute('aria-required') === 'true' || rule === true || (typeof rule === 'function' && rule.call(control, control)); }
    function hasValue(control) { if (control.type === 'file') return !!control.files?.length; if (['checkbox','radio'].includes(control.type)) return control.checked; return control.tagName === 'SELECT' && control.multiple ? Array.from(control.selectedOptions).some(function (o) { return o.value !== ''; }) : control.value.trim() !== ''; }
    function labelFor(control) {
        var label = Array.from(control.labels || []).find(function (n) { return !n.classList.contains('btn'); });
        if (!label) label = control.closest('.form-group')?.querySelector(':scope > label');
        return label || null;
    }
    function labelText(label, control) {
        if (!label) return control.getAttribute('aria-label') || '';
        var copy = label.cloneNode(true); copy.querySelectorAll('input,select,textarea,.form-field-status,.sr-only,i,.tooltip,.btn').forEach(function (n) { n.remove(); });
        return copy.textContent.replace(/\s+/g,' ').trim().replace(/\s*\((Optional|Required)\)\s*$/i,'');
    }
    function example(control, label) {
        if (control.getAttribute('placeholder')) return;
        var name = (control.name || control.id || '').toLowerCase(), examples = words.form_examples || {}, value;
        if (/password/.test(name)) value = text(/confirm/.test(name) ? 'form_password_confirm_hint' : 'field_password_example').replace(':min', window.SnipeFieldGuidance?.passwordMin || 8);
        else if (/secret|token|private.key/.test(name)) value = text('form_token_hint');
        else if (/asset.*name|^name$/.test(name) && /hardware|asset/.test(location.pathname)) value = examples.asset;
        else if (/first.name/.test(name)) value = examples.first_name;
        else if (/last.name/.test(name)) value = examples.last_name;
        else if (/email/.test(name) || control.type === 'email') value = examples.email;
        else if (/phone|mobile/.test(name) || control.type === 'tel') value = examples.phone;
        else if (/url|website/.test(name) || control.type === 'url') value = examples.url;
        else if (/serial/.test(name)) value = examples.serial;
        else if (/barcode|tag/.test(name)) value = examples.tag;
        else if (/note|description/.test(name) || control.tagName === 'TEXTAREA') value = examples.notes;
        else if (control.type === 'number') value = examples.number;
        else if (control.type === 'date') value = examples.date;
        else value = text('field_enter_hint').replace(':label',label.toLowerCase());
        if (control.tagName === 'SELECT') {
            var empty = Array.from(control.options).find(function (o) { return o.value === ''; });
            if (empty && !control.classList.contains('select2-hidden-accessible')) empty.textContent = text('field_choose_hint').replace(':label',label.toLowerCase());
        } else if (value) control.setAttribute('placeholder',value);
    }
    function showSuccessMotion(record) {
        if (record.motion) {
            if (record.motionReady && !record.motionPlaying) {
                if (reducedMotion.matches || record.motionPlayed) record.motion.goToAndStop(record.motion.totalFrames - 1, true);
                else { record.motionPlaying = true; record.motionPlayed = true; record.motion.goToAndPlay(0, true); }
            }
            return;
        }
        if (record.motionLoading || !animationUrl || !window.lottie) return;
        record.motionLoading = true;
        if (!animationDataPromise) animationDataPromise = fetch(animationUrl, {credentials:'same-origin'}).then(function (response) {
            if (!response.ok) throw new Error('Success animation unavailable');
            return response.json();
        });
        animationDataPromise.then(function (data) {
            if (!record.control.isConnected || records.get(record.control) !== record) return;
            var stage = node('span','ui-field-success-motion'); stage.setAttribute('aria-hidden','true'); record.tick.appendChild(stage);
            record.motion = window.lottie.loadAnimation({container:stage,renderer:'svg',loop:false,autoplay:false,animationData:JSON.parse(JSON.stringify(data))});
            record.motion.addEventListener('DOMLoaded',function () {
                record.motionReady = true;
                record.tick.classList.add('has-success-animation');
                var svg = stage.querySelector('svg'); if (svg) { svg.setAttribute('aria-hidden','true'); svg.setAttribute('focusable','false'); }
                if (!record.tick.hidden) showSuccessMotion(record);
            });
            record.motion.addEventListener('complete',function () { record.motionPlaying = false; });
            record.motion.addEventListener('data_failed',function () { record.tick.classList.remove('has-success-animation'); });
        }).catch(function () { /* Keep the static check when the animation cannot load. */ });
    }
    function setSuccess(record, valid) {
        record.shell.classList.toggle('is-field-valid', valid); record.tick.hidden = !valid;
        record.tick.setAttribute('aria-label',text('field_checked'));
        if (valid) showSuccessMotion(record);
        else if (record.motion) { record.motion.pause(); record.motionPlaying = false; }
    }
    function update(record, validate) {
        var control = record.control, filled = hasValue(control), checked = false;
        record.shell.classList.toggle('is-field-empty',!filled);
        record.shell.classList.toggle('is-field-disabled',control.disabled || control.readOnly);
        if (record.label && !['checkbox','radio'].includes(control.type)) {
            var suffix = required(control) ? '' : ' '+text('field_optional');
            var nextLabel = record.title+suffix;
            if (!record.labelCopy) { record.labelCopy = node('span','ui-field-label-copy'); record.inset.appendChild(record.labelCopy); }
            if (record.labelCopy.textContent !== nextLabel) record.labelCopy.textContent = nextLabel;
        }
        if (record.touched && filled && !control.disabled) {
            checked = control.validity.valid;
            if (control.minLength > 0 && control.value.length < control.minLength) checked = false;
            var validator = jqValidator(control);
            if (validator && validate) {
                try { checked = validator.element(control) !== false && checked; } catch (_) { checked = false; }
            }
            if (validator && (validator.invalid[control.name] || validator.pending[control.name])) checked = false;
            if (control.getAttribute('aria-invalid') === 'true' || control.closest('.has-error')) checked = false;
            if (control.dataset.fieldVerification && control.dataset.fieldVerification !== 'valid') checked = false;
        }
        if (!checked) record.motionPlayed = false;
        setSuccess(record, checked);
        var fieldValidator = jqValidator(control);
        var invalid = control.getAttribute('aria-invalid') === 'true' || !!(fieldValidator && fieldValidator.invalid[control.name]) || !!control.closest('.has-error') || (record.touched && !control.validity.valid);
        var missingRequired = invalid && required(control) && !filled && !control.disabled;
        if (missingRequired && !record.requiredChip) {
            record.requiredChip = node('span','ui-field-required-chip',text('form_required'));
            (record.inset || record.shell).appendChild(record.requiredChip);
        }
        if (record.requiredChip) record.requiredChip.hidden = !missingRequired;
        record.shell.classList.toggle('is-required-missing',missingRequired);
        if (fieldValidator) fieldValidator.errorsFor(control).each(function () { this.classList.toggle('ui-required-message-replaced',missingRequired); });
        if (!jqValidator(control) && !control.dataset.fieldVerification && record.touched) {
            if (!record.feedback && invalid) {
                record.feedback = node('p','help-block ui-field-feedback'); record.feedback.id = 'ui-field-feedback-'+(++sequence); record.feedback.setAttribute('role','status'); record.shell.after(record.feedback);
                control.setAttribute('aria-describedby',((control.getAttribute('aria-describedby') || '')+' '+record.feedback.id).trim());
            }
            if (record.feedback) { record.feedback.hidden = !invalid; if (invalid && record.feedback.textContent !== control.validationMessage) record.feedback.textContent = control.validationMessage; }
        }
        if (record.feedback) record.feedback.classList.toggle('ui-required-message-replaced',missingRequired);
        record.shell.classList.toggle('is-field-invalid',invalid);
    }
    function enhance(control) {
        if (records.has(control) || control.matches('.sr-only,.select2-search__field,[data-no-field-ui]') || control.closest('.main-header,.fixed-table-toolbar,.table-bulk-toolbar,.column-filter-panel,[data-permission-editor],.permission-matrix,.permissions-matrix,[role=search],.ui-field-shell')) return;
        var wasFocused = document.activeElement === control;
        var form = control.form;
        if ((!form && !control.closest('.form-group,.modal-body,.ldap-wizard')) || (form && form.getAttribute('role') === 'search')) return;
        var label = labelFor(control), title = labelText(label,control);
        if (!title || control.type === 'hidden') return;
        // Option labels keep their native click target and checked state.
        if (['checkbox','radio'].includes(control.type)) {
            if (!label) return;
            label.classList.add('ui-choice-field'); var choiceTick = node('span','ui-field-check','✓'); choiceTick.hidden = true; choiceTick.setAttribute('role','img'); label.appendChild(choiceTick);
            var choiceRecord = {control:control,shell:label,tick:choiceTick,title:title,label:null,touched:false}; records.set(control,choiceRecord); return;
        }
        var host = control, select2 = control.tagName === 'SELECT' && control.nextElementSibling?.classList.contains('select2-container') ? control.nextElementSibling : null;
        var group = control.closest('.input-group');
        if (control.closest('.employee-picker')) host = control.closest('.employee-picker');
        else if (control.type === 'file') host = control.closest('label.btn') || control;
        var shell = node('div','ui-field-shell'); host.before(shell); shell.appendChild(host); if (select2 && !shell.contains(select2)) shell.appendChild(select2);
        var inset = node('span','ui-field-inset-label'); inset.setAttribute('aria-hidden','true'); shell.prepend(inset);
        var tick = node('span','ui-field-check','✓'); tick.hidden = true; tick.setAttribute('role','img'); shell.appendChild(tick);
        shell.classList.toggle('is-select-field',control.tagName === 'SELECT' || control.getAttribute('role') === 'combobox');
        shell.classList.toggle('is-textarea-field',control.tagName === 'TEXTAREA'); shell.classList.toggle('is-file-field',control.type === 'file');
        shell.classList.toggle('is-special-field',['color','range'].includes(control.type));
        control.classList.add('ui-field-control'); if (!control.id) control.id = 'ui-field-'+(++sequence);
        if (label && !label.contains(host) && !label.classList.contains('btn')) { label.classList.add('ui-field-original-label'); label.htmlFor = control.id; }
        var record = {control:control,shell:shell,tick:tick,inset:inset,title:title,label:label,touched:false,timer:null}; records.set(control,record);
        window.InterfaceForms?.guideControl?.(control); example(control,title);
        if (control.type === 'date' || control.type === 'datetime-local') {
            var dateExample = node('span','ui-field-date-example',control.type === 'date' ? text('field_date_example') : text('field_datetime_example')); dateExample.setAttribute('aria-hidden','true'); shell.appendChild(dateExample);
        }
        update(record,false);
        if (wasFocused) control.focus({preventScroll:true});
    }
    function estimate(form) {
        if (form.querySelector('.ui-form-estimate') || form.closest('[role=search]') || (form.method.toLowerCase() !== 'post' && !form.matches('[data-stepper]'))) return;
        var fields = Array.from(form.querySelectorAll(controlSelector)).filter(function (c) { return !c.matches('.sr-only,.select2-search__field,[data-no-field-ui]') && !c.disabled && !c.closest('.permission-matrix,.permissions-matrix,[data-permission-editor]'); });
        if (!fields.length || !fields.some(function (c) { return records.has(c); })) return;
        var seconds = fields.reduce(function (sum,c) { return sum + (c.type === 'file' ? 40 : c.tagName === 'TEXTAREA' ? 25 : ['checkbox','radio'].includes(c.type) ? 4 : 12); },0);
        var low = Math.max(1,Math.ceil(seconds/60)), high = low+1;
        var message = form.dataset.formEstimate || text('field_estimated_time').replace(':min',low).replace(':max',high);
        if (form.classList.contains('role-form') || form.classList.contains('login-form')) message = text('field_signin_time');
        if (form.id === 'issue-preview-form') message = text('field_issue_time');
        if (form.id === 'receive-intake-form' || form.id === 'receive-manual-form') message = text('field_receiving_time');
        var notice = node('p','ui-form-estimate',message); notice.setAttribute('role','note');
        var panel = form.querySelector('.box-body,.tab-content,.modal-body');
        if (panel) panel.prepend(notice); else form.prepend(notice);
    }
    function inlineActions() {
        document.querySelectorAll('.form-ui-row > .form-ui-aside').forEach(function (aside) {
            var row = aside.parentElement, shell = row.querySelector('.form-ui-input .ui-field-shell');
            var action = aside.querySelector('a[data-target="#createModal"],button.add_field_button');
            if (!shell || !action || shell.classList.contains('has-inline-add')) return;
            var control = shell.querySelector('.ui-field-control'), record = records.get(control);
            action.textContent = text('field_add');
            action.setAttribute('aria-label',text('field_add')+' '+(record ? record.title.toLowerCase() : ''));
            action.classList.add('ui-field-add-button');
            aside.classList.add('ui-field-inline-action'); shell.classList.add('has-inline-add');
            row.classList.add('has-inline-field-action'); shell.appendChild(aside);
        });
    }
    function inlineAddons() {
        document.querySelectorAll('.input-group > .input-group-addon,.input-group > .input-group-btn').forEach(function (addon) {
            var group = addon.parentElement, shell = group.querySelector(':scope > .ui-field-shell');
            if (!shell || group.querySelectorAll('input:not([type=hidden]),select,textarea').length !== 1 || shell.classList.contains('has-inline-add')) return;
            var control = shell.querySelector('.ui-field-control'), record = records.get(control);
            var datePicker = group.matches('.date,[data-provide="datetimepicker"],[data-provide="datepicker"]');
            var colourPicker = group.matches('.colorpicker-component');
            var wrapper = node('span','ui-field-inline-action');
            shell.classList.add('has-inline-add'); group.classList.add('ui-inset-group');
            addon.classList.add('ui-field-add-button','ui-field-inset-addon');
            if (datePicker || colourPicker) {
                Array.from(addon.children).forEach(function (child) { child.classList.add('ui-picker-original-icon'); child.setAttribute('aria-hidden','true'); });
                addon.appendChild(node('span','',text('field_select')));
                addon.setAttribute('role','button'); addon.tabIndex = control.disabled ? -1 : 0;
                addon.setAttribute('aria-label',text('field_select')+' '+(record ? record.title.toLowerCase() : ''));
                addon.addEventListener('keydown',function (event) { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); addon.click(); } });
            }
            wrapper.appendChild(addon); shell.appendChild(wrapper);
        });
    }
    function scan() {
        scheduled = false;
        records.forEach(function (record,c) { if (!c.isConnected || !record.shell.isConnected || (!record.shell.contains(c) && !['checkbox','radio'].includes(c.type))) { clearTimeout(record.timer); if (record.motion) record.motion.destroy(); records.delete(c); } });
        document.querySelectorAll(controlSelector).forEach(enhance);
        inlineActions();
        inlineAddons();
        document.querySelectorAll('form').forEach(estimate);
        var importBody = document.querySelector('#importMappingModal .modal-body');
        if (importBody && !importBody.querySelector('.ui-form-estimate')) importBody.prepend(node('p','ui-form-estimate',text('field_import_time')));
        records.forEach(function (record) { update(record,false); });
    }
    function schedule() { if (!scheduled) { scheduled = true; requestAnimationFrame(scan); } }
    function changed(event) {
        var record = records.get(event.target); if (!record) return;
        record.touched = true; clearTimeout(record.timer); setSuccess(record,false);
        if (event.target.dataset.fieldVerification) event.target.dataset.fieldVerification = 'pending';
        record.timer = setTimeout(function () { update(record,true); },event.type === 'input' ? 180 : 0);
    }
    function init() {
        document.addEventListener('invalid',function (event) { var r=records.get(event.target); if (r) { r.touched = true; update(r,false); } },true);
        document.addEventListener('input',changed); document.addEventListener('change',changed);
        document.addEventListener('focusout',function (event) { var r=records.get(event.target); if (r) { clearTimeout(r.timer); if (hasValue(r.control)) r.touched = true; update(r,true); } });
        document.addEventListener('field:verified',function (event) { var r=records.get(event.target); if (r) { r.touched = true; update(r,false); } });
        if (window.jQuery) window.jQuery(document).on('select2:select select2:clear','select',function () { changed({target:this,type:'change'}); });
        reducedMotion.addEventListener('change',function () {
            if (reducedMotion.matches) records.forEach(function (record) {
                if (record.motionReady) { record.motion.goToAndStop(record.motion.totalFrames - 1, true); record.motionPlaying = false; }
            });
        });
        if (window.jQuery) window.jQuery(document).on('dp.change','.ui-inset-group',function () { var c=this.querySelector('.ui-field-control'); if (c) changed({target:c,type:'change'}); });
        scan();
        new MutationObserver(schedule).observe(document.body,{childList:true,subtree:true,attributes:true,attributeFilter:['required','disabled','aria-required','aria-invalid','data-field-verification']});
    }
    if (window.jQuery) window.jQuery(init); else if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',init); else init();
})();
