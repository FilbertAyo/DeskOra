@php
    $monthLabels = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
    ];
@endphp

<x-app-layout>
    @include('year-plan.partials.page-header')
    
    <p class="text-muted small mb-3">
        Total cost per line is <strong>frequency × units × unit value (TZS)</strong>, stored when you save.
    </p>

    @include('year-plan.partials.activities-table')

    <div class="card shadow-none border border-primary mb-4">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-semibold">Grand total (TZS)</span>
            <span class="fs-5 mb-0">{{ number_format($grandTotal, 2) }}</span>
        </div>
    </div>

    @include('year-plan.partials.activity-modals')
    @include('year-plan.partials.activity-script')
</x-app-layout>
