<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Parent Department Weekly Matrices</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="javascript:void(0)">Matrix Report</a></li>
                            </ul>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('documents.weekly-objective-matrix.download', ['orientation' => 'portrait']) }}"
                               class="btn btn-danger btn-sm">
                                <i class="ti ti-download me-1"></i>Download PDF
                            </a>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createMatrixModal">
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
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0">All Parent Department Matrices</h6>
                    <p class="text-muted small mb-0 mt-1">
                        Each matrix rolls up submitted weekly plans from all sub-departments under the parent department.
                    </p>
                </div>
                <div class="card-body">
                    @if($parentDepartments->isEmpty())
                        <div class="alert alert-warning mb-0">
                            <i class="ti ti-alert-triangle me-1"></i>
                            You do not have access to any parent departments for matrix management.
                        </div>
                    @elseif($departmentMatrices->isEmpty())
                        <div class="alert alert-info mb-0">
                            <i class="ti ti-info-circle me-1"></i>
                            No parent department matrix created yet. Click <strong>Add Department Matrix</strong>, choose the parent department and week (e.g. Week 21), then add activities.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Parent Department</th>
                                    <th>Week</th>
                                    <th>Sub-depts</th>
                                    <th>Plans Submitted</th>
                                    <th>Matrix Rows</th>
                                    <th>Budget</th>
                                    <th>Updated</th>
                                    <th style="width: 200px;">Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($departmentMatrices as $matrix)
                                    @php
                                        $entryCount = is_array($matrix->entries) ? count($matrix->entries) : 0;
                                        $meta = $matrixMeta[(string) $matrix->id] ?? ['sub_department_count' => 0, 'submission_summary' => ['expected_staff' => 0, 'submitted_staff' => 0, 'pending_staff' => 0]];
                                        $summary = $meta['submission_summary'] ?? [];
                                    @endphp
                                    <tr>
                                        <td class="fw-semibold">{{ $matrix->department?->name ?? 'Unknown Department' }}</td>
                                        <td>Week {{ $matrix->period?->week_number }} • {{ $matrix->period?->year }}</td>
                                        <td>{{ $meta['sub_department_count'] ?? 0 }}</td>
                                        <td>
                                            @if(($summary['expected_staff'] ?? 0) > 0)
                                                {{ $summary['submitted_staff'] ?? 0 }} / {{ $summary['expected_staff'] }}
                                                @if(($summary['pending_staff'] ?? 0) > 0)
                                                    <span class="text-warning">({{ $summary['pending_staff'] }} pending)</span>
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $entryCount }}</td>
                                        <td>{{ $matrix->overall_budget !== null ? number_format((float) $matrix->overall_budget, 2) : 'N/A' }}</td>
                                        <td class="small text-muted">{{ optional($matrix->updated_at)->diffForHumans() }}</td>
                                        <td>
                                            <x-table-action-dropdown>
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('matrix-report.edit', $matrix) }}">
                                                        <i class="ti ti-eye me-2"></i> Open
                                                    </a>
                                                </li>
                                                <li>
                                                    <form method="POST" action="{{ route('matrix-report.destroy', $matrix) }}"
                                                          onsubmit="return confirm('Delete this matrix?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="ti ti-trash me-2"></i> Delete
                                                        </button>
                                                    </form>
                                                </li>
                                            </x-table-action-dropdown>
                                        </td>
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

    <div class="modal fade" id="createMatrixModal" tabindex="-1" aria-labelledby="createMatrixModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('matrix-report.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="createMatrixModalLabel">Create Parent Department Matrix</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">
                            Select the whole parent department and the work week. Activities will be pulled from submitted weekly plans across all sub-departments under that parent.
                        </p>

                        <div class="mb-3">
                            <label for="department_id" class="form-label">Parent Department</label>
                            <select class="form-select @error('department_id') is-invalid @enderror" id="department_id" name="department_id" required>
                                <option value="">-- Select Parent Department --</option>
                                @foreach($parentDepartments as $department)
                                    <option value="{{ $department->id }}" {{ (string) $defaultDepartmentId === (string) $department->id ? 'selected' : '' }}>
                                        {{ $department->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('department_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-0">
                            <label for="period_id" class="form-label">Work Week</label>
                            <select class="form-select @error('period_id') is-invalid @enderror" id="period_id" name="period_id" required>
                                <option value="">-- Select Week --</option>
                                @foreach($periods as $period)
                                    <option value="{{ $period->id }}" {{ (string) $defaultPeriodId === (string) $period->id ? 'selected' : '' }}>
                                        Week {{ $period->week_number }} • {{ $period->year }}
                                        ({{ optional($period->week_start_date)->format('d M') }} - {{ optional($period->week_end_date)->format('d M') }})
                                        • {{ ucfirst((string) $period->status) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('period_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-plus me-1"></i>Create / Open Matrix
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

<div id="matrix-modal-error-state"
     data-department-error="{{ $errors->has('department_id') ? '1' : '0' }}"
     data-period-error="{{ $errors->has('period_id') ? '1' : '0' }}"
     class="d-none"></div>

@push('scripts')
    <script>
        (function () {
            const errorState = document.getElementById('matrix-modal-error-state');
            const hasDepartmentError = errorState?.dataset?.departmentError === '1';
            const hasPeriodError = errorState?.dataset?.periodError === '1';
            if (!hasDepartmentError && !hasPeriodError) {
                return;
            }
            const modalEl = document.getElementById('createMatrixModal');
            if (!modalEl || typeof bootstrap === 'undefined') {
                return;
            }
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        })();
    </script>
@endpush
