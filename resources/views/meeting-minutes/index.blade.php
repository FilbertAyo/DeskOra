<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Meeting Minutes</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item" aria-current="page">Meeting Minutes</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ti ti-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="ti ti-alert-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('info'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="ti ti-info-circle me-2"></i>{{ session('info') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-md-12">
            <div class="card tbl-card mb-2">
                <div class="card-header bg-white border-bottom">
                    <div class="row align-items-center">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <h5 class="mb-0">
                                <i class="ti ti-file-text me-2 text-primary"></i>Weekly Meeting Minutes
                            </h5>
                        </div>
                        <div class="col-md-6">
                            <form method="GET" action="{{ route('meeting-minutes.index') }}" class="row" id="filterForm">
                                <div class="col-md-12 mb-2">
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
                    @if($periods->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-center" style="width: 50px;">No.</th>
                                        <th>Work Period</th>
                                        <th style="width: 90px;">Year</th>
                                        <th style="width: 140px;">Status</th>
                                        <th style="width: 180px;">Created By</th>
                                        <th class="text-center" style="width: 120px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($periods as $period)
                                        @php
                                            $minute = $period->meetingMinute;
                                        @endphp
                                        <tr>
                                            <td class="text-center">{{ ($periods->currentPage() - 1) * $periods->perPage() + $loop->iteration }}</td>
                                            <td>
                                                <strong>Week {{ $period->week_number }}</strong>
                                                <div><small class="text-muted">{{ $period->date_range }}</small></div>
                                                @if($currentPeriod && $currentPeriod->id === $period->id)
                                                    <span class="badge bg-light-primary border border-primary mt-1">Current Week</span>
                                                @endif
                                            </td>
                                            <td>{{ $period->year }}</td>
                                            <td>
                                                @if($minute)
                                                    <span class="badge bg-light-success border border-success">Available</span>
                                                @else
                                                    <span class="badge bg-light-secondary border border-secondary">Not Available</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($minute)
                                                    <small class="text-muted">
                                                        {{ $minute->uploader->name ?? 'N/A' }}<br>
                                                        <span>{{ $minute->created_at->format('M d, Y') }}</span>
                                                    </small>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <x-table-action-dropdown>
                                                    @if($minute)
                                                        <a class="dropdown-item" href="{{ route('meeting-minutes.show', $minute) }}">
                                                            <i class="ti ti-eye me-2"></i> View
                                                        </a>
                                                        <a class="dropdown-item" href="{{ route('meeting-minutes.download', $minute) }}">
                                                            <i class="ti ti-download me-2"></i> Download
                                                        </a>
                                                        @can('manage minutes')
                                                            <a class="dropdown-item" href="{{ route('meeting-minutes.edit', $minute) }}">
                                                                <i class="ti ti-edit me-2"></i> Edit
                                                            </a>
                                                        @endcan
                                                    @elseif($canManageMinutes)
                                                        <a class="dropdown-item" href="{{ route('meeting-minutes.create', ['period_id' => $period->id]) }}">
                                                            <i class="ti ti-upload me-2"></i> Upload
                                                        </a>
                                                    @else
                                                        <span class="dropdown-item text-muted disabled">
                                                            <i class="ti ti-file-off me-2"></i> No minutes yet
                                                        </span>
                                                    @endif
                                                </x-table-action-dropdown>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">
                            {{ $periods->links() }}
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="ti ti-calendar-off text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-3 mb-0">No work periods found.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
