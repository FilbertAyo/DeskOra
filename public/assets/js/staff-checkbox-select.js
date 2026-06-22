(function (window) {
    'use strict';

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

    function buildHtml(options) {
        options = options || {};
        const users = normalizeUsers(options.users);
        const selected = normalizeSelected(options.selected);
        const name = options.name || 'staff_ids[]';
        const title = options.title !== undefined ? options.title : 'Assigned Staff';
        const showTitle = options.showTitle !== false && !options.compact;
        const compact = !!options.compact;
        const disabled = !!options.disabled;
        const groupId = options.id || ('staff-cb-' + Math.random().toString(36).slice(2, 10));
        const colClass = compact ? 'col-12' : 'col-12 col-sm-6 col-lg-4';

        let html = '<div class="staff-checkbox-select' + (compact ? ' staff-checkbox-select--compact' : '') + '" data-staff-checkbox-select>';

        if (showTitle) {
            html += '<div class="staff-checkbox-select__title form-label mb-2">' + escapeHtml(title) + '</div>';
        }

        html += '<div class="staff-checkbox-select__grid row g-2">';

        users.forEach(function (user) {
            const checked = selected.has(user.id) ? ' checked' : '';
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

    function resolveRoot(container) {
        if (!container) {
            return null;
        }

        return container.matches('[data-staff-checkbox-select]')
            ? container
            : container.querySelector('[data-staff-checkbox-select]');
    }

    function readSelected(container) {
        const root = resolveRoot(container);

        if (!root) {
            return [];
        }

        return Array.from(root.querySelectorAll('input[type="checkbox"]:checked')).map(function (checkbox) {
            return checkbox.value;
        });
    }

    function setSelected(container, selectedIds) {
        const root = resolveRoot(container);

        if (!root) {
            return;
        }

        const selected = normalizeSelected(selectedIds);

        root.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) {
            checkbox.checked = selected.has(String(checkbox.value));
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

        const legacySelect = document.querySelector('select[name^="content[planned_activities]"][name$="[team_member_ids][]"]')
            || document.querySelector('select[name^="content[key_meetings]"][name$="[participant_user_ids][]"]');

        return usersFromSelect(legacySelect);
    }

    window.StaffCheckboxSelect = {
        buildHtml: buildHtml,
        readSelected: readSelected,
        setSelected: setSelected,
        usersFromOptionsHtml: usersFromOptionsHtml,
        usersFromJsonScript: usersFromJsonScript,
        usersFromSelect: usersFromSelect,
        getTeamUsers: getTeamUsers,
    };
})(window);
