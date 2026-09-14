{{--
  Advisory checks shown before weekly plan/report submit. Never blocks submit.

  EXTENSIBILITY — adding a new check:
    1. Add a <div data-check-panel="your-key"> block inside #weeklySubmitChecks.
    2. Register a runner in DeskOraWeeklySubmitChecks.runners[documentType] array.
    3. A runner is an async function(form, token) that must call
       DeskOraWeeklySubmitChecks.renderPanel(key, html) when done.
    4. Panels that are irrelevant for a document type are hidden via data-check-types.
--}}
<div class="modal fade" id="weeklySubmitScanModal" tabindex="-1"
    aria-labelledby="weeklySubmitScanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">

            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="weeklySubmitScanModalLabel">Submit this document?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body pt-1 pb-0">
                <p class="text-muted mb-3" id="weeklySubmitScanWarning">
                    Once submitted, it cannot be edited.
                </p>

                {{-- ── Check panels container ─────────────────────────────────────── --}}
                <div id="weeklySubmitChecks">

                    {{-- SMART target panel — weekly plan only --}}
                    <div data-check-panel="smart" data-check-types="weekly_plan" class="mb-3 d-none">
                        <div class="border rounded">
                            <button class="btn btn-link w-100 text-start text-decoration-none d-flex justify-content-between align-items-center px-3 py-2"
                                type="button" data-bs-toggle="collapse"
                                data-bs-target="#smartCheckBody" aria-expanded="true">
                                <span class="fw-semibold small">
                                    <i class="ti ti-target me-1 text-primary"></i>Main Target — SMART check
                                </span>
                                <span class="badge bg-light-secondary border smart-overall-badge d-none">—</span>
                            </button>
                            <div id="smartCheckBody" class="collapse show">
                                <div class="px-3 pb-3">

                                    {{-- Loading state --}}
                                    <div class="smart-loading d-flex align-items-center gap-2 small text-muted">
                                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                        <span>Evaluating main target…</span>
                                    </div>

                                    {{-- Result state --}}
                                    <div class="smart-result d-none">

                                        {{-- Overall bar --}}
                                        <div class="d-flex justify-content-between align-items-baseline mb-1">
                                            <span class="small text-muted">Overall SMART score</span>
                                            <strong class="small smart-overall-pct">—</strong>
                                        </div>
                                        <div class="progress mb-3" style="height: 8px;">
                                            <div class="progress-bar" role="progressbar" style="width:0%"
                                                id="smartOverallBar"></div>
                                        </div>

                                        {{-- Dimension bars --}}
                                        <div class="row g-2 mb-2 smart-dimension-rows"></div>

                                        {{-- Tips --}}
                                        <ul class="small text-muted mb-0 ps-3 smart-tips d-none"></ul>

                                        {{-- Fallback message --}}
                                        <p class="small text-muted mb-0 smart-message d-none"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- AI writing style panel — plans and reports --}}
                    <div data-check-panel="writing" data-check-types="weekly_plan,weekly_report" class="mb-3 d-none">
                        <div class="border rounded">
                            <button class="btn btn-link w-100 text-start text-decoration-none d-flex justify-content-between align-items-center px-3 py-2"
                                type="button" data-bs-toggle="collapse"
                                data-bs-target="#writingCheckBody" aria-expanded="true">
                                <span class="fw-semibold small">
                                    <i class="ti ti-writing me-1 text-secondary"></i>Writing style — AI elements
                                </span>
                                <span class="badge bg-light-secondary border writing-label-badge d-none">—</span>
                            </button>
                            <div id="writingCheckBody" class="collapse show">
                                <div class="px-3 pb-3">

                                    {{-- Loading state --}}
                                    <div class="writing-loading d-flex align-items-center gap-2 small text-muted">
                                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                        <span>Checking writing style across all sections…</span>
                                    </div>

                                    {{-- Result state --}}
                                    <div class="writing-result d-none">
                                        <div class="d-flex justify-content-between align-items-baseline mb-1">
                                            <span class="fw-semibold small writing-label-text">—</span>
                                        </div>
                                        <div class="mb-2">
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span>Likely human</span>
                                                <strong class="writing-human-pct">—</strong>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-success writing-human-bar"
                                                    role="progressbar" style="width:0%"></div>
                                            </div>
                                        </div>
                                        <div class="mb-2">
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span>AI-like elements</span>
                                                <strong class="writing-ai-pct">—</strong>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-warning writing-ai-bar"
                                                    role="progressbar" style="width:0%"></div>
                                            </div>
                                        </div>
                                        <ul class="small text-muted mb-0 ps-3 writing-signals"></ul>
                                        <p class="small text-muted mb-0 writing-message d-none"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ── Add future check panels here ─────────────────────────── --}}

                </div>{{-- /weeklySubmitChecks --}}
            </div>

            <div class="modal-footer border-0 pt-2">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="weeklySubmitScanConfirmBtn" disabled>
                    <span class="weekly-scan-btn-label">Checking…</span>
                </button>
            </div>

        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    'use strict';

    const csrf     = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const scanUrl  = @json(route('ai-assist.scan-weekly-writing'));
    const smartUrl = @json(route('ai-assist.evaluate-smart-target'));

    const modalEl      = document.getElementById('weeklySubmitScanModal');
    const titleEl      = document.getElementById('weeklySubmitScanModalLabel');
    const warningEl    = document.getElementById('weeklySubmitScanWarning');
    const confirmBtn   = document.getElementById('weeklySubmitScanConfirmBtn');
    const checksWrap   = document.getElementById('weeklySubmitChecks');

    if (!modalEl || !confirmBtn || !checksWrap) return;

    let pending    = null;
    let scanToken  = 0;
    let doneCount  = 0;
    let totalCount = 0;

    // ── Helpers ────────────────────────────────────────────────────────────────

    function q(sel, root) { return (root || document).querySelector(sel); }

    function nounFor(type) { return type === 'weekly_report' ? 'report' : 'plan'; }

    function barColor(pct) {
        if (pct >= 70) return 'bg-success';
        if (pct >= 40) return 'bg-warning';
        return 'bg-danger';
    }

    function dimLabel(key) {
        return { specific: 'Specific', measurable: 'Measurable',
                 achievable: 'Achievable', relevant: 'Relevant',
                 time_bound: 'Time-bound' }[key] || key;
    }

    function post(url, body, token) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        }).then(function (r) {
            return r.json().catch(function () { return null; });
        });
    }

    function setConfirmBusy(busy, label) {
        confirmBtn.disabled = !!busy;
        if (busy) {
            confirmBtn.innerHTML =
                '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>'
                + '<span class="weekly-scan-btn-label">' + (label || 'Checking…') + '</span>';
        } else {
            confirmBtn.innerHTML =
                '<i class="ti ti-send me-1"></i>'
                + '<span class="weekly-scan-btn-label">' + (label || 'Submit') + '</span>';
        }
    }

    function onCheckDone() {
        doneCount++;
        if (doneCount >= totalCount) {
            const noun = nounFor(pending && pending.documentType);
            setConfirmBusy(false, 'Submit ' + noun);
        }
    }

    // ── Form field collection ──────────────────────────────────────────────────

    function collectNamed(form, matcher) {
        const parts = [];
        form.querySelectorAll('textarea, input[type="text"], input:not([type])').forEach(function (el) {
            if (el.disabled) return;
            const name = el.getAttribute('name') || '';
            if (!matcher(name)) return;
            const val = (el.value || '').trim();
            if (val && val !== '__off_plan__') parts.push(val);
        });
        return parts;
    }

    function collectWeeklyWriting(form, documentType) {
        // On the review step the server pre-serialises the saved content.
        const sourceEl = document.getElementById('weekly-submit-scan-source');
        const parts = [];
        if (sourceEl) {
            try {
                const payload = JSON.parse(sourceEl.textContent || '{}');
                if (payload && typeof payload.text === 'string' && payload.text.trim()) {
                    parts.push(payload.text.trim());
                }
            } catch (e) { /* fall through to live fields */ }
        }

        if (!form) return parts.join('\n\n');

        if (documentType === 'weekly_plan') {
            const mt = form.querySelector('#main_target');
            if (mt && mt.value.trim()) parts.push(mt.value.trim());
            parts.push.apply(parts, collectNamed(form, function (n) {
                return n === 'content[objective][reflection_progress]'
                    || (n.includes('content[planned_activities]') && (n.includes('[activity]') || n.includes('[notes_inclusion]')))
                    || (n.includes('content[key_meetings]') && (n.includes('[meeting]') || n.includes('[reactions_notes]')))
                    || (n.includes('content[budget]') && (n.includes('[item]') || n.includes('[description_purpose]')));
            }));
        } else {
            parts.push.apply(parts, collectNamed(form, function (n) {
                return n === 'content[summary]'
                    || n === 'target_explanation'
                    || (n.includes('content[activity_progress]') && (
                        n.includes('[activity_output]') || n.includes('[off_plan_activity]')
                        || n.includes('[remarks_challenges]') || n.includes('[next_step]')))
                    || (n.includes('content[key_meetings]') && (n.includes('[meeting]') || n.includes('[reactions_notes]')))
                    || n.includes('content[key_achievements]')
                    || (n.includes('content[challenges_mitigation]') && (n.includes('[challenge]') || n.includes('[action_taken_planned]')));
            }));
        }
        return parts.filter(Boolean).join('\n\n');
    }

    function collectMainTarget(form) {
        if (!form) return '';
        const el = form.querySelector('#main_target');
        return el ? (el.value || '').trim() : '';
    }

    // ── Panel helpers ──────────────────────────────────────────────────────────

    function showPanel(key) {
        const panel = checksWrap.querySelector('[data-check-panel="' + key + '"]');
        if (panel) panel.classList.remove('d-none');
    }

    function resetPanel(key) {
        const panel = checksWrap.querySelector('[data-check-panel="' + key + '"]');
        if (!panel) return;
        panel.querySelectorAll('[class*="-loading"]').forEach(function (el) { el.classList.remove('d-none'); });
        panel.querySelectorAll('[class*="-result"]').forEach(function (el) { el.classList.add('d-none'); });
    }

    // ── SMART panel renderer ───────────────────────────────────────────────────

    function renderSmart(data) {
        const panel = checksWrap.querySelector('[data-check-panel="smart"]');
        if (!panel) return;

        const loading = q('.smart-loading', panel);
        const result  = q('.smart-result',  panel);
        const badge   = q('.smart-overall-badge', panel);
        const pctEl   = q('.smart-overall-pct',   panel);
        const bar     = q('#smartOverallBar',      panel);
        const dimsEl  = q('.smart-dimension-rows', panel);
        const tipsEl  = q('.smart-tips',           panel);
        const msgEl   = q('.smart-message',        panel);

        loading.classList.add('d-none');
        result.classList.remove('d-none');

        if (!data || !data.scores) {
            if (msgEl) {
                msgEl.textContent = data && data.message ? data.message : 'SMART check unavailable.';
                msgEl.classList.remove('d-none');
            }
            if (badge) { badge.textContent = 'N/A'; badge.classList.remove('d-none'); }
            return;
        }

        const overall = typeof data.overall === 'number' ? data.overall : 0;
        if (pctEl)  pctEl.textContent  = overall + '%';
        if (bar)    { bar.style.width = overall + '%'; bar.className = 'progress-bar ' + barColor(overall); }
        if (badge)  { badge.textContent = overall + '%'; badge.classList.remove('d-none'); badge.className = 'badge border smart-overall-badge ' + (overall >= 70 ? 'bg-light-success text-success' : overall >= 40 ? 'bg-light-warning text-warning' : 'bg-light-danger text-danger'); }

        if (dimsEl) {
            dimsEl.innerHTML = '';
            const dims = ['specific', 'measurable', 'achievable', 'relevant', 'time_bound'];
            dims.forEach(function (dim) {
                const score = typeof data.scores[dim] === 'number' ? data.scores[dim] : 0;
                const col = document.createElement('div');
                col.className = 'col-6 col-md-4';
                col.innerHTML =
                    '<div class="d-flex justify-content-between small mb-1">'
                    + '<span>' + dimLabel(dim) + '</span>'
                    + '<strong>' + score + '%</strong>'
                    + '</div>'
                    + '<div class="progress" style="height:6px;">'
                    + '<div class="progress-bar ' + barColor(score) + '" role="progressbar" style="width:' + score + '%"></div>'
                    + '</div>';
                dimsEl.appendChild(col);
            });
        }

        if (tipsEl) {
            const tips = Array.isArray(data.tips) ? data.tips : [];
            if (tips.length > 0) {
                tipsEl.innerHTML = '';
                tips.forEach(function (tip) {
                    const li = document.createElement('li');
                    li.textContent = tip;
                    tipsEl.appendChild(li);
                });
                tipsEl.classList.remove('d-none');
            } else {
                tipsEl.classList.add('d-none');
            }
        }
    }

    // ── Writing style panel renderer ───────────────────────────────────────────

    function renderWriting(data) {
        const panel = checksWrap.querySelector('[data-check-panel="writing"]');
        if (!panel) return;

        const loading    = q('.writing-loading',    panel);
        const result     = q('.writing-result',     panel);
        const labelText  = q('.writing-label-text', panel);
        const labelBadge = q('.writing-label-badge',panel);
        const humanPct   = q('.writing-human-pct',  panel);
        const aiPct      = q('.writing-ai-pct',     panel);
        const humanBar   = q('.writing-human-bar',  panel);
        const aiBar      = q('.writing-ai-bar',     panel);
        const signalsEl  = q('.writing-signals',    panel);
        const msgEl      = q('.writing-message',    panel);

        loading.classList.add('d-none');
        result.classList.remove('d-none');

        const aiPercent    = data && typeof data.ai_percent    === 'number' ? data.ai_percent    : null;
        const humanPercent = data && typeof data.human_percent === 'number' ? data.human_percent : null;
        const labelStr     = (data && data.label) || 'Writing check';

        if (labelText)  labelText.textContent  = labelStr;
        if (labelBadge) { labelBadge.textContent = labelStr; labelBadge.classList.remove('d-none'); }

        if (aiPercent === null || humanPercent === null) {
            if (humanPct) humanPct.textContent = '—';
            if (aiPct)    aiPct.textContent    = '—';
        } else {
            if (humanPct)  humanPct.textContent  = humanPercent + '%';
            if (aiPct)     aiPct.textContent     = aiPercent    + '%';
            if (humanBar)  humanBar.style.width  = humanPercent + '%';
            if (aiBar)     aiBar.style.width     = aiPercent    + '%';
        }

        if (signalsEl) {
            signalsEl.innerHTML = '';
            const signals = (data && Array.isArray(data.signals)) ? data.signals : [];
            signals.forEach(function (signal) {
                const li = document.createElement('li');
                li.textContent = signal;
                signalsEl.appendChild(li);
            });
        }

        if (msgEl && data && data.message && !(data.signals && data.signals.length)) {
            msgEl.textContent = data.message;
            msgEl.classList.remove('d-none');
        }
    }

    // ── Check runners ──────────────────────────────────────────────────────────
    //
    // Each runner is an async function(form, token) that calls renderXxx()
    // when its fetch resolves, then calls onCheckDone().
    // To add a new check: push a runner into the relevant documentType array.

    const runners = {
        weekly_plan: [
            // 1. SMART evaluation
            async function smartRunner(form, token) {
                const mainTarget = collectMainTarget(form);
                if (!mainTarget) {
                    renderSmart({ scores: null, message: 'No main target entered.' });
                    onCheckDone();
                    return;
                }
                try {
                    const data = await post(smartUrl, { main_target: mainTarget }, token);
                    if (scanToken !== token) return;
                    renderSmart(data);
                } catch (e) {
                    if (scanToken !== token) return;
                    renderSmart({ scores: null, message: 'SMART check unavailable.' });
                } finally {
                    if (scanToken === token) onCheckDone();
                }
            },
            // 2. AI writing style
            async function writingRunner(form, token) {
                const text = collectWeeklyWriting(form, 'weekly_plan');
                try {
                    const data = await post(scanUrl, { document_type: 'weekly_plan', text }, token);
                    if (scanToken !== token) return;
                    renderWriting(data);
                } catch (e) {
                    if (scanToken !== token) return;
                    renderWriting({ label: 'Could not check writing style', human_percent: null, ai_percent: null, signals: [] });
                } finally {
                    if (scanToken === token) onCheckDone();
                }
            },
        ],
        weekly_report: [
            // 1. AI writing style (only check for reports)
            async function writingRunner(form, token) {
                const text = collectWeeklyWriting(form, 'weekly_report');
                try {
                    const data = await post(scanUrl, { document_type: 'weekly_report', text }, token);
                    if (scanToken !== token) return;
                    renderWriting(data);
                } catch (e) {
                    if (scanToken !== token) return;
                    renderWriting({ label: 'Could not check writing style', human_percent: null, ai_percent: null, signals: [] });
                } finally {
                    if (scanToken === token) onCheckDone();
                }
            },
            // ── Add future report-specific checks here ──
        ],
    };

    // ── Modal lifecycle ────────────────────────────────────────────────────────

    confirmBtn.addEventListener('click', function () {
        if (!pending || !pending.form) return;
        pending.form.dataset.weeklyScanConfirmed = '1';
        const form     = pending.form;
        const submitter = pending.submitter;
        const modal = window.bootstrap?.Modal.getInstance(modalEl);
        if (modal) modal.hide();
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(submitter || undefined);
        } else {
            form.submit();
        }
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        scanToken += 1;
        pending   = null;
        doneCount = 0;
        totalCount = 0;
        setConfirmBusy(true, 'Checking…');
        // Reset all panels
        checksWrap.querySelectorAll('[data-check-panel]').forEach(function (panel) {
            panel.classList.add('d-none');
            resetPanel(panel.getAttribute('data-check-panel'));
        });
    });

    // ── Public API ─────────────────────────────────────────────────────────────

    window.DeskOraWeeklySubmitScan = {
        consumeConfirmed: function (form) {
            if (!form || form.dataset.weeklyScanConfirmed !== '1') return false;
            delete form.dataset.weeklyScanConfirmed;
            return true;
        },

        open: function (options) {
            const form         = options && options.form;
            const documentType = options && options.documentType;
            if (!form || (documentType !== 'weekly_plan' && documentType !== 'weekly_report')) {
                return false;
            }

            pending    = options;
            doneCount  = 0;
            scanToken += 1;
            const token = scanToken;
            const noun  = nounFor(documentType);

            titleEl.textContent  = 'Submit weekly ' + noun + '?';
            warningEl.textContent = 'Once submitted, this ' + noun + ' cannot be edited.';
            setConfirmBusy(true, 'Checking…');

            // Show only the panels relevant to this document type
            checksWrap.querySelectorAll('[data-check-panel]').forEach(function (panel) {
                const types = (panel.getAttribute('data-check-types') || '').split(',').map(function (s) { return s.trim(); });
                if (types.includes(documentType)) {
                    panel.classList.remove('d-none');
                    resetPanel(panel.getAttribute('data-check-panel'));
                } else {
                    panel.classList.add('d-none');
                }
            });

            const activeRunners = runners[documentType] || [];
            totalCount = activeRunners.length;

            if (totalCount === 0) {
                setConfirmBusy(false, 'Submit ' + noun);
            }

            const modal = window.bootstrap
                ? window.bootstrap.Modal.getOrCreateInstance(modalEl)
                : null;
            if (modal) modal.show();

            // Fire all checks in parallel
            activeRunners.forEach(function (runner) { runner(form, token); });

            return true;
        },
    };
})();
</script>
@endpush
