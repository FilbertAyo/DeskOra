<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Activity log</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="javascript: void(0)">Settings</a></li>
                                <li class="breadcrumb-item" aria-current="page">Activity log</li>
                            </ul>
                        </div>

                        <div class="col-auto d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-light" onclick="reloadPage()" title="Refresh">
                                <i class="ti ti-refresh"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-body border-bottom">
                    @php
                        $selectedCauserId = request('causer_id');
                        $staffIds = $staffUsers->pluck('id')->map(fn ($id) => (string) $id)->all();
                        $selectedCauserMissing = filled($selectedCauserId) && ! in_array((string) $selectedCauserId, $staffIds, true);
                    @endphp
                    <form method="GET" action="{{ route('activity-log.index') }}" class="row g-3 align-items-end">
                        <div class="col-md-3 col-lg-2">
                            <label class="form-label small mb-0" for="filter-from">From</label>
                            <input type="date" name="from" id="filter-from" class="form-control form-control-sm"
                                   value="{{ request('from') }}">
                        </div>
                        <div class="col-md-3 col-lg-2">
                            <label class="form-label small mb-0" for="filter-to">To</label>
                            <input type="date" name="to" id="filter-to" class="form-control form-control-sm"
                                   value="{{ request('to') }}">
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <label class="form-label small mb-0" for="filter-causer">Staff</label>
                            <select name="causer_id" id="filter-causer" class="form-select form-select-sm">
                                <option value="">All</option>
                                @foreach ($staffUsers as $u)
                                    <option value="{{ $u->id }}" @selected((string) request('causer_id') === (string) $u->id)>
                                        {{ $u->name }}{{ $u->status !== 'active' ? ' ('.$u->status.')' : '' }}
                                    </option>
                                @endforeach
                                @if ($selectedCauserMissing)
                                    <option value="{{ $selectedCauserId }}" selected>
                                        Unknown / deleted staff (#{{ $selectedCauserId }})
                                    </option>
                                @endif
                            </select>
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <label class="form-label small mb-0" for="filter-log">Log name</label>
                            <select name="log_name" id="filter-log" class="form-select form-select-sm">
                                <option value="">All</option>
                                @foreach ($logNames as $ln)
                                    <option value="{{ $ln }}" @selected(request('log_name') === $ln)>{{ $ln }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <label class="form-label small mb-0" for="filter-event">Event</label>
                            <select name="event" id="filter-event" class="form-select form-select-sm">
                                <option value="">All</option>
                                @foreach ($events as $ev)
                                    <option value="{{ $ev }}" @selected(request('event') === $ev)>{{ $ev }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-auto d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                            <a href="{{ route('activity-log.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                        </div>
                    </form>
                    <p class="text-muted small mt-2 mb-0">
                        Tracks staff create/update actions across documents, requests, approvals, settings, and logins.
                        Entries from removed users are shown as <span class="fw-semibold">Unknown / deleted staff</span> with their ID.
                    </p>
                </div>

                <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h5 class="mb-0"><i class="ti ti-list-details me-2"></i>Entries</h5>
                    <span class="text-muted small">{{ $activities->total() }} total</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered table-striped table-sm align-middle mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>When</th>
                                    <th>Staff</th>
                                    <th>Event</th>
                                    <th>Description</th>
                                    <th>Log</th>
                                    <th>Subject</th>
                                    <th class="text-end">Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($activities as $activity)
                                    @php
                                        $subjectLabel = '—';
                                        $causerLabel = 'System';

                                        if ($activity->causer?->name) {
                                            $causerLabel = $activity->causer->name;
                                        } elseif ($activity->causer_id) {
                                            $causerLabel = 'Unknown / deleted staff (#'.$activity->causer_id.')';
                                        }

                                        if ($activity->subject) {
                                            $subjectLabel = class_basename($activity->subject_type).' #'.$activity->subject_id;
                                            if (filled($activity->subject->title ?? null)) {
                                                $subjectLabel .= ' — '.\Illuminate\Support\Str::limit($activity->subject->title, 40);
                                            } elseif (filled($activity->subject->name ?? null)) {
                                                $subjectLabel .= ' — '.\Illuminate\Support\Str::limit($activity->subject->name, 40);
                                            } elseif (filled($activity->subject->request_no ?? null)) {
                                                $subjectLabel .= ' — '.$activity->subject->request_no;
                                            }
                                        } elseif ($activity->subject_type) {
                                            $subjectLabel = class_basename($activity->subject_type).' #'.$activity->subject_id;
                                        }
                                    @endphp
                                    <tr>
                                        <td class="text-nowrap small">{{ $activity->created_at->format('Y-m-d H:i') }}</td>
                                        <td class="small">{{ $causerLabel }}</td>
                                        <td><code class="small">{{ $activity->event ?? '—' }}</code></td>
                                        <td class="small">{{ \Illuminate\Support\Str::limit($activity->description, 80) }}</td>
                                        <td><span class="badge bg-light-light border border-light text-dark">{{ $activity->log_name ?? 'default' }}</span></td>
                                        <td class="small font-monospace">{{ $subjectLabel }}</td>
                                        <td class="text-end">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary js-activity-log-view"
                                                data-url="{{ route('activity-log.show', $activity) }}"
                                            >
                                                View
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">No activity entries match your filters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($activities->hasPages())
                        <div class="p-3 border-top">
                            {{ $activities->links('pagination::bootstrap-5') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="activityLogDetailModal" tabindex="-1" aria-labelledby="activityLogDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="activityLogDetailModalLabel">Activity detail</h5>
                        <p class="text-muted small mb-0" id="activityLogDetailSubtitle">Loading…</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="activityLogDetailLoading" class="text-center text-muted py-4 d-none">
                        <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
                        Loading activity details…
                    </div>
                    <div id="activityLogDetailError" class="alert alert-danger d-none mb-0" role="alert"></div>
                    <div id="activityLogDetailContent" class="d-none">
                        <dl class="row mb-0">
                            <dt class="col-sm-3 text-muted">When</dt>
                            <dd class="col-sm-9" id="activityLogDetailWhen">—</dd>

                            <dt class="col-sm-3 text-muted">Staff</dt>
                            <dd class="col-sm-9" id="activityLogDetailStaff">—</dd>

                            <dt class="col-sm-3 text-muted">Log name</dt>
                            <dd class="col-sm-9" id="activityLogDetailLogName">—</dd>

                            <dt class="col-sm-3 text-muted">Event</dt>
                            <dd class="col-sm-9"><code id="activityLogDetailEvent">—</code></dd>

                            <dt class="col-sm-3 text-muted">Description</dt>
                            <dd class="col-sm-9" id="activityLogDetailDescription">—</dd>

                            <dt class="col-sm-3 text-muted">Subject</dt>
                            <dd class="col-sm-9 font-monospace small" id="activityLogDetailSubject">—</dd>

                            <dt class="col-sm-3 text-muted d-none" id="activityLogDetailBatchLabel">Batch</dt>
                            <dd class="col-sm-9 font-monospace small d-none" id="activityLogDetailBatch">—</dd>
                        </dl>

                        <div id="activityLogDetailPropertiesWrap" class="mt-3 d-none">
                            <h6 class="mb-2">Properties</h6>
                            <pre class="small bg-light p-3 rounded mb-0 overflow-auto" style="max-height: 320px;" id="activityLogDetailProperties"></pre>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                const modalEl = document.getElementById('activityLogDetailModal');
                if (!modalEl || typeof bootstrap === 'undefined') {
                    return;
                }

                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                const loadingEl = document.getElementById('activityLogDetailLoading');
                const errorEl = document.getElementById('activityLogDetailError');
                const contentEl = document.getElementById('activityLogDetailContent');
                const subtitleEl = document.getElementById('activityLogDetailSubtitle');
                const propertiesWrap = document.getElementById('activityLogDetailPropertiesWrap');
                const propertiesEl = document.getElementById('activityLogDetailProperties');
                const batchLabel = document.getElementById('activityLogDetailBatchLabel');
                const batchValue = document.getElementById('activityLogDetailBatch');

                function setText(id, value) {
                    const el = document.getElementById(id);
                    if (el) {
                        el.textContent = value || '—';
                    }
                }

                function showState(state) {
                    loadingEl.classList.toggle('d-none', state !== 'loading');
                    errorEl.classList.toggle('d-none', state !== 'error');
                    contentEl.classList.toggle('d-none', state !== 'ready');
                }

                async function openActivityDetail(url) {
                    subtitleEl.textContent = 'Loading…';
                    errorEl.textContent = '';
                    showState('loading');
                    modal.show();

                    try {
                        const response = await fetch(url, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                        });

                        if (!response.ok) {
                            throw new Error('Unable to load activity details.');
                        }

                        const data = await response.json();
                        subtitleEl.textContent = 'Entry #' + data.id;
                        setText('activityLogDetailWhen', data.when);
                        setText(
                            'activityLogDetailStaff',
                            data.staff_email ? (data.staff + ' (' + data.staff_email + ')') : data.staff
                        );
                        setText('activityLogDetailLogName', data.log_name);
                        setText('activityLogDetailEvent', data.event);
                        setText('activityLogDetailDescription', data.description);
                        setText('activityLogDetailSubject', data.subject);

                        if (data.batch_uuid) {
                            batchLabel.classList.remove('d-none');
                            batchValue.classList.remove('d-none');
                            setText('activityLogDetailBatch', data.batch_uuid);
                        } else {
                            batchLabel.classList.add('d-none');
                            batchValue.classList.add('d-none');
                        }

                        if (data.properties_pretty) {
                            propertiesWrap.classList.remove('d-none');
                            propertiesEl.textContent = data.properties_pretty;
                        } else {
                            propertiesWrap.classList.add('d-none');
                            propertiesEl.textContent = '';
                        }

                        showState('ready');
                    } catch (error) {
                        subtitleEl.textContent = 'Activity detail';
                        errorEl.textContent = error.message || 'Unable to load activity details.';
                        showState('error');
                    }
                }

                document.querySelectorAll('.js-activity-log-view').forEach(function (button) {
                    button.addEventListener('click', function () {
                        const url = button.getAttribute('data-url');
                        if (url) {
                            openActivityDetail(url);
                        }
                    });
                });
            })();
        </script>
    @endpush
</x-app-layout>
