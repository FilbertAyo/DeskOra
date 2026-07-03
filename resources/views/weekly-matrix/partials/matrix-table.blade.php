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

<style>
    .weekly-matrix-modal-dialog {
        max-width: 96vw;
        width: 96vw;
    }
    .weekly-matrix-consolidated-table {
        width: max-content;
        min-width: 100%;
        table-layout: auto;
    }
    .weekly-matrix-consolidated-table th,
    .weekly-matrix-consolidated-table td {
        min-width: 260px;
        max-width: 360px;
        vertical-align: top;
        text-align: left;
    }
    .weekly-matrix-consolidated-table th:first-child,
    .weekly-matrix-consolidated-table td:first-child {
        min-width: 180px;
        max-width: 220px;
    }
    .weekly-matrix-consolidated-table th:last-child,
    .weekly-matrix-consolidated-table td:last-child {
        min-width: 220px;
        max-width: 280px;
    }
    .weekly-matrix-consolidated-scroll {
        overflow-x: auto;
    }
</style>

<div class="table-responsive weekly-matrix-consolidated-scroll">
    <table class="table table-bordered table-sm mb-0 align-top weekly-matrix-consolidated-table">
        <thead class="table-light">
            <tr>
                <th>Sub-department</th>
                @foreach ($columnLabels as $label)
                    <th>{{ $label }}</th>
                @endforeach
                <th>Year plan links</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($matrices as $row)
                @php
                    $department = $row['department'];
                    $entry = $row['entry'];
                @endphp
                <tr>
                    <td class="fw-semibold">
                        @if ($department?->parent?->name)
                            <div class="small text-muted">{{ $department->parent->name }}</div>
                        @endif
                        {{ $department?->name ?? '—' }}
                    </td>
                    @foreach ($columnLabels as $field => $label)
                        <td><div class="mb-0" style="white-space: pre-wrap;">{{ $entry?->{$field} ?: '—' }}</div></td>
                    @endforeach
                    <td>
                        @forelse ($entry?->yearPlanActivities ?? [] as $activity)
                            <div class="small mb-1">
                                <span class="badge bg-light text-dark border">{{ $activity->displayCode() }}</span>
                                {{ \Illuminate\Support\Str::limit($activity->description, 120) }}
                            </div>
                        @empty
                            <span class="text-muted">—</span>
                        @endforelse
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columnLabels) + 2 }}" class="text-center text-muted py-4">
                        <i class="ti ti-info-circle me-1"></i> No matrix present
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
