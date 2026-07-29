<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
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

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ti ti-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="ti ti-alert-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-md-12">
            <div class="card tbl-card mb-2">
                <div class="card-header bg-white border-bottom">
                    <div class="row align-items-center">
                        <div class="col-md-4 mb-2 mb-md-0">
                            <h5 class="mb-0">
                                <i class="ti ti-mail me-2 text-primary"></i>My Official Letters
                            </h5>
                        </div>
                        <div class="col-md-8">
                            <form method="GET" action="{{ route('official-letters.index') }}" class="row g-2" id="filterForm">
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
                                        <th class="text-center" style="width: 140px;">Actions</th>
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
                                        @endphp
                                        <tr>
                                            <td class="text-center">{{ ($letters->currentPage() - 1) * $letters->perPage() + $loop->iteration }}</td>
                                            <td>
                                                <code class="small">{{ $letter->reference_number }}</code>
                                                @if($isSharedWithMe)
                                                    <span class="badge bg-light-info border border-info ms-1">Shared</span>
                                                @endif
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
                                            <td class="text-center">
                                                <a href="{{ route('official-letters.show', $letter) }}" class="btn btn-sm btn-light" title="View">
                                                    <i class="ti ti-eye"></i>
                                                </a>
                                                <a href="{{ route('official-letters.download', $letter) }}" class="btn btn-sm btn-danger" title="Download PDF">
                                                    <i class="ti ti-download"></i>
                                                </a>
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
                            <p class="text-muted mb-3 mt-3">No official letters found</p>
                            @if($canManage)
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
</x-app-layout>
