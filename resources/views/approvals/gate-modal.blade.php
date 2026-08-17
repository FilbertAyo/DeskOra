{{-- Session reminder for pending requisitions and weekly budgets. Snooze lasts until next login. --}}
@php
    $gate = $approvalGateSnapshot;
@endphp
<div class="modal fade" id="pendingApprovalGateModal" tabindex="-1" aria-labelledby="pendingApprovalGateModalLabel"
    aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h4 class="mb-1" id="pendingApprovalGateModalLabel">Action needed</h4>
                    <p class="text-muted mb-0">
                        You have pending requisitions or weekly budgets. Open them now, or remind yourself later this session.
                    </p>
                </div>
            </div>
            <div class="modal-body pt-3">
                @if($gate->requisitionCount > 0)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-light-danger border border-danger text-danger">
                                {{ $gate->requisitionCount }} requisition{{ $gate->requisitionCount === 1 ? '' : 's' }} waiting
                            </span>
                            @if($gate->requisitionActionUrl)
                                <a href="{{ $gate->requisitionActionUrl }}" class="btn btn-sm btn-primary">
                                    Open requisitions
                                </a>
                            @endif
                        </div>
                        <div class="list-group">
                            @foreach($gate->requisitions as $item)
                                <a href="{{ $item['url'] }}" class="list-group-item list-group-item-action">
                                    <div class="d-flex justify-content-between gap-2">
                                        <div>
                                            <div class="fw-semibold">{{ $item['summary'] }}</div>
                                            <small class="text-muted">
                                                {{ $item['ref'] }}
                                                @if($item['requester_name']) · {{ $item['requester_name'] }} @endif
                                                @if($item['department_name']) · {{ $item['department_name'] }} @endif
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-semibold">TZS {{ $item['amount'] }}</div>
                                            <span class="badge bg-light-warning border border-warning text-dark">{{ $item['stage_label'] }}</span>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($gate->budgetRemaining > 0)
                    <div class="border rounded p-3 d-flex flex-wrap justify-content-between align-items-center gap-2 {{ $gate->requisitionCount > 0 ? '' : 'mt-0' }}">
                        <div>
                            <div class="fw-semibold">Weekly budgets</div>
                            <small class="text-muted">
                                {{ $gate->budgetRemaining }} item{{ $gate->budgetRemaining === 1 ? '' : 's' }} waiting in the latest two work periods.
                            </small>
                        </div>
                        @if($gate->budgetActionUrl)
                            <a href="{{ $gate->budgetActionUrl }}" class="btn btn-primary">
                                Open weekly budgets
                            </a>
                        @endif
                    </div>
                @endif
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" id="pagRemindLater">
                    Remind me later
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const modalEl = document.getElementById('pendingApprovalGateModal');
    if (!modalEl || typeof bootstrap === 'undefined') {
        return;
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const remindUrl = @json(route('approval-gate.remind-later'));
    const modal = new bootstrap.Modal(modalEl);
    const remindBtn = document.getElementById('pagRemindLater');

    function snooze() {
        if (remindBtn) remindBtn.disabled = true;
        fetch(remindUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        }).finally(function () {
            modal.hide();
        });
    }

    if (remindBtn) {
        remindBtn.addEventListener('click', snooze);
    }

    document.addEventListener('DOMContentLoaded', function () { modal.show(); });
    if (document.readyState !== 'loading') {
        modal.show();
    }
})();
</script>
@endpush
