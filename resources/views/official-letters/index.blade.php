<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Official Letters</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item" aria-current="page">Official Letters</li>
                            </ul>
                        </div>
                        @if($canManage)
                            <a href="{{ route('official-letters.create') }}" class="btn btn-primary btn-sm">
                                <i class="ti ti-plus me-1"></i> New Letter
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($canAuthorizeDownloads && $pendingAuthorizationCount > 0 && $tab !== 'authorizations')
        <div class="alert alert-warning border-warning d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
            <div>
                <strong>{{ $pendingAuthorizationCount }}</strong>
                {{ \Illuminate\Support\Str::plural('letter', $pendingAuthorizationCount) }}
                waiting for download authorization.
            </div>
            <a href="{{ route('official-letters.index', ['tab' => 'authorizations']) }}" class="btn btn-warning btn-sm">
                Review now
            </a>
        </div>
    @endif

    <div class="row">
        <div class="col-md-12">
            <div class="card tbl-card mb-2">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                        <div>
                            @if($canAuthorizeDownloads)
                                <ul class="nav nav-tabs border-0 mb-0">
                                    <li class="nav-item">
                                        <a class="nav-link {{ $tab === 'mine' ? 'active' : '' }}"
                                           href="{{ route('official-letters.index', array_filter(['department_code' => request('department_code'), 'year' => request('year'), 'month' => request('month')])) }}">
                                            My Letters
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ $tab === 'authorizations' ? 'active' : '' }}"
                                           href="{{ route('official-letters.index', array_filter(['tab' => 'authorizations', 'department_code' => request('department_code'), 'year' => request('year'), 'month' => request('month')])) }}">
                                            Download authorizations
                                            @if($pendingAuthorizationCount > 0)
                                                <span class="badge bg-danger ms-1">{{ $pendingAuthorizationCount }}</span>
                                            @endif
                                        </a>
                                    </li>
                                </ul>
                            @else
                                <h5 class="mb-0">
                                    <i class="ti ti-mail me-2 text-primary"></i>My Official Letters
                                </h5>
                            @endif
                        </div>
                        <form method="GET" action="{{ route('official-letters.index') }}" class="row g-2 flex-grow-1" id="filterForm" style="max-width: 42rem;">
                            @if($tab === 'authorizations')
                                <input type="hidden" name="tab" value="authorizations">
                            @endif
                            <div class="col-md-4">
                                <select name="department_code" class="form-control" onchange="document.getElementById('filterForm').submit();">
                                    <option value="">All Departments</option>
                                    @foreach($departments as $code => $label)
                                        <option value="{{ $code }}" {{ request('department_code') === $code ? 'selected' : '' }}>
                                            {{ $code }} — {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select name="year" class="form-control" onchange="document.getElementById('filterForm').submit();">
                                    <option value="">All Years</option>
                                    @foreach($years as $year)
                                        <option value="{{ $year }}" {{ (string) request('year') === (string) $year ? 'selected' : '' }}>
                                            {{ $year }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select name="month" class="form-control" onchange="document.getElementById('filterForm').submit();">
                                    <option value="">All Months</option>
                                    @for($m = 1; $m <= 12; $m++)
                                        <option value="{{ $m }}" {{ (string) request('month') === (string) $m ? 'selected' : '' }}>
                                            {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                                        </option>
                                    @endfor
                                </select>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    @if($letters->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-center" style="width: 50px;">No.</th>
                                        <th>Reference</th>
                                        <th>Subject</th>
                                        <th style="width: 100px;">Dept</th>
                                        <th style="width: 120px;">Letter Date</th>
                                        <th style="width: 160px;">Created By</th>
                                        @if($tab === 'authorizations')
                                            <th style="width: 160px;">Requested By</th>
                                        @endif
                                        <th class="text-center" style="width: 200px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($letters as $letter)
                                        @php
                                            $isOwned = (string) $letter->created_by === (string) $user->id;
                                            $isSharedWithMe = ! $isOwned && $letter->sharedUsers->contains(
                                                fn ($shared) => (string) $shared->id === (string) $user->id
                                            );
                                            $canEditLetter = $isOwned || $isSharedWithMe;
                                            $canAuthorizeThis = $user->can('authorizeDownload', $letter);
                                        @endphp
                                        <tr>
                                            <td class="text-center">{{ ($letters->currentPage() - 1) * $letters->perPage() + $loop->iteration }}</td>
                                            <td>
                                                <code class="small">{{ $letter->reference_number }}</code>
                                                @if($isSharedWithMe)
                                                    <span class="badge bg-light-info border border-info ms-1">Shared</span>
                                                @endif
                                                @include('official-letters.partials.download-status-badge', ['letter' => $letter, 'class' => 'ms-1'])
                                            </td>
                                            <td>{{ \Illuminate\Support\Str::limit($letter->subject, 60) }}</td>
                                            <td>{{ $letter->department_code }}</td>
                                            <td>{{ $letter->letter_date->format('d/m/Y') }}</td>
                                            <td>
                                                <small class="text-muted">
                                                    {{ $letter->creator->name ?? 'N/A' }}<br>
                                                    <span>{{ $letter->created_at->format('M d, Y') }}</span>
                                                </small>
                                            </td>
                                            @if($tab === 'authorizations')
                                                <td>
                                                    <small>
                                                        {{ $letter->latestDownloadRequest?->requester?->name ?? 'N/A' }}
                                                    </small>
                                                </td>
                                            @endif
                                            <td class="text-center">
                                                @if($canAuthorizeThis)
                                                    <form method="POST" action="{{ route('official-letters.authorize-download', $letter) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success" title="Authorize download">
                                                            <i class="ti ti-check"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="{{ route('official-letters.reject-download', $letter) }}" class="d-inline" onsubmit="return confirm('Reject this download request?');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Reject download">
                                                            <i class="ti ti-x"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                                <a href="{{ route('official-letters.show', $letter) }}" class="btn btn-sm btn-light" title="View">
                                                    <i class="ti ti-eye"></i>
                                                </a>
                                                @include('official-letters.partials.download-button', ['letter' => $letter, 'user' => $user, 'compact' => true])
                                                @if($canEditLetter)
                                                    <a href="{{ route('official-letters.edit', $letter) }}" class="btn btn-sm btn-light" title="Edit">
                                                        <i class="ti ti-pencil"></i>
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            {{ $letters->links() }}
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="ti ti-mail-off" style="font-size: 3rem; opacity: 0.3;"></i>
                            <p class="text-muted mb-3 mt-3">
                                @if($tab === 'authorizations')
                                    No download authorization requests yet
                                @else
                                    No official letters found
                                @endif
                            </p>
                            @if($canManage && $tab !== 'authorizations')
                                <a href="{{ route('official-letters.create') }}" class="btn btn-primary btn-sm">
                                    <i class="ti ti-plus me-1"></i> Create your first letter
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @include('official-letters.partials.download-modals')
</x-app-layout>
