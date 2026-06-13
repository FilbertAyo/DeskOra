<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Work Period: Week {{ $period->week_number }} of {{ $period->year }}</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('work-periods.index') }}">Work Periods</a></li>
                                <li class="breadcrumb-item" aria-current="page">Week {{ $period->week_number }}</li>
                            </ul>
                        </div>
                        <div class="d-flex gap-2">
                            @if(Auth::user()->hasPermissionTo('manage work periods'))
                                <a href="{{ route('work-periods.edit', $period) }}" class="btn btn-sm btn-primary">
                                    <i class="ti ti-edit me-1"></i> Edit
                                </a>
                            @endif
                            <a href="{{ route('work-periods.index') }}" class="btn btn-sm btn-light">
                                <i class="ti ti-arrow-left me-1"></i> Back
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card border shadow-none">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="ti ti-calendar me-2"></i>Period details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-lg-3 mb-3">
                            <small class="text-muted d-block">Date range</small>
                            <strong>{{ $period->date_range }}</strong>
                        </div>
                        <div class="col-md-6 col-lg-3 mb-3">
                            <small class="text-muted d-block">Plan deadline</small>
                            <strong>{{ $period->getPlanDeadlineCutoff()->format('M d, Y H:i') }}</strong>
                        </div>
                        <div class="col-md-6 col-lg-3 mb-3">
                            <small class="text-muted d-block">Report deadline</small>
                            <strong>{{ $period->getReportDeadlineCutoff()->format('M d, Y H:i') }}</strong>
                        </div>
                        <div class="col-md-6 col-lg-3 mb-3">
                            <small class="text-muted d-block">Status</small>
                            @if($period->status === 'open')
                                <span class="badge bg-success">Open</span>
                            @elseif($period->status === 'closed')
                                <span class="badge bg-secondary">Closed</span>
                            @else
                                <span class="badge bg-dark">Archived</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-6 col-md-3 mb-3">
            <div class="card border shadow-none text-center">
                <div class="card-body py-3">
                    <div class="text-muted small">All documents</div>
                    <div class="h4 mb-0 text-primary">{{ $stats['total_documents'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="card border shadow-none text-center">
                <div class="card-body py-3">
                    <div class="text-muted small">Weekly plans</div>
                    <div class="h4 mb-0">{{ $stats['weekly_plans'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="card border shadow-none text-center">
                <div class="card-body py-3">
                    <div class="text-muted small">Weekly reports</div>
                    <div class="h4 mb-0">{{ $stats['weekly_reports'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="card border shadow-none text-center">
                <div class="card-body py-3">
                    <div class="text-muted small">Monthly reports</div>
                    <div class="h4 mb-0">{{ $stats['monthly_reports'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card border shadow-none">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="ti ti-files me-2"></i>Documents in this period</h5>
                </div>
                <div class="card-body">
                    @if($period->documents->isEmpty())
                        <p class="text-muted mb-0">No documents linked to this work period yet.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered table-striped mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Type</th>
                                        <th>Title</th>
                                        <th>User</th>
                                        <th class="text-center">State</th>
                                        <th class="text-center" style="width: 100px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($period->documents->sortBy('type') as $doc)
                                        <tr>
                                            <td><span class="badge bg-light text-dark">{{ $doc->type_name }}</span></td>
                                            <td>{{ $doc->display_title }}</td>
                                            <td>{{ $doc->user?->name ?? '—' }}</td>
                                            <td class="text-center">
                                                @if($doc->state === 'submitted')
                                                    <span class="badge bg-success">Submitted</span>
                                                @else
                                                    <span class="badge bg-warning text-dark">Draft</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <x-table-action-dropdown>
    <a class="dropdown-item" href="{{ $doc->type === \App\Models\Document::TYPE_ACTION_LOG ? route('action-logs.show', $doc) : route('documents.show', $doc) }}">
    <i class="ti ti-eye me-2"></i> View
    </a>
</x-table-action-dropdown>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
