<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Weekly Objective Matrix</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="javascript:void(0)">Matrix Report</a></li>
                            </ul>
                        </div>
                        <div class="d-flex gap-2">
                            @if($currentPeriod)
                                <a href="{{ route('documents.weekly-objective-matrix.download', ['orientation' => 'portrait']) }}"
                                   class="btn btn-danger btn-sm">
                                    <i class="ti ti-download me-1"></i>Download PDF
                                </a>
                            @endif
                            <button type="button" class="btn btn-primary btn-sm"
                                    onclick="document.getElementById('matrix-create-card')?.scrollIntoView({behavior: 'smooth', block: 'start'});">
                                <i class="ti ti-plus me-1"></i>Add Department Matrix
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12">
            <div class="card shadow-none border">
                <div class="card-body">
                    @if(!$activeDepartmentId)
                        <div class="alert alert-warning mb-0">
                            <i class="ti ti-alert-triangle me-1"></i>
                            Your account is not assigned to any department, so you cannot create a department matrix yet.
                        </div>
                    @elseif(!$matrixPeriod)
                        <div class="alert alert-warning mb-0">
                            <i class="ti ti-alert-triangle me-1"></i>
                            There is no available work period for matrix creation yet.
                        </div>
                    @else
                        <div id="matrix-create-card" class="card border-primary shadow-none mb-0">
                            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Create / Update Department Weekly Matrix</h6>
                                <span class="badge bg-light text-dark">Week {{ $matrixPeriod->week_number }} • {{ $matrixPeriod->year }}</span>
                            </div>
                            <div class="card-body">
                                @php
                                    $summary = $planSubmissionSummary ?? ['expected_staff' => 0, 'submitted_staff' => 0, 'pending_staff' => 0, 'all_submitted' => false];
                                @endphp
                                <div class="alert alert-light border mb-3">
                                    <div class="fw-semibold mb-1">How department matrix works</div>
                                    <ul class="mb-2 ps-3 small">
                                        <li>Matrix has <strong>no submission deadline</strong> (unlike weekly plans).</li>
                                        <li>Activities are pulled only from <strong>submitted</strong> weekly plans in your department.</li>
                                        <li>You can start once at least one staff plan is submitted, then use <strong>Edit</strong> later to add activities from delayed submissions.</li>
                                    </ul>
                                    @if(($summary['expected_staff'] ?? 0) > 0)
                                        <div class="small">
                                            <strong>Weekly plan submissions:</strong>
                                            {{ $summary['submitted_staff'] ?? 0 }} of {{ $summary['expected_staff'] }} staff submitted
                                            @if(($summary['pending_staff'] ?? 0) > 0)
                                                <span class="text-warning">({{ $summary['pending_staff'] }} pending)</span>
                                            @else
                                                <span class="text-success">(all submitted)</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                @if($availableActivities->isEmpty())
                                    <div class="alert alert-info mb-0">
                                        <i class="ti ti-info-circle me-1"></i>
                                        No submitted weekly-plan activities found for your department this week yet. Check back after staff submit their plans.
                                    </div>
                                @elseif($impactOptions->isEmpty())
                                    <div class="alert alert-warning mb-0">
                                        <i class="ti ti-alert-circle me-1"></i>
                                        No department year-plan activities found for {{ $matrixPeriod->year }}.
                                    </div>
                                @else
                                    <form method="POST" action="{{ route('matrix-report.store') }}">
                                        @csrf
                                        <input type="hidden" name="period_id" value="{{ $matrixPeriod->id }}">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label">Overall Budget (TZS)</label>
                                                <input type="number" step="0.01" min="0"
                                                       name="overall_budget"
                                                       value="{{ old('overall_budget') }}"
                                                       class="form-control @error('overall_budget') is-invalid @enderror"
                                                       placeholder="e.g. 1500000">
                                                @error('overall_budget')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-8">
                                                <label class="form-label">Notes (optional)</label>
                                                <textarea name="notes" rows="2"
                                                          class="form-control @error('notes') is-invalid @enderror"
                                                          placeholder="Brief context for this week matrix">{{ old('notes') }}</textarea>
                                                @error('notes')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="table-responsive mt-3">
                                            <table class="table table-bordered align-middle mb-0">
                                                <thead class="table-light">
                                                <tr>
                                                    <th style="width: 26%;">Activity (from staff weekly plans)</th>
                                                    <th>Input</th>
                                                    <th>Output</th>
                                                    <th style="width: 24%;">Impact (Year Plan)</th>
                                                    <th style="width: 48px;"></th>
                                                </tr>
                                                </thead>
                                                <tbody id="matrixEntriesBody">
                                                @foreach($entryDefaults as $i => $entry)
                                                    @php $entry = is_array($entry) ? $entry : []; @endphp
                                                    <tr>
                                                        <td>
                                                            <select class="form-select @error("entries.$i.activity_key") is-invalid @enderror"
                                                                    name="entries[{{ $i }}][activity_key]" required>
                                                                <option value="">-- Select activity --</option>
                                                                @foreach($availableActivities as $activityOpt)
                                                                    <option value="{{ $activityOpt['key'] }}"
                                                                        {{ (string) old("entries.$i.activity_key", $entry['activity_key'] ?? '') === (string) $activityOpt['key'] ? 'selected' : '' }}>
                                                                        {{ $activityOpt['label'] }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                            @error("entries.$i.activity_key")
                                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                                            @enderror
                                                        </td>
                                                        <td>
                                                            <textarea rows="2"
                                                                      class="form-control @error("entries.$i.input") is-invalid @enderror"
                                                                      name="entries[{{ $i }}][input]"
                                                                      placeholder="Input" required>{{ old("entries.$i.input", $entry['input'] ?? '') }}</textarea>
                                                            @error("entries.$i.input")
                                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                                            @enderror
                                                        </td>
                                                        <td>
                                                            <textarea rows="2"
                                                                      class="form-control @error("entries.$i.output") is-invalid @enderror"
                                                                      name="entries[{{ $i }}][output]"
                                                                      placeholder="Output" required>{{ old("entries.$i.output", $entry['output'] ?? '') }}</textarea>
                                                            @error("entries.$i.output")
                                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                                            @enderror
                                                        </td>
                                                        <td>
                                                            <select class="form-select @error("entries.$i.impact_year_plan_activity_id") is-invalid @enderror"
                                                                    name="entries[{{ $i }}][impact_year_plan_activity_id]" required>
                                                                <option value="">-- Select impact --</option>
                                                                @foreach($impactOptions as $impactOpt)
                                                                    <option value="{{ $impactOpt['id'] }}"
                                                                        {{ (string) old("entries.$i.impact_year_plan_activity_id", $entry['impact_year_plan_activity_id'] ?? '') === (string) $impactOpt['id'] ? 'selected' : '' }}>
                                                                        {{ $impactOpt['label'] }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                            @error("entries.$i.impact_year_plan_activity_id")
                                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                                            @enderror
                                                        </td>
                                                        <td class="text-center align-middle">
                                                            <button type="button" class="btn btn-sm btn-outline-danger remove-matrix-row">&times;</button>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        @error('entries')
                                        <div class="text-danger small mt-2">{{ $message }}</div>
                                        @enderror

                                        <div class="d-flex justify-content-between align-items-center mt-3">
                                            <button type="button" id="addMatrixRowBtn" class="btn btn-sm btn-outline-primary">
                                                <i class="ti ti-plus me-1"></i>Add Row
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="ti ti-device-floppy me-1"></i>Save Department Matrix
                                            </button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0">Department Matrix Entries (Selected Matrix Week)</h6>
                </div>
                <div class="card-body">
                    @if($departmentMatrices->isEmpty())
                        <div class="alert alert-info mb-0">
                            <i class="ti ti-info-circle me-1"></i>
                            No department matrix has been created for the selected matrix week yet.
                        </div>
                    @else
                        <div class="row g-3">
                            @foreach($departmentMatrices as $matrix)
                                @php $entries = is_array($matrix->entries) ? $matrix->entries : []; @endphp
                                <div class="col-12">
                                    <div class="card border shadow-none mb-0">
                                        <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                                            <div>
                                                <strong>{{ $matrix->department?->name ?? 'Unknown Department' }}</strong>
                                                <span class="text-muted ms-2">Week {{ $matrix->period?->week_number }}</span>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-secondary">
                                                    Budget: {{ $matrix->overall_budget !== null ? number_format((float) $matrix->overall_budget, 2) : 'N/A' }}
                                                </span>
                                                <a href="{{ route('matrix-report.edit', $matrix) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="ti ti-edit me-1"></i>Edit
                                                </a>
                                                <form method="POST" action="{{ route('matrix-report.destroy', $matrix) }}"
                                                      onsubmit="return confirm('Delete this matrix?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="ti ti-trash me-1"></i>Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            @if(!empty($matrix->notes))
                                                <p class="mb-3"><strong>Notes:</strong> {{ $matrix->notes }}</p>
                                            @endif

                                            @if(empty($entries))
                                                <div class="text-muted">No matrix rows saved.</div>
                                            @else
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered align-middle mb-0">
                                                        <thead class="table-light">
                                                        <tr>
                                                            <th>Activity</th>
                                                            <th>Input</th>
                                                            <th>Output</th>
                                                            <th>Impact</th>
                                                        </tr>
                                                        </thead>
                                                        <tbody>
                                                        @foreach($entries as $entry)
                                                            <tr>
                                                                <td>{{ $entry['activity_label'] ?? ($entry['activity'] ?? '-') }}</td>
                                                                <td>{{ $entry['input'] ?? '-' }}</td>
                                                                <td>{{ $entry['output'] ?? '-' }}</td>
                                                                <td>{{ $entry['impact_label'] ?? '-' }}</td>
                                                            </tr>
                                                        @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @endif

                                            <div class="text-muted small mt-2">
                                                Last updated {{ optional($matrix->updated_at)->diffForHumans() }} by {{ $matrix->updater?->name ?? $matrix->creator?->name ?? 'Unknown' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0">Submitted Weekly Objective Reference</h6>
                </div>
                <div class="card-body">
                    @if(!$currentPeriod)
                        <div class="alert alert-warning mb-0">
                            <i class="ti ti-alert-triangle me-1"></i>
                            There is no active work period yet.
                        </div>
                    @elseif($matrixRows->isEmpty())
                        <div class="alert alert-info mb-0">
                            <i class="ti ti-info-circle me-1"></i>
                            No submitted weekly plans with a filled main objective were found for this week.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover table-striped align-middle mb-0">
                                <thead>
                                <tr>
                                    <th style="width: 260px;">Staff</th>
                                    <th style="width: 240px;">Department</th>
                                    <th>Main Objective</th>
                                    <th>Progress / Reflection</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($matrixRows as $plan)
                                    @php
                                        $planContent = is_array($plan->content ?? null) ? $plan->content : [];
                                        $objective = is_array($planContent['objective'] ?? null) ? $planContent['objective'] : [];
                                        $reflectionProgress = trim((string) ($objective['reflection_progress'] ?? ''));
                                    @endphp
                                    <tr>
                                        <td class="fw-semibold">{{ $plan->user?->name ?? 'Unknown Staff' }}</td>
                                        <td>{{ $plan->user?->department?->name ?? 'Unknown Sub Department' }}</td>
                                        <td>{{ $plan->main_target }}</td>
                                        <td>{{ $reflectionProgress !== '' ? $reflectionProgress : '-' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

@php
    $matrixActivityOptionList = $availableActivities
        ->map(function ($row) {
            return ['value' => $row['key'], 'label' => $row['label']];
        })
        ->values()
        ->all();
    $matrixImpactOptionList = $impactOptions
        ->map(function ($row) {
            return ['value' => $row['id'], 'label' => $row['label']];
        })
        ->values()
        ->all();
@endphp

<script type="application/json" id="matrix-activity-options-json">{!! json_encode($matrixActivityOptionList) !!}</script>
<script type="application/json" id="matrix-impact-options-json">{!! json_encode($matrixImpactOptionList) !!}</script>

@push('scripts')
    <script>
        (function () {
            const addBtn = document.getElementById('addMatrixRowBtn');
            const body = document.getElementById('matrixEntriesBody');
            if (!addBtn || !body) return;

            const activityOptions = JSON.parse(document.getElementById('matrix-activity-options-json')?.textContent || '[]');
            const impactOptions = JSON.parse(document.getElementById('matrix-impact-options-json')?.textContent || '[]');

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const renderOptions = (options, placeholder) => {
                let html = `<option value="">${escapeHtml(placeholder)}</option>`;
                options.forEach(opt => {
                    html += `<option value="${escapeHtml(opt.value)}">${escapeHtml(opt.label)}</option>`;
                });
                return html;
            };

            const buildRow = (index) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <select class="form-select" name="entries[${index}][activity_key]" required>
                            ${renderOptions(activityOptions, '-- Select activity --')}
                        </select>
                    </td>
                    <td>
                        <textarea rows="2" class="form-control" name="entries[${index}][input]" placeholder="Input" required></textarea>
                    </td>
                    <td>
                        <textarea rows="2" class="form-control" name="entries[${index}][output]" placeholder="Output" required></textarea>
                    </td>
                    <td>
                        <select class="form-select" name="entries[${index}][impact_year_plan_activity_id]" required>
                            ${renderOptions(impactOptions, '-- Select impact --')}
                        </select>
                    </td>
                    <td class="text-center align-middle">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-matrix-row">&times;</button>
                    </td>
                `;
                return tr;
            };

            const reindexRows = () => {
                const rows = Array.from(body.querySelectorAll('tr'));
                rows.forEach((row, idx) => {
                    row.querySelectorAll('select, textarea, input').forEach(el => {
                        if (!el.name) return;
                        el.name = el.name.replace(/entries\[\d+]/, `entries[${idx}]`);
                    });
                });
            };

            addBtn.addEventListener('click', function () {
                const index = body.querySelectorAll('tr').length;
                body.appendChild(buildRow(index));
            });

            body.addEventListener('click', function (event) {
                const removeButton = event.target.closest('.remove-matrix-row');
                if (!removeButton) return;
                const rows = body.querySelectorAll('tr');
                if (rows.length <= 1) {
                    return;
                }
                removeButton.closest('tr')?.remove();
                reindexRows();
            });
        })();
    </script>
@endpush
