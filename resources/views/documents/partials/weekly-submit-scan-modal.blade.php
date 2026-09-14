{{-- Advisory AI-style check shown before weekly plan/report submit. Never blocks submit. --}}
<div class="modal fade" id="weeklySubmitScanModal" tabindex="-1" aria-labelledby="weeklySubmitScanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="weeklySubmitScanModalLabel">Submit this document?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="text-muted mb-3" id="weeklySubmitScanWarning">
                    Once submitted, it cannot be edited.
                </p>

                <div id="weeklySubmitScanLoading" class="border rounded p-3 bg-light">
                    <div class="d-flex align-items-center gap-2">
                        <span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>
                        <span>Checking writing style across all sections…</span>
                    </div>
                </div>

                <div id="weeklySubmitScanResult" class="d-none">
                    <div class="d-flex justify-content-between align-items-baseline mb-1">
                        <span class="fw-semibold" id="weeklySubmitScanLabel">Looks mostly human</span>
                        <span class="badge bg-light-secondary border" id="weeklySubmitScanBadge">Guide only</span>
                    </div>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Likely human</span>
                            <strong id="weeklySubmitScanHumanPct">—</strong>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" id="weeklySubmitScanHumanBar" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>AI-like elements</span>
                            <strong id="weeklySubmitScanAiPct">—</strong>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-warning" id="weeklySubmitScanAiBar" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                    <ul class="small text-muted mb-0 ps-3" id="weeklySubmitScanSignals"></ul>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
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
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const scanUrl = @json(route('ai-assist.scan-weekly-writing'));
    const modalEl = document.getElementById('weeklySubmitScanModal');
    const titleEl = document.getElementById('weeklySubmitScanModalLabel');
    const warningEl = document.getElementById('weeklySubmitScanWarning');
    const loadingEl = document.getElementById('weeklySubmitScanLoading');
    const resultEl = document.getElementById('weeklySubmitScanResult');
    const labelEl = document.getElementById('weeklySubmitScanLabel');
    const humanPctEl = document.getElementById('weeklySubmitScanHumanPct');
    const aiPctEl = document.getElementById('weeklySubmitScanAiPct');
    const humanBarEl = document.getElementById('weeklySubmitScanHumanBar');
    const aiBarEl = document.getElementById('weeklySubmitScanAiBar');
    const signalsEl = document.getElementById('weeklySubmitScanSignals');
    const confirmBtn = document.getElementById('weeklySubmitScanConfirmBtn');

    if (!modalEl || !confirmBtn) {
        return;
    }

    let pending = null;
    let scanToken = 0;

    function nounFor(type) {
        return type === 'weekly_report' ? 'report' : 'plan';
    }

    function collectNamed(form, matcher) {
        const parts = [];
        form.querySelectorAll('textarea, input[type="text"], input:not([type])').forEach(function (el) {
            if (el.disabled) return;
            const name = el.getAttribute('name') || '';
            if (!matcher(name)) return;
            const value = (el.value || '').trim();
            if (value && value !== '__off_plan__') {
                parts.push(value);
            }
        });
        return parts;
    }

    function collectWeeklyWriting(form, documentType) {
        const sourceEl = document.getElementById('weekly-submit-scan-source');
        const parts = [];
        if (sourceEl) {
            try {
                const payload = JSON.parse(sourceEl.textContent || '{}');
                if (payload && typeof payload.text === 'string' && payload.text.trim()) {
                    parts.push(payload.text.trim());
                }
            } catch (e) {
                // Fall through to live fields.
            }
        }

        if (!form) {
            return parts.join('\n\n');
        }

        if (documentType === 'weekly_plan') {
            const mainTarget = form.querySelector('#main_target');
            if (mainTarget && mainTarget.value.trim()) {
                parts.push(mainTarget.value.trim());
            }
            parts.push.apply(parts, collectNamed(form, function (name) {
                return name === 'content[objective][reflection_progress]'
                    || (name.indexOf('content[planned_activities]') === 0 && (name.indexOf('[activity]') !== -1 || name.indexOf('[notes_inclusion]') !== -1))
                    || (name.indexOf('content[key_meetings]') === 0 && (name.indexOf('[meeting]') !== -1 || name.indexOf('[reactions_notes]') !== -1))
                    || (name.indexOf('content[budget]') === 0 && (name.indexOf('[item]') !== -1 || name.indexOf('[description_purpose]') !== -1));
            }));
        } else {
            parts.push.apply(parts, collectNamed(form, function (name) {
                return name === 'content[summary]'
                    || name === 'target_explanation'
                    || (name.indexOf('content[activity_progress]') === 0 && (
                        name.indexOf('[activity_output]') !== -1
                        || name.indexOf('[off_plan_activity]') !== -1
                        || name.indexOf('[remarks_challenges]') !== -1
                        || name.indexOf('[next_step]') !== -1
                    ))
                    || (name.indexOf('content[key_meetings]') === 0 && (name.indexOf('[meeting]') !== -1 || name.indexOf('[reactions_notes]') !== -1))
                    || name.indexOf('content[key_achievements]') === 0
                    || (name.indexOf('content[challenges_mitigation]') === 0 && (
                        name.indexOf('[challenge]') !== -1
                        || name.indexOf('[action_taken_planned]') !== -1
                    ));
            }));
        }

        return parts.filter(Boolean).join('\n\n');
    }

    function setConfirmBusy(busy, label) {
        confirmBtn.disabled = !!busy;
        if (busy) {
            confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>'
                + '<span class="weekly-scan-btn-label">' + (label || 'Checking…') + '</span>';
        } else {
            confirmBtn.innerHTML = '<i class="ti ti-send me-1"></i><span class="weekly-scan-btn-label">' + (label || 'Submit') + '</span>';
        }
    }

    function renderResult(data, documentType) {
        loadingEl.classList.add('d-none');
        resultEl.classList.remove('d-none');

        const aiPercent = data && typeof data.ai_percent === 'number' ? data.ai_percent : null;
        const humanPercent = data && typeof data.human_percent === 'number' ? data.human_percent : null;
        labelEl.textContent = (data && data.label) || 'Writing check';

        if (aiPercent === null || humanPercent === null) {
            humanPctEl.textContent = '—';
            aiPctEl.textContent = '—';
            humanBarEl.style.width = '0%';
            aiBarEl.style.width = '0%';
        } else {
            humanPctEl.textContent = humanPercent + '%';
            aiPctEl.textContent = aiPercent + '%';
            humanBarEl.style.width = humanPercent + '%';
            aiBarEl.style.width = aiPercent + '%';
        }

        signalsEl.innerHTML = '';
        const signals = (data && Array.isArray(data.signals)) ? data.signals : [];
        signals.forEach(function (signal) {
            const li = document.createElement('li');
            li.textContent = signal;
            signalsEl.appendChild(li);
        });
        if (data && data.message && signals.length === 0) {
            const li = document.createElement('li');
            li.textContent = data.message;
            signalsEl.appendChild(li);
        }
    }

    async function runScan(documentType, text, token) {
        try {
            const response = await fetch(scanUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    document_type: documentType,
                    text: text,
                }),
            });
            const data = await response.json().catch(function () { return null; });
            if (token !== scanToken) return;
            renderResult(data, documentType);
        } catch (e) {
            if (token !== scanToken) return;
            renderResult({
                label: 'Could not check writing style',
                human_percent: null,
                ai_percent: null,
                signals: [],
            }, documentType);
        } finally {
            if (token === scanToken) {
                setConfirmBusy(false, 'Submit ' + nounFor(documentType));
            }
        }
    }

    confirmBtn.addEventListener('click', function () {
        if (!pending || !pending.form) return;
        pending.form.dataset.weeklyScanConfirmed = '1';
        const form = pending.form;
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
        pending = null;
        setConfirmBusy(true, 'Checking…');
        loadingEl.classList.remove('d-none');
        resultEl.classList.add('d-none');
    });

    window.DeskOraWeeklySubmitScan = {
        consumeConfirmed: function (form) {
            if (!form || form.dataset.weeklyScanConfirmed !== '1') {
                return false;
            }
            delete form.dataset.weeklyScanConfirmed;
            return true;
        },
        open: function (options) {
            const form = options && options.form;
            const documentType = options && options.documentType;
            if (!form || (documentType !== 'weekly_plan' && documentType !== 'weekly_report')) {
                return false;
            }

            pending = options;
            scanToken += 1;
            const token = scanToken;
            const noun = nounFor(documentType);
            titleEl.textContent = 'Submit weekly ' + noun + '?';
            warningEl.textContent = 'Once submitted, this ' + noun + ' cannot be edited.';
            loadingEl.classList.remove('d-none');
            resultEl.classList.add('d-none');
            setConfirmBusy(true, 'Checking…');

            const modal = window.bootstrap
                ? window.bootstrap.Modal.getOrCreateInstance(modalEl)
                : null;
            if (modal) {
                modal.show();
            }

            const text = collectWeeklyWriting(form, documentType);
            runScan(documentType, text, token);
            return true;
        },
    };
})();
</script>
@endpush
