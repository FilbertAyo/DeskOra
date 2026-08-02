<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Feature usage</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="javascript: void(0)">Settings</a></li>
                                <li class="breadcrumb-item" aria-current="page">Feature usage</li>
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
                        $staffIds = $staffUsers->pluck('id')->map(fn ($id) => (string) $id)->all();
                        $selectedUserMissing = filled($selectedUserId) && ! in_array((string) $selectedUserId, $staffIds, true);
                    @endphp
                    <form method="GET" action="{{ route('feature-usage.index') }}" class="row g-3 align-items-end">
                        <div class="col-md-3 col-lg-2">
                            <label class="form-label small mb-0" for="filter-from">From</label>
                            <input type="date" name="from" id="filter-from" class="form-control form-control-sm"
                                   value="{{ $from }}">
                        </div>
                        <div class="col-md-3 col-lg-2">
                            <label class="form-label small mb-0" for="filter-to">To</label>
                            <input type="date" name="to" id="filter-to" class="form-control form-control-sm"
                                   value="{{ $to }}">
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <label class="form-label small mb-0" for="filter-module">Module</label>
                            <select name="module" id="filter-module" class="form-select form-select-sm">
                                <option value="">All</option>
                                @foreach ($modules as $mod)
                                    <option value="{{ $mod }}" @selected((string) $selectedModule === (string) $mod)>{{ $mod }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <label class="form-label small mb-0" for="filter-event-type">Event type</label>
                            <select name="event_type" id="filter-event-type" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="page_view" @selected($selectedEventType === 'page_view')>Page view</option>
                                <option value="feature_click" @selected($selectedEventType === 'feature_click')>Feature click</option>
                            </select>
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <label class="form-label small mb-0" for="filter-user">Staff</label>
                            <select name="user_id" id="filter-user" class="form-select form-select-sm">
                                <option value="">All</option>
                                @foreach ($staffUsers as $u)
                                    <option value="{{ $u->id }}" @selected((string) $selectedUserId === (string) $u->id)>
                                        {{ $u->name }}{{ $u->status !== 'active' ? ' ('.$u->status.')' : '' }}
                                    </option>
                                @endforeach
                                @if ($selectedUserMissing)
                                    <option value="{{ $selectedUserId }}" selected>
                                        Unknown / deleted staff (#{{ $selectedUserId }})
                                    </option>
                                @endif
                            </select>
                        </div>

                        <div class="col-auto d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                            <a href="{{ route('feature-usage.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                        </div>
                    </form>
                    <p class="text-muted small mt-2 mb-0">
                        Adoption % is unique users ÷ active staff. Share % is interactions ÷ all tracked interactions in the period.
                        Distinct from Activity log (which audits model changes).
                    </p>
                </div>

                <div class="card-body border-bottom">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">Active staff</div>
                                <div class="fs-4 fw-semibold">{{ number_format($activeUsers) }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">Tracked interactions</div>
                                <div class="fs-4 fw-semibold">{{ number_format($totalInteractions) }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small">Modules with activity</div>
                                <div class="fs-4 fw-semibold">{{ number_format($moduleRows->count()) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                @if ($chart['labels'] !== [])
                    <div class="card-body border-bottom">
                        <h6 class="mb-3">Top modules by share</h6>
                        <div id="featureUsageChart" style="min-height: 260px;"></div>
                    </div>
                @endif

                <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h5 class="mb-0"><i class="ti ti-layout-grid me-2"></i>Modules</h5>
                    <span class="text-muted small">{{ $from }} → {{ $to }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered table-striped table-sm align-middle mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Module</th>
                                    <th class="text-end">Interactions</th>
                                    <th class="text-end">Unique users</th>
                                    <th style="min-width: 160px;">Adoption %</th>
                                    <th style="min-width: 160px;">Share %</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($moduleRows as $row)
                                    <tr>
                                        <td class="fw-semibold">{{ $row['label'] }}</td>
                                        <td class="text-end">{{ number_format($row['interactions']) }}</td>
                                        <td class="text-end">{{ number_format($row['unique_users']) }}</td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height: 6px;">
                                                    <div class="progress-bar bg-primary" style="width: {{ min(100, $row['adoption']) }}%"></div>
                                                </div>
                                                <span class="small text-nowrap">{{ number_format($row['adoption'], 1) }}%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height: 6px;">
                                                    <div class="progress-bar bg-success" style="width: {{ min(100, $row['share']) }}%"></div>
                                                </div>
                                                <span class="small text-nowrap">{{ number_format($row['share'], 1) }}%</span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">No module usage in this period.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-header bg-white border-bottom border-top d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h5 class="mb-0"><i class="ti ti-click me-2"></i>Features &amp; pages</h5>
                    <span class="text-muted small">{{ $featureRows->count() }} rows</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered table-striped table-sm align-middle mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Feature / page</th>
                                    <th>Module</th>
                                    <th>Type</th>
                                    <th class="text-end">Interactions</th>
                                    <th class="text-end">Unique users</th>
                                    <th style="min-width: 140px;">Adoption %</th>
                                    <th style="min-width: 140px;">Share %</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($featureRows as $row)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $row['label'] }}</div>
                                            <code class="small text-muted">{{ $row['key'] }}</code>
                                        </td>
                                        <td class="small">{{ $row['module'] ?? '—' }}</td>
                                        <td>
                                            <span class="badge bg-light-light border border-light text-dark">
                                                {{ ($row['event_type'] ?? '') === 'feature_click' ? 'Feature' : 'Page' }}
                                            </span>
                                        </td>
                                        <td class="text-end">{{ number_format($row['interactions']) }}</td>
                                        <td class="text-end">{{ number_format($row['unique_users']) }}</td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height: 6px;">
                                                    <div class="progress-bar bg-primary" style="width: {{ min(100, $row['adoption']) }}%"></div>
                                                </div>
                                                <span class="small text-nowrap">{{ number_format($row['adoption'], 1) }}%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height: 6px;">
                                                    <div class="progress-bar bg-success" style="width: {{ min(100, $row['share']) }}%"></div>
                                                </div>
                                                <span class="small text-nowrap">{{ number_format($row['share'], 1) }}%</span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">No feature usage in this period.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        @if ($chart['labels'] !== [])
            <script src="{{ asset('assets/js/plugins/apexcharts.min.js') }}"></script>
            <script>
                (function () {
                    const el = document.getElementById('featureUsageChart');
                    if (!el || typeof ApexCharts === 'undefined') return;

                    const chart = new ApexCharts(el, {
                        chart: {
                            type: 'bar',
                            height: 260,
                            toolbar: { show: false },
                            fontFamily: 'Public Sans, sans-serif',
                        },
                        series: [{
                            name: 'Share %',
                            data: @json($chart['series']),
                        }],
                        plotOptions: {
                            bar: {
                                horizontal: true,
                                borderRadius: 2,
                                barHeight: '60%',
                            },
                        },
                        dataLabels: {
                            enabled: true,
                            formatter: (value) => value + '%',
                        },
                        xaxis: {
                            categories: @json($chart['labels']),
                            max: 100,
                            labels: {
                                formatter: (value) => Math.round(value) + '%',
                            },
                        },
                        colors: ['#4680ff'],
                        tooltip: {
                            y: {
                                formatter: (value) => value + '% of interactions',
                            },
                        },
                        grid: {
                            borderColor: '#e5e7eb',
                            strokeDashArray: 4,
                        },
                    });
                    chart.render();
                })();
            </script>
        @endif
        <script>
            function reloadPage() {
                window.location.reload();
            }
        </script>
    @endpush
</x-app-layout>
