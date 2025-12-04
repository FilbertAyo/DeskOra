<x-app-layout>
    <div class="row align-items-center mb-3 border-bottom no-gutters">
        <div class="col">
            <ul class="nav nav-tabs border-0" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab"
                        aria-controls="home" aria-selected="true">
                        <i class="fe fe-clipboard me-1"></i> Submitted Documents Dashboard
                    </a>
                </li>
            </ul>
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

    <!-- Statistics Cards -->
    <div class="row mb-3">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border shadow-none">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <span class="text-muted d-block">Total Submitted</span>
                            <h3 class="mb-0">{{ $stats['total_submitted'] }}</h3>
                        </div>
                        <div class="text-primary">
                            <i class="fe fe-file-text fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border shadow-none">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <span class="text-muted d-block">Weekly Plans</span>
                            <h3 class="mb-0 text-info">{{ $stats['weekly_plans'] }}</h3>
                        </div>
                        <div class="text-info">
                            <i class="fe fe-calendar fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border shadow-none">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <span class="text-muted d-block">Weekly Reports</span>
                            <h3 class="mb-0 text-success">{{ $stats['weekly_reports'] }}</h3>
                        </div>
                        <div class="text-success">
                            <i class="fe fe-file-text fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border shadow-none">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <span class="text-muted d-block">Monthly Reports</span>
                            <h3 class="mb-0 text-warning">{{ $stats['monthly_reports'] }}</h3>
                        </div>
                        <div class="text-warning">
                            <i class="fe fe-bar-chart fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0"><i class="fe fe-filter me-2"></i>Filters</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('documents.reviewer-dashboard') }}" id="filterForm">
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <label class="form-label small text-muted">Document Type</label>
                                <select class="form-control form-control-sm" name="type" id="typeFilter">
                                    <option value="">All Types</option>
                                    <option value="weekly_plan" {{ request('type') == 'weekly_plan' ? 'selected' : '' }}>Weekly Plans</option>
                                    <option value="weekly_report" {{ request('type') == 'weekly_report' ? 'selected' : '' }}>Weekly Reports</option>
                                    <option value="monthly_report" {{ request('type') == 'monthly_report' ? 'selected' : '' }}>Monthly Reports</option>
                                    <option value="weekly_minutes" {{ request('type') == 'weekly_minutes' ? 'selected' : '' }}>Weekly Minutes</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label small text-muted">User</label>
                                <select class="form-control form-control-sm" name="user_id" id="userFilter">
                                    <option value="">All Users</option>
                                    @foreach($usersWithDocuments as $user)
                                        <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label small text-muted">Period</label>
                                <select class="form-control form-control-sm" name="period" id="periodFilter">
                                    <option value="">All Time</option>
                                    <option value="this_week" {{ request('period') == 'this_week' ? 'selected' : '' }}>This Week</option>
                                    <option value="last_week" {{ request('period') == 'last_week' ? 'selected' : '' }}>Last Week</option>
                                    <option value="this_month" {{ request('period') == 'this_month' ? 'selected' : '' }}>This Month</option>
                                    <option value="last_month" {{ request('period') == 'last_month' ? 'selected' : '' }}>Last Month</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label small text-muted">Specific Week</label>
                                <select class="form-control form-control-sm" name="work_period_id">
                                    <option value="">Any Week</option>
                                    @foreach($periodOptions as $periodOption)
                                        <option value="{{ $periodOption->id }}" {{ request('work_period_id') == $periodOption->id ? 'selected' : '' }}>
                                            Week {{ $periodOption->week_number }} • {{ $periodOption->date_range }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label small text-muted">Year</label>
                                <select class="form-control form-control-sm" name="year">
                                    <option value="">Any Year</option>
                                    @foreach($years as $year)
                                        <option value="{{ $year }}" {{ request('year') == $year ? 'selected' : '' }}>
                                            {{ $year }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="fe fe-filter me-1"></i> Apply Filters
                                </button>
                            </div>
                        </div>
                        @if(request()->hasAny(['type', 'user_id', 'period']))
                            <div class="row mt-2">
                                <div class="col-md-12">
                                    <a href="{{ route('documents.reviewer-dashboard') }}" class="btn btn-sm btn-light">
                                        <i class="fe fe-x me-1"></i> Clear Filters
                                    </a>
                                </div>
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Grouped Documents by Type -->
    <div class="row">
        @php
            $typeLabels = [
                'weekly_plan' => ['name' => 'Weekly Plans', 'icon' => 'fe-calendar', 'color' => 'info'],
                'weekly_report' => ['name' => 'Weekly Reports', 'icon' => 'fe-file-text', 'color' => 'success'],
                'monthly_report' => ['name' => 'Monthly Reports', 'icon' => 'fe-bar-chart', 'color' => 'warning'],
                'weekly_minutes' => ['name' => 'Weekly Minutes', 'icon' => 'fe-clipboard', 'color' => 'primary'],
            ];
        @endphp

        @foreach($groupedDocuments as $type => $documents)
            @if($documents->count() > 0 || !$selectedType)
                <div class="col-md-12 mb-4">
                    <div class="card shadow-none border">
                        <div class="card-header bg-white border-bottom">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fe {{ $typeLabels[$type]['icon'] }} me-2 text-{{ $typeLabels[$type]['color'] }}"></i>
                                    {{ $typeLabels[$type]['name'] }}
                                    <span class="badge badge-{{ $typeLabels[$type]['color'] }} ml-2">{{ $documents->count() }}</span>
                                </h5>
                            </div>
                        </div>
                        <div class="card-body">
                            @if($documents->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-hover table-bordered">
                                        <thead class="thead-light">
                                            <tr>
                                                <th class="text-center" style="width: 50px;">No.</th>
                                                <th>Title</th>
                                                <th>Created By</th>
                                                <th>Submitted</th>
                                                <th>Comments</th>
                                                <th class="text-center" style="width: 150px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($documents as $document)
                                                <tr>
                                                    <td class="text-center">{{ $loop->iteration }}</td>
                                                    <td>
                                                        <strong>{{ $document->display_title }}</strong>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mr-2" style="width: 24px; height: 24px; font-size: 10px;">
                                                                {{ strtoupper(substr($document->user->name, 0, 1)) }}
                                                            </div>
                                                            <span>{{ $document->user->name }}</span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            {{ $document->updated_at->format('M d, Y') }}
                                                            <br>
                                                            <span class="text-muted">{{ $document->updated_at->diffForHumans() }}</span>
                                                        </small>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge badge-primary badge-pill">
                                                            {{ $document->comments->count() }}
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        @if($document->type === \App\Models\Document::TYPE_WEEKLY_MINUTES)
                                                            <a href="{{ route('minutes.show', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}"
                                                                class="btn btn-info btn-sm text-white" title="View">
                                                                <i class="fe fe-eye"></i>
                                                            </a>
                                                        @else
                                                            <a href="{{ route('documents.show', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}"
                                                                class="btn btn-info btn-sm text-white" title="View">
                                                                <i class="fe fe-eye"></i>
                                                            </a>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-center py-4">
                                    <i class="fe {{ $typeLabels[$type]['icon'] }} fs-1 text-muted"></i>
                                    <p class="text-muted mb-0 mt-2">No {{ strtolower($typeLabels[$type]['name']) }} found</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        @endforeach

        @if(collect($groupedDocuments)->sum(fn($docs) => $docs->count()) === 0)
            <div class="col-md-12">
                <div class="card shadow-none border">
                    <div class="card-body">
                        <div class="text-center py-5">
                            <i class="fe fe-clipboard fs-1 text-muted"></i>
                            <p class="text-muted mb-0 mt-3">No submitted documents found matching your filters</p>
                            <a href="{{ route('documents.reviewer-dashboard') }}" class="btn btn-primary btn-sm mt-3">
                                <i class="fe fe-refresh-cw me-1"></i> Clear Filters
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
