@php
    /** @var array $cashFlowForecast */
    $forecast = $cashFlowForecast ?? null;
@endphp
@if($forecast)
    <div class="row g-2 mb-3">
        <div class="col-md-3 col-6">
            <div class="border rounded p-3 h-100 bg-white">
                <div class="text-muted small">Available balance</div>
                <div class="fs-5 fw-semibold {{ $forecast['available'] < 0 ? 'text-danger' : 'text-success' }}">
                    {{ number_format($forecast['available'], 0, '.', ',') }}/=
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="border rounded p-3 h-100 bg-white">
                <div class="text-muted small">Committed (awaiting payment)</div>
                <div class="fs-5 fw-semibold">
                    {{ number_format($forecast['committed'], 0, '.', ',') }}/=
                </div>
                <div class="small text-muted">{{ (int) $forecast['awaiting_payment_count'] }} request(s)</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="border rounded p-3 h-100 bg-white">
                <div class="text-muted small">Projected after payments</div>
                <div class="fs-5 fw-semibold {{ $forecast['shortage'] ? 'text-danger' : '' }}">
                    {{ number_format($forecast['projected'], 0, '.', ',') }}/=
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="border rounded p-3 h-100 bg-white">
                <div class="text-muted small">Authorized budgets ({{ $forecast['horizon_label'] }})</div>
                <div class="fs-5 fw-semibold">
                    {{ number_format($forecast['authorized_budget_next_two_weeks'], 0, '.', ',') }}/=
                </div>
                <div class="small text-muted">Planning signal — not auto-deducted</div>
            </div>
        </div>
    </div>
    @if($forecast['shortage'])
        <div class="alert alert-warning py-2">
            <i class="ti ti-alert-triangle me-1"></i>
            Committed payments exceed available balance. Top up deposits before clearing the payment queue.
        </div>
    @endif
@endif
