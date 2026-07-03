<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Staff Appreciation</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="javascript: void(0)">Management</a></li>
                                <li class="breadcrumb-item" aria-current="page">Staff Appreciation</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="alert alert-light-primary border border-primary d-flex align-items-start gap-2" role="alert">
                <i class="ti ti-shield-lock f-20 mt-1"></i>
                <div class="small">
                    Nominations are <strong>anonymous</strong>. You can see who was nominated and why, but never who
                    submitted a nomination. These results are intended to surface staff who positively impact colleagues;
                    the final Employee of the Month decision remains with management.
                </div>
            </div>
        </div>
    </div>

    @if (! $currentPeriod)
        <div class="card shadow-none border">
            <div class="card-body text-center py-5">
                <i class="ti ti-calendar-heart fs-1 text-muted"></i>
                <h5 class="mt-2">No nomination periods yet</h5>
                <p class="text-muted mb-0">
                    The nomination window opens automatically on the 26th of each month and closes on the last day.
                </p>
            </div>
        </div>
    @else
        <div class="card shadow-none border">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0">
                        {{ $currentPeriod->label() }}
                        @if ($currentPeriod->isOpen() && $currentPeriod->isWindowOpenNow())
                            <span class="badge bg-light-success border border-success ms-2">Open now</span>
                        @else
                            <span class="badge bg-light-secondary border border-secondary ms-2">Closed</span>
                        @endif
                    </h5>
                    <p class="text-muted small mb-0 mt-1">
                        Window: {{ $currentPeriod->opens_at->format('M d, H:i') }} – {{ $currentPeriod->closes_at->format('M d, H:i') }}
                    </p>
                </div>
                <a href="{{ route('management.appreciation.show', $currentPeriod) }}" class="btn btn-sm btn-light">
                    <i class="ti ti-external-link me-1"></i> Full results
                </a>
            </div>
            <div class="card-body">
                @include('management.staff-appreciation.partials.period-detail', [
                    'period' => $currentPeriod,
                    'stats' => $currentStats,
                    'tallies' => $currentTallies,
                    'reasons' => $currentReasons,
                ])
            </div>
        </div>

        @if ($previousPeriod && $previousStats)
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="ti ti-history me-2"></i>Previous month — {{ $previousPeriod->label() }}</h5>
                    <a href="{{ route('management.appreciation.show', $previousPeriod) }}" class="btn btn-sm btn-light">
                        <i class="ti ti-external-link me-1"></i> View results
                    </a>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <p class="text-muted small mb-1 text-uppercase">Participation</p>
                            <h4 class="mb-0">{{ $previousStats['rate'] }}%</h4>
                        </div>
                        <div class="col-6 col-md-3">
                            <p class="text-muted small mb-1 text-uppercase">Responded</p>
                            <h4 class="mb-0">{{ $previousStats['responded'] }} / {{ $previousStats['eligible'] }}</h4>
                        </div>
                        <div class="col-6 col-md-3">
                            <p class="text-muted small mb-1 text-uppercase">Nominations</p>
                            <h4 class="mb-0 text-success">{{ $previousStats['nominated'] }}</h4>
                        </div>
                        <div class="col-6 col-md-3">
                            <p class="text-muted small mb-1 text-uppercase">Skipped</p>
                            <h4 class="mb-0 text-muted">{{ $previousStats['skipped'] }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="card shadow-none border">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0"><i class="ti ti-calendar-stats me-2"></i>History</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered mb-0 align-middle">
                        <thead class="thead-light">
                            <tr>
                                <th>Period</th>
                                <th style="width:120px;">Status</th>
                                <th class="text-center" style="width:140px;">Participation</th>
                                <th class="text-center" style="width:130px;">Nominations</th>
                                <th class="text-center" style="width:110px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($periods as $period)
                                @php $stats = app(\App\Services\StaffAppreciationService::class)->participationStats($period); @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $period->label() }}</td>
                                    <td>
                                        @if ($period->isOpen() && $period->isWindowOpenNow())
                                            <span class="badge bg-light-success border border-success">Open</span>
                                        @else
                                            <span class="badge bg-light-secondary border border-secondary">Closed</span>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $stats['rate'] }}% <span class="text-muted small">({{ $stats['responded'] }}/{{ $stats['eligible'] }})</span></td>
                                    <td class="text-center">{{ $stats['nominated'] }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('management.appreciation.show', $period) }}" class="btn btn-sm btn-link">
                                            <i class="ti ti-eye me-1"></i> View
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No history yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</x-app-layout>
