<x-app-layout>
    <div class="row align-items-center mb-3 border-bottom no-gutters">
        <div class="col">
            <ul class="nav nav-tabs border-0" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab"
                        aria-controls="home" aria-selected="true">
                        <i class="fe fe-edit me-1"></i> Edit Document
                    </a>
                </li>
            </ul>
        </div>
        <div class="col-auto">
            <a href="{{ route('documents.show', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}" class="btn btn-sm btn-info text-white me-1">
                <i class="fe fe-eye me-1"></i> View Document
            </a>
            <a href="{{ route('documents.index') }}" class="btn btn-sm btn-light">
                <i class="fe fe-arrow-left me-1"></i> Back to Documents
            </a>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fe fe-alert-circle me-2"></i>{{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fe fe-alert-circle me-2"></i>
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <div class="row">
        <div class="col-md-12">
            <form method="POST" action="{{ route('documents.update', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}" id="editDocumentForm" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="card shadow-none border">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">
                            <i class="fe fe-file-text me-2"></i>Edit Document
                            <span class="badge badge-info ml-2">{{ $document->type_name }}</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Document Type (Read-only) -->
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label class="form-label">Document Type</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fe fe-file"></i>
                                        </span>
                                        <input type="text"
                                            class="form-control"
                                            value="{{ $document->type_name }}"
                                            disabled>
                                    </div>
                                    <small class="form-text text-muted">Document type cannot be changed</small>
                                </div>
                            </div>

                            <!-- State (Read-only) -->
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label class="form-label">State</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fe fe-info"></i>
                                        </span>
                                        <input type="text"
                                            class="form-control"
                                            value="{{ $document->state_name }}"
                                            disabled>
                                    </div>
                                    <small class="form-text text-muted">Current document state</small>
                                </div>
                            </div>

                            <!-- Title -->
                            <div class="col-md-12 mb-3">
                                <div class="form-group">
                                    <label for="title" class="form-label">
                                        Title
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fe fe-type"></i>
                                        </span>
                                        <input type="text"
                                            class="form-control @error('title') is-invalid @enderror"
                                            name="title"
                                            id="title"
                                            placeholder="Enter document title"
                                            value="{{ old('title', $document->title) }}">
                                    </div>
                                    @error('title')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Current File -->
                            @if($document->file_path)
                            <div class="col-12 mb-3">
                                <div class="alert alert-info">
                                    <h6 class="mb-2">
                                        <i class="fe fe-file me-2"></i>Current PDF File
                                    </h6>
                                    <p class="mb-2">
                                        <strong>File:</strong> {{ $document->original_filename ?? 'Document.pdf' }}<br>
                                        <strong>Size:</strong> {{ $document->formatted_file_size }}<br>
                                        <strong>Uploaded:</strong> {{ $document->created_at->format('M d, Y H:i') }}
                                    </p>
                                    <a href="{{ $document->file_url }}" target="_blank" class="btn btn-sm btn-info">
                                        <i class="fe fe-download me-1"></i> View/Download Current File
                                    </a>
                                </div>
                            </div>
                            @endif

                            <!-- PDF File Upload (Optional - to replace existing file) -->
                            <div class="col-12 mb-3">
                                <div class="form-group">
                                    <label for="file" class="form-label">
                                        Replace PDF File <span class="text-muted">(Optional)</span>
                                    </label>
                                    <div class="input-group">
                                        <div class="custom-file">
                                            <input type="file"
                                                class="custom-file-input @error('file') is-invalid @enderror"
                                                name="file"
                                                id="file"
                                                accept=".pdf">
                                            <label class="custom-file-label" for="file" id="fileLabel">
                                                Choose new PDF file...
                                            </label>
                                        </div>
                                    </div>
                                    @error('file')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">
                                        <i class="fe fe-info me-1"></i>
                                        Only PDF files are allowed. Maximum file size: 10MB. Leave empty to keep current file.
                                    </small>
                                </div>
                            </div>

                            <!-- Main Target for Weekly Plans -->
                            @if($document->type === 'weekly_plan')
                            <div class="col-12 mb-3">
                                <div class="form-group">
                                    <label for="main_target" class="form-label">
                                        Main Target/Goal for This Week
                                    </label>
                                    <textarea class="form-control @error('main_target') is-invalid @enderror"
                                        name="main_target"
                                        id="main_target"
                                        rows="4"
                                        placeholder="Enter the main target or goal for this week (one goal among all stated in the PDF)">{{ old('main_target', $document->main_target) }}</textarea>
                                    @error('main_target')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">
                                        <i class="fe fe-info me-1"></i>
                                        State the main target or goal for this week. This should be one key goal among all the goals stated in your PDF.
                                    </small>
                                </div>
                            </div>
                            @endif

                            <!-- Target Accomplishment for Weekly Reports -->
                            @if($document->type === 'weekly_report')
                            <div class="col-12 mb-3">
                                <div class="card border-info">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="mb-0"><i class="fe fe-target me-2"></i>Main Target Accomplishment</h6>
                                    </div>
                                    <div class="card-body">
                                        @php
                                            $relatedPlan = \App\Models\Document::where('user_id', $document->user_id)
                                                ->where('period_id', $document->period_id)
                                                ->where('type', 'weekly_plan')
                                                ->first();
                                        @endphp

                                        @if($relatedPlan && $relatedPlan->main_target)
                                            <div class="mb-3">
                                                <label class="form-label text-muted">Main Target from Weekly Plan:</label>
                                                <div class="alert alert-light border">
                                                    <p class="mb-0"><strong>{{ $relatedPlan->main_target }}</strong></p>
                                                </div>
                                            </div>
                                        @else
                                            <div class="mb-3">
                                                <div class="alert alert-warning">
                                                    <i class="fe fe-alert-circle me-2"></i>
                                                    No weekly plan found for this work period.
                                                </div>
                                            </div>
                                        @endif

                                        <div class="mb-3">
                                            <label for="target_accomplished" class="form-label">
                                                Was the target accomplished?
                                            </label>
                                            <select class="form-control @error('target_accomplished') is-invalid @enderror"
                                                name="target_accomplished"
                                                id="target_accomplished">
                                                <option value="">-- Select Status --</option>
                                                <option value="1" {{ old('target_accomplished', $document->target_accomplished) === true || old('target_accomplished') === '1' ? 'selected' : '' }}>Yes, Completed</option>
                                                <option value="0" {{ old('target_accomplished', $document->target_accomplished) === false || (old('target_accomplished') === '0' && $document->target_accomplished !== null) ? 'selected' : '' }}>No, Not Completed</option>
                                            </select>
                                            @error('target_accomplished')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="mb-0" id="targetExplanationContainer" style="display: {{ (old('target_accomplished', $document->target_accomplished) === false || old('target_accomplished') === '0') ? '' : 'none;' }}">
                                            <label for="target_explanation" class="form-label">
                                                Why was the target not accomplished?
                                            </label>
                                            <textarea class="form-control @error('target_explanation') is-invalid @enderror"
                                                name="target_explanation"
                                                id="target_explanation"
                                                rows="3"
                                                placeholder="Please explain why the target was not accomplished...">{{ old('target_explanation', $document->target_explanation) }}</textarea>
                                            @error('target_explanation')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            <small class="form-text text-muted">
                                                <i class="fe fe-info me-1"></i>
                                                Please provide a clear explanation for why the target was not accomplished.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>

                    <div class="card-footer bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{ route('documents.show', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}" class="btn btn-secondary">
                                <i class="fe fe-x me-1"></i> Cancel
                            </a>
                            <div class="btn-group">
                                <button type="submit" name="action" value="draft" class="btn btn-outline-primary" id="saveDraftBtn">
                                    <i class="fe fe-save me-1"></i> Save Draft
                                </button>
                                <button type="submit" name="action" value="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="fe fe-send me-1"></i> Submit
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('editDocumentForm');
            const submitBtn = document.getElementById('submitBtn');
            const fileInput = document.getElementById('file');
            const fileLabel = document.getElementById('fileLabel');

            // Update file label when file is selected
            if (fileInput) {
                fileInput.addEventListener('change', function(e) {
                    const fileName = e.target.files[0]?.name || 'Choose new PDF file...';
                    fileLabel.textContent = fileName;
                });
            }

            // Handle target accomplishment dropdown for weekly reports
            const targetAccomplishedSelect = document.getElementById('target_accomplished');
            const targetExplanationContainer = document.getElementById('targetExplanationContainer');
            const targetExplanationTextarea = document.getElementById('target_explanation');

            function toggleTargetExplanation() {
                if (targetAccomplishedSelect && targetExplanationContainer && targetExplanationTextarea) {
                    const value = targetAccomplishedSelect.value;
                    if (value === '0') {
                        // Not accomplished - show explanation
                        targetExplanationContainer.style.display = '';
                        targetExplanationTextarea.required = true;
                    } else {
                        // Accomplished - hide explanation
                        targetExplanationContainer.style.display = 'none';
                        targetExplanationTextarea.required = false;
                        if (value === '1') {
                            targetExplanationTextarea.value = '';
                        }
                    }
                }
            }

            if (targetAccomplishedSelect) {
                targetAccomplishedSelect.addEventListener('change', toggleTargetExplanation);
                // Check initial state
                toggleTargetExplanation();
            }

            form.addEventListener('submit', function(e) {
                const clickedButton = e.submitter;
                const action = clickedButton?.value || 'draft';
                
                // Validate file if provided
                if (fileInput && fileInput.files && fileInput.files.length > 0) {
                    const file = fileInput.files[0];
                    
                    // Validate file type
                    if (file.type !== 'application/pdf') {
                        e.preventDefault();
                        alert('Please select a valid PDF file.');
                        return false;
                    }

                    // Validate file size (10MB)
                    if (file.size > 10 * 1024 * 1024) {
                        e.preventDefault();
                        alert('File size must be less than 10MB.');
                        return false;
                    }
                }
                
                if (action === 'submit') {
                    if (!confirm('Are you sure you want to submit this document? Once submitted, it cannot be edited.')) {
                        e.preventDefault();
                        return false;
                    }
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fe fe-loader spinning me-1"></i> Submitting...';
                    }
                } else {
                    const saveDraftBtn = document.getElementById('saveDraftBtn');
                    if (saveDraftBtn) {
                        saveDraftBtn.disabled = true;
                        saveDraftBtn.innerHTML = '<i class="fe fe-loader spinning me-1"></i> Saving...';
                    }
                }
            });
        });
    </script>

    <style>
        .spinning {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</x-app-layout>

