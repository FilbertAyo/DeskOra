(function () {
    function pickerData() {
        return window.inventoryPickerData || { locations: [], departments: [], users: [] };
    }

    function fillSelect(select, items, placeholder, selected) {
        const current = selected !== undefined ? selected : select.value;
        select.innerHTML = '';
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = placeholder;
        select.appendChild(empty);
        items.forEach(function (item) {
            const opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = item.name;
            select.appendChild(opt);
        });
        if (current && items.some(function (item) { return String(item.id) === String(current); })) {
            select.value = String(current);
        }
    }

    function initLocationRoom(wrap) {
        if (wrap.dataset.bound === '1') {
            return;
        }
        wrap.dataset.bound = '1';

        const locations = pickerData().locations || [];
        const branchSelect = wrap.querySelector('.js-location-branch');
        const roomSelect = wrap.querySelector('.js-location-room-select');
        const roomWrap = wrap.querySelector('.js-location-room-wrap');
        const hidden = wrap.querySelector('.js-location-id');
        const emptyLabel = wrap.dataset.emptyLabel || '-- Select --';
        const selected = wrap.dataset.selected || (hidden ? hidden.value : '') || '';
        const branches = locations.filter(function (location) { return !location.parent_id; });

        fillSelect(branchSelect, branches, emptyLabel, '');

        function roomsFor(branchId) {
            return locations.filter(function (location) {
                return String(location.parent_id) === String(branchId);
            });
        }

        function syncHidden() {
            if (!hidden) {
                return;
            }
            hidden.value = (roomSelect && roomSelect.value) || branchSelect.value || '';
        }

        function renderRooms(preferredRoom) {
            const branchId = branchSelect.value;
            const rooms = roomsFor(branchId);
            fillSelect(roomSelect, rooms, 'No specific room', preferredRoom || '');
            if (!branchId || rooms.length === 0) {
                roomWrap.classList.add('d-none');
                roomSelect.value = '';
            } else {
                roomWrap.classList.remove('d-none');
            }
            syncHidden();
        }

        if (selected) {
            const loc = locations.find(function (location) { return String(location.id) === String(selected); });
            if (loc) {
                if (loc.parent_id) {
                    branchSelect.value = String(loc.parent_id);
                    renderRooms(loc.id);
                } else {
                    branchSelect.value = String(loc.id);
                    renderRooms('');
                }
            } else {
                renderRooms('');
            }
        } else {
            renderRooms('');
        }

        branchSelect.addEventListener('change', function () {
            renderRooms('');
        });
        roomSelect.addEventListener('change', syncHidden);
    }

    function initOwnership(wrap) {
        if (wrap.dataset.bound === '1') {
            return;
        }
        wrap.dataset.bound = '1';

        const departments = pickerData().departments || [];
        const users = pickerData().users || [];
        const parentSelect = wrap.querySelector('.js-own-parent');
        const wholeDept = wrap.querySelector('.js-own-whole-dept');
        const subWrap = wrap.querySelector('.js-own-sub-wrap');
        const subSelect = wrap.querySelector('.js-own-sub');
        const wholeSubWrap = wrap.querySelector('.js-own-whole-sub-wrap');
        const wholeSub = wrap.querySelector('.js-own-whole-sub');
        const userWrap = wrap.querySelector('.js-own-user-wrap');
        const userSelect = wrap.querySelector('.js-own-user');
        const hiddenDept = wrap.querySelector('.js-own-dept-id');
        const hiddenUser = wrap.querySelector('.js-own-user-id');
        const summary = wrap.querySelector('.js-own-summary');
        const selectedDept = wrap.dataset.selectedDept || (hiddenDept ? hiddenDept.value : '') || '';
        const selectedUser = wrap.dataset.selectedUser || (hiddenUser ? hiddenUser.value : '') || '';
        const parents = departments.filter(function (department) { return !department.parent_id; });

        fillSelect(parentSelect, parents, '-- Select department --', '');
        wholeDept.checked = false;
        wholeSub.checked = false;
        subSelect.value = '';
        userSelect.value = '';

        function childrenOf(parentId) {
            return departments.filter(function (department) {
                return String(department.parent_id) === String(parentId);
            });
        }

        function usersFor(parentId, subId) {
            if (subId) {
                return users.filter(function (user) {
                    const subIds = user.sub_department_ids || [];
                    return String(user.department_id) === String(subId)
                        || subIds.map(String).indexOf(String(subId)) !== -1;
                });
            }
            if (!parentId) {
                return [];
            }
            const childIds = childrenOf(parentId).map(function (child) { return String(child.id); });
            return users.filter(function (user) {
                const subIds = (user.sub_department_ids || []).map(String);
                return String(user.department_id) === String(parentId)
                    || childIds.indexOf(String(user.department_id)) !== -1
                    || subIds.indexOf(String(parentId)) !== -1
                    || subIds.some(function (id) { return childIds.indexOf(id) !== -1; });
            });
        }

        function nameOf(list, id) {
            const match = list.find(function (item) { return String(item.id) === String(id); });
            return match ? match.name : '';
        }

        function sync() {
            const parentId = parentSelect.value;
            const subs = childrenOf(parentId);
            const wholeDeptChecked = wholeDept.checked;
            const currentSub = subSelect.value;
            const wholeSubChecked = wholeSub.checked;

            if (!parentId || wholeDeptChecked) {
                subWrap.classList.add('d-none');
                userWrap.classList.add('d-none');
                if (wholeDeptChecked) {
                    subSelect.value = '';
                    userSelect.value = '';
                    wholeSub.checked = false;
                }
            } else {
                fillSelect(subSelect, subs, '-- Select sub-department --', currentSub);
                if (subs.length === 0) {
                    subWrap.classList.add('d-none');
                    subSelect.value = '';
                    wholeSub.checked = false;
                    userWrap.classList.remove('d-none');
                    fillSelect(userSelect, usersFor(parentId, ''), '-- Select staff --', userSelect.value);
                } else {
                    subWrap.classList.remove('d-none');
                    wholeSubWrap.classList.toggle('d-none', !subSelect.value);
                    if (!subSelect.value) {
                        userWrap.classList.add('d-none');
                        userSelect.value = '';
                    } else if (wholeSubChecked) {
                        userWrap.classList.add('d-none');
                        userSelect.value = '';
                    } else {
                        userWrap.classList.remove('d-none');
                        fillSelect(userSelect, usersFor(parentId, subSelect.value), '-- Select staff --', userSelect.value);
                    }
                }
            }

            let deptId = '';
            let userId = '';
            if (wholeDept.checked && parentId) {
                deptId = parentId;
            } else if (wholeSub.checked && subSelect.value) {
                deptId = subSelect.value;
            } else if (userSelect.value) {
                userId = userSelect.value;
                deptId = subSelect.value || parentId;
            } else if (subSelect.value) {
                deptId = subSelect.value;
            } else if (parentId && childrenOf(parentId).length === 0) {
                deptId = parentId;
            }

            if (hiddenDept) {
                hiddenDept.value = deptId;
            }
            if (hiddenUser) {
                hiddenUser.value = userId;
            }

            if (summary) {
                const parts = [];
                const parentName = nameOf(parents, parentId);
                const subName = nameOf(departments, subSelect.value);
                const userName = nameOf(users, userSelect.value);
                if (parentName) {
                    parts.push(parentName);
                }
                if (subName && !wholeDept.checked) {
                    parts.push(subName);
                }
                if (userName) {
                    parts.push(userName);
                } else if (wholeDept.checked && parentName) {
                    parts.push('entire department');
                } else if (wholeSub.checked && subName) {
                    parts.push('entire sub-department');
                }
                summary.textContent = parts.length
                    ? parts.join(' → ')
                    : 'Choose a department, then optionally a sub-department or person.';
            }
        }

        if (selectedDept) {
            const dept = departments.find(function (department) {
                return String(department.id) === String(selectedDept);
            });
            if (dept) {
                if (dept.parent_id) {
                    parentSelect.value = String(dept.parent_id);
                    wholeDept.checked = false;
                    fillSelect(subSelect, childrenOf(dept.parent_id), '-- Select sub-department --', dept.id);
                    wholeSub.checked = !selectedUser;
                } else {
                    parentSelect.value = String(dept.id);
                    wholeDept.checked = !selectedUser;
                }
            }
        }

        if (selectedUser) {
            wholeDept.checked = false;
            wholeSub.checked = false;
            const user = users.find(function (item) { return String(item.id) === String(selectedUser); });
            if (user && !parentSelect.value) {
                const userDept = departments.find(function (department) {
                    return String(department.id) === String(user.department_id);
                });
                if (userDept) {
                    if (userDept.parent_id) {
                        parentSelect.value = String(userDept.parent_id);
                        fillSelect(subSelect, childrenOf(userDept.parent_id), '-- Select sub-department --', userDept.id);
                    } else {
                        parentSelect.value = String(userDept.id);
                        const subIds = user.sub_department_ids || [];
                        if (subIds.length) {
                            fillSelect(subSelect, childrenOf(userDept.id), '-- Select sub-department --', subIds[0]);
                        }
                    }
                }
            }
        }

        sync();
        if (selectedUser) {
            userSelect.value = String(selectedUser);
            if (hiddenUser) {
                hiddenUser.value = String(selectedUser);
            }
            sync();
        }

        parentSelect.addEventListener('change', function () {
            subSelect.value = '';
            userSelect.value = '';
            wholeSub.checked = false;
            sync();
        });
        wholeDept.addEventListener('change', sync);
        subSelect.addEventListener('change', function () {
            userSelect.value = '';
            if (!subSelect.value) {
                wholeSub.checked = false;
            }
            sync();
        });
        wholeSub.addEventListener('change', sync);
        userSelect.addEventListener('change', sync);
    }

    function init(root) {
        (root || document).querySelectorAll('.js-location-room').forEach(initLocationRoom);
        (root || document).querySelectorAll('.js-ownership-cascade').forEach(initOwnership);
    }

    function rebind(root) {
        (root || document).querySelectorAll('.js-location-room, .js-ownership-cascade').forEach(function (el) {
            delete el.dataset.bound;
        });
        init(root);
    }

    window.InventoryPickers = { init: init, rebind: rebind };

    document.addEventListener('DOMContentLoaded', function () {
        init(document);
    });
})();
