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

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="mb-0"><i class="ti ti-table me-2"></i>Weekly matrices</h5>
                        <p class="text-muted small mb-0 mt-1">One key activity per sub-department for this work period.</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#periodMatrixModal">
                            <i class="ti ti-layout-grid me-1"></i> View matrix
                        </button>
                        <a href="{{ route('weekly-matrix.download', $period) }}" class="btn btn-outline-danger btn-sm">
                            <i class="ti ti-download me-1"></i> PDF
                        </a>
                        @if ($canCreate)
                            <a href="{{ route('weekly-matrix.create', $period) }}" class="btn btn-primary btn-sm">
                                <i class="ti ti-plus me-1"></i> Create
                            </a>
                        @endif
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive p-3">
                        <table class="table table-hover table-bordered mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Parent department</th>
                                    <th>Sub-department</th>
                                    <th style="min-width: 220px;">Key activity</th>
                                    <th>Year plan links</th>
                                    <th>Updated</th>
                                    <th class="text-center" style="width: 120px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($matrices as $row)
                                    @php
                                        $department = $row['department'];
                                        $matrix = $row['matrix'];
                                        $entry = $row['entry'];
                                    @endphp
                                    <tr>
                                        <td>{{ $department?->parent?->name ?? '—' }}</td>
                                        <td class="fw-semibold">{{ $department?->name ?? '—' }}</td>
                                        <td>
                                            @if ($entry)
                                                <div class="small text-wrap" style="white-space: pre-wrap;">{{ Str::limit($entry->key_activity, 120) }}</div>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if ($row['year_plan_link_count'] > 0)
                                                {{ $row['year_plan_link_count'] }}
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="small text-muted">{{ $matrix->updated_at?->diffForHumans() ?? '—' }}</td>
                                        <td class="text-center">
                                            <x-table-action-dropdown>
                                                <a class="dropdown-item" href="{{ route('weekly-matrix.edit', [$period, $department, 'view' => 1]) }}">
                                                    <i class="ti ti-eye me-2"></i> View
                                                </a>
                                                <a class="dropdown-item" href="{{ route('weekly-matrix.edit', [$period, $department]) }}">
                                                    <i class="ti ti-edit me-2"></i> Edit
                                                </a>
                                            </x-table-action-dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="ti ti-info-circle me-1"></i> No matrix present
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="periodMatrixModal" tabindex="-1" aria-labelledby="periodMatrixModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable weekly-matrix-modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="periodMatrixModalLabel">
                        Weekly Matrix — Week {{ $period->week_number }} · {{ $period->year }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @include('weekly-matrix.partials.matrix-table', ['period' => $period, 'matrices' => $matrices])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <a href="{{ route('weekly-matrix.download', $period) }}" class="btn btn-sm btn-outline-danger">
                        <i class="ti ti-download me-1"></i> Download PDF
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
