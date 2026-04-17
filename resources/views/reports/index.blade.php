<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Organization Reports</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="javascript: void(0)">Reports</a></li>
                               
                            </ul>
                        </div>

                        <div class="col-auto d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-light" onclick="reloadPage()"
                                title="Refresh">
                                <i class="ti ti-refresh"></i>
                            </button>
                            @can('upload organization reports')
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                data-bs-target="#uploadOrganizationDocumentModal">
                                <i class="ti ti-upload me-1"></i>Upload Document
                            </button>
                        @endcan

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


   

    <div class="row">

    @forelse($organizationReports as $organizationReport)
                            @php
                                $fileExtension = strtoupper(pathinfo($organizationReport->stored_name, PATHINFO_EXTENSION));
                                $fileSizeKb = $organizationReport->size / 1024;
                                $sizeLabel = $fileSizeKb >= 1024
                                    ? number_format($fileSizeKb / 1024, 2) . ' MB'
                                    : number_format($fileSizeKb, 2) . ' KB';
                            @endphp
                            <div class="col-sm-6 col-md-3 mb-3">
                                <div class="card card-stats card-light card-round shadow-none border h-100">
                                    <!-- <img src="{{ route('reports.organization-documents.preview', $organizationReport) }}"
                                        alt="{{ $organizationReport->name }} preview" class="card-img-top"
                                        style="height: 120px; object-fit: cover;"> -->
                                    <div class="card-body d-flex flex-column">
                                        <h6 class="mb-2 text-truncate" title="{{ $organizationReport->name }}">{{ $organizationReport->name }}</h6>
                                        <small class="text-muted d-block mb-1">Type: {{ $fileExtension ?: 'N/A' }}</small>
                                        <small class="text-muted d-block mb-1">Size: {{ $sizeLabel }}</small>
                                        <small class="text-muted d-block mb-1">
                                            By {{ $organizationReport->uploader->name ?? 'Unknown' }}<br>
                                            {{ $organizationReport->created_at->format('M d, Y h:i A') }}
                                        </small>
                                        <div class="mt-auto d-flex gap-1">
                                            <a href="{{ route('reports.organization-documents.view', $organizationReport) }}"
                                                class="btn btn-info btn-sm text-white" target="_blank" title="View">
                                                <i class="ti ti-eye"></i>
                                            </a>
                                            <a href="{{ route('reports.organization-documents.download', $organizationReport) }}"
                                                class="btn btn-success btn-sm" title="Download">
                                                <i class="ti ti-download"></i>
                                            </a>
                                            @can('upload organization reports')
                                                <form action="{{ route('reports.organization-documents.destroy', $organizationReport) }}"
                                                    method="POST" onsubmit="return confirm('Delete this document?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                                        <i class="ti ti-trash"></i>
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <div class="text-center text-muted py-4">
                                    No organization documents uploaded yet.
                                </div>
                            </div>
                        @endforelse

                         
                    @if ($organizationReports->hasPages())
                        <div class="mt-3">
                            {{ $organizationReports->links('pagination::bootstrap-5') }}
                        </div>
                    @endif


        @can('view cashflow movements')
            <div class="col-sm-6 col-md-3">
                <a href="{{ route('reports.petties') }}" class="text-decoration-none">
                    <div class="card card-stats card-light card-round shadow-none border">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 col-stats">
                                    <div class="numbers">
                                        <div class="h5">Petty Cash</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endcan

        <div class="col-sm-6 col-md-3">
            <a href="{{ route('reports.users') }}" class="text-decoration-none">
                <div class="card card-stats card-light card-round shadow-none border">
                    <div class="card-body">
                        <div class="row">

                            <div class="col-12 col-stats">
                                <div class="numbers">
                                    <div class="h5">Users List</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-sm-6 col-md-3">
            <a href="{{ route('reports.transaction') }}" class="text-decoration-none">
                <div class="card card-stats card-light card-round shadow-none border">
                    <div class="card-body">
                        <div class="row">

                            <div class="col-12 col-stats">
                                <div class="numbers">
                                    <div class="h5">Transactions</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>


                 

    @can('upload organization reports')
        <div class="modal fade" id="uploadOrganizationDocumentModal" tabindex="-1"
            aria-labelledby="uploadOrganizationDocumentModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('reports.organization-documents.store') }}"
                        enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="uploadOrganizationDocumentModalLabel">Upload Organization Document</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="organization-document-name" class="form-label">Document Name</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                    id="organization-document-name" name="name" value="{{ old('name') }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="organization-document-file" class="form-label">File</label>
                                <input type="file" class="form-control @error('file') is-invalid @enderror"
                                    id="organization-document-file" name="file"
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" required>
                                @error('file')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Accepted: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX (Max 20MB)</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-upload me-1"></i>Upload
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    @if ($errors->has('name') || $errors->has('file'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const uploadModalElement = document.getElementById('uploadOrganizationDocumentModal');
                if (uploadModalElement) {
                    const uploadModal = new bootstrap.Modal(uploadModalElement);
                    uploadModal.show();
                }
            });
        </script>
    @endif
</x-app-layout>
