(function () {
    const board = document.querySelector('.kanban-board');
    if (!board) return;

    const moveUrl = board.getAttribute('data-move-url');
    const csrf = board.getAttribute('data-csrf') || '';
    let dragCard = null;

    function updateCounts() {
        board.querySelectorAll('.kanban-column').forEach((column) => {
            const count = column.querySelectorAll('.kanban-card').length;
            const badge = column.querySelector('.kanban-column__count');
            if (badge) badge.textContent = String(count);
            const body = column.querySelector('.kanban-column__body');
            if (!body) return;
            let empty = body.querySelector('.kanban-empty');
            if (count === 0) {
                if (!empty) {
                    empty = document.createElement('div');
                    empty.className = 'kanban-empty';
                    empty.textContent = 'No tasks';
                    body.appendChild(empty);
                }
            } else if (empty) {
                empty.remove();
            }
        });
    }

    function toast(message, type) {
        const el = document.createElement('div');
        el.className = 'alert alert-' + (type || 'info') + ' alert-dismissible fade show position-fixed';
        el.style.cssText = 'top:1rem;right:1rem;z-index:2000;max-width:360px;';
        el.setAttribute('role', 'alert');
        el.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 4000);
    }

    async function persistMove(card, status, previousParent, nextSibling) {
        const taskUid = card.getAttribute('data-task-uid');
        try {
            const response = await fetch(moveUrl, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    task_uid: taskUid,
                    progress: status,
                }),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.success) {
                previousParent.insertBefore(card, nextSibling);
                updateCounts();
                toast((data && data.message) || 'Could not move task.', 'warning');
                return;
            }
            card.setAttribute('data-progress', status);
        } catch (e) {
            previousParent.insertBefore(card, nextSibling);
            updateCounts();
            toast('Could not move task.', 'warning');
        }
    }

    board.querySelectorAll('.kanban-card[draggable="true"]').forEach((card) => {
        card.addEventListener('dragstart', (event) => {
            dragCard = card;
            card.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', card.getAttribute('data-task-uid') || '');
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('is-dragging');
            board.querySelectorAll('.kanban-column__body').forEach((zone) => zone.classList.remove('is-over'));
            dragCard = null;
        });
    });

    board.querySelectorAll('[data-drop-zone]').forEach((zone) => {
        zone.addEventListener('dragover', (event) => {
            event.preventDefault();
            zone.classList.add('is-over');
            event.dataTransfer.dropEffect = 'move';
        });
        zone.addEventListener('dragleave', () => zone.classList.remove('is-over'));
        zone.addEventListener('drop', (event) => {
            event.preventDefault();
            zone.classList.remove('is-over');
            if (!dragCard) return;

            const status = zone.getAttribute('data-drop-zone');
            const previousParent = dragCard.parentElement;
            const nextSibling = dragCard.nextSibling;
            const previousStatus = dragCard.getAttribute('data-progress');

            if (status === previousStatus && previousParent === zone) {
                return;
            }

            const empty = zone.querySelector('.kanban-empty');
            if (empty) empty.remove();
            zone.appendChild(dragCard);
            updateCounts();
            persistMove(dragCard, status, previousParent, nextSibling);
        });
    });
})();
