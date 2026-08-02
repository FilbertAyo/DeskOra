{{-- Shared DeskOra AI action helpers (fail-open). Include once per page that needs draft/suggest buttons. --}}
<script>
(function () {
    if (window.DeskOraAiAssist) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const statusUrl = @json(route('ai-assist.status'));
    const draftUrl = @json(route('ai-assist.draft-weekly-report'));
    const rejectUrl = @json(route('ai-assist.suggest-rejection'));

    let availableCache = null;

    async function fetchJson(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.method && options.method !== 'GET' ? {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                } : {}),
                ...(options.headers || {}),
            },
            ...options,
        });
        let data = null;
        try { data = await response.json(); } catch (e) { data = null; }
        return { ok: response.ok, status: response.status, data };
    }

    async function isAvailable() {
        if (availableCache !== null) return availableCache;
        try {
            const { ok, data } = await fetchJson(statusUrl);
            availableCache = ok && !!(data && data.available);
        } catch (e) {
            availableCache = false;
        }
        return availableCache;
    }

    function setBusy(button, busy, label) {
        if (!button) return;
        button.disabled = !!busy;
        if (busy) {
            button.dataset.prevHtml = button.innerHTML;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>' + (label || 'Working…');
        } else if (button.dataset.prevHtml) {
            button.innerHTML = button.dataset.prevHtml;
        }
    }

    function toast(message, type) {
        if (!message) return;
        const el = document.createElement('div');
        el.className = 'alert alert-' + (type || 'info') + ' alert-dismissible fade show position-fixed';
        el.style.cssText = 'top:1rem;right:1rem;z-index:2000;max-width:360px;';
        el.setAttribute('role', 'alert');
        el.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 5000);
    }

    async function revealAiButtons(root) {
        const scope = root || document;
        const show = await isAvailable();
        scope.querySelectorAll('[data-ai-assist-only]').forEach((el) => {
            el.classList.toggle('d-none', !show);
        });
        // Draft can still run heuristic without AI — always show those.
        scope.querySelectorAll('[data-ai-draft-weekly-report]').forEach((el) => {
            el.classList.remove('d-none');
        });
    }

    async function draftWeeklyReport(button) {
        const periodInput = document.getElementById('period_id')
            || document.getElementById('period_id_hidden')
            || document.querySelector('[name="period_id"]');
        const periodId = periodInput ? periodInput.value : '';

        setBusy(button, true, 'Drafting…');
        try {
            const { ok, data } = await fetchJson(draftUrl, {
                method: 'POST',
                body: JSON.stringify({ period_id: periodId || null }),
            });

            if (!ok || !data || !data.draft) {
                toast((data && data.message) || 'Could not draft report right now.', 'warning');
                return;
            }

            const draft = data.draft;
            const summary = document.getElementById('weekly_report_summary')
                || document.querySelector('textarea[name="content[summary]"]');
            if (summary && draft.summary) {
                if (summary.value.trim() && !confirm('Replace the current summary with the AI draft?')) {
                    // Still offer achievements/challenges below if empty.
                } else {
                    summary.value = draft.summary;
                }
            }

            fillAchievements(draft.achievements || []);
            fillChallenges(draft.challenges || []);

            if (data.message) {
                toast(data.message, data.used_ai ? 'success' : 'info');
            } else {
                toast(data.used_ai ? 'Draft ready — review before submitting.' : 'Basic draft ready — review before submitting.', 'success');
            }
        } catch (e) {
            toast('AI currently unavailable.', 'warning');
        } finally {
            setBusy(button, false);
        }
    }

    function fillAchievements(items) {
        if (!items.length) return;
        const body = document.getElementById('achievementsBody')
            || document.querySelector('textarea[name="content[key_achievements][0]"]')?.closest('tbody');
        if (!body) {
            const first = document.querySelector('textarea[name^="content[key_achievements]"]');
            if (first && !first.value.trim()) first.value = items[0] || '';
            return;
        }

        const textareas = body.querySelectorAll('textarea[name^="content[key_achievements]"]');
        items.forEach((text, index) => {
            if (textareas[index]) {
                if (!textareas[index].value.trim()) {
                    textareas[index].value = text;
                }
            }
        });
    }

    function fillChallenges(items) {
        if (!items.length) return;
        items.forEach((item, index) => {
            const challenge = document.querySelector(`textarea[name="content[challenges_mitigation][${index}][challenge]"]`);
            const action = document.querySelector(`textarea[name="content[challenges_mitigation][${index}][action_taken_planned]"]`);
            if (challenge && !challenge.value.trim()) challenge.value = item.challenge || '';
            if (action && !action.value.trim()) action.value = item.action_taken_planned || '';
        });
    }

    async function suggestRejection(button) {
        const targetSelector = button.getAttribute('data-ai-target');
        const target = targetSelector ? document.querySelector(targetSelector) : null;
        if (!target) return;

        if (!(await isAvailable())) {
            toast('AI currently unavailable.', 'warning');
            button.classList.add('d-none');
            return;
        }

        const payload = {
            context_type: button.getAttribute('data-ai-context') || 'finance',
            title: button.getAttribute('data-ai-title') || '',
            details: button.getAttribute('data-ai-details') || '',
            amount: button.getAttribute('data-ai-amount') || '',
            requester: button.getAttribute('data-ai-requester') || '',
            outcome: button.getAttribute('data-ai-outcome') || 'rejected',
        };

        const outcomeInput = document.querySelector('input[name="action"]:checked');
        if (outcomeInput) payload.outcome = outcomeInput.value;

        setBusy(button, true, 'Suggesting…');
        try {
            const { ok, data } = await fetchJson(rejectUrl, {
                method: 'POST',
                body: JSON.stringify(payload),
            });

            if (!ok || !data || !data.available || !data.suggestion) {
                toast((data && data.message) || 'AI currently unavailable.', 'warning');
                if (data && data.available === false) {
                    document.querySelectorAll('[data-ai-assist-only]').forEach((el) => el.classList.add('d-none'));
                    availableCache = false;
                }
                return;
            }

            if (target.value.trim() && !confirm('Replace the current comment with the AI suggestion?')) {
                return;
            }
            target.value = data.suggestion;
            toast('Suggestion ready — edit before submitting.', 'success');
        } catch (e) {
            toast('AI currently unavailable.', 'warning');
        } finally {
            setBusy(button, false);
        }
    }

    document.addEventListener('click', (event) => {
        const draftBtn = event.target.closest('[data-ai-draft-weekly-report]');
        if (draftBtn) {
            event.preventDefault();
            draftWeeklyReport(draftBtn);
            return;
        }
        const rejectBtn = event.target.closest('[data-ai-suggest-rejection]');
        if (rejectBtn) {
            event.preventDefault();
            suggestRejection(rejectBtn);
        }
    });

    document.addEventListener('DOMContentLoaded', () => revealAiButtons(document));

    window.DeskOraAiAssist = { isAvailable, revealAiButtons, draftWeeklyReport, suggestRejection };
})();
</script>
