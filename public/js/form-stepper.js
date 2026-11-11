/* Progressive enhancement: keep original fields, validation rules and POST routes. */
(function ($) {
    'use strict';
    var stock = [
        ['Item details', 'Identify the item', []],
        ['Stock & location', 'Quantity and availability', ['location_id', 'qty', 'min_amt', 'default_supplier_id', 'requestable']],
        ['Purchase & notes', 'Costs and supporting details', ['supplier_id', 'order_number', 'purchase_date', 'purchase_cost', 'currency', 'notes', 'image', 'files[]']]
    ];
    var definitions = {
        kit: [['Recipient', 'Choose the person and dates', []], ['Items', 'Review bundle quantities and stock', []]],
        bulk: [
            ['Recipient', 'Who needs the items and when', []],
            ['Items', 'Select assets and availability', ['selected_assets[]', 'status_id', 'requestable']]
        ],
        group: [
            ['Group details', 'Name and purpose', []],
            ['Members', 'People in this group', []],
            ['Permissions', 'Access for this group', []]
        ],
        user: [
            ['Account', 'Identity and sign-in', []],
            ['Organisation', 'Work and assignment', ['company_ids[]', 'locale', 'employee_num', 'jobtitle', 'manager_id', 'department_id', 'start_date', 'end_date', 'vip', 'autoassign_licenses', 'remote', 'location_id']],
            ['Contact', 'Contact details and notes', ['phone', 'mobile', 'website', 'address', 'city', 'state', 'country', 'zip', 'notes']],
            ['Access', 'Groups and permissions', ['groups[]', 'groups', 'two_factor_optin']]
        ],
        asset: [
            ['Identification', 'Model and asset details', []],
            ['Specifications', 'Model-specific fields', []],
            ['Assignment', 'Location and availability', ['checkout_to_type', 'assigned_user', 'assigned_asset', 'assigned_location', 'location_id', 'rtd_location_id', 'requestable']],
            ['Lifecycle', 'Warranty and tracking', ['warranty_months', 'expected_checkin', 'next_audit_date', 'byod', 'asset_eol_date', 'notes', 'image']],
            ['Purchase', 'Order and cost details', ['order_number', 'purchase_date', 'supplier_id', 'purchase_cost']]
        ],
        license: [
            ['License', 'Product, key and seats', []],
            ['Ownership', 'License holder and usage', ['license_name', 'license_email', 'reassignable', 'maintained', 'requestable']],
            ['Purchase & dates', 'Costs, renewal and notes', ['supplier_id', 'order_number', 'purchase_cost', 'purchase_date', 'expiration_date', 'termination_date', 'purchase_order', 'depreciation_id', 'notes']]
        ],
        accessory: stock,
        consumable: stock,
        component: stock,
        maintenance: [
            ['Details', 'Asset, type and owner', []],
            ['Schedule', 'Dates and completion', ['start_date', 'expected_completion_date', 'completed_at']],
            ['Cost & evidence', 'Supplier and attachments', ['supplier_id', 'is_warranty', 'cost', 'url', 'image', 'file', 'file[]', 'files[]', 'notes']]
        ]
    };
    function node(tag, className, text) {
        var el = document.createElement(tag);
        if (className) el.className = className;
        if (text !== undefined) el.textContent = text;
        return el;
    }
    function plainFieldLabel(label) {
        if (!label) return '';
        var copy = label.cloneNode(true);
        copy.querySelectorAll('.form-field-status').forEach(function (status) { status.remove(); });
        return copy.textContent.replace(/\s+/g, ' ').trim();
    }
    function init(form) {
        var kind = form.dataset.stepper;
        var editing = form.dataset.editing === 'true';
        if (!definitions[kind] || form.classList.contains('form-stepper-ready')) return;
        var sections = definitions[kind].map(function (s) { return { title: s[0], hint: s[1], fields: s[2] }; });
        sections.push({ title: 'Review', hint: 'Check before saving', fields: [] });
        var reviewIndex = sections.length - 1;
        var current = 0;
        var completed = new Set();
        var items = [];
        var formId = form.id || ('stepper-' + kind);
        var footer = form.querySelector('.box-footer');
        var panel = form.querySelector('.box-body, .tab-content');
        if (!footer || !panel) return;
        var permissionPane = kind === 'user' ? form.querySelector('#permissions') : (kind === 'group' ? form.querySelector('[data-stepper-section="2"]') : null);
        var permissionIndex = kind === 'group' ? 2 : 3;
        function includedSteps() {
            return sections.map(function (_, i) { return i; }).filter(function (i) {
                return !(kind === 'asset' && i === 1 && !form.querySelector('#custom_fields_content .form-group')) && !(kind === 'group' && i === 1 && !form.querySelector('#selected-select'));
            });
        }
        function fieldStep(name) {
            if (name === 'image_delete' || name === 'use_cloned_image') name = 'image';
            for (var i = 1; i < reviewIndex; i++) {
                if (sections[i].fields.indexOf(name) !== -1) return i;
            }
            return 0;
        }
        function registerRows() {
            items = [];
            form.querySelectorAll('.form-group').forEach(function (row) {
                if (row.closest('.stepper-review') || row.closest('[data-stepper-section]') || (permissionPane && permissionPane.contains(row))) return;
                var control = row.querySelector('input[name]:not([type="hidden"]), select[name], textarea[name]');
                var label = row.querySelector('.control-label, label[for]');
                var name = control ? control.name : (label ? label.getAttribute('for') : '');
                var step = kind === 'asset' && row.closest('#custom_fields_content') ? 1 : fieldStep(name);
                if (!control && !label) {
                    var previous = row.previousElementSibling;
                    if (previous && previous.hasAttribute('data-form-step')) step = Number(previous.dataset.formStep);
                }
                row.dataset.formStep = step;
                items.push(row);
            });
            var customPanel = kind === 'asset' && form.querySelector('#custom_fields_content');
            if (customPanel) {
                customPanel.dataset.formStep = '1';
                items.push(customPanel);
            }
            form.querySelectorAll('[data-stepper-section]').forEach(function (section) {
                section.dataset.formStep = section.dataset.stepperSection;
                items.push(section);
            });
            if (permissionPane && kind === 'user') {
                permissionPane.dataset.formStep = String(permissionIndex);
                items.push(permissionPane);
            }
        }
        var nav = node('nav', 'form-stepper' + (editing ? ' form-section-nav' : '')); 
        nav.setAttribute('aria-label', editing ? 'Edit sections' : 'Form progress');
        var list = node('ol', 'stepper-list');
        var buttons = sections.map(function (s, i) {
            var li = node('li', 'stepper-item');
            var b = node('button', 'stepper-button');
            b.type = 'button';
            b.setAttribute('aria-label', 'Step ' + (i + 1) + ': ' + s.title);
            var shortLabels = { Identification: 'Identity', Specifications: 'Specs', Assignment: 'Assign', Organisation: 'Work', 'Stock & location': 'Stock', 'Purchase & notes': 'Purchase', 'Purchase & dates': 'Purchase', 'Cost & evidence': 'Evidence' };
            var label = node('span', 'stepper-label');
            label.append(node('span', 'stepper-label-full', s.title), node('span', 'stepper-label-short', shortLabels[s.title] || s.title));
            b.append(node('span', 'stepper-marker', String(i + 1)), label, node('span', 'stepper-hint', s.hint));
            b.addEventListener('click', function () { navigate(i); });
            li.append(b);
            list.append(li);
            return b;
        });
        nav.append(list);
        form.prepend(nav);
        var sectionHeader = node('div', 'stepper-section-header');
        var status = node('p', 'stepper-status');
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        var heading = node('h2', 'stepper-section-title');
        heading.id = formId + '-step-title';
        heading.tabIndex = -1;
        sectionHeader.append(status, heading);
        panel.prepend(sectionHeader);
        var review = node('div', 'stepper-review');
        review.hidden = true;
        if (panel.contains(footer)) {
            footer.parentElement.classList.add('stepper-legacy-content');
            footer.parentElement.insertBefore(review, footer);
        } else panel.append(review);
        var right = footer.querySelector('.text-right');
        if (!right) return;
        var finalActions = right.querySelector('.btn-group') || right.querySelector('[type="submit"]');
        var back = node('button', 'btn btn-default stepper-back', 'Back');
        var next = node('button', 'btn btn-primary stepper-next', 'Continue');
        back.type = next.type = 'button';
        right.prepend(back, next);
        if (finalActions) finalActions.classList.add('stepper-final-actions');
        footer.classList.add('stepper-footer');
        form.querySelectorAll('.box-header [type="submit"]').forEach(function (b) { b.hidden = true; });
        form.querySelectorAll('.box-header').forEach(function (h) {
            var title = h.querySelector('h1.box-title, h2.box-title, h3.box-title, h4.box-title');
            if (!title || !title.textContent.trim()) h.hidden = true;
        });
        // The wizard replaces the old optional-section disclosures and user tab navigation.
        form.querySelectorAll('fieldset > legend').forEach(function (e) {
            if (!e.closest('#permissions')) e.classList.add('stepper-replaced-heading');
        });
        registerRows();
        form.classList.add('form-stepper-ready');
        function syncProgressHeight() { form.style.setProperty('--ui-stepper-height', nav.offsetHeight + 'px'); }
        syncProgressHeight();
        if (window.ResizeObserver) new ResizeObserver(syncProgressHeight).observe(nav);
        else window.addEventListener('resize', syncProgressHeight);
        form.noValidate = true;
        function controlsIn(step, includeDisabled) {
            return Array.from(form.querySelectorAll('input[name], select[name], textarea[name]')).filter(function (c) {
                var owner = c.closest('[data-form-step]');
                return owner && Number(owner.dataset.formStep) === step && (includeDisabled || !c.disabled) && c.type !== 'hidden';
            });
        }
        function conditionalHidden(control) {
            var el = control.closest('.form-group') || control;
            while (el && el !== form) {
                if (el.style && (el.style.display === 'none' || el.style.visibility === 'hidden')) {
                    // Old optional disclosures are always open inside the stepper.
                    if (!el.matches('#optional_details, #order_details, .toggle-content-optional_user_details')) return true;
                }
                el = el.parentElement;
            }
            return false;
        }
        function focusControl(control) {
            if (control.classList.contains('select2-hidden-accessible')) {
                var selection = control.parentElement.querySelector('.select2-selection');
                if (selection) { selection.focus(); return; }
            }
            control.focus();
        }
        function validate(step) {
            var validator = $(form).data('validator');
            var first = null;
            controlsIn(step).forEach(function (c) {
                if (conditionalHidden(c)) return;
                var valid = validator ? validator.element(c) : c.checkValidity();
                if (!valid && !first) first = c;
            });
            if (first) {
                if (window.InterfaceForms?.reportErrors) window.InterfaceForms.reportErrors(form, validator ? validator.errorList : [{element: first, message: first.validationMessage}]);
                else focusControl(first);
                if (!validator) first.reportValidity();
                return false;
            }
            return true;
        }
        function readValue(c) {
            if (c.type === 'password' || (kind === 'license' && c.name === 'serial') || c.closest('.form-group')?.querySelector('.fa-lock')) return c.value ? 'Provided' : 'Not provided';
            if (c.type === 'file') {
                var files = Array.from(form.querySelectorAll('input[type="file"]')).filter(function (f) { return f.name === c.name; })
                    .flatMap(function (f) { return Array.from(f.files).map(function (file) { return file.name; }); });
                return files.length ? Array.from(new Set(files)).join(', ') : 'No new file selected';
            }
            function choiceLabel(input) {
                var label = input.closest('label') || Array.from(input.closest('.form-group').querySelectorAll('label')).find(function (l) { return l.htmlFor === input.id; });
                return label ? label.textContent.trim() : input.value;
            }
            if (c.type === 'checkbox') {
                var choices = Array.from(form.querySelectorAll('input[type="checkbox"]')).filter(function (input) { return input.name === c.name; });
                if (choices.length > 1) return choices.filter(function (input) { return input.checked; }).map(choiceLabel).join(', ') || 'None selected';
                return c.checked ? 'Yes' : 'No';
            }
            if (c.type === 'radio') return c.checked ? choiceLabel(c) : 'Not provided';
            if (c.tagName === 'SELECT') return Array.from(c.selectedOptions).map(function (o) { return o.value ? o.textContent.trim() : ''; }).filter(Boolean).join(', ') || 'Not provided';
            return c.value.trim() || 'Not provided';
        }
        function buildReview() {
            review.replaceChildren();
            review.append(node('p', 'stepper-review-intro', 'Check the details below. You can return to any section to make changes before saving.'));
            sections.slice(0, reviewIndex).forEach(function (s, i) {
                if (includedSteps().indexOf(i) === -1) return;
                var section = node('section', 'stepper-review-section');
                var top = node('div', 'stepper-review-heading');
                var change = node('button', 'btn btn-link', 'Change');
                change.type = 'button';
                change.setAttribute('aria-label', 'Change ' + s.title.toLowerCase());
                change.addEventListener('click', function () { show(i, true); });
                top.append(node('h3', '', s.title), change);
                section.append(top);
                var dl = node('dl', 'stepper-summary');
                var seen = new Set();
                controlsIn(i, true).forEach(function (c) {
                    if (conditionalHidden(c) || seen.has(c.name) || /^permission\[/.test(c.name)) return;
                    if (c.type === 'radio') {
                        var chosen = controlsIn(i, true).find(function (input) { return input.name === c.name && input.type === 'radio' && input.checked; });
                        if (chosen && chosen !== c) return;
                    }
                    seen.add(c.name);
                    var row = c.closest('.form-group');
                    var label = row && (row.querySelector('.control-label, label[for]') || c.closest('label') || row.querySelector('label'));
                    var labelText = plainFieldLabel(label);
                    if (!labelText && row) {
                        label = Array.from(row.querySelectorAll('label')).find(function (l) { return l.textContent.trim() && !l.classList.contains('sr-only'); });
                        labelText = plainFieldLabel(label);
                    }
                    if (!labelText) labelText = c.name.replace(/_/g, ' ');
                    dl.append(node('dt', '', labelText), node('dd', '', readValue(c)));
                });
                items.filter(function (row) { return row.id !== 'custom_fields_content' && Number(row.dataset.formStep) === i; }).forEach(function (row) {
                    var value = row.querySelector('.form-control-static');
                    var label = row.querySelector('.control-label');
                    if (value && label) dl.append(node('dt', '', label.textContent.trim()), node('dd', '', value.textContent.trim() || 'Not provided'));
                });
                if (kind === 'kit' && i === 1) {
                    var bundle = form.querySelector('.presentation-kit-summary');
                    if (bundle) section.append(bundle.cloneNode(true));
                }
                if (kind === 'group' && i === 1) {
                    var selectedUsers = form.querySelector('#selected-select');
                    dl.append(node('dt', '', 'Members'), node('dd', '', selectedUsers ? selectedUsers.options.length + ' people selected' : 'Membership is managed separately.'));
                }
                if (permissionPane && i === permissionIndex) {
                    var radios = Array.from(permissionPane.querySelectorAll('input[type="radio"][name^="permission["]:checked'));
                    var allow = radios.filter(function (r) { return r.value === '1'; }).length;
                    var deny = radios.filter(function (r) { return r.value === '-1'; }).length;
                    var permissionsEditable = permissionPane.querySelector('input[name^="permission["]');
                    var permissionSummary = permissionsEditable ? allow + ' allowed, ' + deny + ' denied' + (kind === 'user' ? '; remaining settings inherit their defaults.' : '.') : 'Read-only for your role.';
                    dl.append(node('dt', '', 'Individual permissions'), node('dd', '', permissionSummary));
                }
                if (!dl.children.length && !(kind === 'kit' && i === 1)) dl.append(node('dt', '', 'Details'), node('dd', '', 'No additional information provided.'));
                if (dl.children.length) section.append(dl);
                review.append(section);
            });
        }
        function show(index, focus) {
            var previousStep = current;
            current = index;
            items.forEach(function (row) { row.hidden = Number(row.dataset.formStep) !== index; });
            review.hidden = index !== reviewIndex;
            form.querySelectorAll('.form-field-guidance').forEach(function (guide) { guide.hidden = index === reviewIndex; });
            if (index === reviewIndex) buildReview();
            var included = includedSteps();
            buttons.forEach(function (b, i) {
                b.parentElement.hidden = included.indexOf(i) === -1;
                b.setAttribute('aria-label', 'Step ' + (included.indexOf(i) + 1) + ': ' + sections[i].title);
                b.parentElement.classList.toggle('is-current', i === index);
                b.parentElement.classList.toggle('is-complete', completed.has(i) && i !== index);
                b.querySelector('.stepper-marker').textContent = completed.has(i) && i !== index ? '✓' : String(included.indexOf(i) + 1);
                if (i === index) b.setAttribute('aria-current', 'step');
                else b.removeAttribute('aria-current');
            });
            status.textContent = editing ? 'Edit sections' : 'Step ' + (included.indexOf(index) + 1) + ' of ' + included.length;
            heading.textContent = sections[index].title;
            back.hidden = editing || index === 0;
            next.hidden = editing || index === reviewIndex;
            next.textContent = index === included[included.length - 2] ? 'Review details' : 'Continue';
            if (finalActions) {
                finalActions.hidden = !editing && index !== reviewIndex;
                if (finalActions.matches('.btn-group')) {
                    if (index === reviewIndex) finalActions.insertBefore(back, finalActions.querySelector('[type="submit"]'));
                    else right.insertBefore(back, next);
                }
            }
            if (focus && previousStep !== index) form.dispatchEvent(new CustomEvent('interface:stepchange', {bubbles: true, detail: {form: form, from: previousStep, to: index}}));
            if (focus) {
                heading.focus({ preventScroll: true });
                requestAnimationFrame(function () {
                    var header = document.querySelector('.main-header');
                    var offset = (header ? header.getBoundingClientRect().height : 62) + 16;
                    window.scrollTo({ top: Math.max(0, form.getBoundingClientRect().top + window.scrollY - offset), behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
                });
            }
        }
        function navigate(target) {
            if (!editing && target > current) {
                for (var i = current; i < target; i++) {
                    if (includedSteps().indexOf(i) === -1) continue;
                    show(i, false);
                    if (!validate(i)) return;
                    completed.add(i);
                }
            }
            show(target, true);
        }
        function advance() {
            var included = includedSteps();
            navigate(included[Math.min(included.indexOf(current) + 1, included.length - 1)]);
        }
        back.addEventListener('click', function () {
            var included = includedSteps();
            show(included[Math.max(0, included.indexOf(current) - 1)], true);
        });
        next.addEventListener('click', advance);
        form.addEventListener('change', function (event) {
            var row = event.target.closest('[data-form-step]');
            if (row) completed.delete(Number(row.dataset.formStep));
        });
        form.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && event.target.matches('input:not([type="file"]):not([type="checkbox"]):not([type="radio"])') && !event.target.classList.contains('select2-search__field')) {
                event.preventDefault();
                if (!editing && current < reviewIndex) advance();
            }
        });
        form.addEventListener('submit', function (event) {
            if (editing) {
                var returnTo = current;
                for (var section = 0; section < reviewIndex; section++) {
                    if (includedSteps().indexOf(section) === -1) continue;
                    show(section, false);
                    if (!validate(section)) {
                        event.preventDefault(); event.stopImmediatePropagation(); return;
                    }
                }
                show(returnTo, false);
            }
            if (!editing && current !== reviewIndex) {
                event.preventDefault();
                event.stopImmediatePropagation();
                advance();
                return;
            }
            var validator = $(form).data('validator');
            if (validator && !validator.form()) {
                event.preventDefault();
                event.stopImmediatePropagation();
                var first = validator.errorList[0] && validator.errorList[0].element;
                var owner = first && first.closest('[data-form-step]');
                if (owner) show(Number(owner.dataset.formStep), true);
                if (first) focusControl(first);
            }
        }, true);
        form.addEventListener('form:reveal-field', function (event) {
            var control = event.detail.control;
            var owner = control.closest('[data-form-step]');
            if (owner) show(Number(owner.dataset.formStep), false);
            requestAnimationFrame(function () {
                focusControl(control);
                control.closest('.form-group')?.scrollIntoView({block: 'start', behavior: 'auto'});
            });
        });
        // Return server validation errors to the section that needs attention.
        var errorRow = items.find(function (row) { return row.matches('.has-error') || row.querySelector('.has-error'); });
        show(errorRow ? Number(errorRow.dataset.formStep) : 0, false);
        // Model fields and Livewire's additional tag rows arrive after initialization.
        if (kind === 'asset') new MutationObserver(function (records) {
            var changedRows = records.some(function (record) {
                return Array.from(record.addedNodes).concat(Array.from(record.removedNodes)).some(function (el) {
                    return el.nodeType === 1 && (el.matches('.form-group') || el.querySelector('.form-group'));
                });
            });
            if (changedRows) { registerRows(); show(current, false); }
        }).observe(form, { childList: true, subtree: true });
    }
    $(function () { document.querySelectorAll('form[data-stepper]').forEach(init); });
})(window.jQuery);
