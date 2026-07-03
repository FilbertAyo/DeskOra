<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Week {{ $period->week_number }} · {{ $period->year }}</h5>
                            <ul class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('weekly-matrix.index', ['year' => $period->year]) }}">Weekly Matrix</a></li>
                                <li class="breadcrumb-item" aria-current="page">Week {{ $period->week_number }}</li>
                            </ul>
                        </div>
                        <a href="{{ route('weekly-matrix.index', ['year' => $period->year]) }}" class="btn btn-outline-secondary btn-sm">
                            <i class="ti ti-arrow-left me-1"></i> All weeks
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <div class="alert alert-light border mb-0">
                <i class="ti ti-calendar me-1"></i>
                <strong>{{ $period->date_range }}</strong>
                <span class="badge {{ $period->status === 'open' ? 'bg-success' : 'bg-secondary' }} ms-2">{{ ucfirst($period->status) }}</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="ti ti-building-community me-2"></i>Sub-departments</h5>
                    <p class="text-muted small mb-0 mt-1">Each sub-department has one key activities matrix for this work period.</p>
                </div>
                <div class="card-body">
                    @if ($subDepartments->isEmpty())
                        <div class="alert alert-warning mb-0">
                            <i class="ti ti-alert-triangle me-1"></i>
                            No sub-departments are configured yet. Add sub-departments under Settings → Departments.
                        </div>
                    @else
                        <div class="row g-3">
                            @foreach ($subDepartments as $row)
                                @php
                                    $department = $row['department'];
                                    $matrix = $row['matrix'];
                                    $parentName = $department->parent?->name;
                                @endphp
                                <div class="col-md-6 col-lg-4">
                                    <div class="card h-100 border">
                                        <div class="card-body d-flex flex-column">
                                            <div class="mb-2">
                                                @if ($parentName)
                                                    <div class="small text-muted">{{ $parentName }}</div>
                                                @endif
                                                <h6 class="mb-0 fw-semibold">{{ $department->name }}</h6>
                                            </div>
                                            <div class="small text-muted mb-3">
                                                @if ($matrix)
                                                    {{ $row['entry_count'] }} {{ Str::plural('row', $row['entry_count']) }}
                                                    @if ($row['year_plan_link_count'] > 0)
                                                        · {{ $row['year_plan_link_count'] }} year-plan {{ Str::plural('link', $row['year_plan_link_count']) }}
                                                    @endif
                                                    <br>
                                                    Updated {{ $matrix->updated_at?->diffForHumans() }}
                                                @else
                                                    No matrix yet
                                                @endif
                                            </div>
                                            <div class="mt-auto d-flex gap-2 flex-wrap">
                                                <a href="{{ route('weekly-matrix.edit', [$period, $department]) }}"
                                                   class="btn btn-primary btn-sm">
                                                    <i class="ti ti-{{ $matrix ? 'edit' : 'plus' }} me-1"></i>
                                                    {{ $matrix ? 'Open' : 'Create' }}
                                                </a>
                                                @if ($matrix)
                                                    <a href="{{ route('weekly-matrix.edit', [$period, $department, 'view' => 1]) }}"
                                                       class="btn btn-outline-secondary btn-sm">
                                                        <i class="ti ti-eye me-1"></i> View
                                                    </a>
                                                @endif
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
    </div>
</x-app-layout>
