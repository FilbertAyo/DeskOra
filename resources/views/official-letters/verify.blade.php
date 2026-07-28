<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Document Verification</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('official-letters.index') }}">Official Letters</a></li>
                                <li class="breadcrumb-item" aria-current="page">Verify</li>
                            </ul>
                        </div>
                        <a href="{{ route('official-letters.index') }}" class="btn btn-sm btn-light">
                            <i class="ti ti-arrow-left me-1"></i> Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-none border">
                <div class="card-body text-center py-4">
                    <div class="mb-3">
                        <span class="badge bg-success fs-6 px-3 py-2">
                            <i class="ti ti-shield-check me-1"></i> Valid Official Letter
                        </span>
                    </div>
                    <p class="text-muted mb-4">
                        This QR code matches an authentic letter recorded in DeskOra. You do not have access to open the full letter.
                    </p>

                    <div class="text-start mx-auto" style="max-width: 520px;">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Reference</dt>
                            <dd class="col-sm-8"><code>{{ $letter->reference_number }}</code></dd>

                            <dt class="col-sm-4">Subject</dt>
                            <dd class="col-sm-8">{{ $letter->subject }}</dd>

                            <dt class="col-sm-4">Department</dt>
                            <dd class="col-sm-8">{{ $letter->department_code }} — {{ $letter->departmentLabel() }}</dd>

                            <dt class="col-sm-4">Letter Date</dt>
                            <dd class="col-sm-8">{{ $letter->letter_date->format('d F Y') }}</dd>

                            <dt class="col-sm-4">Created By</dt>
                            <dd class="col-sm-8">{{ $letter->creator->name ?? 'N/A' }}</dd>

                            <dt class="col-sm-4">Signatory</dt>
                            <dd class="col-sm-8">
                                {{ $letter->signatory_name }}
                                @if($letter->signatory_title)
                                    <br><small class="text-muted">{{ $letter->signatory_title }}</small>
                                @endif
                            </dd>
                        </dl>
                    </div>

                    <div class="alert alert-info text-start mt-4 mb-0">
                        <i class="ti ti-info-circle me-1"></i>
                        To view or edit the full letter, ask the author to share it with you.
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
