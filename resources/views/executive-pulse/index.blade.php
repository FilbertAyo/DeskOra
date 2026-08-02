<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Executive Pulse</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item" aria-current="page">Executive Pulse</li>
                            </ul>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small">
                                Generated {{ $snapshot['generated_at']->format('d M Y, H:i') }}
                            </span>
                            <a href="{{ route('executive-pulse.download') }}" class="btn btn-sm btn-outline-danger">
                                <i class="ti ti-download me-1"></i> PDF
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3 col-6">
            <div class="card border shadow-none h-100">
                <div class="card-body">
                    <div class="text-muted small">Weekly reports</div>
                    <div class="fs-3 fw-semibold">{{ $snapshot['reports']['percent'] }}%</div>
                    <div class="small text-muted">
                        {{ $snapshot['reports']['submitted'] }} submitted · {{ $snapshot['reports']['owing'] }} owing
                        <br>{{ $snapshot['reports']['period_label'] }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border shadow-none h-100">
                <div class="card-body">
                    <div class="text-muted small">Weekly plans</div>
                    <div class="fs-3 fw-semibold">{{ $snapshot['plans']['percent'] }}%</div>
                    <div class="small text-muted">
                        {{ $snapshot['plans']['submitted'] }} submitted · {{ $snapshot['plans']['owing'] }} owing
                        <br>{{ $snapshot['plans']['period_label'] }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border shadow-none h-100">
                <div class="card-body">
                    <div class="text-muted small">Open finance approvals</div>
                    <div class="fs-3 fw-semibold">{{ $snapshot['approvals']['open_finance_requests'] }}</div>
                    <div class="small text-muted">
                        Budget pending: {{ $snapshot['approvals']['budget_pending_org'] }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border shadow-none h-100">
                <div class="card-body">
                    <div class="text-muted small">Action tasks (current week)</div>
                    <div class="fs-3 fw-semibold">{{ $snapshot['action_tasks']['active'] }}</div>
                    <div class="small text-muted">
                        {{ $snapshot['action_tasks']['overdue'] }} overdue ·
                        {{ $snapshot['action_tasks']['needs_update'] }} need update
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border shadow-none h-100">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Highlights & blockers</h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0 ps-3">
                        @foreach($snapshot['highlights'] as $item)
                            <li class="mb-2">{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border shadow-none h-100">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Deadlines</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">Plan deadline</div>
                        <div>{{ $snapshot['deadlines']['plan_text'] ?? 'No active deadline info' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Report deadline</div>
                        <div>{{ $snapshot['deadlines']['report_text'] ?? 'No active deadline info' }}</div>
                    </div>
                    <div>
                        <div class="text-muted small">Your pending finance approvals</div>
                        <div>{{ $snapshot['approvals']['viewer_pending_total'] }} pending for you</div>
                        @if($snapshot['approvals']['viewer_pending_total'] > 0)
                            <a href="{{ route('petty.list') }}" class="btn btn-sm btn-outline-primary mt-2">Open Approvals</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
