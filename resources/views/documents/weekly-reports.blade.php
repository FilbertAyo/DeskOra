<x-app-layout>
    <div class="row align-items-center mb-3 border-bottom no-gutters">
        <div class="col">
            <ul class="nav nav-tabs border-0" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="reports-tab" data-toggle="tab" href="#reports" role="tab"
                        aria-controls="reports" aria-selected="true">
                        <i class="fe fe-activity me-1"></i> Weekly Reports
                    </a>
                </li>
            </ul>
        </div>
        <div class="col-auto">
            <div class="btn-group">
                
                <a href="{{ route('documents.create') }}?type=weekly_report" class="btn btn-sm btn-primary">
                    <i class="fe fe-plus-circle me-1"></i> New Weekly Report
                </a>
            </div>
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
                            <h5 class="mb-0">
                                <i class="fe fe-activity me-2 text-primary"></i>Weekly Reports
                            </h5>
                        </div>
                        <div class="col-md-6">
                            <form method="GET" action="{{ route('documents.weekly-reports') }}" class="row" id="filterForm">
                                <div class="col-md-6 mb-2">
                                    <select name="period_id" class="form-control" onchange="document.getElementById('filterForm').submit();">
                                        <option value="" {{ !request('period_id') ? 'selected' : '' }}>All Weeks</option>
                                        @if($currentPeriod)
                                            <option value="{{ $currentPeriod->id }}" {{ request('period_id') == $currentPeriod->id ? 'selected' : '' }}>
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
                                <div class="col-md-6 mb-2">
                                    <select name="year" class="form-control" onchange="document.getElementById('filterForm').submit();">
                                        <option value="" {{ !request('year') ? 'selected' : '' }}>All Years</option>
                                        <option value="{{ date('Y') }}" {{ request('year') == date('Y') ? 'selected' : '' }}>
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
                    <!-- Deadline Status -->
                    @if($deadlineStatuses)
                        <div class="alert alert-primary mb-4">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="mb-1">
                                        <i class="fe fe-clock me-2"></i>Weekly Report Deadline
                                    </h6>
                                    <p class="mb-0">
                                        <strong>{{ \Carbon\Carbon::parse($deadlineStatuses['report_due'])->format('M d, Y') }}</strong>
                                        <span class="badge {{ $deadlineStatuses['report_badge'] }} ml-2">
                                            {{ $deadlineStatuses['report_text'] }}
                                        </span>
                                    </p>
                                </div>
                                <div class="col-md-4 text-right">
                                    @if(isset($availableTypes['weekly_report']))
                                        <a href="{{ route('documents.create') }}?type=weekly_report" class="btn btn-primary">
                                            <i class="fe fe-plus-circle me-1"></i> Create New Report
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Reports List -->
                    @if($documents->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-center" style="width: 50px;">No.</th>
                                        <th>Title</th>
                                        <th>Week Period</th>
                                        <th>Author</th>
                                        <th>Created</th>
                                        <th>Last Updated</th>
                                        <th class="text-center" style="width: 120px;">Status</th>
                                        <th class="text-center" style="width: 200px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($documents as $document)
                                        <tr>
                                            <td class="text-center">{{ ($documents->currentPage() - 1) * $documents->perPage() + $loop->iteration }}</td>
                                            <td>
                                                <strong>{{ $document->display_title }}</strong>
                                            </td>
                                            <td>
                                                @if($document->period)
                                                    <small class="text-muted">
                                                        Week {{ $document->period->week_number }} • {{ $document->period->date_range }}
                                                    </small>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($document->user)
                                                    <div class="d-flex align-items-center">
                                                       
                                                        <span>{{ $document->user->name }}</span>
                                                    </div>
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
                                                        class="btn btn-primary text-white" title="View">
                                                        <i class="fe fe-eye"></i>
                                                    </a>
                                                    @if($document->canBeEdited())
                                                        <a href="{{ route('documents.edit', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}"
                                                            class="btn btn-info text-white" title="Edit">
                                                            <i class="fe fe-edit"></i>
                                                        </a>
                                                    @endif
                                                    @if($document->period)
                                                        @php
                                                            $relatedPlan = \App\Models\Document::where('period_id', $document->period_id)
                                                                ->where('type', \App\Models\Document::TYPE_WEEKLY_PLAN)
                                                                ->forUser($document->user_id)
                                                                ->first();
                                                        @endphp
                                                        @if($relatedPlan)
                                                            <a href="{{ route('documents.show', \Vinkla\Hashids\Facades\Hashids::encode($relatedPlan->id)) }}"
                                                                class="btn btn-outline-info" title="View Related Plan">
                                                                <i class="fe fe-target"></i>
                                                            </a>
                                                        @endif
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
                            <i class="fe fe-activity fs-1 text-muted"></i>
                            <p class="text-muted mb-3 mt-3">No weekly reports found</p>
                            <p class="text-muted small">
                                @if(request('period_id'))
                                    No reports found for the selected week period. Try selecting a different week or create a new report.
                                @else
                                    Try adjusting your filters or create a new weekly report.
                                @endif
                            </p>
                            @if(isset($availableTypes['weekly_report']))
                                <a href="{{ route('documents.create') }}?type=weekly_report" class="btn btn-primary">
                                    <i class="fe fe-plus-circle me-1"></i>Create New Weekly Report
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <style>
        .bg-info-light {
            background-color: #e7f3ff;
        }
        .bg-primary-light {
            background-color: #f0f4ff;
        }
    </style>
</x-app-layout>

