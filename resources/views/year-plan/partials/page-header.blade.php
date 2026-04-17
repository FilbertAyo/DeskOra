<div class="page-header">
    <div class="page-block">
        <div class="row align-items-center">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Year plan {{ $yearPlan->year }}</h5>
                        <ul class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('year-plan.index') }}">Year Plans</a></li>
                            <li class="breadcrumb-item" aria-current="page">{{ $yearPlan->year }}</li>
                        </ul>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#activityModal"
                            id="btnAddActivityTop">
                            <i class="ti ti-circle-plus me-1"></i> Add activity
                        </button>
                        @can('create', \App\Models\Document::class)
                            @if(\App\Models\Document::canUserCreateType(auth()->user(), 'monthly_report'))
                                @php
                                    $ypY = (int) $yearPlan->year;
                                    $cy = (int) now()->year;
                                    $defaultMrMonth = $ypY === $cy
                                        ? ((int) now()->month === 1 ? 12 : (int) now()->month - 1)
                                        : ($ypY < $cy ? 12 : 1);
                                @endphp
                               
                            @endif
                        @endcan
                        <a href="{{ route('year-plan.index') }}" class="btn btn-light btn-sm">
                            <i class="ti ti-arrow-left me-1"></i> All years
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
