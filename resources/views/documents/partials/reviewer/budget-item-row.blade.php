@php
    $ctx = \App\Support\WeeklyBudgetApprovalFlow::itemViewContext($item, auth()->user());
@endphp
<tr>
    <td class="text-center">{{ $rowNumber }}</td>
    <td>{{ $item['item'] }}</td>
    <td>{{ $item['description_purpose'] }}</td>
    <td class="text-end">{{ $item['amount'] }}</td>
    <td class="text-end">
        @if($ctx['showProvided'])
            {{ number_format($ctx['displayProvided'], 2) }}
        @else
            <span class="text-muted">—</span>
        @endif
    </td>
    <td>
        <div class="d-flex flex-column gap-1">
            <span class="badge {{ $ctx['statusBadgeClass'] }}">{{ $ctx['statusLabel'] }}</span>
            @unless($ctx['isFinalApproved'] || $ctx['isRejected'])
                <small class="text-muted">
                    Step {{ $ctx['currentStep'] }}/3
                    @if($ctx['awaitingLabel'] && ! $ctx['actionLabel'])
                        · {{ $ctx['awaitingLabel'] }}
                    @endif
                </small>
            @endunless
        </div>
    </td>
    <td class="text-center">
        @if($ctx['actionLabel'])
            <x-table-action-dropdown :label="$ctx['actionLabel']">
                <button type="button" class="dropdown-item"
                    data-bs-toggle="modal"
                    data-bs-target="#itemReviewModal_{{ $item['document_id'] }}_{{ $item['budget_uid'] }}">
                    <i class="ti ti-check me-2"></i>
                    {{ $ctx['actionLabel'] }}
                </button>
            </x-table-action-dropdown>
        @elseif($ctx['awaitingLabel'])
            <span class="badge bg-light-info border border-info text-dark">{{ $ctx['awaitingLabel'] }}</span>
        @elseif($ctx['isFinalApproved'])
            <span class="badge bg-light-success border border-success">Complete</span>
        @elseif($ctx['isRejected'])
            <span class="badge bg-light-danger border border-danger">Closed</span>
        @else
            <span class="text-muted">—</span>
        @endif
    </td>
</tr>
