@php
    $requestedBackUrl = request('return_to');
    $sessionBackUrl = session('documents.last_listing_url');
    $resolvedBackUrl = is_string($requestedBackUrl) && \Illuminate\Support\Str::startsWith($requestedBackUrl, url('/documents'))
        ? $requestedBackUrl
        : ((is_string($sessionBackUrl) && \Illuminate\Support\Str::startsWith($sessionBackUrl, url('/documents'))) ? $sessionBackUrl : route('documents.index'));
@endphp
<div class="page-header">
    <div class="page-block">
        <div class="row align-items-center">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Edit Document</h5>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ $resolvedBackUrl }}">Documents</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('documents.show', ['document' => $document->id, 'return_to' => $resolvedBackUrl]) }}">View</a></li>
                            <li class="breadcrumb-item" aria-current="page">Edit</li>
                        </ul>
                    </div>
                    <div class="col-auto d-flex align-items-center gap-2">
                        <a href="{{ route('documents.show', ['document' => $document->id, 'return_to' => $resolvedBackUrl]) }}" class="btn btn-sm btn-info text-white">
                            <i class="ti ti-eye me-1"></i> View Document
                        </a>
                        <a href="{{ $resolvedBackUrl }}" class="btn btn-sm btn-light">
                            <i class="ti ti-arrow-left me-1"></i> Back to Documents
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
