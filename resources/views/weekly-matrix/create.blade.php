@php
    $columnLabels = [
        'key_activity' => 'Key Activities',
        'output' => 'Output',
        'outcome' => 'Outcome',
        'impact' => 'Impact',
        'indicators' => 'Indicators',
        'baseline' => 'Baseline',
        'target' => 'Target',
        'means_of_verification' => 'Means of Verification',
    ];
@endphp

<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Create weekly matrix — Week {{ $period->week_number }}</h5>
                            <ul class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('weekly-matrix.index', ['year' => $period->year]) }}">Weekly Matrix</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('weekly-matrix.period', $period) }}">Week {{ $period->week_number }}</a></li>
                                <li class="breadcrumb-item" aria-current="page">Create</li>
                            </ul>
                        </div>
                        <a href="{{ route('weekly-matrix.period', $period) }}" class="btn btn-outline-secondary btn-sm">
                            <i class="ti ti-arrow-left me-1"></i> Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($suggestions->isNotEmpty())
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

    <form method="POST" action="{{ route('weekly-matrix.create.store', $period) }}" id="weeklyMatrixCreateForm">
        @csrf

        <div class="card shadow-none border mb-3">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0">Sub-department & year plan</h6>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label for="department_id" class="form-label">Sub-department <span class="text-danger">*</span></label>
                        <select name="department_id"
                                id="department_id"
                                class="form-select @error('department_id') is-invalid @enderror"
                                required
                                onchange="window.location.href='{{ route('weekly-matrix.create', $period) }}?department_id=' + encodeURIComponent(this.value)">
                            @foreach ($availableSubDepartments as $subDepartment)
                                <option value="{{ $subDepartment->id }}"
                                    @selected((string) $selectedDepartment?->id === (string) $subDepartment->id)>
                                    @if ($subDepartment->parent?->name)
                                        {{ $subDepartment->parent->name }} —
                                    @endif
                                    {{ $subDepartment->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('department_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        @include('weekly-matrix.partials.year-plan-picker', [
                            'period' => $period,
                            'entry' => $entry,
                            'yearPlanOptions' => $yearPlanOptions,
                            'namePrefix' => 'entry',
                        ])
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-none border mb-3">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0">Key activity details</h6>
                <p class="text-muted small mb-0 mt-1">Each field is shown full width so you can enter lists, dashes, and line breaks easily.</p>
            </div>
            <div class="card-body">
                @include('weekly-matrix.partials.entry-fields-vertical', [
                    'entry' => $entry,
                    'namePrefix' => 'entry',
                ])
            </div>
            <div class="card-footer bg-white border-top text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-device-floppy me-1"></i> Save matrix
                </button>
            </div>
        </div>
    </form>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.weekly-matrix-suggestion-btn').forEach((button) => {
                    button.addEventListener('click', function () {
                        const textarea = document.querySelector('textarea[name="entry[key_activity]"]');
                        if (textarea) {
                            textarea.value = button.getAttribute('data-activity') || '';
                            textarea.focus();
                        }
                    });
                });
            });
        </script>
    @endpush

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
