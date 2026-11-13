<style>
    @keyframes deskora-spin {
        to { transform: rotate(360deg); }
    }

    button.is-pending,
    input.is-pending,
    a.is-pending {
        cursor: wait !important;
        opacity: 0.72;
        pointer-events: none;
    }

    button.is-pending::before,
    a.btn.is-pending::before {
        content: "";
        display: inline-block;
        width: 0.85em;
        height: 0.85em;
        margin-right: 0.45em;
        border: 2px solid currentColor;
        border-right-color: transparent;
        border-radius: 999px;
        animation: deskora-spin 0.6s linear infinite;
        vertical-align: -0.125em;
    }

    .gooey-toast-wrap,
    .gooey-toast-expanded .gooey-toast-wrap,
    .gooey-toast-collapsing .gooey-toast-wrap,
    .gooey-toast-has-timer .gooey-toast-wrap,
    .gooey-toast-has-progress .gooey-toast-wrap {
        filter: none !important;
    }

    [aria-label="Notifications"] {
        align-items: center !important;
    }

    .gooey-toast-tab {
        border: 1px solid var(--gooey-toast-border);
        border-radius: 999px !important;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.28);
    }

    .gooey-toast-tab-chevron,
    .gooey-toast-body {
        display: none !important;
    }
</style>
<script>
    (function () {
        function release(el) {
            if (!el) return;
            el.classList.remove('is-pending');
            el.removeAttribute('aria-busy');
            if (el.dataset.pendingDisabled === '1') {
                el.disabled = false;
                delete el.dataset.pendingDisabled;
            }
        }

        function arm(el) {
            if (!el || el.classList.contains('is-pending')) return;
            el.classList.add('is-pending');
            el.setAttribute('aria-busy', 'true');
            if ('disabled' in el && !el.disabled) {
                el.disabled = true;
                el.dataset.pendingDisabled = '1';
            }
            window.setTimeout(function () { release(el); }, 15000);
        }

        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!form || form.tagName !== 'FORM') return;
            var button = event.submitter || form.querySelector('button[type="submit"], input[type="submit"]');
            if (!button) return;
            arm(button);
            window.setTimeout(function () {
                if (event.defaultPrevented) release(button);
            }, 700);
        }, true);

        document.addEventListener('click', function (event) {
            var el = event.target && event.target.closest ? event.target.closest('a.btn') : null;
            if (!el) return;
            if (el.closest('.pc-sidebar, .pc-header, .dropdown, .dropdown-menu')) return;
            if (el.matches('[data-bs-toggle], [data-bs-dismiss], [data-pc-toggle]')) return;
            var href = el.getAttribute('href') || '';
            if (href === '' || href.charAt(0) === '#') return;
            arm(el);
            window.setTimeout(function () { release(el); }, 2500);
        }, true);
    })();
</script>
