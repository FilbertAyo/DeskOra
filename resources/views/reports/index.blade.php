<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Organization Reports/Documents</h5>
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
        <div class="col-12 mb-3">
            <div class="card card-stats card-light card-round shadow-none border">
                <div class="card-body">
                    <form action="{{ route('reports') }}" method="GET" class="row g-2 align-items-end">
                        <div class="col-md-6">
                            <label for="search-reports" class="form-label mb-1">Search Organization Documents</label>
                            <input type="text" id="search-reports" name="search" value="{{ $search ?? '' }}"
                                class="form-control" placeholder="Search by name, file name, or uploader">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-search me-1"></i>Search
                            </button>
                        </div>
                        @if(!empty($search))
                            <div class="col-auto">
                                <a href="{{ route('reports') }}" class="btn btn-light">Clear</a>
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>

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
                                            <button
                                                type="button"
                                                class="btn btn-info btn-sm text-white"
                                                title="View"
                                                data-bs-toggle="modal"
                                                data-bs-target="#viewOrganizationDocumentModal"
                                                data-view-url="{{ route('reports.organization-documents.view', $organizationReport) }}"
                                                data-download-url="{{ route('reports.organization-documents.download', $organizationReport) }}"
                                                data-document-name="{{ $organizationReport->name }}"
                                                data-document-extension="{{ strtolower($fileExtension) }}"
                                            >
                                                <i class="ti ti-eye"></i>
                                            </button>
                                            <a href="{{ route('reports.organization-documents.download', $organizationReport) }}"
                                                class="btn btn-success btn-sm" title="Download">
                                                <i class="ti ti-download"></i>
                                            </a>
                                            @can('upload organization reports')
                                                <button
                                                    type="button"
                                                    class="btn btn-warning btn-sm text-white"
                                                    title="Edit"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editOrganizationDocumentModal"
                                                    data-update-url="{{ route('reports.organization-documents.update', $organizationReport) }}"
                                                    data-document-id="{{ $organizationReport->id }}"
                                                    data-document-name="{{ $organizationReport->name }}"
                                                    data-document-file="{{ $organizationReport->stored_name }}"
                                                >
                                                    <i class="ti ti-pencil"></i>
                                                </button>
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

                    @if(!empty($search))
                        <div class="col-12 mb-2">
                            <small class="text-muted">
                                Showing {{ $organizationReports->total() }} result(s) for "{{ $search }}"
                            </small>
                        </div>
                    @endif

                         
                    @if ($organizationReports->hasPages())
                        <div class="mt-3">
                            {{ $organizationReports->links('pagination::bootstrap-5') }}
                        </div>
                    @endif

    </div>


                 

    <div class="modal fade" id="viewOrganizationDocumentModal" tabindex="-1"
        aria-labelledby="viewOrganizationDocumentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-truncate pe-3" id="viewOrganizationDocumentModalLabel">View Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="view-organization-document-pdf" class="d-none">
                        <iframe
                            id="view-organization-document-iframe"
                            title="Document preview"
                            style="width: 100%; height: 75vh; border: none;"
                        ></iframe>
                    </div>
                    <div id="view-organization-document-fallback" class="d-none text-center py-5 px-3">
                        <i class="ti ti-file-description fs-1 text-muted d-block mb-3"></i>
                        <p class="text-muted mb-3">This file type cannot be previewed in the browser.</p>
                        <a id="view-organization-document-download" href="#" class="btn btn-primary">
                            <i class="ti ti-download me-1"></i>Download to View
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <a id="view-organization-document-open" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-light btn-sm">
                        <i class="ti ti-external-link me-1"></i>Open in New Tab
                    </a>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
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
                                <input type="text" class="form-control @if($errors->uploadOrganizationDocument->has('name')) is-invalid @endif"
                                    id="organization-document-name" name="name" value="{{ old('name') }}" required>
                                @if($errors->uploadOrganizationDocument->has('name'))
                                    <div class="invalid-feedback">{{ $errors->uploadOrganizationDocument->first('name') }}</div>
                                @endif
                            </div>
                            <div class="mb-3">
                                <label for="organization-document-file" class="form-label">File</label>
                                <input type="file" class="form-control @if($errors->uploadOrganizationDocument->has('file')) is-invalid @endif"
                                    id="organization-document-file" name="file"
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" required>
                                @if($errors->uploadOrganizationDocument->has('file'))
                                    <div class="invalid-feedback">{{ $errors->uploadOrganizationDocument->first('file') }}</div>
                                @endif
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

    @can('upload organization reports')
        <div class="modal fade" id="editOrganizationDocumentModal" tabindex="-1"
            aria-labelledby="editOrganizationDocumentModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form
                        method="POST"
                        id="editOrganizationDocumentForm"
                        data-route-template="{{ route('reports.organization-documents.update', ['organizationReport' => '__ORG_REPORT_ID__']) }}"
                        action="#"
                        enctype="multipart/form-data"
                    >
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="edit_document_id" id="edit-organization-document-id" value="{{ old('edit_document_id') }}">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editOrganizationDocumentModalLabel">Edit Organization Document</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="edit-organization-document-name" class="form-label">Document Name</label>
                                <input
                                    type="text"
                                    class="form-control @if($errors->editOrganizationDocument->has('name')) is-invalid @endif"
                                    id="edit-organization-document-name"
                                    name="name"
                                    value="{{ old('name') }}"
                                    required
                                >
                                @if($errors->editOrganizationDocument->has('name'))
                                    <div class="invalid-feedback">{{ $errors->editOrganizationDocument->first('name') }}</div>
                                @endif
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">
                                    Current file: <span id="edit-organization-document-current-file">-</span>
                                </small>
                            </div>
                            <div class="mb-3">
                                <label for="edit-organization-document-file" class="form-label">Replace File (optional)</label>
                                <input
                                    type="file"
                                    class="form-control @if($errors->editOrganizationDocument->has('file')) is-invalid @endif"
                                    id="edit-organization-document-file"
                                    name="file"
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
                                >
                                @if($errors->editOrganizationDocument->has('file'))
                                    <div class="invalid-feedback">{{ $errors->editOrganizationDocument->first('file') }}</div>
                                @endif
                                <small class="text-muted">Accepted: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX (Max 20MB)</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-device-floppy me-1"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const viewModalElement = document.getElementById('viewOrganizationDocumentModal');
            const viewPdfContainer = document.getElementById('view-organization-document-pdf');
            const viewIframe = document.getElementById('view-organization-document-iframe');
            const viewFallbackContainer = document.getElementById('view-organization-document-fallback');
            const viewModalTitle = document.getElementById('viewOrganizationDocumentModalLabel');
            const viewDownloadLink = document.getElementById('view-organization-document-download');
            const viewOpenLink = document.getElementById('view-organization-document-open');

            if (viewModalElement) {
                viewModalElement.addEventListener('show.bs.modal', function(event) {
                    const triggerButton = event.relatedTarget;
                    if (!triggerButton) {
                        return;
                    }

                    const viewUrl = triggerButton.getAttribute('data-view-url') || '#';
                    const downloadUrl = triggerButton.getAttribute('data-download-url') || '#';
                    const documentName = triggerButton.getAttribute('data-document-name') || 'Document';
                    const extension = (triggerButton.getAttribute('data-document-extension') || '').toLowerCase();

                    viewModalTitle.textContent = documentName;
                    viewDownloadLink.setAttribute('href', downloadUrl);
                    viewOpenLink.setAttribute('href', viewUrl);

                    if (extension === 'pdf') {
                        viewFallbackContainer.classList.add('d-none');
                        viewPdfContainer.classList.remove('d-none');
                        viewIframe.setAttribute('src', viewUrl);
                    } else {
                        viewPdfContainer.classList.add('d-none');
                        viewIframe.removeAttribute('src');
                        viewFallbackContainer.classList.remove('d-none');
                    }
                });

                viewModalElement.addEventListener('hidden.bs.modal', function() {
                    viewIframe.removeAttribute('src');
                    viewPdfContainer.classList.add('d-none');
                    viewFallbackContainer.classList.add('d-none');
                });
            }
        });
    </script>

    @can('upload organization reports')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const uploadModalElement = document.getElementById('uploadOrganizationDocumentModal');
                const shouldOpenUploadModal = uploadModalElement
                    && uploadModalElement.querySelector('.is-invalid') !== null;
                if (shouldOpenUploadModal) {
                    const uploadModal = new bootstrap.Modal(uploadModalElement);
                    uploadModal.show();
                }

                const editModalElement = document.getElementById('editOrganizationDocumentModal');
                const editForm = document.getElementById('editOrganizationDocumentForm');
                const editNameInput = document.getElementById('edit-organization-document-name');
                const editCurrentFile = document.getElementById('edit-organization-document-current-file');
                const editDocumentIdInput = document.getElementById('edit-organization-document-id');

                if (editModalElement && editForm) {
                    editModalElement.addEventListener('show.bs.modal', function(event) {
                        const triggerButton = event.relatedTarget;
                        if (!triggerButton) {
                            return;
                        }

                        const updateUrl = triggerButton.getAttribute('data-update-url');
                        const documentId = triggerButton.getAttribute('data-document-id') || '';
                        const documentName = triggerButton.getAttribute('data-document-name') || '';
                        const documentFile = triggerButton.getAttribute('data-document-file') || '';

                        editForm.setAttribute('action', updateUrl || '#');
                        editDocumentIdInput.value = documentId;
                        editNameInput.value = documentName;
                        editCurrentFile.textContent = documentFile || '-';
                    });

                    const shouldOpenEditModal = editForm.querySelector('.is-invalid') !== null;
                    if (shouldOpenEditModal) {
                        const editDocumentId = editDocumentIdInput.value;
                        const routeTemplate = editForm.getAttribute('data-route-template');
                        if (editDocumentId && routeTemplate) {
                            editForm.setAttribute('action', routeTemplate.replace('__ORG_REPORT_ID__', editDocumentId));
                        }

                        if (editCurrentFile && editCurrentFile.textContent.trim() === '-') {
                            const selectedButton = document.querySelector('[data-document-id="' + editDocumentId + '"]');
                            if (selectedButton) {
                                editCurrentFile.textContent = selectedButton.getAttribute('data-document-file') || '-';
                            }
                        }

                        const editModal = new bootstrap.Modal(editModalElement);
                        editModal.show();
                    }
                }
            });
        </script>
    @endcan
</x-app-layout>
