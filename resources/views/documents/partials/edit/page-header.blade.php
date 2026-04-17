<div class="page-header">
    <div class="page-block">
        <div class="row align-items-center">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Edit Document</h5>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('documents.index') }}">Documents</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('documents.show', $document->id) }}">View</a></li>
                            <li class="breadcrumb-item" aria-current="page">Edit</li>
                        </ul>
                    </div>
                    <div class="col-auto d-flex align-items-center gap-2">
                        <a href="{{ route('documents.show', $document->id) }}" class="btn btn-sm btn-info text-white">
                            <i class="ti ti-eye me-1"></i> View Document
                        </a>
                        <a href="{{ route('documents.index') }}" class="btn btn-sm btn-light">
                            <i class="ti ti-arrow-left me-1"></i> Back to Documents
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
