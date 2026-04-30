<x-app-layout>

<div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                                <h5 class="m-b-10">Requisition Details</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="javascript: void(0)">Petty Cash</a></li>
                                <li class="breadcrumb-item" aria-current="page">Requisition Details</li>
                            </ul>
                        </div>

                        <div class="col-auto d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-light" onclick="reloadPage()"
                                title="Refresh">
                                <i class="ti ti-refresh"></i>
                            </button>
                            <a href="{{ route('requisition.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i>Back
            </a>
            @if (in_array($request->status, ['pending', 'resubmission']))
                <a href="{{ route('requisition.edit', $request->id) }}" class="btn btn-primary btn-sm text-white">
                    <i class="ti ti-edit me-1"></i>Edit
                </a>
            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>




    @include('requests.requisition.partials.form-preview')

    @include('requests.approval.logs')
</x-app-layout>
