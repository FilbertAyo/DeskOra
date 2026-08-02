{{-- Shared DeskOra AI action helpers (fail-open). Include once per page that needs draft/suggest buttons. --}}
<script>
(function () {
    if (window.DeskOraAiAssist) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const statusUrl = @json(route('ai-assist.status'));
    const draftUrl = @json(route('ai-assist.draft-weekly-report'));
    const polishTipsUrl = @json(route('ai-assist.polish-performance-tips'));
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
        const activityProgress = collectActivityProgressRows();
        const hasFilledFields = Boolean(
            (document.getElementById('weekly_report_summary')?.value || '').trim()
            || activityProgress.some((row) => row.progress || row.remarks_challenges || row.next_step)
        );

        let overwrite = true;
        if (hasFilledFields) {
            overwrite = confirm('Replace filled report fields with the AI draft? Empty fields will always be filled.');
        }

        setBusy(button, true, 'Drafting…');
        try {
            const { ok, data } = await fetchJson(draftUrl, {
                method: 'POST',
                body: JSON.stringify({
                    period_id: periodId || null,
                    activity_progress: activityProgress,
                }),
            });

            if (!ok || !data || !data.draft) {
                toast((data && data.message) || 'Could not draft report right now.', 'warning');
                return;
            }

            const draft = data.draft;
            const summary = document.getElementById('weekly_report_summary')
                || document.querySelector('textarea[name="content[summary]"]');
            if (summary && draft.summary) {
                if (!summary.value.trim() || overwrite) {
                    summary.value = draft.summary;
                }
            }

            fillActivityProgress(draft.activity_progress || [], overwrite);
            fillAchievements(draft.achievements || [], overwrite);
            fillChallenges(draft.challenges || [], overwrite);

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

    function collectActivityProgressRows() {
        const body = document.getElementById('reportActivityProgressBody');
        if (!body) return [];

        return Array.from(body.querySelectorAll('tr')).map((row) => {
            const activity = row.querySelector('[name*="[activity_output]"]');
            const progress = row.querySelector('[name*="[progress]"]');
            const remarks = row.querySelector('[name*="[remarks_challenges]"]');
            const next = row.querySelector('[name*="[next_step]"]');
            return {
                activity_output: activity ? activity.value : '',
                progress: progress ? progress.value : '',
                remarks_challenges: remarks ? remarks.value : '',
                next_step: next ? next.value : '',
            };
        }).filter((row) => (row.activity_output || '').trim() !== '');
    }

    function fillActivityProgress(items, overwrite) {
        if (!items.length) return;
        const body = document.getElementById('reportActivityProgressBody');
        if (!body) return;

        const rows = Array.from(body.querySelectorAll('tr'));
        items.forEach((item) => {
            const label = (item.activity_output || '').trim();
            if (!label) return;

            const row = rows.find((candidate) => {
                const select = candidate.querySelector('[name*="[activity_output]"]');
                return select && select.value === label;
            });
            if (!row) return;

            const progress = row.querySelector('[name*="[progress]"]');
            const remarks = row.querySelector('[name*="[remarks_challenges]"]');
            const next = row.querySelector('[name*="[next_step]"]');

            if (progress && item.progress && (!progress.value || overwrite)) {
                progress.value = item.progress;
            }
            if (remarks && item.remarks_challenges && (!remarks.value.trim() || overwrite)) {
                remarks.value = item.remarks_challenges;
            }
            if (next && item.next_step && (!next.value.trim() || overwrite)) {
                next.value = item.next_step;
            }
        });
    }

    function fillAchievements(items, overwrite = false) {
        if (!items.length) return;
        const body = document.getElementById('achievementsBody')
            || document.querySelector('textarea[name="content[key_achievements][0]"]')?.closest('tbody');
        if (!body) {
            const first = document.querySelector('textarea[name^="content[key_achievements]"]');
            if (first && (!first.value.trim() || overwrite)) first.value = items[0] || '';
            return;
        }

        const textareas = body.querySelectorAll('textarea[name^="content[key_achievements]"]');
        items.forEach((text, index) => {
            if (textareas[index]) {
                if (!textareas[index].value.trim() || overwrite) {
                    textareas[index].value = text;
                }
            }
        });
    }

    function fillChallenges(items, overwrite = false) {
        if (!items.length) return;
        items.forEach((item, index) => {
            const challenge = document.querySelector(`textarea[name="content[challenges_mitigation][${index}][challenge]"]`);
            const action = document.querySelector(`textarea[name="content[challenges_mitigation][${index}][action_taken_planned]"]`);
            if (challenge && (!challenge.value.trim() || overwrite)) challenge.value = item.challenge || '';
            if (action && (!action.value.trim() || overwrite)) action.value = item.action_taken_planned || '';
        });
    }

    async function polishPerformanceTips(button) {
        const list = document.getElementById('staffPerformanceTips');
        if (!list) return;

        const tips = Array.from(list.querySelectorAll('li'))
            .map((li) => (li.textContent || '').trim())
            .filter(Boolean);
        if (!tips.length) {
            toast('No tips to polish.', 'info');
            return;
        }

        const metrics = window.__staffPerformancePayload
            ? {
                overall_score: window.__staffPerformancePayload.overall_score,
                pending_reviews: window.__staffPerformancePayload.pending_reviews,
            }
            : {};

        setBusy(button, true, 'Polishing…');
        try {
            const { ok, data } = await fetchJson(polishTipsUrl, {
                method: 'POST',
                body: JSON.stringify({ tips, metrics }),
            });

            if (!ok || !data || !Array.isArray(data.tips) || !data.tips.length) {
                toast((data && data.message) || 'Could not polish tips right now.', 'warning');
                return;
            }

            list.innerHTML = data.tips.map((tip) => '<li>' + String(tip)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;') + '</li>').join('');

            if (data.message) {
                toast(data.message, data.used_ai ? 'success' : 'info');
            } else {
                toast(data.used_ai ? 'Tips polished — still based on your metrics.' : 'Original tips kept.', 'success');
            }
        } catch (e) {
            toast('AI currently unavailable.', 'warning');
        } finally {
            setBusy(button, false);
        }
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
        const polishBtn = event.target.closest('[data-ai-polish-performance-tips]');
        if (polishBtn) {
            event.preventDefault();
            polishPerformanceTips(polishBtn);
            return;
        }
        const rejectBtn = event.target.closest('[data-ai-suggest-rejection]');
        if (rejectBtn) {
            event.preventDefault();
            suggestRejection(rejectBtn);
        }
    });

    document.addEventListener('DOMContentLoaded', () => revealAiButtons(document));

    window.DeskOraAiAssist = { isAvailable, revealAiButtons, draftWeeklyReport, polishPerformanceTips, suggestRejection };
})();
</script>
