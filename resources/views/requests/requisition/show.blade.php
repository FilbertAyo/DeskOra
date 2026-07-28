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
            @if (in_array($request->status, ['draft', 'resubmission'], true))
                <a href="{{ route('requisition.edit', $request->id) }}" class="btn btn-primary btn-sm text-white">
                    <i class="ti ti-edit me-1"></i>{{ $request->status === 'draft' ? 'Continue Draft' : 'Edit' }}
                </a>
            @endif
            @if ($request->status === 'draft')
                <form action="{{ route('requisition.destroy', $request->id) }}" method="POST" class="d-inline"
                    onsubmit="return confirm('Delete this draft requisition?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="ti ti-trash me-1"></i>Delete Draft
                    </button>
                </form>
            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>




    @if ($request->status === 'draft')
        <div class="alert alert-warning border-warning mb-4">
            <strong>Draft</strong> — this requisition has not been submitted for approval yet.
            <a href="{{ route('requisition.edit', $request->id) }}" class="alert-link">Continue editing</a> when you are ready to submit.
        </div>
    @endif

    @include('requests.requisition.partials.form-preview')

    @include('requests.approval.logs')
</x-app-layout>
