@php
    $todayWorkLog = $todayWorkLog ?? null;
    $currentPeriod = $currentPeriod ?? null;
    $todayKey = now()->toDateString();
@endphp

@if($currentPeriod)
    <div class="modal fade" id="dashboardWorkLogModal" tabindex="-1" aria-labelledby="dashboardWorkLogModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST"
                    action="{{ $todayWorkLog ? route('weekly-work-logs.update', $todayWorkLog) : route('weekly-work-logs.store') }}"
                    class="js-dashboard-work-log-form">
                    @csrf
                    @if($todayWorkLog)
                        @method('PUT')
                    @else
                        <input type="hidden" name="period_id" value="{{ $currentPeriod->id }}">
                        <input type="hidden" name="log_date" value="{{ $todayKey }}">
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title" id="dashboardWorkLogModalLabel">
                            <i class="ti ti-notebook me-2"></i>{{ $todayWorkLog ? 'Edit' : 'Add' }} work log
                            <span class="text-muted fw-normal">· {{ now()->format('D, M j') }}</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="dashboardWorkLogDone" class="form-label">What I did</label>
                            <textarea
                                name="done"
                                id="dashboardWorkLogDone"
                                class="form-control @error('done') is-invalid @enderror"
                                rows="3"
                                required
                                maxlength="4000"
                                placeholder="What did you accomplish today?">{{ old('done', $todayWorkLog?->done) }}</textarea>
                            @error('done')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="dashboardWorkLogBlocker" class="form-label">Blocker <span class="text-muted">(optional)</span></label>
                            <textarea
                                name="blocker"
                                id="dashboardWorkLogBlocker"
                                class="form-control"
                                rows="2"
                                maxlength="4000"
                                placeholder="Anything blocking progress?">{{ old('blocker', $todayWorkLog?->blocker) }}</textarea>
                        </div>
                        <div class="mb-0">
                            <label for="dashboardWorkLogNext" class="form-label">Next <span class="text-muted">(optional)</span></label>
                            <textarea
                                name="next"
                                id="dashboardWorkLogNext"
                                class="form-control"
                                rows="2"
                                maxlength="4000"
                                placeholder="What’s next?">{{ old('next', $todayWorkLog?->next) }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-device-floppy me-1"></i>{{ $todayWorkLog ? 'Update' : 'Save' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if($errors->has('done') || $errors->has('blocker') || $errors->has('next') || $errors->has('period_id') || $errors->has('log_date'))
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const modalEl = document.getElementById('dashboardWorkLogModal');
                    if (modalEl && window.bootstrap?.Modal) {
                        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    }
                });
            </script>
        @endpush
    @endif

    @once
        @push('scripts')
            <script>
                document.querySelectorAll('.js-dashboard-work-log-form').forEach((form) => {
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
        @endpush
    @endonce
@endif
