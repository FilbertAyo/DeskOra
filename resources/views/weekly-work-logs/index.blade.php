<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Personal Work Log</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item" aria-current="page">Work Log</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('weekly-work-logs.index') }}" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label">Work period</label>
                    <select name="period_id" class="form-select" onchange="this.form.submit()">
                        @foreach($periods as $optionPeriod)
                            <option value="{{ $optionPeriod->id }}" @selected(($period?->id ?? '') === $optionPeriod->id)>
                                Week {{ $optionPeriod->week_number }}, {{ $optionPeriod->year }}
                                ({{ $optionPeriod->date_range }})
                            </option>
                        @endforeach
                    </select>
                </div>
                @if($canViewTeam)
                    <div class="col-md-5">
                        <label class="form-label">Staff</label>
                        <select name="user_id" class="form-select" onchange="this.form.submit()">
                            @foreach($teamUsers as $teamUser)
                                <option value="{{ $teamUser->id }}" @selected($viewUserId === (string) $teamUser->id)>
                                    {{ $teamUser->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </form>
        </div>
    </div>

    @if(!$period)
        <div class="alert alert-warning">No work period is available yet.</div>
    @else
        @php
            $isOwnView = $viewUserId === (string) auth()->id();
            $todayKey = now()->toDateString();
        @endphp

        @if($isOwnView)
            <div class="card mb-3 border-primary">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="ti ti-edit me-2"></i>Log today ({{ now()->format('D, M j') }})</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ $todayLog ? route('weekly-work-logs.update', $todayLog) : route('weekly-work-logs.store') }}">
                        @csrf
                        @if($todayLog)
                            @method('PUT')
                        @else
                            <input type="hidden" name="period_id" value="{{ $period->id }}">
                            <input type="hidden" name="log_date" value="{{ $todayKey }}">
                        @endif
                        <div class="mb-3">
                            <label class="form-label">What I did</label>
                            <textarea name="done" class="form-control @error('done') is-invalid @enderror" rows="3" required>{{ old('done', $todayLog?->done) }}</textarea>
                            @error('done')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Blocker <span class="text-muted">(optional)</span></label>
                            <textarea name="blocker" class="form-control" rows="2">{{ old('blocker', $todayLog?->blocker) }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Next <span class="text-muted">(optional)</span></label>
                            <textarea name="next" class="form-control" rows="2">{{ old('next', $todayLog?->next) }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-device-floppy me-1"></i>{{ $todayLog ? 'Update today' : 'Save today' }}
                        </button>
                    </form>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    Week {{ $period->week_number }}, {{ $period->year }}
                    <span class="text-muted fw-normal">· {{ $period->date_range }}</span>
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 140px;">Day</th>
                                <th>Done</th>
                                <th>Blocker</th>
                                <th>Next</th>
                                @if($isOwnView)
                                    <th style="width: 160px;">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($days as $day)
                                @php $log = $day['log']; @endphp
                                <tr class="{{ $day['is_today'] ? 'table-primary' : '' }}">
                                    <td>
                                        <strong>{{ $day['date']->format('D') }}</strong><br>
                                        <small class="text-muted">{{ $day['date']->format('M j') }}</small>
                                    </td>
                                    <td>{!! $log ? nl2br(e($log->done)) : '<span class="text-muted">—</span>' !!}</td>
                                    <td>{!! $log && $log->blocker ? nl2br(e($log->blocker)) : '<span class="text-muted">—</span>' !!}</td>
                                    <td>{!! $log && $log->next ? nl2br(e($log->next)) : '<span class="text-muted">—</span>' !!}</td>
                                    @if($isOwnView)
                                        <td>
                                            @if($log && $log->hasBlocker() && ! $log->hasLinkedAction())
                                                <form method="POST" action="{{ route('weekly-work-logs.create-action', $log) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-warning">
                                                        Create action
                                                    </button>
                                                </form>
                                            @elseif($log && $log->hasLinkedAction())
                                                <span class="badge bg-light-success text-success border border-success">Action linked</span>
                                            @elseif(! $log && $day['is_today'])
                                                <span class="text-muted small">Use form above</span>
                                            @elseif(! $log)
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#logDayModal"
                                                    data-log-date="{{ $day['key'] }}"
                                                    data-log-label="{{ $day['date']->format('D, M j') }}">
                                                    Add
                                                </button>
                                            @else
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editLogModal{{ $log->id }}">
                                                    Edit
                                                </button>
                                            @endif
                                        </td>
                                    @endif
                                </tr>

                                @if($isOwnView && $log)
                                    <div class="modal fade" id="editLogModal{{ $log->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="{{ route('weekly-work-logs.update', $log) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit {{ $day['date']->format('D, M j') }}</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label">What I did</label>
                                                            <textarea name="done" class="form-control" rows="3" required>{{ $log->done }}</textarea>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Blocker</label>
                                                            <textarea name="blocker" class="form-control" rows="2">{{ $log->blocker }}</textarea>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Next</label>
                                                            <textarea name="next" class="form-control" rows="2">{{ $log->next }}</textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Save</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="{{ $isOwnView ? 5 : 4 }}" class="text-center text-muted py-4">No days in this period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if($isOwnView)
            <div class="modal fade" id="logDayModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('weekly-work-logs.store') }}">
                            @csrf
                            <input type="hidden" name="period_id" value="{{ $period->id }}">
                            <input type="hidden" name="log_date" id="logDayModalDate">
                            <div class="modal-header">
                                <h5 class="modal-title">Log <span id="logDayModalLabel"></span></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">What I did</label>
                                    <textarea name="done" class="form-control" rows="3" required></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Blocker</label>
                                    <textarea name="blocker" class="form-control" rows="2"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Next</label>
                                    <textarea name="next" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <script>
                document.getElementById('logDayModal')?.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    document.getElementById('logDayModalDate').value = button.getAttribute('data-log-date') || '';
                    document.getElementById('logDayModalLabel').textContent = button.getAttribute('data-log-label') || '';
                });

                // Offline-friendly: if submit fails while offline, queue the draft locally.
                document.querySelectorAll('form[action*="weekly-work-logs"]').forEach((form) => {
                    form.addEventListener('submit', function (event) {
                        if (navigator.onLine || !window.DeskOraOfflineLogs) {
                            return;
                        }
                        event.preventDefault();
                        const data = Object.fromEntries(new FormData(form).entries());
                        window.DeskOraOfflineLogs.saveDraft(data);
                        alert('You are offline. This work log was saved on this device and will sync when you are back online.');
                    });
                });
            </script>
        @endif
    @endif
</x-app-layout>
