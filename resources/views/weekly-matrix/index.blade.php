<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Weekly Matrix</h5>
                            <ul class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item" aria-current="page">Weekly Matrix</li>
                            </ul>
                        </div>
                        <form method="GET" action="{{ route('weekly-matrix.index') }}" class="d-flex align-items-center gap-2">
                            <label for="year" class="form-label mb-0 small text-muted">Year</label>
                            <select name="year" id="year" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                @foreach ($availableYears as $availableYear)
                                    <option value="{{ $availableYear }}" @selected($availableYear === $year)>{{ $availableYear }}</option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="ti ti-layout-grid me-2"></i>Work periods — {{ $year }}</h5>
                    <p class="text-muted small mb-0 mt-1">Select a week to manage sub-department key activity matrices.</p>
                </div>
                <div class="card-body">
                    @if ($periodCards->isEmpty())
                        <div class="alert alert-info mb-0">
                            <i class="ti ti-info-circle me-1"></i>
                            No work periods found for {{ $year }}.
                        </div>
                    @else
                        <div class="row g-3">
                            @foreach ($periodCards as $card)
                                @php
                                    /** @var \App\Models\WorkPeriod $period */
                                    $period = $card['period'];
                                    $matrixCount = $card['matrix_count'];
                                    $subDepartmentCount = $card['sub_department_count'];
                                    $isCurrent = $card['is_current'];
                                    $completionLabel = $subDepartmentCount > 0
                                        ? "{$matrixCount} / {$subDepartmentCount} sub-depts"
                                        : 'No sub-depts';
                                @endphp
                                <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                                    <a href="{{ route('weekly-matrix.period', $period) }}"
                                       class="text-decoration-none d-block h-100">
                                        <div class="card h-100 border {{ $isCurrent ? 'border-primary shadow-sm' : '' }} weekly-matrix-period-card">
                                            <div class="card-body p-3 d-flex flex-column">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <span class="fw-bold text-dark">Week {{ $period->week_number }}</span>
                                                    @if ($isCurrent)
                                                        <span class="badge bg-primary">Current</span>
                                                    @endif
                                                </div>
                                                <div class="small text-muted mb-2">{{ $period->date_range }}</div>
                                                <div class="mt-auto">
                                                    <span class="badge {{ $period->status === 'open' ? 'bg-success' : 'bg-secondary' }} mb-2">
                                                        {{ ucfirst($period->status) }}
                                                    </span>
                                                    <div class="small text-muted">{{ $completionLabel }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('styles')
        <style>
            .weekly-matrix-period-card {
                transition: box-shadow 0.15s ease, border-color 0.15s ease;
            }
            .weekly-matrix-period-card:hover {
                box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.08);
                border-color: var(--bs-primary) !important;
            }
        </style>
    @endpush
</x-app-layout>
