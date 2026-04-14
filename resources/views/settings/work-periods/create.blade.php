<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Create Work Period</h5>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('work-periods.index') }}">Work Periods</a></li>
                            <li class="breadcrumb-item" aria-current="page">Create</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card border shadow-none">
                <div class="card-body">
                    <p class="mb-3">New periods are created from the work periods list using <strong>Add Work Period</strong> (next week only).</p>
                    <a href="{{ route('work-periods.index') }}" class="btn btn-primary">
                        <i class="ti ti-arrow-left me-1"></i> Go to Work Periods
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
