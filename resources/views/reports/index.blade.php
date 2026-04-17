<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Reports</h5>
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

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ti ti-check me-1"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="ti ti-alert-circle me-1"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
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

    <div class="row mt-3">
        <div class="col-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Organization Documents</h5>
                        @can('upload organization reports')
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                data-bs-target="#uploadOrganizationDocumentModal">
                                <i class="ti ti-upload me-1"></i>Upload Document
                            </button>
                        @endcan
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 60px;" class="text-center">#</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Uploaded By</th>
                                    <th>Uploaded At</th>
                                    <th style="width: 220px;" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($organizationReports as $organizationReport)
                                    @php
                                        $fileExtension = strtoupper(pathinfo($organizationReport->stored_name, PATHINFO_EXTENSION));
                                        $fileSizeKb = $organizationReport->size / 1024;
                                        $sizeLabel = $fileSizeKb >= 1024
                                            ? number_format($fileSizeKb / 1024, 2) . ' MB'
                                            : number_format($fileSizeKb, 2) . ' KB';
                                    @endphp
                                    <tr>
                                        <td class="text-center">
                                            {{ ($organizationReports->currentPage() - 1) * $organizationReports->perPage() + $loop->iteration }}
                                        </td>
                                        <td>{{ $organizationReport->name }}</td>
                                        <td>{{ $fileExtension ?: 'N/A' }}</td>
                                        <td>{{ $sizeLabel }}</td>
                                        <td>{{ $organizationReport->uploader->name ?? 'Unknown' }}</td>
                                        <td>{{ $organizationReport->created_at->format('M d, Y h:i A') }}</td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-1">
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
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            No organization documents uploaded yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($organizationReports->hasPages())
                        <div class="mt-3">
                            {{ $organizationReports->links('pagination::bootstrap-5') }}
                        </div>
                    @endif
                </div>
            </div>
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
