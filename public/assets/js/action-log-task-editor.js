(function (window) {
    'use strict';

    const PROGRESS_OPTIONS = ['Not Started', 'In Progress', 'Done', 'Cancelled'];

    function progressBadgeClass(progress) {
        switch (progress) {
            case 'Done':
                return 'bg-light-success border border-success';
            case 'Cancelled':
                return 'bg-light-danger border border-danger';
            case 'In Progress':
                return 'bg-light-info border border-info';
            default:
                return 'bg-light-secondary border border-secondary';
        }
    }

    function dateOnlyPart(value) {
        const str = String(value || '').trim();
        if (!str) {
            return '';
        }
        return str.split(/[T ]/)[0];
    }

    function formatDateOnly(value) {
        const datePart = dateOnlyPart(value);
        if (!datePart) {
            return '—';
        }
        const match = datePart.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!match) {
            return datePart;
        }
        const dt = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
        if (isNaN(dt.getTime())) {
            return datePart;
        }
        return dt.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function toDateInputValue(value) {
        const datePart = dateOnlyPart(value);
        return /^\d{4}-\d{2}-\d{2}$/.test(datePart) ? datePart : '';
    }

    function genUid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return 'task_' + window.crypto.randomUUID();
        }
        return 'task_' + Date.now() + '_' + Math.random().toString(36).slice(2, 10);
    }

    function normalizeTask(task) {
        return {
            task_uid: String(task.task_uid || ''),
            task_no: Number(task.task_no || 0),
            task: String(task.task || ''),
            responsible_user_ids: Array.isArray(task.responsible_user_ids)
                ? task.responsible_user_ids.map(String)
                : [],
            progress: String(task.progress || 'Not Started'),
            due_date: toDateInputValue(task.due_date),
            compilation_date: toDateInputValue(task.compilation_date),
            notes_progress: String(task.notes_progress || ''),
        };
    }

    function buildUserNameMap(userOptionsSource) {
        const map = {};
        if (!userOptionsSource) {
            return map;
        }
        try {
            const users = JSON.parse(userOptionsSource.textContent);
            if (Array.isArray(users)) {
                users.forEach(function (user) {
                    if (user && user.id) {
                        map[String(user.id)] = String(user.name || '');
                    }
                });
            }
        } catch (e) {
            // ignore parse errors
        }
        return map;
    }

    function showAutosaveAlert(container, message, type) {
        if (!container) {
            return;
        }
        container.innerHTML = '';
        const alert = document.createElement('div');
        alert.className = 'alert alert-' + (type || 'success') + ' alert-dismissible fade show py-2 mb-3';
        alert.setAttribute('role', 'alert');
        alert.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        container.appendChild(alert);
        window.setTimeout(function () {
            if (alert.parentNode) {
                alert.classList.remove('show');
                window.setTimeout(function () {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 200);
            }
        }, 3500);
    }

    window.ActionLogTaskEditor = {
        init: function (config) {
            const form = document.getElementById(config.formId);
            const submitBtn = document.getElementById(config.submitBtnId || 'submitBtn');
            const hiddenActionInput = document.getElementById(config.hiddenActionId || 'hiddenAction');
            const taskRowsBody = document.getElementById('taskRowsBody');
            const taskTableContainer = document.getElementById('taskTableContainer');
            const taskTableBody = document.getElementById('taskTableBody');
            const taskListEmpty = document.getElementById('taskListEmpty');
            const addTaskRowBtn = document.getElementById('addTaskRowBtn');
            const modalEl = document.getElementById('actionLogTaskModal');
            const modalTitle = document.getElementById('actionLogTaskModalLabel');
            const modalSaveBtn = document.getElementById('actionLogTaskModalSave');
            const userOptionsSource = document.getElementById('actionLogUserOptionsSource');
            const modalResponsible = document.getElementById('actionLogModalResponsible');
            const autosaveAlert = document.getElementById('actionLogAutosaveAlert');
            const stateDisplay = document.getElementById('actionLogStateDisplay');

            let saveUrl = config.saveUrl;
            let usePutMethod = Boolean(config.usePutMethod);
            let documentState = config.documentState || 'draft';
            const periodId = config.periodId;
            const csrfToken = config.csrfToken;
            const userNameMap = buildUserNameMap(userOptionsSource);

            let tasks = [];
            let editingIndex = null;
            let autosaveInFlight = false;

            function assigneeLabel(task) {
                const ids = Array.isArray(task.responsible_user_ids) ? task.responsible_user_ids : [];
                const names = ids.map(function (id) {
                    return userNameMap[String(id)];
                }).filter(Boolean);
                return names.length > 0 ? names.join(', ') : '';
            }

            function fillProgressSelect(selected) {
                const sel = document.getElementById('actionLogModalProgress');
                if (!sel) {
                    return;
                }
                sel.innerHTML = '';
                PROGRESS_OPTIONS.forEach(function (label) {
                    const option = document.createElement('option');
                    option.value = label;
                    option.textContent = label;
                    if (label === selected) {
                        option.selected = true;
                    }
                    sel.appendChild(option);
                });
            }

            function cloneUserOptionsIntoModal(selectedIds) {
                if (typeof window.StaffCheckboxSelect === 'undefined') {
                    if (modalResponsible) {
                        modalResponsible.innerHTML = '';
                    }
                    return;
                }
                const users = window.StaffCheckboxSelect.usersFromJsonScript(userOptionsSource);
                modalResponsible.innerHTML = window.StaffCheckboxSelect.buildHtml({
                    users: users,
                    name: 'modal_responsible_user_ids[]',
                    selected: selectedIds || [],
                    title: 'Assigned Staff',
                    showTitle: true,
                    compact: false,
                });
            }

            function setManagerOnlyVisible() {
                document.querySelectorAll('.action-log-modal-manager-only').forEach(function (el) {
                    el.classList.remove('d-none');
                });
                const taskField = document.querySelector('.action-log-modal-field-task');
                const textarea = document.getElementById('actionLogModalTask');
                if (taskField && textarea) {
                    const label = taskField.querySelector('.form-label');
                    textarea.readOnly = false;
                    if (label) {
                        label.innerHTML = 'What needs to be done? <span class="text-danger">*</span>';
                    }
                }
            }

            function clearModal() {
                document.getElementById('actionLogModalTaskUid').value = '';
                document.getElementById('actionLogModalTask').value = '';
                document.getElementById('actionLogModalDueDate').value = '';
                document.getElementById('actionLogModalCompilationDate').value = '';
                document.getElementById('actionLogModalNotes').value = '';
                fillProgressSelect('Not Started');
                cloneUserOptionsIntoModal([]);
            }

            function readModal() {
                return {
                    task_uid: document.getElementById('actionLogModalTaskUid').value || genUid(),
                    task: document.getElementById('actionLogModalTask').value,
                    responsible_user_ids: window.StaffCheckboxSelect
                        ? window.StaffCheckboxSelect.readSelected(modalResponsible)
                        : [],
                    progress: document.getElementById('actionLogModalProgress').value,
                    due_date: document.getElementById('actionLogModalDueDate').value,
                    compilation_date: document.getElementById('actionLogModalCompilationDate').value,
                    notes_progress: document.getElementById('actionLogModalNotes').value,
                };
            }

            function fillModal(task) {
                document.getElementById('actionLogModalTaskUid').value = task.task_uid || '';
                document.getElementById('actionLogModalTask').value = task.task || '';
                document.getElementById('actionLogModalDueDate').value = task.due_date || '';
                document.getElementById('actionLogModalCompilationDate').value = task.compilation_date || '';
                document.getElementById('actionLogModalNotes').value = task.notes_progress || '';
                fillProgressSelect(task.progress || 'Not Started');
                cloneUserOptionsIntoModal(task.responsible_user_ids || []);
            }

            function showModal() {
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal && modalEl) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
            }

            function hideModal() {
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal && modalEl) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                }
            }

            function buildFormData(taskList, action) {
                const formData = new FormData();
                formData.append('_token', csrfToken);
                if (usePutMethod) {
                    formData.append('_method', 'PUT');
                }
                formData.append('action', action);
                formData.append('period_id', periodId);

                taskList.forEach(function (task, index) {
                    const uid = task.task_uid || genUid();
                    formData.append('content[tasks][' + index + '][task_uid]', uid);
                    formData.append('content[tasks][' + index + '][task]', task.task || '');
                    (task.responsible_user_ids || []).forEach(function (responsibleId) {
                        formData.append('content[tasks][' + index + '][responsible_user_ids][]', responsibleId);
                    });
                    formData.append('content[tasks][' + index + '][progress]', task.progress || 'Not Started');
                    formData.append('content[tasks][' + index + '][due_date]', task.due_date || '');
                    formData.append('content[tasks][' + index + '][compilation_date]', task.compilation_date || '');
                    formData.append('content[tasks][' + index + '][notes_progress]', task.notes_progress || '');
                });

                return formData;
            }

            function syncPayload() {
                if (!taskRowsBody) {
                    return;
                }
                taskRowsBody.innerHTML = '';
                tasks.forEach(function (task, index) {
                    if (!task.task_uid) {
                        task.task_uid = genUid();
                    }
                    const uidInput = document.createElement('input');
                    uidInput.type = 'hidden';
                    uidInput.name = 'content[tasks][' + index + '][task_uid]';
                    uidInput.value = task.task_uid;
                    taskRowsBody.appendChild(uidInput);

                    const taskInput = document.createElement('textarea');
                    taskInput.name = 'content[tasks][' + index + '][task]';
                    taskInput.className = 'd-none';
                    taskInput.textContent = task.task || '';
                    taskRowsBody.appendChild(taskInput);

                    (task.responsible_user_ids || []).forEach(function (responsibleId) {
                        const responsibleInput = document.createElement('input');
                        responsibleInput.type = 'hidden';
                        responsibleInput.name = 'content[tasks][' + index + '][responsible_user_ids][]';
                        responsibleInput.value = responsibleId;
                        taskRowsBody.appendChild(responsibleInput);
                    });

                    ['progress', 'due_date', 'compilation_date', 'notes_progress'].forEach(function (key) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'content[tasks][' + index + '][' + key + ']';
                        input.value = task[key] != null ? String(task[key]) : '';
                        taskRowsBody.appendChild(input);
                    });
                });
            }

            function updateSubmitVisibility() {
                if (!submitBtn) {
                    return;
                }
                if (documentState === 'draft') {
                    submitBtn.classList.remove('d-none');
                } else {
                    submitBtn.classList.add('d-none');
                }
            }

            function updateStateDisplay() {
                if (!stateDisplay) {
                    return;
                }
                stateDisplay.value = documentState === 'submitted' ? 'Submitted' : 'Draft';
            }

            function switchToUpdateMode(editUrl) {
                if (!form || !editUrl) {
                    return;
                }
                saveUrl = editUrl;
                usePutMethod = true;
                form.action = editUrl;
                let methodInput = form.querySelector('input[name="_method"]');
                if (!methodInput) {
                    methodInput = document.createElement('input');
                    methodInput.type = 'hidden';
                    methodInput.name = '_method';
                    form.appendChild(methodInput);
                }
                methodInput.value = 'PUT';
            }

            function applyServerResponse(data) {
                if (Array.isArray(data.tasks)) {
                    tasks = data.tasks.map(normalizeTask);
                }
                if (data.state) {
                    documentState = data.state;
                }
                if (data.edit_url && config.isCreateMode) {
                    switchToUpdateMode(data.edit_url);
                }
                renderTaskTable();
                updateSubmitVisibility();
                updateStateDisplay();
            }

            async function autosaveTasks() {
                if (autosaveInFlight) {
                    return;
                }
                if (!tasks.length) {
                    throw new Error('Please add at least one action log task.');
                }

                autosaveInFlight = true;
                if (modalSaveBtn) {
                    modalSaveBtn.disabled = true;
                }

                try {
                    const response = await fetch(saveUrl, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: buildFormData(tasks, 'draft'),
                    });

                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Failed to save task.');
                    }

                    applyServerResponse(data);
                    showAutosaveAlert(autosaveAlert, '<i class="ti ti-check me-1"></i>' + (data.message || 'Task saved.'), 'success');
                    return data;
                } finally {
                    autosaveInFlight = false;
                    if (modalSaveBtn) {
                        modalSaveBtn.disabled = false;
                    }
                }
            }

            function renderTaskTable() {
                if (!taskTableBody) {
                    return;
                }

                taskTableBody.innerHTML = '';

                if (!tasks.length) {
                    if (taskTableContainer) {
                        taskTableContainer.classList.add('d-none');
                    }
                    if (taskListEmpty) {
                        taskListEmpty.classList.remove('d-none');
                    }
                    syncPayload();
                    return;
                }

                if (taskTableContainer) {
                    taskTableContainer.classList.remove('d-none');
                }
                if (taskListEmpty) {
                    taskListEmpty.classList.add('d-none');
                }

                tasks.forEach(function (task, index) {
                    const row = document.createElement('tr');
                    const taskNumber = task.task_no > 0 ? task.task_no : index + 1;
                    const assigneeNames = assigneeLabel(task);
                    const progress = task.progress || 'Not Started';
                    const notes = (task.notes_progress || '').trim();

                    const numberCell = document.createElement('td');
                    numberCell.className = 'text-center fw-bold';
                    numberCell.textContent = String(taskNumber);

                    const taskCell = document.createElement('td');
                    taskCell.className = 'text-wrap small';
                    taskCell.textContent = task.task || '';

                    const responsibleCell = document.createElement('td');
                    responsibleCell.className = 'small';
                    if (assigneeNames) {
                        const span = document.createElement('span');
                        span.className = 'text-muted small';
                        span.textContent = assigneeNames;
                        responsibleCell.appendChild(span);
                    } else {
                        const badge = document.createElement('span');
                        badge.className = 'badge bg-light-warning border border-warning text-dark';
                        badge.textContent = 'Needs assignment';
                        responsibleCell.appendChild(badge);
                    }

                    const progressCell = document.createElement('td');
                    const progressBadge = document.createElement('span');
                    progressBadge.className = 'badge ' + progressBadgeClass(progress);
                    progressBadge.textContent = progress;
                    progressCell.appendChild(progressBadge);

                    const dueCell = document.createElement('td');
                    dueCell.className = 'small';
                    dueCell.textContent = formatDateOnly(task.due_date);

                    const compilationCell = document.createElement('td');
                    compilationCell.className = 'small';
                    compilationCell.textContent = formatDateOnly(task.compilation_date);

                    const notesCell = document.createElement('td');
                    notesCell.className = 'text-muted small';
                    notesCell.textContent = notes !== '' ? notes : '—';

                    const actionsCell = document.createElement('td');
                    actionsCell.className = 'text-nowrap';
                    const actionGroup = document.createElement('div');
                    actionGroup.className = 'btn-group btn-group-sm';

                    const editButton = document.createElement('button');
                    editButton.type = 'button';
                    editButton.className = 'btn btn-outline-primary js-edit-task';
                    editButton.setAttribute('data-index', String(index));
                    editButton.innerHTML = '<i class="ti ti-edit"></i>';

                    const deleteButton = document.createElement('button');
                    deleteButton.type = 'button';
                    deleteButton.className = 'btn btn-outline-danger js-remove-task';
                    deleteButton.setAttribute('data-index', String(index));
                    deleteButton.innerHTML = '<i class="ti ti-trash"></i>';

                    actionGroup.appendChild(editButton);
                    actionGroup.appendChild(deleteButton);
                    actionsCell.appendChild(actionGroup);

                    row.appendChild(numberCell);
                    row.appendChild(taskCell);
                    row.appendChild(responsibleCell);
                    row.appendChild(progressCell);
                    row.appendChild(dueCell);
                    row.appendChild(compilationCell);
                    row.appendChild(notesCell);
                    row.appendChild(actionsCell);

                    taskTableBody.appendChild(row);
                });

                syncPayload();
            }

            function loadInitialTasks() {
                const element = document.getElementById('action-log-initial-tasks');
                if (!element) {
                    tasks = [];
                    return;
                }
                try {
                    const parsed = JSON.parse(element.textContent);
                    tasks = Array.isArray(parsed) ? parsed.map(normalizeTask) : [];
                } catch (e) {
                    tasks = [];
                }
            }

            if (modalEl) {
                modalEl.addEventListener('show.bs.modal', function (event) {
                    const trigger = event.relatedTarget;
                    if (trigger && trigger.id === 'addTaskRowBtn') {
                        editingIndex = null;
                        modalTitle.textContent = 'Add task';
                        setManagerOnlyVisible();
                        clearModal();
                    }
                });
            }

            if (addTaskRowBtn) {
                addTaskRowBtn.addEventListener('click', function () {
                    editingIndex = null;
                    modalTitle.textContent = 'Add task';
                    setManagerOnlyVisible();
                    clearModal();
                });
            }

            if (taskTableBody) {
                taskTableBody.addEventListener('click', function (event) {
                    const editButton = event.target.closest('.js-edit-task');
                    const deleteButton = event.target.closest('.js-remove-task');

                    if (editButton) {
                        const index = parseInt(editButton.getAttribute('data-index'), 10);
                        if (Number.isNaN(index) || !tasks[index]) {
                            return;
                        }
                        editingIndex = index;
                        modalTitle.textContent = 'Edit task ' + (index + 1);
                        setManagerOnlyVisible();
                        fillModal(tasks[index]);
                        showModal();
                        return;
                    }

                    if (deleteButton) {
                        const index = parseInt(deleteButton.getAttribute('data-index'), 10);
                        if (Number.isNaN(index)) {
                            return;
                        }
                        if (tasks.length <= 1) {
                            window.alert('You need at least one task on the action log.');
                            return;
                        }
                        if (!window.confirm('Remove this task from the list?')) {
                            return;
                        }
                        const previousTasks = tasks.slice();
                        tasks.splice(index, 1);
                        renderTaskTable();
                        autosaveTasks().catch(function (error) {
                            tasks = previousTasks;
                            renderTaskTable();
                            showAutosaveAlert(autosaveAlert, error.message || 'Failed to remove task.', 'danger');
                        });
                    }
                });
            }

            if (modalSaveBtn) {
                modalSaveBtn.addEventListener('click', function () {
                    const modalTask = normalizeTask(readModal());
                    if (!modalTask.task.trim()) {
                        window.alert('Please describe what needs to be done.');
                        return;
                    }

                    const previousTasks = tasks.slice();
                    const previousIndex = editingIndex;

                    if (editingIndex === null) {
                        tasks.push(modalTask);
                    } else {
                        tasks[editingIndex] = modalTask;
                    }

                    autosaveTasks()
                        .then(function () {
                            hideModal();
                        })
                        .catch(function (error) {
                            tasks = previousTasks;
                            editingIndex = previousIndex;
                            renderTaskTable();
                            showAutosaveAlert(autosaveAlert, error.message || 'Failed to save task.', 'danger');
                        });
                });
            }

            if (submitBtn && hiddenActionInput) {
                submitBtn.addEventListener('click', function () {
                    hiddenActionInput.value = 'submit';
                });
            }

            if (form) {
                form.addEventListener('submit', function (event) {
                    syncPayload();
                    const clickedButton = event.submitter;
                    const action = clickedButton && clickedButton.value
                        ? clickedButton.value
                        : (hiddenActionInput ? hiddenActionInput.value : 'draft');

                    if (hiddenActionInput) {
                        hiddenActionInput.value = action;
                    }

                    if (action === 'submit') {
                        if (!tasks.length) {
                            event.preventDefault();
                            window.alert('Please add at least one task before submitting.');
                            return;
                        }
                        if (!window.confirm('Are you sure you want to submit this action log?')) {
                            event.preventDefault();
                            return;
                        }
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="ti ti-loader spinning me-1"></i> Submitting...';
                    }
                });
            }

            loadInitialTasks();
            renderTaskTable();
            updateSubmitVisibility();
            updateStateDisplay();
        },
    };
})(window);
