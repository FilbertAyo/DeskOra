(function () {
    function isInventoryPath() {
        return window.location.pathname.indexOf('/inventory') === 0;
    }

    function formMethod(form) {
        const override = form.querySelector('input[name="_method"]');
        if (override && override.value) {
            return String(override.value).toUpperCase();
        }

        return String(form.getAttribute('method') || 'GET').toUpperCase();
    }

    function isMutating(method) {
        return ['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(method) !== -1;
    }

    function ensureReturnUrl(form) {
        if (form.querySelector('[name="return_url"]')) {
            return;
        }

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'return_url';
        input.value = window.location.href;
        form.appendChild(input);
    }

    function busyLabel(button) {
        if (button.getAttribute('data-busy-label')) {
            return button.getAttribute('data-busy-label');
        }

        const text = (button.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
        if (text.indexOf('save') !== -1) return 'Saving…';
        if (text.indexOf('add') !== -1) return 'Saving…';
        if (text.indexOf('issue') !== -1) return 'Issuing…';
        if (text.indexOf('transfer') !== -1) return 'Transferring…';
        if (text.indexOf('delete') !== -1) return 'Deleting…';
        if (text.indexOf('approve') !== -1) return 'Approving…';
        if (text.indexOf('reject') !== -1) return 'Rejecting…';
        if (text.indexOf('submit') !== -1) return 'Submitting…';
        if (text.indexOf('confirm') !== -1) return 'Confirming…';
        if (text.indexOf('complete') !== -1) return 'Completing…';
        if (text.indexOf('record') !== -1) return 'Saving…';
        if (text.indexOf('start') !== -1) return 'Starting…';
        if (text.indexOf('report') !== -1) return 'Saving…';
        if (text.indexOf('adjust') !== -1) return 'Saving…';

        return 'Please wait…';
    }

    function markBusy(button) {
        if (!button || button.dataset.invBusy === '1') {
            return;
        }

        button.dataset.invBusy = '1';
        button.disabled = true;
        button.classList.add('is-inv-busy');
        button.setAttribute('aria-busy', 'true');
        button.innerHTML = '<i class="ti ti-loader-2 inv-busy-icon me-1"></i>' + busyLabel(button);
    }

    function highlightUpdatedRow() {
        const row = document.querySelector('tr.inv-just-updated');
        if (!row) {
            return;
        }

        if (typeof row.scrollIntoView === 'function') {
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    document.addEventListener('submit', function (event) {
        if (event.defaultPrevented) {
            return;
        }

        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !isInventoryPath()) {
            return;
        }

        if (form.getAttribute('data-skip-busy') === '1') {
            return;
        }

        if (!isMutating(formMethod(form))) {
            return;
        }

        if (form.dataset.invSubmitting === '1') {
            event.preventDefault();
            return;
        }

        form.dataset.invSubmitting = '1';
        ensureReturnUrl(form);

        const buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        const submitter = event.submitter;
        if (submitter && submitter.matches('button, input')) {
            markBusy(submitter);
        }

        buttons.forEach(function (button) {
            if (button !== submitter) {
                button.disabled = true;
            }
            if (button === submitter) {
                markBusy(button);
            }
        });
    });

    document.addEventListener('DOMContentLoaded', highlightUpdatedRow);
})();
