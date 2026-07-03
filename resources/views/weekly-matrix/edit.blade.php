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
    $isEditing = ! $viewMode;
    $formAction = $matrix
        ? route('weekly-matrix.update', [$period, $department])
        : route('weekly-matrix.store', [$period, $department]);
    $formMethod = $matrix ? 'PUT' : 'POST';
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

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($isEditing && $suggestions->isNotEmpty())
        <div class="card shadow-none border mb-3">
            <div class="card-header bg-white border-bottom py-2">
                <button class="btn btn-link text-decoration-none p-0 fw-semibold text-dark"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#weeklyMatrixSuggestions"
                        aria-expanded="false">
                    <i class="ti ti-bulb me-1"></i> Suggestions from submitted weekly plans ({{ $suggestions->count() }})
                </button>
            </div>
            <div class="collapse" id="weeklyMatrixSuggestions">
                <div class="card-body pt-2">
                    <p class="text-muted small">These come from submitted weekly plans for this sub-department. Click to add a row with the key activity pre-filled.</p>
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
        <form method="POST" action="{{ $formAction }}" id="weeklyMatrixForm">
            @csrf
            @if ($formMethod === 'PUT')
                @method('PUT')
            @endif

            <div class="card shadow-none border mb-3">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0">Key activities matrix</h6>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="addMatrixRowBtn">
                        <i class="ti ti-plus me-1"></i> Add row
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 align-middle weekly-matrix-entry-table" id="weeklyMatrixEntryTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width: 180px;">{{ $columnLabels['key_activity'] }}</th>
                                    <th style="min-width: 140px;">{{ $columnLabels['output'] }}</th>
                                    <th style="min-width: 140px;">{{ $columnLabels['outcome'] }}</th>
                                    <th style="min-width: 140px;">{{ $columnLabels['impact'] }}</th>
                                    <th style="min-width: 140px;">{{ $columnLabels['indicators'] }}</th>
                                    <th style="min-width: 120px;">{{ $columnLabels['baseline'] }}</th>
                                    <th style="min-width: 120px;">{{ $columnLabels['target'] }}</th>
                                    <th style="min-width: 160px;">{{ $columnLabels['means_of_verification'] }}</th>
                                    <th style="min-width: 180px;">Year plan links</th>
                                    <th style="width: 60px;"></th>
                                </tr>
                            </thead>
                            <tbody id="weeklyMatrixEntryBody">
                                @foreach ($entryDefaults as $index => $entry)
                                    @include('weekly-matrix.partials.entry-row', [
                                        'index' => $index,
                                        'entry' => $entry,
                                        'yearPlanOptions' => $yearPlanOptions,
                                        'viewMode' => false,
                                    ])
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        @if ($matrix)
                            <button type="button"
                                    class="btn btn-outline-danger btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#deleteMatrixModal">
                                <i class="ti ti-trash me-1"></i> Delete matrix
                            </button>
                        @endif
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i> Save matrix
                    </button>
                </div>
            </div>
        </form>

        @if ($matrix)
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
        @endif

        <template id="weeklyMatrixRowTemplate">
            @include('weekly-matrix.partials.entry-row', [
                'index' => '__INDEX__',
                'entry' => [
                    'key_activity' => '',
                    'output' => '',
                    'outcome' => '',
                    'impact' => '',
                    'indicators' => '',
                    'baseline' => '',
                    'target' => '',
                    'means_of_verification' => '',
                    'year_plan_activity_ids' => [],
                ],
                'yearPlanOptions' => $yearPlanOptions,
                'viewMode' => false,
            ])
        </template>

        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const body = document.getElementById('weeklyMatrixEntryBody');
                    const template = document.getElementById('weeklyMatrixRowTemplate');
                    const addBtn = document.getElementById('addMatrixRowBtn');

                    function reindexRows() {
                        body.querySelectorAll('tr[data-matrix-row]').forEach((row, index) => {
                            row.querySelectorAll('[name]').forEach((input) => {
                                input.name = input.name.replace(/entries\[\d+]/, 'entries[' + index + ']');
                            });
                        });
                    }

                    function addRow(keyActivity) {
                        const index = body.querySelectorAll('tr[data-matrix-row]').length;
                        let html = template.innerHTML.replaceAll('__INDEX__', String(index));
                        body.insertAdjacentHTML('beforeend', html);
                        if (keyActivity) {
                            const lastRow = body.lastElementChild;
                            const textarea = lastRow.querySelector('textarea[name$="[key_activity]"]');
                            if (textarea) {
                                textarea.value = keyActivity;
                            }
                        }
                    }

                    addBtn?.addEventListener('click', function () {
                        addRow('');
                    });

                    body.addEventListener('click', function (event) {
                        const removeBtn = event.target.closest('.weekly-matrix-remove-row');
                        if (!removeBtn) {
                            return;
                        }
                        const rows = body.querySelectorAll('tr[data-matrix-row]');
                        if (rows.length <= 1) {
                            window.alert('At least one row is required.');
                            return;
                        }
                        removeBtn.closest('tr')?.remove();
                        reindexRows();
                    });

                    document.querySelectorAll('.weekly-matrix-suggestion-btn').forEach((button) => {
                        button.addEventListener('click', function () {
                            addRow(button.getAttribute('data-activity') || '');
                        });
                    });
                });
            </script>
        @endpush
    @else
        <div class="card shadow-none border">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0">Key activities matrix</h6>
            </div>
            <div class="card-body p-0">
                @if (! $matrix || $matrix->entries->isEmpty())
                    <div class="p-4 text-muted">No matrix entries yet.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 weekly-matrix-entry-table">
                            <thead class="table-light">
                                <tr>
                                    @foreach ($columnLabels as $label)
                                        <th style="min-width: 140px;">{{ $label }}</th>
                                    @endforeach
                                    <th style="min-width: 180px;">Year plan links</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($matrix->entries as $entry)
                                    <tr>
                                        <td><div class="mb-0" style="white-space: pre-wrap;">{{ $entry->key_activity }}</div></td>
                                        <td><div class="mb-0" style="white-space: pre-wrap;">{{ $entry->output }}</div></td>
                                        <td><div class="mb-0" style="white-space: pre-wrap;">{{ $entry->outcome }}</div></td>
                                        <td><div class="mb-0" style="white-space: pre-wrap;">{{ $entry->impact }}</div></td>
                                        <td><div class="mb-0" style="white-space: pre-wrap;">{{ $entry->indicators }}</div></td>
                                        <td><div class="mb-0" style="white-space: pre-wrap;">{{ $entry->baseline }}</div></td>
                                        <td><div class="mb-0" style="white-space: pre-wrap;">{{ $entry->target }}</div></td>
                                        <td><div class="mb-0" style="white-space: pre-wrap;">{{ $entry->means_of_verification }}</div></td>
                                        <td>
                                            @forelse ($entry->yearPlanActivities as $activity)
                                                <div class="small mb-1">
                                                    <span class="badge bg-light text-dark border">{{ $activity->displayCode() }}</span>
                                                    {{ Str::limit($activity->description, 80) }}
                                                </div>
                                            @empty
                                                <span class="text-muted">—</span>
                                            @endforelse
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @push('styles')
        <style>
            .weekly-matrix-entry-table textarea {
                min-height: 96px;
                font-size: 0.875rem;
                resize: vertical;
            }
            .weekly-matrix-entry-table td {
                vertical-align: top;
            }
        </style>
    @endpush
</x-app-layout>
