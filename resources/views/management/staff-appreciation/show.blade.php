<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">{{ $period->label() }}</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('management.appreciation.index') }}">Staff Appreciation</a></li>
                                <li class="breadcrumb-item" aria-current="page">{{ $period->label() }}</li>
                            </ul>
                        </div>
                        <a href="{{ route('management.appreciation.index') }}" class="btn btn-sm btn-light">
                            <i class="ti ti-arrow-left me-1"></i> Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-none border">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0">
                {{ $period->label() }}
                @if ($isWindowOpen)
                    <span class="badge bg-light-success border border-success ms-2">Open now</span>
                @else
                    <span class="badge bg-light-secondary border border-secondary ms-2">Closed</span>
                @endif
            </h5>
            <p class="text-muted small mb-0 mt-1">
                Window: {{ $period->opens_at->format('M d, Y H:i') }} – {{ $period->closes_at->format('M d, Y H:i') }}
            </p>
        </div>
        <div class="card-body">
            @include('management.staff-appreciation.partials.period-detail', [
                'period' => $period,
                'stats' => $stats,
                'tallies' => $tallies,
                'reasons' => $reasons,
            ])
        </div>
    </div>
</x-app-layout>
