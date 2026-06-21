<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Activity log</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="javascript: void(0)">Settings</a></li>
                                <li class="breadcrumb-item" aria-current="page">Activity log</li>
                            </ul>
                        </div>

                        <div class="col-auto d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-light" onclick="reloadPage()" title="Refresh">
                                <i class="ti ti-refresh"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-none border">
              
                <div class="card-body border-bottom">
                    @php
                        $selectedCauserId = request('causer_id');
                        $staffIds = $staffUsers->pluck('id')->map(fn ($id) => (string) $id)->all();
                        $selectedCauserMissing = filled($selectedCauserId) && ! in_array((string) $selectedCauserId, $staffIds, true);
                    @endphp
                    <form method="GET" action="{{ route('activity-log.index') }}" class="row g-3 align-items-end">
                        <div class="col-md-3 col-lg-2">
                            <label class="form-label small mb-0" for="filter-from">From</label>
                            <input type="date" name="from" id="filter-from" class="form-control form-control-sm"
                                   value="{{ request('from') }}">
                        </div>
                        <div class="col-md-3 col-lg-2">
                            <label class="form-label small mb-0" for="filter-to">To</label>
                            <input type="date" name="to" id="filter-to" class="form-control form-control-sm"
                                   value="{{ request('to') }}">
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <label class="form-label small mb-0" for="filter-causer">Staff</label>
                            <select name="causer_id" id="filter-causer" class="form-select form-select-sm">
                                <option value="">All</option>
                                @foreach ($staffUsers as $u)
                                    <option value="{{ $u->id }}" @selected((string) request('causer_id') === (string) $u->id)>
                                        {{ $u->name }}
                                    </option>
                                @endforeach
                                @if ($selectedCauserMissing)
                                    <option value="{{ $selectedCauserId }}" selected>
                                        Unknown / deleted staff (#{{ $selectedCauserId }})
                                    </option>
                                @endif
                            </select>
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <label class="form-label small mb-0" for="filter-log">Log name</label>
                            <select name="log_name" id="filter-log" class="form-select form-select-sm">
                                <option value="">All</option>
                                @foreach ($logNames as $ln)
                                    <option value="{{ $ln }}" @selected(request('log_name') === $ln)>{{ $ln }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <label class="form-label small mb-0" for="filter-event">Event</label>
                            <select name="event" id="filter-event" class="form-select form-select-sm">
                                <option value="">All</option>
                                @foreach ($events as $ev)
                                    <option value="{{ $ev }}" @selected(request('event') === $ev)>{{ $ev }}</option>
                                @endforeach
                            </select>
                        </div>
                     
                        <div class="col-auto d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                            <a href="{{ route('activity-log.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                        </div>
                    </form>
                    <p class="text-muted small mt-2 mb-0">
                        Entries from removed users are shown as <span class="fw-semibold">Unknown / deleted staff</span> with their ID.
                    </p>
                </div>

                <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h5 class="mb-0"><i class="ti ti-list-details me-2"></i>Entries</h5>
                    <span class="text-muted small">{{ $activities->total() }} total</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered table-striped table-sm align-middle mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>When</th>
                                    <th>Staff</th>
                                    <th>Event</th>
                                    <th>Description</th>
                                    <th>Log</th>
                                    <th>Subject</th>
                                    <th class="text-end">Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($activities as $activity)
                                    @php
                                        $subjectLabel = '—';
                                        $causerLabel = 'System';

                                        if ($activity->causer?->name) {
                                            $causerLabel = $activity->causer->name;
                                        } elseif ($activity->causer_id) {
                                            $causerLabel = 'Unknown / deleted staff (#'.$activity->causer_id.')';
                                        }

                                        if ($activity->subject) {
                                            $subjectLabel = class_basename($activity->subject_type).' #'.$activity->subject_id;
                                        } elseif ($activity->subject_type) {
                                            $subjectLabel = class_basename($activity->subject_type).' #'.$activity->subject_id;
                                        }
                                    @endphp
                                    <tr>
                                        <td class="text-nowrap small">{{ $activity->created_at->format('Y-m-d H:i') }}</td>
                                        <td class="small">{{ $causerLabel }}</td>
                                        <td><code class="small">{{ $activity->event ?? '—' }}</code></td>
                                        <td class="small">{{ \Illuminate\Support\Str::limit($activity->description, 80) }}</td>
                                        <td><span class="badge bg-light-light border border-light text-dark">{{ $activity->log_name ?? 'default' }}</span></td>
                                        <td class="small font-monospace">{{ $subjectLabel }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('activity-log.show', $activity) }}" class="btn btn-sm btn-outline-primary">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">No activity entries match your filters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($activities->hasPages())
                        <div class="p-3 border-top">
                            
                            {{ $activities->links('pagination::bootstrap-5') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>


 
</x-app-layout>
