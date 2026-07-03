{{-- Monthly Staff Appreciation Nomination modal (anonymous). Rendered globally for eligible staff. --}}
<div class="modal fade" id="staffNominationModal" tabindex="-1" aria-labelledby="staffNominationModalLabel"
    aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-3">
                  
                    <div>
                        <h4 class="mb-0" id="staffNominationModalLabel">Monthly Staff Appreciation Nomination</h4>
                        <small class="text-success">For {{ $staffNominationPeriod?->label() }}</small>
                    </div>
                </div>
          </div>

            <div class="modal-body pt-2">
                <p class="text-muted mb-3">
                    Who made a positive impact on your work this month? Nominate one colleague who demonstrated
                    teamwork, leadership, helpfulness, or exceptional performance. Your nomination is
                    <strong>anonymous</strong> and can help management on staff recognition.
                </p>

                <div id="sanmAlert" class="alert d-none" role="alert"></div>

                <div class="mb-3 position-relative">
                    <label for="sanmSearch" class="form-label fw-semibold">Find a colleague</label>
                    <div class="position-relative">
                        <span class="position-absolute top-50 translate-middle-y ps-3 text-muted">
                            <i class="ti ti-search"></i>
                        </span>
                        <input type="text" class="form-control ps-5" id="sanmSearch" autocomplete="off"
                            placeholder="Search by name, title, or email...">
                    </div>

                    <div id="sanmResults" class="list-group position-absolute w-100 shadow-sm d-none"
                        style="z-index:1080;max-height:260px;overflow-y:auto;"></div>
                </div>

                <div id="sanmSelected" class="d-none align-items-center justify-content-between border rounded p-2 mb-3 bg-light">
                    <div class="d-flex align-items-center gap-2">
                        <img id="sanmSelectedAvatar" src="" alt="" class="rounded-circle" style="width:40px;height:40px;object-fit:cover;">
                        <div>
                            <div class="fw-semibold" id="sanmSelectedName"></div>
                            <small class="text-muted" id="sanmSelectedMeta"></small>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-link text-danger" id="sanmClearSelection">
                        <i class="ti ti-x"></i> Change
                    </button>
                </div>

                <input type="hidden" id="sanmNomineeId" value="">

                <div class="mb-1">
                    <label for="sanmReason" class="form-label fw-semibold">
                        Reason <span class="text-muted fw-normal">(optional)</span>
                    </label>
                    <textarea class="form-control" id="sanmReason" rows="3" maxlength="500"
                        placeholder="Briefly, why are you nominating this colleague?"></textarea>
                    <small class="text-muted"><span id="sanmReasonCount">0</span>/500</small>
                </div>
            </div>

            <div class="modal-footer border-0 d-flex justify-content-between flex-wrap gap-2">
                <button type="button" class="btn btn-link text-danger p-0" data-sanm-action="skip">
                    Skip This Month
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light" data-sanm-action="remind">
                        Remind Me Later
                    </button>
                    <button type="button" class="btn btn-primary" id="sanmSubmit" data-sanm-action="nominate" disabled>
                        <span class="sanm-label"><i class="ti ti-check me-1"></i>Nominate a Colleague</span>
                        <span class="sanm-spinner d-none">
                            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                            Submitting...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const modalEl = document.getElementById('staffNominationModal');
    if (!modalEl || typeof bootstrap === 'undefined') {
        return;
    }

    const routes = {
        search: @json(route('appreciation.staff-search')),
        nominate: @json(route('appreciation.nominate')),
        skip: @json(route('appreciation.skip')),
        remind: @json(route('appreciation.remind-later')),
    };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const searchInput = document.getElementById('sanmSearch');
    const resultsBox = document.getElementById('sanmResults');
    const selectedBox = document.getElementById('sanmSelected');
    const selectedAvatar = document.getElementById('sanmSelectedAvatar');
    const selectedName = document.getElementById('sanmSelectedName');
    const selectedMeta = document.getElementById('sanmSelectedMeta');
    const clearBtn = document.getElementById('sanmClearSelection');
    const nomineeIdInput = document.getElementById('sanmNomineeId');
    const reasonInput = document.getElementById('sanmReason');
    const reasonCount = document.getElementById('sanmReasonCount');
    const submitBtn = document.getElementById('sanmSubmit');
    const alertBox = document.getElementById('sanmAlert');

    const modal = new bootstrap.Modal(modalEl);
    let actionTaken = false;
    let searchTimer = null;

    function showAlert(type, message) {
        alertBox.className = 'alert alert-' + type;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    }

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body || {}),
        });
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function renderResults(items) {
        resultsBox.innerHTML = '';
        if (!items.length) {
            const empty = document.createElement('div');
            empty.className = 'list-group-item text-muted small';
            empty.textContent = 'No matching colleagues found.';
            resultsBox.appendChild(empty);
            resultsBox.classList.remove('d-none');
            return;
        }

        items.forEach(function (item) {
            const meta = [item.title, item.department].filter(Boolean).join(' • ');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action d-flex align-items-center gap-2';
            btn.innerHTML = '<img src="' + escapeHtml(item.avatar_url) + '" alt="" class="rounded-circle" style="width:32px;height:32px;object-fit:cover;">' +
                '<span class="text-start"><span class="d-block fw-semibold">' + escapeHtml(item.name) + '</span>' +
                '<small class="text-muted">' + escapeHtml(meta) + '</small></span>';
            btn.addEventListener('click', function () {
                selectColleague(item);
            });
            resultsBox.appendChild(btn);
        });
        resultsBox.classList.remove('d-none');
    }

    function selectColleague(item) {
        nomineeIdInput.value = item.id;
        selectedAvatar.src = item.avatar_url;
        selectedName.textContent = item.name;
        selectedMeta.textContent = [item.title, item.department].filter(Boolean).join(' • ');
        selectedBox.classList.remove('d-none');
        selectedBox.classList.add('d-flex');
        resultsBox.classList.add('d-none');
        searchInput.value = '';
        submitBtn.disabled = false;
    }

    function clearSelection() {
        nomineeIdInput.value = '';
        selectedBox.classList.add('d-none');
        selectedBox.classList.remove('d-flex');
        submitBtn.disabled = true;
    }

    function runSearch(term) {
        const url = routes.search + '?q=' + encodeURIComponent(term);
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : { data: [] }; })
            .then(function (payload) { renderResults(payload.data || []); })
            .catch(function () { /* silent */ });
    }

    function setSubmitting(isSubmitting) {
        submitBtn.disabled = isSubmitting || !nomineeIdInput.value;
        submitBtn.querySelector('.sanm-label').classList.toggle('d-none', isSubmitting);
        submitBtn.querySelector('.sanm-spinner').classList.toggle('d-none', !isSubmitting);
    }

    searchInput.addEventListener('input', function () {
        const term = searchInput.value.trim();
        clearTimeout(searchTimer);
        if (term.length < 1) {
            resultsBox.classList.add('d-none');
            return;
        }
        searchTimer = setTimeout(function () { runSearch(term); }, 250);
    });

    document.addEventListener('click', function (e) {
        if (!resultsBox.contains(e.target) && e.target !== searchInput) {
            resultsBox.classList.add('d-none');
        }
    });

    clearBtn.addEventListener('click', clearSelection);

    reasonInput.addEventListener('input', function () {
        reasonCount.textContent = reasonInput.value.length;
    });

    modalEl.querySelectorAll('[data-sanm-action]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const action = btn.getAttribute('data-sanm-action');

            if (action === 'nominate') {
                if (!nomineeIdInput.value) { return; }
                setSubmitting(true);
                post(routes.nominate, { nominee_id: nomineeIdInput.value, reason: reasonInput.value })
                    .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, body: d }; }); })
                    .then(function (res) {
                        if (res.ok && res.body.ok) {
                            actionTaken = true;
                            modal.hide();
                        } else {
                            setSubmitting(false);
                            showAlert('danger', res.body.message || 'Something went wrong. Please try again.');
                        }
                    })
                    .catch(function () {
                        setSubmitting(false);
                        showAlert('danger', 'Network error. Please try again.');
                    });
                return;
            }

            if (action === 'skip') {
                actionTaken = true;
                post(routes.skip).finally(function () { modal.hide(); });
                return;
            }

            // 'remind' and 'dismiss' both snooze for this session.
            actionTaken = true;
            post(routes.remind).finally(function () { modal.hide(); });
        });
    });

    // If the modal is dismissed without an explicit choice, snooze for the session.
    modalEl.addEventListener('hidden.bs.modal', function () {
        if (!actionTaken) {
            post(routes.remind);
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        modal.show();
    });
    if (document.readyState !== 'loading') {
        modal.show();
    }
})();
</script>
@endpush
