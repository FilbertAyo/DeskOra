<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Edit Parent Department Matrix</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('matrix-report.index') }}">Matrix Report</a></li>
                                <li class="breadcrumb-item"><a href="javascript:void(0)">Edit</a></li>
                            </ul>
                        </div>
                        <a href="{{ route('matrix-report.index') }}" class="btn btn-light btn-sm">
                            <i class="ti ti-arrow-left me-1"></i>Back to Matrix List
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @php
        $summary = $planSubmissionSummary ?? ['expected_staff' => 0, 'submitted_staff' => 0, 'pending_staff' => 0, 'all_submitted' => false];
        $matrixActivityOptionList = $availableActivities
            ->map(fn ($row) => ['value' => $row['key'], 'label' => $row['label']])
            ->values()
            ->all();
        $matrixImpactOptionList = $impactOptions
            ->map(fn ($row) => ['value' => $row['id'], 'label' => $row['label']])
            ->values()
            ->all();
    @endphp

    <div class="row g-3">
        <div class="col-12">
            <div class="card border shadow-none">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="mb-0">{{ $matrix->department?->name ?? 'Parent Department' }} • Week {{ $matrix->period?->week_number }} ({{ $matrix->period?->year }})</h6>
                            <p class="text-muted small mb-0 mt-1">
                                Activities are compiled from submitted weekly plans across
                                {{ $subDepartmentCount ?? 0 }} sub-department{{ ($subDepartmentCount ?? 0) === 1 ? '' : 's' }} under this parent department.
                            </p>
                        </div>
                        <span class="text-muted small">Last update: {{ optional($matrix->updated_at)->diffForHumans() }}</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-light border mb-3">
                        <div class="small mb-1">
                            Add a row for each staff activity. Fill in <strong>Input</strong> and link one or more <strong>Year plan</strong> impacts. You can return anytime to add rows when more staff submit their plans.
                        </div>
                        @if(($summary['expected_staff'] ?? 0) > 0)
                            <div class="small">
                                Staff plans submitted: <strong>{{ $summary['submitted_staff'] ?? 0 }} / {{ $summary['expected_staff'] }}</strong>
                                @if(($summary['pending_staff'] ?? 0) > 0)
                                    <span class="text-warning">({{ $summary['pending_staff'] }} still pending)</span>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if($availableActivities->isEmpty())
                        <div class="alert alert-warning">
                            <i class="ti ti-alert-triangle me-1"></i>
                            No submitted weekly-plan activities are available yet for this week. Rows can still be added once staff submit their plans.
                        </div>
                    @endif

                    @if($impactOptions->isEmpty())
                        <div class="alert alert-warning">
                            <i class="ti ti-alert-circle me-1"></i>
                            No year-plan activities found for sub-departments under this parent for {{ $matrix->period?->year }}. Set up the year plan first, then select impacts.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('matrix-report.update', $matrix) }}">
                        @csrf
                        @method('PUT')

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Overall Budget (TZS)</label>
                                <input type="number" step="0.01" min="0"
                                       name="overall_budget"
                                       value="{{ old('overall_budget', $matrix->overall_budget ?? $rolledUpBudget) }}"
                                       class="form-control @error('overall_budget') is-invalid @enderror"
                                       placeholder="e.g. {{ number_format((float) ($rolledUpBudget ?? 0), 2, '.', '') }}">
                                <div class="form-text">
                                    Auto-total from all submitted staff weekly-plan budget lines in this parent department:
                                    <strong>{{ number_format((float) ($rolledUpBudget ?? 0), 2) }} TZS</strong>.
                                    Saving uses this total when the field is left empty.
                                </div>
                                @error('overall_budget')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Notes (optional)</label>
                                <textarea name="notes" rows="2"
                                          class="form-control @error('notes') is-invalid @enderror"
                                          placeholder="Brief context for this week's parent department matrix">{{ old('notes', $matrix->notes) }}</textarea>
                                @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="table-responsive mt-3">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th style="width: 48px;">No</th>
                                    <th style="width: 32%;">Activity</th>
                                    <th>Input</th>
                                    <th style="width: 28%;">Impact (year plan)</th>
                                    <th style="width: 48px;"></th>
                                </tr>
                                </thead>
                                <tbody id="matrixEntriesBody">
                                @foreach($entryDefaults as $i => $entry)
                                    @php
                                        $entry = is_array($entry) ? $entry : [];
                                        $selectedImpactIds = collect(old("entries.$i.impact_year_plan_activity_ids", $entry['impact_year_plan_activity_ids'] ?? []))
                                            ->map(fn ($id) => (string) $id)
                                            ->all();
                                    @endphp
                                    <tr>
                                        <td class="text-center text-muted matrix-row-number">{{ $loop->iteration }}</td>
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
                                            <select class="form-select @error("entries.$i.impact_year_plan_activity_ids") is-invalid @enderror @error("entries.$i.impact_year_plan_activity_ids.*") is-invalid @enderror"
                                                    name="entries[{{ $i }}][impact_year_plan_activity_ids][]"
                                                    multiple
                                                    size="{{ min(6, max(3, $impactOptions->count())) }}"
                                                    required>
                                                @foreach($impactOptions as $impactOpt)
                                                    <option value="{{ $impactOpt['id'] }}"
                                                        {{ in_array((string) $impactOpt['id'], $selectedImpactIds, true) ? 'selected' : '' }}>
                                                        {{ $impactOpt['label'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="form-text">Hold Ctrl (Windows) or Cmd (Mac) to select multiple.</div>
                                            @error("entries.$i.impact_year_plan_activity_ids")
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            @error("entries.$i.impact_year_plan_activity_ids.*")
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td class="text-center align-middle">
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-matrix-row" title="Remove row">&times;</button>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        @error('entries')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                        @enderror

                        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                            <button type="button" id="addMatrixRowBtn" class="btn btn-sm btn-outline-primary">
                                <i class="ti ti-plus me-1"></i>Add Row
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-device-floppy me-1"></i>Save Matrix
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

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
                const impactSelectSize = Math.min(6, Math.max(3, impactOptions.length || 3));

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

                const renderImpactOptions = () => {
                    return impactOptions.map(opt =>
                        `<option value="${escapeHtml(opt.value)}">${escapeHtml(opt.label)}</option>`
                    ).join('');
                };

                const buildRow = (index) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="text-center text-muted matrix-row-number"></td>
                        <td>
                            <select class="form-select" name="entries[${index}][activity_key]" required>
                                ${renderOptions(activityOptions, '-- Select activity --')}
                            </select>
                        </td>
                        <td>
                            <textarea rows="2" class="form-control" name="entries[${index}][input]" placeholder="Input" required></textarea>
                        </td>
                        <td>
                            <select class="form-select" name="entries[${index}][impact_year_plan_activity_ids][]" multiple size="${impactSelectSize}" required>
                                ${renderImpactOptions()}
                            </select>
                            <div class="form-text">Hold Ctrl (Windows) or Cmd (Mac) to select multiple.</div>
                        </td>
                        <td class="text-center align-middle">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-matrix-row" title="Remove row">&times;</button>
                        </td>
                    `;
                    return tr;
                };

                const reindexRows = () => {
                    const rows = Array.from(body.querySelectorAll('tr'));
                    rows.forEach((row, idx) => {
                        const numberCell = row.querySelector('.matrix-row-number');
                        if (numberCell) {
                            numberCell.textContent = String(idx + 1);
                        }
                        row.querySelectorAll('select, textarea, input').forEach(el => {
                            if (!el.name) return;
                            el.name = el.name.replace(/entries\[\d+]/, `entries[${idx}]`);
                        });
                    });
                };

                addBtn.addEventListener('click', function () {
                    const index = body.querySelectorAll('tr').length;
                    body.appendChild(buildRow(index));
                    reindexRows();
                });

                body.addEventListener('click', function (event) {
                    const removeButton = event.target.closest('.remove-matrix-row');
                    if (!removeButton) return;
                    const rows = body.querySelectorAll('tr');
                    if (rows.length <= 1) return;
                    removeButton.closest('tr')?.remove();
                    reindexRows();
                });

                reindexRows();
            })();
        </script>
    @endpush
</x-app-layout>
