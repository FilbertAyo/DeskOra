{{--
    Reusable detail block for a single nomination period.
    Expects: $period (NominationPeriod), $stats (array), $tallies (Collection), $reasons (Collection)
--}}
@php
    $maxVotes = $tallies->max('votes') ?: 1;
@endphp

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card shadow-none border mb-0">
            <div class="card-body">
                <p class="text-muted mb-1 small text-uppercase">Eligible staff</p>
                <h3 class="mb-0">{{ $stats['eligible'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card shadow-none border mb-0">
            <div class="card-body">
                <p class="text-muted mb-1 small text-uppercase">Responded</p>
                <h3 class="mb-0">{{ $stats['responded'] }} <span class="text-muted f-14">/ {{ $stats['eligible'] }}</span>       <span class="badge bg-light-primary border border-primary text-dark mt-1">{{ $stats['rate'] }}% participation</span>
                </h3>
           </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card shadow-none border mb-0">
            <div class="card-body">
                <p class="text-muted mb-1 small text-uppercase">Nominations</p>
                <h3 class="mb-0 text-success">{{ $stats['nominated'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card shadow-none border mb-0">
            <div class="card-body">
                <p class="text-muted mb-1 small text-uppercase">Skipped</p>
                <h3 class="mb-0 text-muted">{{ $stats['skipped'] }}</h3>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card shadow-none border mb-0 h-100">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0"><i class="ti ti-trophy me-2"></i>Most appreciated colleagues</h5>
                <p class="text-muted small mb-0 mt-1">Ranked by number of nominations received. Management decisions remain final.</p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="thead-light">
                            <tr>
                                <th class="text-center" style="width:56px;">#</th>
                                <th>Colleague</th>
                                <th style="width:220px;">Nominations</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tallies as $row)
                                @php $nominee = $row['user']; @endphp
                                <tr>
                                    <td class="text-center text-muted">{{ $loop->iteration }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="{{ $nominee?->file ? asset($nominee->file) : asset('assets/images/profile.png') }}"
                                                alt="" class="rounded-circle" style="width:36px;height:36px;object-fit:cover;">
                                            <div>
                                                <div class="fw-semibold">{{ $nominee?->name ?? 'Unknown user' }}</div>
                                                <small class="text-muted">{{ collect([$nominee?->title, $nominee?->department?->name])->filter()->implode(' • ') }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height:8px;">
                                                <div class="progress-bar bg-primary" role="progressbar"
                                                    style="width: {{ round(($row['votes'] / $maxVotes) * 100) }}%;"></div>
                                            </div>
                                            <span class="badge bg-light-primary border border-primary text-dark">{{ $row['votes'] }}</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-4">
                                        <i class="ti ti-users fs-1 text-muted"></i>
                                        <p class="text-muted mb-0">No nominations recorded for this period yet.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-none border mb-0 h-100">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0"><i class="ti ti-message-2 me-2"></i>Anonymous reasons</h5>
                <p class="text-muted small mb-0 mt-1">Comments cannot be traced to any nominator.</p>
            </div>
            <div class="card-body" style="max-height: 520px; overflow-y:auto;">
                @forelse ($reasons as $reason)
                    <div class="border rounded p-3 mb-2 bg-light">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                            <span class="fw-semibold small">
                                <i class="ti ti-arrow-right text-primary me-1"></i>{{ $reason->nominee?->name ?? 'Unknown user' }}
                            </span>
                            <small class="text-muted">{{ $reason->created_at?->format('M d') }}</small>
                        </div>
                        <div class="small text-body" style="white-space: pre-wrap;">{{ $reason->reason }}</div>
                    </div>
                @empty
                    <div class="text-center py-4">
                        <i class="ti ti-message-off fs-1 text-muted"></i>
                        <p class="text-muted mb-0">No written reasons submitted for this period.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
