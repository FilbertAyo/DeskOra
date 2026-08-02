@php
    $perf = $monthlyPerformance ?? [
        'categories' => [],
        'series' => [],
        'tips' => [],
        'overall_score' => 0,
        'pending_reviews' => 0,
    ];
    $overallScore = (int) ($perf['overall_score'] ?? 0);
    $scoreTone = $overallScore >= 80 ? 'success' : ($overallScore >= 50 ? 'warning' : 'danger');
    $tipCount = count($perf['tips'] ?? []);
@endphp

<div class="dash-panel dash-panel--performance">
    <div class="dash-panel__header">
        <h6 class="dash-panel__title">
            <i class="ti ti-chart-bar text-primary"></i>My monthly performance
        </h6>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-light-{{ $scoreTone }} text-{{ $scoreTone }} border border-{{ $scoreTone }}">
                {{ $overallScore }}% this month
            </span>
            <button type="button"
                class="btn btn-sm btn-outline-primary"
                data-bs-toggle="modal"
                data-bs-target="#staffPerformanceDetailsModal"
                title="View performance details and coach tips">
                <i class="ti ti-list-details me-1"></i>Details
                @if($tipCount > 0)
                    <span class="badge bg-primary ms-1">{{ $tipCount }}</span>
                @endif
            </button>
        </div>
    </div>
    <div class="dash-panel__body">
        <div id="staffPerformanceChart" class="staff-performance-chart"></div>
    </div>
</div>

<div class="modal fade" id="staffPerformanceDetailsModal" tabindex="-1" aria-labelledby="staffPerformanceDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staffPerformanceDetailsModalLabel">
                    Performance details
                    <span class="badge bg-light-{{ $scoreTone }} text-{{ $scoreTone }} border border-{{ $scoreTone }} ms-2">
                        {{ $overallScore }}%
                    </span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @if(($perf['pending_reviews'] ?? 0) > 0)
                    <div class="alert alert-warning py-2 px-3 small mb-3">
                        <i class="ti ti-alert-circle me-1"></i>
                        {{ $perf['pending_reviews'] }} approval {{ \Illuminate\Support\Str::plural('item', $perf['pending_reviews']) }} waiting for you now.
                    </div>
                @endif

                <div class="staff-performance-tips">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <h6 class="mb-0 small text-uppercase text-muted">Coach tips</h6>
                        <button type="button"
                            class="btn btn-sm btn-outline-warning d-none"
                            data-ai-assist-only
                            data-ai-polish-performance-tips
                            title="Rewrite tips with AI">
                            <i class="ti ti-flare me-1"></i>Polish with AI
                        </button>
                    </div>
                    <ul class="staff-performance-tips__list mb-0" id="staffPerformanceTips">
                        @forelse(($perf['tips'] ?? []) as $tip)
                            <li>{{ $tip }}</li>
                        @empty
                            <li class="text-muted">No tips right now — keep up the good work.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ asset('assets/js/plugins/apexcharts.min.js') }}"></script>
<script>
(function () {
    const payload = @json($perf);
    const el = document.getElementById('staffPerformanceChart');
    if (!el || typeof ApexCharts === 'undefined') return;

    const series = (payload.series || []).map((item) => ({
        name: item.name,
        data: (item.data || []).map((value) => (value === null || value === undefined ? null : Number(value))),
    }));

    if (!series.length) {
        el.innerHTML = '<p class="text-muted small mb-0">No performance series available for your role yet.</p>';
        return;
    }

    const chart = new ApexCharts(el, {
        chart: {
            type: 'bar',
            height: 280,
            toolbar: { show: false },
            fontFamily: 'Public Sans, sans-serif',
        },
        series: series,
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '55%',
                borderRadius: 2,
            },
        },
        dataLabels: { enabled: false },
        stroke: { show: true, width: 2, colors: ['transparent'] },
        xaxis: {
            categories: payload.categories || [],
        },
        yaxis: {
            min: 0,
            max: 100,
            labels: {
                formatter: (value) => Math.round(value) + '%',
            },
        },
        colors: ['#4680ff', '#0caf60', '#faad14', '#dc2626', '#7c3aed'],
        legend: {
            position: 'bottom',
            fontSize: '12px',
        },
        tooltip: {
            y: {
                formatter: (value) => (value === null || value === undefined ? 'n/a' : value + '%'),
            },
        },
        grid: {
            borderColor: '#e5e7eb',
            strokeDashArray: 4,
        },
    });
    chart.render();

    window.__staffPerformancePayload = payload;
})();
</script>
@endpush
