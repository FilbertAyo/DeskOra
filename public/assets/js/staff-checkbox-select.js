(function (window) {
    'use strict';

    var activePicker = null;
    var modalInstance = null;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function normalizeUsers(users) {
        return (users || [])
            .map(function (user) {
                return {
                    id: String(user.id ?? user.value ?? ''),
                    name: String(user.name ?? user.label ?? ''),
                };
            })
            .filter(function (user) {
                return user.id !== '';
            });
    }

    function normalizeSelected(selected) {
        return new Set((selected || []).map(String));
    }

    function readPickerSelected(pickerEl) {
        if (!pickerEl) {
            return [];
        }

        return Array.from(pickerEl.querySelectorAll('.staff-picker-compact__inputs input[type="hidden"]'))
            .map(function (input) {
                return String(input.value);
            })
            .filter(Boolean);
    }

    function formatSummaryText(users, selectedIds) {
        const userMap = new Map(normalizeUsers(users).map(function (user) {
            return [user.id, user.name];
        }));
        const names = (selectedIds || [])
            .map(function (id) {
                return userMap.get(String(id)) || '';
            })
            .filter(Boolean);

        if (names.length === 0) {
            return 'None selected';
        }

        if (names.length <= 2) {
            return names.join(', ');
        }

        return names.slice(0, 2).join(', ') + ' +' + (names.length - 2) + ' more';
    }

    function syncPickerInputs(pickerEl, selectedIds, fieldName) {
        const container = pickerEl.querySelector('.staff-picker-compact__inputs');

        if (!container) {
            return;
        }

        container.innerHTML = '';
        (selectedIds || []).forEach(function (id) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = fieldName;
            input.value = id;
            container.appendChild(input);
        });
    }

    function updatePickerSummary(pickerEl, users) {
        const summaryEl = pickerEl.querySelector('.staff-picker-compact__summary');

        if (!summaryEl) {
            return;
        }

        const selected = readPickerSelected(pickerEl);
        const sourceUsers = users || normalizeUsers(JSON.parse(pickerEl.getAttribute('data-users') || '[]'));
        const text = formatSummaryText(sourceUsers, selected);

        summaryEl.textContent = text;
        summaryEl.classList.toggle('text-muted', selected.length === 0);
        summaryEl.classList.toggle('text-body', selected.length > 0);
    }

    function buildCheckboxGridHtml(users, selected, options) {
        options = options || {};
        const name = options.name || 'staff_ids[]';
        const title = options.title !== undefined ? options.title : 'Assigned Staff';
        const showTitle = options.showTitle !== false;
        const disabled = !!options.disabled;
        const groupId = options.id || ('staff-cb-' + Math.random().toString(36).slice(2, 10));
        const selectedSet = normalizeSelected(selected);
        const colClass = options.colClass || 'col-12 col-sm-6 col-lg-4';

        let html = '<div class="staff-checkbox-select" data-staff-checkbox-select>';

        if (showTitle) {
            html += '<div class="staff-checkbox-select__title form-label mb-2">' + escapeHtml(title) + '</div>';
        }

        html += '<div class="staff-checkbox-select__grid row g-2">';

        normalizeUsers(users).forEach(function (user) {
            const checked = selectedSet.has(user.id) ? ' checked' : '';
            const dis = disabled ? ' disabled' : '';
            const inputId = groupId + '-' + user.id;

            html += '<div class="' + colClass + '">';
            html += '<div class="form-check staff-checkbox-select__item">';
            html += '<input class="form-check-input" type="checkbox" name="' + escapeHtml(name) + '" id="' + escapeHtml(inputId) + '" value="' + escapeHtml(user.id) + '"' + checked + dis + '>';
            html += '<label class="form-check-label" for="' + escapeHtml(inputId) + '">' + escapeHtml(user.name) + '</label>';
            html += '</div></div>';
        });

        html += '</div></div>';

        return html;
    }

    function buildPickerHtml(options) {
        options = options || {};
        const users = normalizeUsers(options.users);
        const selected = (options.selected || []).map(String);
        const fieldName = options.name || 'staff_ids[]';
        const disabled = !!options.disabled;
        const usersJson = escapeHtml(JSON.stringify(users));
        const selectedJson = escapeHtml(JSON.stringify(selected));
        const summary = formatSummaryText(users, selected);
        const summaryClass = selected.length > 0 ? 'text-body' : 'text-muted';

        let inputsHtml = selected.map(function (id) {
            return '<input type="hidden" name="' + escapeHtml(fieldName) + '" value="' + escapeHtml(id) + '">';
        }).join('');

        let html = '<div class="staff-picker-compact" data-staff-picker data-field-name="' + escapeHtml(fieldName) + '" data-disabled="' + (disabled ? '1' : '0') + '" data-users="' + usersJson + '" data-selected="' + selectedJson + '">';
        html += '<div class="d-flex align-items-start gap-1">';
        html += '<span class="staff-picker-compact__summary small ' + summaryClass + ' flex-grow-1">' + escapeHtml(summary) + '</span>';

        if (!disabled) {
            html += '<button type="button" class="btn btn-sm btn-outline-secondary staff-picker-compact__open py-0 px-2" title="Select staff" aria-label="Select staff">';
            html += '<i class="ti ti-users"></i>';
            html += '</button>';
        }

        html += '</div>';
        html += '<div class="staff-picker-compact__inputs">' + inputsHtml + '</div>';
        html += '</div>';

        return html;
    }

    function buildHtml(options) {
        options = options || {};

        if (options.compact) {
            return buildPickerHtml(options);
        }

        return buildCheckboxGridHtml(options.users, options.selected, options);
    }

    function resolveRoot(container) {
        if (!container) {
            return null;
        }

        if (container.matches('[data-staff-picker]')) {
            return container;
        }

        if (container.matches('[data-staff-checkbox-select]')) {
            return container;
        }

        return container.querySelector('[data-staff-picker], [data-staff-checkbox-select]');
    }

    function readSelected(container) {
        const picker = container && container.matches('[data-staff-picker]')
            ? container
            : container && container.querySelector('[data-staff-picker]');

        if (picker) {
            return readPickerSelected(picker);
        }

        const root = resolveRoot(container);

        if (!root) {
            return [];
        }

        return Array.from(root.querySelectorAll('input[type="checkbox"]:checked')).map(function (checkbox) {
            return checkbox.value;
        });
    }

    function setSelected(container, selectedIds) {
        const picker = container && container.matches('[data-staff-picker]')
            ? container
            : container && container.querySelector('[data-staff-picker]');

        if (picker) {
            const fieldName = picker.getAttribute('data-field-name') || 'staff_ids[]';
            syncPickerInputs(picker, selectedIds || [], fieldName);
            updatePickerSummary(picker);
            return;
        }

        const root = resolveRoot(container);

        if (!root) {
            return;
        }

        const selected = normalizeSelected(selectedIds);

        root.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) {
            checkbox.checked = selected.has(String(checkbox.value));
        });
    }

    function ensureModal() {
        let modalEl = document.getElementById('staffPickerModal');

        if (!modalEl) {
            modalEl = document.createElement('div');
            modalEl.className = 'modal fade';
            modalEl.id = 'staffPickerModal';
            modalEl.tabIndex = -1;
            modalEl.setAttribute('aria-labelledby', 'staffPickerModalLabel');
            modalEl.setAttribute('aria-hidden', 'true');
            modalEl.innerHTML = ''
                + '<div class="modal-dialog modal-dialog-scrollable">'
                + '  <div class="modal-content">'
                + '    <div class="modal-header">'
                + '      <h5 class="modal-title" id="staffPickerModalLabel">Assigned Staff</h5>'
                + '      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
                + '    </div>'
                + '    <div class="modal-body" id="staffPickerModalBody"></div>'
                + '    <div class="modal-footer">'
                + '      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>'
                + '      <button type="button" class="btn btn-primary" id="staffPickerModalSave">Done</button>'
                + '    </div>'
                + '  </div>'
                + '</div>';
            document.body.appendChild(modalEl);

            document.getElementById('staffPickerModalSave').addEventListener('click', function () {
                if (!activePicker) {
                    return;
                }

                const body = document.getElementById('staffPickerModalBody');
                const selected = Array.from(body.querySelectorAll('input[type="checkbox"]:checked')).map(function (checkbox) {
                    return checkbox.value;
                });
                const fieldName = activePicker.getAttribute('data-field-name') || 'staff_ids[]';

                syncPickerInputs(activePicker, selected, fieldName);
                updatePickerSummary(activePicker);
                hideModal();
            });
        }

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
        }

        return modalEl;
    }

    function hideModal() {
        if (modalInstance) {
            modalInstance.hide();
        }

        activePicker = null;
    }

    function openPickerModal(pickerEl) {
        if (!pickerEl || pickerEl.getAttribute('data-disabled') === '1') {
            return;
        }

        const modalEl = ensureModal();
        const body = document.getElementById('staffPickerModalBody');
        const users = normalizeUsers(JSON.parse(pickerEl.getAttribute('data-users') || '[]'));
        const selected = readPickerSelected(pickerEl);
        const fieldName = pickerEl.getAttribute('data-field-name') || 'staff_modal_ids[]';

        activePicker = pickerEl;
        body.innerHTML = buildCheckboxGridHtml(users, selected, {
            name: fieldName + '__modal',
            showTitle: false,
            id: 'staff-picker-modal-grid',
            colClass: 'col-12 col-md-6 col-xl-6',
        });

        if (modalInstance) {
            modalInstance.show();
        }
    }

    function initPickers(root) {
        const scope = root || document;

        scope.querySelectorAll('[data-staff-picker]').forEach(function (pickerEl) {
            updatePickerSummary(pickerEl);
        });
    }

    function usersFromOptionsHtml(optionsHtml) {
        const container = document.createElement('div');
        container.innerHTML = '<select>' + (optionsHtml || '') + '</select>';

        return normalizeUsers(
            Array.from(container.querySelectorAll('option')).map(function (option) {
                return {
                    id: option.value,
                    name: option.textContent,
                };
            })
        );
    }

    function usersFromJsonScript(scriptEl) {
        if (!scriptEl) {
            return [];
        }

        try {
            return normalizeUsers(JSON.parse(scriptEl.textContent));
        } catch (error) {
            return [];
        }
    }

    function usersFromSelect(selectEl) {
        if (!selectEl) {
            return [];
        }

        return normalizeUsers(
            Array.from(selectEl.options).map(function (option) {
                return {
                    id: option.value,
                    name: option.textContent,
                };
            })
        );
    }

    function getTeamUsers() {
        if (Array.isArray(window.__teamUsers) && window.__teamUsers.length) {
            return normalizeUsers(window.__teamUsers);
        }

        const dataEl = document.getElementById('teamUsersData');

        if (dataEl) {
            return usersFromJsonScript(dataEl);
        }

        const legacyPicker = document.querySelector('[data-staff-picker]');

        if (legacyPicker) {
            return normalizeUsers(JSON.parse(legacyPicker.getAttribute('data-users') || '[]'));
        }

        return [];
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('.staff-picker-compact__open');

        if (!button) {
            return;
        }

        event.preventDefault();
        openPickerModal(button.closest('[data-staff-picker]'));
    });

    document.addEventListener('DOMContentLoaded', function () {
        initPickers();
    });

    window.StaffCheckboxSelect = {
        buildHtml: buildHtml,
        buildPickerHtml: buildPickerHtml,
        readSelected: readSelected,
        setSelected: setSelected,
        initPickers: initPickers,
        usersFromOptionsHtml: usersFromOptionsHtml,
        usersFromJsonScript: usersFromJsonScript,
        usersFromSelect: usersFromSelect,
        getTeamUsers: getTeamUsers,
    };
})(window);
