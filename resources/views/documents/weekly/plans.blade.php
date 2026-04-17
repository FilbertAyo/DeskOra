<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Weekly Plans</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('documents.index') }}">Documents</a></li>
                                <li class="breadcrumb-item" aria-current="page">Weekly Plans</li>
                            </ul>
                        </div>

                        <div class="col-auto">
                            @if(isset($availableTypes['weekly_plan']))
                                <a href="{{ route('documents.create') }}?type=weekly_plan" class="btn btn-info btn-sm">
                                    <i class="ti ti-circle-plus me-1"></i> New Weekly Plan
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card tbl-card mb-2">
                <div class="card-header bg-white border-bottom">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0">
                                <i class="ti ti-target me-2 text-info"></i>Weekly Plans
                            </h5>
                        </div>
                        <div class="col-md-6">
                            <form method="GET" action="{{ route('documents.weekly-plans') }}" class="row" id="filterForm">
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
                        <div class="alert alert-info mb-4">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="mb-1">
                                        Weekly Plan Deadline
                                        @isset($nextPeriod)
                                            <span class="text-muted fw-normal">(for Week {{ $nextPeriod->week_number }})</span>
                                        @endisset
                                    </h6>
                                    <p class="mb-0">
                                        <strong>{{ \Carbon\Carbon::parse($deadlineStatuses['plan_due'])->format('M d, Y') }}</strong>
                                        <span class="badge bg-danger {{ $deadlineStatuses['plan_badge'] }} ml-2">
                                            {{ $deadlineStatuses['plan_text'] }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Plans List -->
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
                                                    <span class="badge bg-warning">
                                                        <i class="ti ti-edit me-1"></i>Draft
                                                    </span>
                                                    @if(isset($returnedDocumentMap[$document->id]))
                                                        <span class="badge bg-danger mt-1">
                                                            <i class="ti ti-arrow-back-up me-1"></i>Returned
                                                        </span>
                                                    @endif
                                                @else
                                                    <span class="badge bg-success">
                                                        <i class="ti ti-circle-check me-1"></i>Submitted
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="{{ route('documents.show', $document->id) }}"
                                                        class="btn btn-info text-white" title="View">
                                                        <i class="ti ti-eye"></i>
                                                    </a>
                                                    @if($document->canBeEdited())
                                                        <a href="{{ route('documents.edit', $document->id) }}"
                                                            class="btn btn-primary text-white" title="Edit">
                                                            <i class="ti ti-edit"></i>
                                                        </a>
                                                    @endif
                                                    @if($document->period)
                                                        @php
                                                            $relatedReport = \App\Models\Document::where('period_id', $document->period_id)
                                                                ->where('type', \App\Models\Document::TYPE_WEEKLY_REPORT)
                                                                ->forUser($document->user_id)
                                                                ->first();
                                                        @endphp
                                                        @if($relatedReport)
                                                            <a href="{{ route('documents.show', $relatedReport->id) }}"
                                                                class="btn btn-outline-primary" title="View Related Report">
                                                                <i class="ti ti-activity"></i>
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
                            <i class="ti ti-target fs-1 text-muted"></i>
                            <p class="text-muted mb-3 mt-3">No weekly plans found</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

</x-app-layout>

