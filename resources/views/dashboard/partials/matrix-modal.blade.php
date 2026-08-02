@if (($currentPeriod ?? null))
    <div class="modal fade" id="dashboardMatrixModal" tabindex="-1" aria-labelledby="dashboardMatrixModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable weekly-matrix-modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dashboardMatrixModalLabel">
                        Weekly Matrix — Week {{ $currentPeriod->week_number }} · {{ $currentPeriod->year }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="dashboardMatrixBody">
                    <div class="text-center text-muted py-5">
                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                        Loading matrix…
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="{{ route('weekly-matrix.period', $currentPeriod) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="ti ti-external-link me-1"></i> Open period
                    </a>
                    <a href="{{ route('weekly-matrix.download', $currentPeriod) }}" class="btn btn-sm btn-outline-danger">
                        <i class="ti ti-download me-1"></i> Download PDF
                    </a>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalEl = document.getElementById('dashboardMatrixModal');
                const bodyEl = document.getElementById('dashboardMatrixBody');
                if (!modalEl || !bodyEl) {
                    return;
                }

                let loaded = false;
                modalEl.addEventListener('show.bs.modal', function () {
                    if (loaded) {
                        return;
                    }

                    fetch('{{ route('weekly-matrix.period.table', $currentPeriod) }}', {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    })
                        .then((response) => response.ok ? response.json() : Promise.reject(response))
                        .then((data) => {
                            bodyEl.innerHTML = data.html || '<p class="text-muted mb-0">No matrix present.</p>';
                            loaded = true;
                        })
                        .catch(() => {
                            bodyEl.innerHTML = '<div class="alert alert-warning mb-0">Could not load the weekly matrix. Please try again.</div>';
                        });
                });
            });
        </script>
    @endpush
@endif
