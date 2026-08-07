@forelse ($sections as $section)
    @php($dept = $section['department'])
    <div class="card shadow-none border mb-4">
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-0 text-uppercase text-primary fw-bold">
                    @if ($dept->parent)
                        <span class="text-muted">{{ $dept->parent->name }}</span>
                        <i class="ti ti-chevron-right mx-1 text-muted small"></i>
                    @endif
                    {{ $dept->name }}
                </h5>
                @if ($dept->plan_section_number === null)
                    <span class="badge bg-light-warning border border-warning text-dark mt-1">Section number not set — codes show as —.n</span>
                @endif
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('year-plan.department.download', [$yearPlan->year, $dept, 'format' => 'pdf']) }}"
                    class="btn btn-sm btn-outline-danger" title="Download this sub-department as PDF">
                    <i class="ti ti-file-type-pdf me-1"></i> PDF
                </a>
                <a href="{{ route('year-plan.department.download', [$yearPlan->year, $dept, 'format' => 'excel']) }}"
                    class="btn btn-sm btn-outline-danger" title="Download this sub-department as Excel">
                    <i class="ti ti-file-spreadsheet me-1"></i> Excel
                </a>
                <button type="button" class="btn btn-sm btn-outline-primary btn-add-activity" data-bs-toggle="modal"
                    data-bs-target="#activityModal" data-department-id="{{ $dept->id }}">
                    <i class="ti ti-plus me-1"></i> Add Activity to {{ $dept->name }}
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-striped mb-0 align-middle text-nowrap">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th style="min-width: 220px;">Description</th>
                            <th class="text-end">Freq.</th>
                            <th class="text-end">Units</th>
                            <th class="text-end">Unit value (TZS)</th>
                            <th class="text-end">Total (TZS)</th>
                            @foreach ($monthLabels as $m => $label)
                                <th class="text-center small px-1">{{ $label }}</th>
                            @endforeach
                            <th class="text-center" style="width: 100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($section['activities'] as $activity)
                            <tr>
                                <td class="fw-semibold">{{ $activity->displayCode() }}</td>
                                <td class="text-wrap" style="white-space: normal;">{{ $activity->description }}</td>
                                <td class="text-end">{{ $activity->frequency }}</td>
                                <td class="text-end">{{ $activity->unit_count }}</td>
                                <td class="text-end">{{ number_format((float) $activity->unit_value_tzs, 2) }}</td>
                                <td class="text-end">{{ number_format((float) $activity->total_cost_tzs, 2) }}</td>
                                @foreach ($monthLabels as $m => $label)
                                    <td class="text-center px-0">
                                        @if ($activity->isPlannedInMonth($m))
                                            <i class="ti ti-check text-success"></i>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="text-center">
                                    <x-table-action-dropdown>
    <button type="button" class="dropdown-item btn-edit-activity"
    data-activity-b64="{{ base64_encode(json_encode([
    'id' => $activity->id,
    'department_id' => (string) $activity->department_id,
    'sort_order' => (int) $activity->sort_order,
    'description' => (string) $activity->description,
    'frequency' => (string) $activity->frequency,
    'unit_count' => (string) $activity->unit_count,
    'unit_value_tzs' => (string) $activity->unit_value_tzs,
    'planned_months' => array_values(array_map('intval', $activity->planned_months ?? [])),
    ], JSON_UNESCAPED_UNICODE)) }}">
    <i class="ti ti-edit me-2"></i> Edit
    </button>
    <form action="{{ route('year-plan.activities.destroy', [$yearPlan->year, $activity]) }}" method="post"
    onsubmit="return confirm('Remove this activity?');">
    @csrf
    @method('DELETE')
    <button type="submit" class="dropdown-item text-danger">
    <i class="ti ti-trash me-2"></i> Delete
    </button>
    </form>
</x-table-action-dropdown>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="5" class="text-end">Subtotal (TZS)</th>
                            <th class="text-end">{{ number_format($section['subtotal'], 2) }}</th>
                            <th colspan="13"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
@empty
    <div class="card border shadow-none">
        <div class="card-body text-center text-muted py-5">
            <p class="mb-2">No activities yet. Add a sub-department line using <strong>Add activity</strong>, then pick the sub-department.</p>
            <p class="small mb-0">Sub-departments are managed under Settings › Departments.</p>
        </div>
    </div>
@endforelse
