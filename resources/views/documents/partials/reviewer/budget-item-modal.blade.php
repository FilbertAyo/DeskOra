@php
    use App\Support\WeeklyBudgetApprovalFlow;

    $ctx = WeeklyBudgetApprovalFlow::itemViewContext($item, auth()->user());
@endphp
@if($ctx['actionLabel'])
    <div class="modal fade" id="itemReviewModal_{{ $item['document_id'] }}_{{ $item['budget_uid'] }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('documents.process-weekly-budget-review', $item['document_id']) }}" class="modal-content">
                @csrf
                <input type="hidden" name="budget_uid" value="{{ $item['budget_uid'] }}">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $ctx['modalTitle'] }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if($ctx['isDirectAuthorization'])
                        <div class="alert alert-light border py-2 mb-3">
                            <small>
                                You are authorizing before earlier steps were completed.
                                Review and approval will be recorded automatically with your amount.
                            </small>
                        </div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label text-muted">Item</label>
                        <div class="fw-semibold">{{ $item['item'] }}</div>
                        @if($item['description_purpose'])
                            <small class="text-muted d-block">{{ $item['description_purpose'] }}</small>
                        @endif
                        <div class="mt-2">
                            <span class="badge bg-light-warning border border-warning text-dark">Requested: {{ $item['amount'] }}</span>
                        </div>
                    </div>

                    @if($ctx['nextStage'] === WeeklyBudgetApprovalFlow::STAGE_APPROVE && $ctx['reviewAmount'] !== null)
                        <div class="alert alert-light border mb-3 py-2">
                            <small class="text-muted d-block mb-1">Previous review</small>
                            <div class="fw-semibold">
                                Provided amount:
                                <span class="badge bg-light-success border border-success">{{ number_format($ctx['reviewAmount'], 2) }}</span>
                            </div>
                            @if(! empty($item['review_stage_by_name']))
                                <small class="text-muted d-block mt-1">
                                    Reviewed by {{ $item['review_stage_by_name'] }}
                                    @if(! empty($item['review_stage_at']))
                                        on {{ \Carbon\Carbon::parse($item['review_stage_at'])->format('M d, Y H:i') }}
                                    @endif
                                </small>
                            @endif
                        </div>
                    @endif

                    @if($ctx['nextStage'] === WeeklyBudgetApprovalFlow::STAGE_AUTHORIZE && ! $ctx['isDirectAuthorization'])
                        <div class="alert alert-light border mb-3 py-2">
                            <small class="text-muted d-block mb-1">Approval history</small>
                            @if($ctx['reviewAmount'] !== null)
                                <div class="mb-1">
                                    Review amount:
                                    <span class="badge bg-light-success border border-success">{{ number_format($ctx['reviewAmount'], 2) }}</span>
                                    @if(! empty($item['review_stage_by_name']))
                                        <small class="text-muted">by {{ $item['review_stage_by_name'] }}</small>
                                    @endif
                                </div>
                            @endif
                            @if($ctx['approveAmount'] !== null)
                                <div>
                                    Approval amount:
                                    <span class="badge bg-light-success border border-success">{{ number_format($ctx['approveAmount'], 2) }}</span>
                                    @if(! empty($item['approve_stage_by_name']))
                                        <small class="text-muted">by {{ $item['approve_stage_by_name'] }}</small>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label">Decision</label>
                        <select name="decision" class="form-control js-budget-decision" required>
                            <option value="approved" selected>Approve</option>
                            <option value="rejected">Reject</option>
                        </select>
                    </div>
                    <div class="mb-3 js-provided-amount-group">
                        <label class="form-label">{{ $ctx['providedAmountLabel'] }}</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="provided_amount"
                            value="{{ number_format($ctx['defaultProvidedInput'], 2, '.', '') }}"
                            class="form-control js-provided-amount-input">
                        <small class="text-muted">Required when decision is approve.</small>
                    </div>
                    <div class="mb-0 js-note-group">
                        <label class="form-label">Note</label>
                        <textarea name="note" rows="3" class="form-control js-note-input" placeholder="Reason for rejection">{{ $item['note'] ?? '' }}</textarea>
                        <small class="text-muted">Required when decision is reject.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn {{ $ctx['nextStage'] === WeeklyBudgetApprovalFlow::STAGE_AUTHORIZE ? 'btn-success' : 'btn-primary' }}">
                        {{ $ctx['submitLabel'] }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
