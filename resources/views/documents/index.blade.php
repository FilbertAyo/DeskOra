<x-app-layout>
    <div class="row align-items-center mb-3 border-bottom no-gutters">
        <div class="col">
            <ul class="nav nav-tabs border-0" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab"
                        aria-controls="home" aria-selected="true">
                        <i class="fe fe-file-text me-1"></i> My Documents
                    </a>
                </li>
            </ul>
        </div>
        <div class="col-auto">
            <a href="{{ route('documents.create') }}" class="btn mb-2 btn-primary btn-sm">
                <span class="btn-label">
                    <i class="fe fe-plus-circle"></i>
                </span>
                New Document
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fe fe-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fe fe-alert-circle me-2"></i>{{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-none border mb-4">
                <div class="card-header bg-white border-bottom">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0"><i class="fe fe-list me-2"></i>Documents List</h5>
                        </div>
                        <div class="col-md-6">
                            <form method="GET" action="{{ route('documents.index') }}" class="row">
                                <div class="col-md-4 mb-2">
                                    <select name="type" class="form-control">
                                        @if(request('type') === 'monthly_report')
                                            <option value="monthly_report" selected>Monthly Reports</option>
                                        @else
                                            <option value="">All Types</option>
                                            <option value="weekly_plan" {{ request('type') === 'weekly_plan' ? 'selected' : '' }}>Weekly Plans</option>
                                            <option value="weekly_report" {{ request('type') === 'weekly_report' ? 'selected' : '' }}>Weekly Reports</option>
                                            <option value="monthly_report" {{ request('type') === 'monthly_report' ? 'selected' : '' }}>Monthly Reports</option>
                                            <option value="weekly_minutes" {{ request('type') === 'weekly_minutes' ? 'selected' : '' }}>Minutes</option>
                                        @endif
                                    </select>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <select name="period_id" class="form-control">
                                        @if($currentPeriod)
                                            <option value="{{ $currentPeriod->id }}" {{ (!request('period_id') || request('period_id') == $currentPeriod->id) ? 'selected' : '' }}>
                                                Current Week (Week {{ $currentPeriod->week_number }})
                                            </option>
                                        @endif
                                        @foreach($periods as $period)
                                            @if(!$currentPeriod || $period->id != $currentPeriod->id)
                                                <option value="{{ $period->id }}" {{ request('period_id') == $period->id ? 'selected' : '' }}>
                                                    Week {{ $period->week_number }} • {{ $period->date_range }}
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <select name="year" class="form-control">
                                        <option value="{{ date('Y') }}" {{ (!request('year') || request('year') == date('Y')) ? 'selected' : '' }}>
                                            Current Year ({{ date('Y') }})
                                        </option>
                                        @foreach($years as $year)
                                            @if($year != date('Y'))
                                                <option value="{{ $year }}" {{ request('year') == $year ? 'selected' : '' }}>
                                                    {{ $year }}
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1"><i class="fe fe-target me-1 text-info"></i>Plan Deadline</p>
                                        @if($deadlineStatuses)
                                            <h5 class="mb-1">{{ \Carbon\Carbon::parse($deadlineStatuses['plan_due'])->format('M d, Y') }}</h5>
                                            <span class="badge {{ $deadlineStatuses['plan_badge'] }}">{{ $deadlineStatuses['plan_text'] }}</span>
                                        @else
                                            <span class="text-muted">No current period</span>
                                        @endif
                                    </div>
                                    <a href="{{ route('documents.index', ['type' => 'weekly_plan']) }}" class="btn btn-outline-info btn-sm">
                                        View Plans
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1"><i class="fe fe-activity me-1 text-primary"></i>Report Deadline</p>
                                        @if($deadlineStatuses)
                                            <h5 class="mb-1">{{ \Carbon\Carbon::parse($deadlineStatuses['report_due'])->format('M d, Y') }}</h5>
                                            <span class="badge {{ $deadlineStatuses['report_badge'] }}">{{ $deadlineStatuses['report_text'] }}</span>
                                        @else
                                            <span class="text-muted">No current period</span>
                                        @endif
                                    </div>
                                    <a href="{{ route('documents.index', ['type' => 'weekly_report']) }}" class="btn btn-outline-primary btn-sm">
                                        View Reports
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1"><i class="fe fe-calendar me-1 text-secondary"></i>Quick Period</p>
                                        @if($currentPeriod)
                                            <h5 class="mb-1">Week {{ $currentPeriod->week_number }}</h5>
                                            <small class="text-muted">{{ $currentPeriod->date_range }}</small>
                                        @else
                                            <span class="text-muted">No period</span>
                                        @endif
                                    </div>
                                    <a href="{{ route('work-periods.index') }}" class="btn btn-outline-secondary btn-sm">
                                        Work Periods
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($documents->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-center" style="width: 50px;">No.</th>
                                        <th>Document Type</th>
                                        <th>Title</th>
                                        <th>Author</th>
                                        <th>Week</th>
                                        <th>Created</th>
                                        <th>Last Updated</th>
                                        <th class="text-center" style="width: 120px;">Status</th>
                                        <th class="text-center" style="width: 150px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($documents as $document)
                                        <tr>
                                            <td class="text-center">{{ ($documents->currentPage() - 1) * $documents->perPage() + $loop->iteration }}</td>
                                            <td>
                                                <span class="badge badge-info">
                                                    <i class="fe fe-file me-1"></i>
                                                    {{ $document->type_name }}
                                                </span>
                                            </td>
                                            <td>
                                                <strong>{{ $document->display_title }}</strong>
                                            </td>
                                            <td>
                                                @if($document->user)
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mr-2" style="width: 24px; height: 24px; font-size: 10px;">
                                                            {{ strtoupper(substr($document->user->name, 0, 1)) }}
                                                        </div>
                                                        <span>{{ $document->user->name }}</span>
                                                    </div>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($document->period)
                                                    <small class="text-muted">
                                                        Week {{ $document->period->week_number }}
                                                    </small>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    {{ $document->created_at->format('M d, Y') }}
                                                </small>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    {{ $document->updated_at->format('M d, Y') }}
                                                    <br>
                                                    <span class="text-muted">{{ $document->updated_at->diffForHumans() }}</span>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                @if($document->state === 'draft')
                                                    <span class="badge badge-warning">
                                                        <i class="fe fe-edit me-1"></i>Draft
                                                    </span>
                                                @else
                                                    <span class="badge badge-success">
                                                        <i class="fe fe-check-circle me-1"></i>Submitted
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="{{ route('documents.show', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}"
                                                        class="btn btn-info text-white" title="View">
                                                        <i class="fe fe-eye"></i>
                                                    </a>
                                                    @if($document->canBeEdited())
                                                        <a href="{{ route('documents.edit', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}"
                                                            class="btn btn-primary text-white" title="Edit">
                                                            <i class="fe fe-edit"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">
                            {{ $documents->links() }}
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="fe fe-file-text fs-1 text-muted"></i>
                            <p class="text-muted mb-3 mt-3">No documents found</p>
                            <p class="text-muted small">Try adjusting your filters or create a new document.</p>
                            <a href="{{ route('documents.create') }}" class="btn btn-primary">
                                <i class="fe fe-plus-circle me-1"></i>Create New Document
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

