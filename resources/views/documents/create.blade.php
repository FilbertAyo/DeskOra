<x-app-layout>
    <div class="row align-items-center mb-3 border-bottom no-gutters">
        <div class="col">
            <ul class="nav nav-tabs border-0" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab"
                        aria-controls="home" aria-selected="true">
                        <i class="fe fe-plus-circle me-1"></i> Create New Document
                    </a>
                </li>
            </ul>
        </div>
        <div class="col-auto">
            <a href="{{ route('documents.index') }}" class="btn btn-sm btn-light">
                <i class="fe fe-arrow-left me-1"></i> Back to Documents
            </a>
        </div>
    </div>

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
            <form method="POST" action="{{ route('documents.store') }}" id="createDocumentForm" enctype="multipart/form-data">
                @csrf
                <div class="card shadow-none border">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0"><i class="fe fe-file-text me-2"></i>Document Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Document Type -->
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="type" class="form-label">
                                        Document Type <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fe fe-file"></i>
                                        </span>
                                        <select class="form-control @error('type') is-invalid @enderror"
                                            name="type"
                                            id="type"
                                            {{ isset($selectedType) ? 'disabled' : '' }}
                                            required>
                                            <option value="" disabled {{ (old('type') || isset($selectedType)) ? '' : 'selected' }}>
                                                -- Select Document Type --
                                            </option>
                                            @foreach($availableTypes as $type => $name)
                                                <option value="{{ $type }}" 
                                                    {{ old('type', $selectedType ?? '') == $type ? 'selected' : '' }}>
                                                    {{ $name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if(isset($selectedType))
                                            <input type="hidden" name="type" value="{{ $selectedType }}">
                                        @endif
                                    </div>
                                    @error('type')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">
                                        @if(isset($selectedType))
                                            Document type is set to: <strong>{{ $availableTypes[$selectedType] ?? $selectedType }}</strong>
                                        @else
                                            Select the type of document you want to create
                                        @endif
                                    </small>
                                </div>
                            </div>

                            <!-- Work Period (for weekly plans and reports) -->
                            <div class="col-md-6 mb-3" id="workPeriodContainer" @if(!isset($selectedType) || !in_array($selectedType, ['weekly_plan', 'weekly_report'])) style="display: none;" @endif>
                                <div class="form-group">
                                    <label for="period_id" class="form-label">
                                        Work Period <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fe fe-calendar"></i>
                                        </span>
                                        <select class="form-control @error('period_id') is-invalid @enderror"
                                            name="period_id"
                                            id="period_id">
                                            <option value="">-- Select Work Period --</option>
                                            @foreach($workPeriods as $workPeriod)
                                                <option value="{{ $workPeriod->id }}" 
                                                    {{ old('period_id', $defaultPeriodId ?? '') == $workPeriod->id ? 'selected' : '' }}>
                                                    Week {{ $workPeriod->week_number }} of {{ $workPeriod->year }} 
                                                    ({{ \Carbon\Carbon::parse($workPeriod->week_start_date)->format('M d') }} - 
                                                     {{ \Carbon\Carbon::parse($workPeriod->week_end_date)->format('M d, Y') }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    @error('period_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">Select the work period for this document</small>
                                </div>
                            </div>

                            <!-- Title (Optional) -->
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="title" class="form-label">
                                        Title (Optional)
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
                                            value="{{ old('title') }}">
                                    </div>
                                    @error('title')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">Leave blank to use "Untitled"</small>
                                </div>
                            </div>

                            <!-- PDF File Upload -->
                            <div class="col-6 mb-3">
                                <div class="form-group">
                                    <label for="file" class="form-label">
                                        PDF File <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <div class="custom-file">
                                            <input type="file"
                                                class="custom-file-input @error('file') is-invalid @enderror"
                                                name="file"
                                                id="file"
                                                accept=".pdf"
                                                required>
                                            <label class="custom-file-label" for="file" id="fileLabel">
                                                Choose PDF file...
                                            </label>
                                        </div>
                                    </div>
                                    @error('file')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">
                                        <i class="fe fe-info me-1"></i>
                                        Only PDF files are allowed. Maximum file size: 10MB
                                    </small>
                                </div>
                            </div>

                            <!-- Main Target for Weekly Plans -->
                            @if(isset($selectedType) && $selectedType === 'weekly_plan')
                            <div class="col-12 mb-3">
                                <div class="form-group">
                                    <label for="main_target" class="form-label">
                                        Main Target/Goal for This Week <span class="text-danger">*</span>
                                    </label>
                                    <textarea class="form-control @error('main_target') is-invalid @enderror"
                                        name="main_target"
                                        id="main_target"
                                        rows="4"
                                        placeholder="Enter the main target or goal for this week (one goal among all stated in the PDF)"
                                        required>{{ old('main_target') }}</textarea>
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
                            @if(isset($selectedType) && $selectedType === 'weekly_report')
                            <div class="col-12 mb-3">
                                <div class="card border-info">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="mb-0"><i class="fe fe-target me-2"></i>Main Target Accomplishment</h6>
                                    </div>
                                    <div class="card-body">
                                        @php
                                            $planMainTarget = null;
                                            if (isset($relatedPlan) && $relatedPlan && $relatedPlan->main_target) {
                                                $planMainTarget = $relatedPlan->main_target;
                                            } elseif (old('period_id')) {
                                                // Try to get plan for the selected period
                                                $selectedPeriod = \App\Models\WorkPeriod::find(old('period_id'));
                                                if ($selectedPeriod) {
                                                    $plan = \App\Models\Document::where('user_id', auth()->id())
                                                        ->where('period_id', $selectedPeriod->id)
                                                        ->where('type', 'weekly_plan')
                                                        ->first();
                                                    if ($plan) {
                                                        $planMainTarget = $plan->main_target;
                                                    }
                                                }
                                            }
                                        @endphp

                                        <div id="planTargetContainer">
                                            @if($planMainTarget)
                                                <div class="mb-3">
                                                    <label class="form-label text-muted">Main Target from Weekly Plan:</label>
                                                    <div class="alert alert-light border">
                                                        <p class="mb-0"><strong id="planTargetText">{{ $planMainTarget }}</strong></p>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="mb-3">
                                                    <div class="alert alert-warning" id="noPlanWarning">
                                                        <i class="fe fe-alert-circle me-2"></i>
                                                        <span id="noPlanMessage">No weekly plan found for the selected work period. Please ensure you have created a weekly plan first.</span>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="mb-3">
                                            <label for="target_accomplished" class="form-label">
                                                Was the target accomplished? <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-control @error('target_accomplished') is-invalid @enderror"
                                                name="target_accomplished"
                                                id="target_accomplished"
                                                required>
                                                <option value="">-- Select Status --</option>
                                                <option value="1" {{ old('target_accomplished') === '1' ? 'selected' : '' }}>Yes, Completed</option>
                                                <option value="0" {{ old('target_accomplished') === '0' ? 'selected' : '' }}>No, Not Completed</option>
                                            </select>
                                            @error('target_accomplished')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="mb-0" id="targetExplanationContainer" style="display: none;">
                                            <label for="target_explanation" class="form-label">
                                                Why was the target not accomplished? <span class="text-danger">*</span>
                                            </label>
                                            <textarea class="form-control @error('target_explanation') is-invalid @enderror"
                                                name="target_explanation"
                                                id="target_explanation"
                                                rows="3"
                                                placeholder="Please explain why the target was not accomplished...">{{ old('target_explanation') }}</textarea>
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
                            <a href="{{ route('documents.index') }}" class="btn btn-secondary">
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

    <script type="application/json" id="document-form-data">
        {
            "workPeriods": {!! json_encode($workPeriods ?? []) !!},
            "defaultPeriodId": {!! json_encode($defaultPeriodId ?? null) !!},
            "selectedType": {!! json_encode($selectedType ?? null) !!},
            "currentPeriodId": {!! json_encode(isset($currentPeriod) ? $currentPeriod->id : null) !!},
            "nextPeriodId": {!! json_encode(isset($nextPeriod) ? $nextPeriod->id : null) !!},
            "relatedPlanMainTarget": {!! json_encode(isset($relatedPlan) && $relatedPlan ? $relatedPlan->main_target : null) !!}
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('createDocumentForm');
            const submitBtn = document.getElementById('submitBtn');
            const fileInput = document.getElementById('file');
            const fileLabel = document.getElementById('fileLabel');
            const typeSelect = document.getElementById('type');
            const workPeriodContainer = document.getElementById('workPeriodContainer');
            const periodSelect = document.getElementById('period_id');
            
            // Work periods data for default selection
            const formData = JSON.parse(document.getElementById('document-form-data').textContent);
            const workPeriodsData = formData.workPeriods;
            const defaultPeriodId = formData.defaultPeriodId;
            const selectedType = formData.selectedType;
            const currentPeriodId = formData.currentPeriodId;
            const nextPeriodId = formData.nextPeriodId;
            const relatedPlanMainTarget = formData.relatedPlanMainTarget;

            // Show/hide work period dropdown based on document type
            function toggleWorkPeriod() {
                const selectedTypeValue = typeSelect.value;
                const weeklyTypes = ['weekly_plan', 'weekly_report'];
                
                if (weeklyTypes.includes(selectedTypeValue)) {
                    workPeriodContainer.style.display = '';
                    periodSelect.required = true;
                    
                    // Set default period based on type if not already selected
                    if (!periodSelect.value) {
                        if (selectedTypeValue === 'weekly_plan' && nextPeriodId) {
                            periodSelect.value = nextPeriodId;
                        } else if (selectedTypeValue === 'weekly_report' && currentPeriodId) {
                            periodSelect.value = currentPeriodId;
                        } else if (defaultPeriodId) {
                            periodSelect.value = defaultPeriodId;
                        }
                    }
                } else {
                    workPeriodContainer.style.display = 'none';
                    periodSelect.required = false;
                    periodSelect.value = '';
                }
            }

            // Handle target accomplishment dropdown for weekly reports
            const targetAccomplishedSelect = document.getElementById('target_accomplished');
            const targetExplanationContainer = document.getElementById('targetExplanationContainer');
            const targetExplanationTextarea = document.getElementById('target_explanation');

            function toggleTargetExplanation() {
                if (targetAccomplishedSelect && targetExplanationContainer && targetExplanationTextarea) {
                    const value = targetAccomplishedSelect.value;
                    if (value === '0') {
                        // Not accomplished - show explanation and make it required
                        targetExplanationContainer.style.display = '';
                        targetExplanationTextarea.required = true;
                    } else {
                        // Accomplished - hide explanation and make it not required
                        targetExplanationContainer.style.display = 'none';
                        targetExplanationTextarea.required = false;
                        targetExplanationTextarea.value = '';
                    }
                }
            }

            if (targetAccomplishedSelect) {
                targetAccomplishedSelect.addEventListener('change', toggleTargetExplanation);
                // Check initial state
                toggleTargetExplanation();
            }

            // Load plan's main target when period changes (for weekly reports)
            if (selectedType === 'weekly_report' && periodSelect) {
                periodSelect.addEventListener('change', function() {
                    const periodId = this.value;
                    if (periodId) {
                        // Fetch the plan's main target via AJAX
                        fetch(`/api/documents/plan-target?period_id=${periodId}`, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            const planTargetContainer = document.getElementById('planTargetContainer');
                            if (planTargetContainer) {
                                if (data.main_target) {
                                    planTargetContainer.innerHTML = `
                                        <div class="mb-3">
                                            <label class="form-label text-muted">Main Target from Weekly Plan:</label>
                                            <div class="alert alert-light border">
                                                <p class="mb-0"><strong id="planTargetText">${data.main_target}</strong></p>
                                            </div>
                                        </div>
                                    `;
                                } else {
                                    planTargetContainer.innerHTML = `
                                        <div class="mb-3">
                                            <div class="alert alert-warning" id="noPlanWarning">
                                                <i class="fe fe-alert-circle me-2"></i>
                                                <span id="noPlanMessage">No weekly plan found for the selected work period. Please ensure you have created a weekly plan first.</span>
                                            </div>
                                        </div>
                                    `;
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching plan target:', error);
                        });
                    }
                });
            }

            // Initialize on page load
            if (typeSelect && !typeSelect.disabled) {
                typeSelect.addEventListener('change', toggleWorkPeriod);
                toggleWorkPeriod(); // Check initial state
            } else if (selectedType && ['weekly_plan', 'weekly_report'].includes(selectedType)) {
                // If type is pre-selected and disabled, ensure work period is shown
                workPeriodContainer.style.display = '';
                periodSelect.required = true;
            }

            // Update file label when file is selected
            fileInput.addEventListener('change', function(e) {
                const fileName = e.target.files[0]?.name || 'Choose PDF file...';
                fileLabel.textContent = fileName;
            });

            form.addEventListener('submit', function(e) {
                const clickedButton = e.submitter;
                const action = clickedButton?.value || 'draft';
                const selectedTypeValue = typeSelect.value || selectedType;
                const weeklyTypes = ['weekly_plan', 'weekly_report'];
                
                // Validate work period for weekly plans and reports
                if (weeklyTypes.includes(selectedTypeValue)) {
                    if (!periodSelect.value) {
                        e.preventDefault();
                        alert('Please select a work period.');
                        return false;
                    }
                }

                // Validate target accomplishment for weekly reports
                if (selectedTypeValue === 'weekly_report') {
                    const targetAccomplished = document.getElementById('target_accomplished');
                    if (targetAccomplished && !targetAccomplished.value) {
                        e.preventDefault();
                        alert('Please indicate whether the target was accomplished.');
                        return false;
                    }
                    
                    // If not accomplished, explanation is required
                    if (targetAccomplished && targetAccomplished.value === '0') {
                        const targetExplanation = document.getElementById('target_explanation');
                        if (targetExplanation && !targetExplanation.value.trim()) {
                            e.preventDefault();
                            alert('Please explain why the target was not accomplished.');
                            return false;
                        }
                    }
                }

                // Validate main target for weekly plans
                if (selectedTypeValue === 'weekly_plan') {
                    const mainTarget = document.getElementById('main_target');
                    if (mainTarget && !mainTarget.value.trim()) {
                        e.preventDefault();
                        alert('Please enter the main target for this week.');
                        return false;
                    }
                }
                
                // Validate file is selected
                if (!fileInput.files || fileInput.files.length === 0) {
                    e.preventDefault();
                    alert('Please select a PDF file to upload.');
                    return false;
                }

                // Validate file type
                const file = fileInput.files[0];
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

