<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Weekly Reports</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('documents.index') }}">Documents</a></li>
                                <li class="breadcrumb-item" aria-current="page">Weekly Reports</li>
                            </ul>
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
                        <div class="col-md-6 mb-2 mb-md-0">
                            <h5 class="mb-0">
                                <i class="ti ti-activity me-2 text-primary"></i>Weekly Reports
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
                        <div class="alert {{ ($weeklyReportSlot ?? null)?->graceActive ? 'alert-warning' : 'alert-info' }} mb-4">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="mb-1">
                                        Weekly Report Deadline
                                        @if(($weeklyReportSlot ?? null)?->period)
                                            <span class="text-muted fw-normal">(for Week {{ $weeklyReportSlot->period->week_number }} of {{ $weeklyReportSlot->period->year }})</span>
                                        @elseif(isset($currentPeriod))
                                            <span class="text-muted fw-normal">(for Week {{ $currentPeriod->week_number }})</span>
                                        @endif
                                    </h6>
                                    <p class="mb-0">
                                        <strong>{{ \Carbon\Carbon::parse($deadlineStatuses['report_due'])->format('M d, Y H:i') }}</strong>
                                        <span class="badge bg-light-danger {{ $deadlineStatuses['report_badge'] }} ml-2">
                                            {{ $deadlineStatuses['report_text'] }}
                                        </span>
                                    </p>
                                    @if(($weeklyReportSlot ?? null)?->graceActive && ($weeklyReportSlot->graceEndsAt ?? null))
                                        <p class="mb-0 mt-2 small">
                                            <i class="ti ti-clock-hour-4 me-1"></i>
                                            Grace period: you can still create or submit until {{ $weeklyReportSlot->graceEndsAt->format('M d, Y H:i') }}.
                                        </p>
                                    @endif
                                </div>
                                <div class="col-md-4 text-md-end mt-2 mt-md-0">
                                    @if(isset($availableTypes['weekly_report']))
                                        @include('documents.partials.weekly-submission-action-button', [
                                            'slot' => $weeklyReportSlot ?? null,
                                            'documentType' => 'weekly_report',
                                        ])
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Reports List -->
                    @if($documents->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered table-striped">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-center" style="width: 50px;">No.</th>
                                        <th>Title</th>
                                        <th>Week Period</th>
                                        <th>Author</th>
                                        <th>Collaborators</th>
                                        <th>Created</th>
                                        <th>Last Updated</th>
                                        <th class="text-center" style="width: 120px;">Status</th>
                                        <th class="text-center" style="width: 200px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($documents as $document)
                                        @php
                                            $sharedUsersForDisplay = $document->relationLoaded('effectiveSharedUsers')
                                                ? $document->getRelation('effectiveSharedUsers')
                                                : $document->sharedUsers;
                                        @endphp
                                        <tr>
                                            <td class="text-center">{{ ($documents->currentPage() - 1) * $documents->perPage() + $loop->iteration }}</td>
                                            <td>
                                                <strong>{{ $document->display_title }}</strong>
                                                @if($sharedUsersForDisplay->isNotEmpty())
                                                    <div class="mt-1">
                                                        @if((string) auth()->id() === (string) $document->user_id)
                                                            <span class="badge bg-light-primary text-primary">Owner (Shared)</span>
                                                        @else
                                                            <span class="badge bg-light-info text-info">Shared With You</span>
                                                        @endif
                                                    </div>
                                                @endif
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
                                                @include('documents.partials.weekly-shared-names-cell', ['document' => $document])
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
                                                    <span class="badge bg-light-warning">
                                                        <i class="ti ti-edit me-1"></i>Draft
                                                    </span>
                                                    @if(isset($returnedDocumentMap[$document->id]))
                                                        <span class="badge bg-light-danger mt-1">
                                                            <i class="ti ti-arrow-back-up me-1"></i>Returned
                                                        </span>
                                                    @endif
                                                @else
                                                    <span class="badge bg-light-success">
                                                        <i class="ti ti-circle-check me-1"></i>Submitted
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <x-table-action-dropdown>
    <a class="dropdown-item" href="{{ route('documents.show', $document->id) }}">
    <i class="ti ti-eye me-2"></i> View
    </a>
    @if($document->canBeEdited())
    <a class="dropdown-item" href="{{ route('documents.edit', $document->id) }}">
    <i class="ti ti-edit me-2"></i> Edit
    </a>
    @endif
    @if($document->period)
    @php
    $relatedPlan = $document->relatedWeeklyDocument();
    @endphp
    @if($relatedPlan)
    <a class="dropdown-item" href="{{ route('documents.show', $relatedPlan->id) }}">
    <i class="ti ti-target me-2"></i> View Related Plan
    </a>
    @endif
    @endif
</x-table-action-dropdown>
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
                            <i class="ti ti-activity fs-1 text-muted"></i>
                            <p class="text-muted mb-3 mt-3">No weekly reports found</p>
                          
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <style>
        .table-hover tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.05);
        }
    </style>
</x-app-layout>
