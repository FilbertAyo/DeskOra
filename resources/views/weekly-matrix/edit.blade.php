@php
    $fieldLabels = [
        'key_activity' => 'Key Activities',
        'output' => 'Output',
        'outcome' => 'Outcome',
        'impact' => 'Impact',
        'indicators' => 'Indicators',
        'baseline' => 'Baseline',
        'target' => 'Target',
        'means_of_verification' => 'Means of Verification',
    ];
    $isEditing = ! $viewMode;
    $entry = $entryDefaults[0] ?? [];
    $singleEntry = $matrix?->entries->first();
@endphp

<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="page-header-title">
                            <h5 class="m-b-10">
                                Week {{ $period->week_number }} — {{ $department->name }}
                                @if ($viewMode)
                                    <span class="badge bg-secondary ms-1">View</span>
                                @endif
                            </h5>
                            <ul class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('weekly-matrix.index', ['year' => $period->year]) }}">Weekly Matrix</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('weekly-matrix.period', $period) }}">Week {{ $period->week_number }}</a></li>
                                <li class="breadcrumb-item" aria-current="page">{{ $department->name }}</li>
                            </ul>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ route('weekly-matrix.period', $period) }}" class="btn btn-outline-secondary btn-sm">
                                <i class="ti ti-arrow-left me-1"></i> Back
                            </a>
                            @if ($viewMode && $matrix)
                                <a href="{{ route('weekly-matrix.edit', [$period, $department]) }}" class="btn btn-primary btn-sm">
                                    <i class="ti ti-edit me-1"></i> Edit
                                </a>
                            @elseif ($matrix)
                                <a href="{{ route('weekly-matrix.edit', [$period, $department, 'view' => 1]) }}" class="btn btn-outline-secondary btn-sm">
                                    <i class="ti ti-eye me-1"></i> View
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($isEditing && $suggestions->isNotEmpty())
        <div class="card shadow-none border mb-3">
            <div class="card-header bg-white border-bottom py-2">
                <button class="btn btn-link text-decoration-none p-0 fw-semibold text-warning"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#weeklyMatrixSuggestions"
                        aria-expanded="false">
                    <i class="ti ti-bulb me-1"></i> Suggestions from submitted weekly plans ({{ $suggestions->count() }})
                </button>
            </div>
            <div class="collapse" id="weeklyMatrixSuggestions">
                <div class="card-body pt-2">
                    <p class="text-muted small">Click a suggestion to pre-fill the key activity field.</p>
                    <div class="list-group list-group-flush">
                        @foreach ($suggestions as $suggestion)
                            <button type="button"
                                    class="list-group-item list-group-item-action weekly-matrix-suggestion-btn text-start"
                                    data-activity="{{ $suggestion['activity'] }}">
                                <div class="fw-semibold">{{ $suggestion['activity'] }}</div>
                                <div class="small text-muted">
                                    {{ $suggestion['staff_name'] }}
                                    @if ($suggestion['day'])
                                        · {{ $suggestion['day'] }}
                                    @endif
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($isEditing)
        <form method="POST" action="{{ route('weekly-matrix.update', [$period, $department]) }}" id="weeklyMatrixForm">
            @csrf
            @method('PUT')

            <div class="card shadow-none border mb-3">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0">Sub-department & year plan</h6>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">Sub-department</label>
                            <div class="form-control-plaintext fw-semibold">
                                @if ($department->parent?->name)
                                    <span class="text-muted">{{ $department->parent->name }} —</span>
                                @endif
                                {{ $department->name }}
                            </div>
                        </div>
                        <div class="col-md-6">
                            @include('weekly-matrix.partials.year-plan-picker', [
                                'period' => $period,
                                'entry' => $entry,
                                'yearPlanOptions' => $yearPlanOptions,
                                'namePrefix' => 'entries[0]',
                            ])
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-none border mb-3">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0">Key activity details</h6>
                </div>
                <div class="card-body">
                    @include('weekly-matrix.partials.entry-fields-vertical', [
                        'entry' => $entry,
                        'namePrefix' => 'entries[0]',
                    ])
                </div>
                <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <button type="button"
                            class="btn btn-outline-danger btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#deleteMatrixModal">
                        <i class="ti ti-trash me-1"></i> Delete matrix
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i> Save changes
                    </button>
                </div>
            </div>
        </form>

        <div class="modal fade" id="deleteMatrixModal" tabindex="-1" aria-labelledby="deleteMatrixModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteMatrixModalLabel">Delete matrix</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Delete the weekly matrix for <strong>{{ $department->name }}</strong> in week {{ $period->week_number }}?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form method="POST" action="{{ route('weekly-matrix.destroy', [$period, $department]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    document.querySelectorAll('.weekly-matrix-suggestion-btn').forEach((button) => {
                        button.addEventListener('click', function () {
                            const textarea = document.querySelector('textarea[name="entries[0][key_activity]"]');
                            if (textarea) {
                                textarea.value = button.getAttribute('data-activity') || '';
                                textarea.focus();
                            }
                        });
                    });
                });
            </script>
        @endpush
    @else
        <div class="card shadow-none border mb-3">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0">Sub-department & year plan</h6>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Sub-department</label>
                        <div class="fw-semibold">
                            @if ($department->parent?->name)
                                <span class="text-muted">{{ $department->parent->name }} —</span>
                            @endif
                            {{ $department->name }}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Year plan links</label>
                        @if ($singleEntry && $singleEntry->yearPlanActivities->isNotEmpty())
                            <div class="d-flex flex-column gap-1">
                                @foreach ($singleEntry->yearPlanActivities as $activity)
                                    <div class="small">
                                        <span class="badge bg-light text-dark border">{{ $activity->displayCode() }}</span>
                                        {{ $activity->description }}
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-muted">—</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-none border">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0">Key activity details</h6>
            </div>
            <div class="card-body">
                @if (! $singleEntry)
                    <div class="text-muted">No matrix entries yet.</div>
                @else
                    <div class="row g-4">
                        @foreach ($fieldLabels as $field => $label)
                            <div class="col-xl-6 col-md-6 col-12">
                                <label class="form-label text-muted small mb-1">{{ $label }}</label>
                                <div class="border rounded p-3 bg-light h-100" style="white-space: pre-wrap;">{{ $singleEntry->{$field} ?: '—' }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    @push('styles')
        <style>
            .weekly-matrix-field-input {
                font-size: 0.9375rem;
                line-height: 1.5;
                resize: vertical;
            }
        </style>
    @endpush
</x-app-layout>
