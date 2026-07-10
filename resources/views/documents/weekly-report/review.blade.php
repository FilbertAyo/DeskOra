<x-app-layout>
    @php
        $targetStatusValue = old('target_accomplished_status');
        if (! is_string($targetStatusValue) || trim($targetStatusValue) === '') {
            $targetStatusValue = trim((string) ($content['target_accomplished_status'] ?? ''));
        }
        if ($targetStatusValue === '' && $document->target_accomplished === true) {
            $targetStatusValue = 'completed';
        } elseif ($targetStatusValue === '' && $document->target_accomplished === false) {
            $targetStatusValue = 'not_completed';
        }
        $showTargetExplanation = in_array($targetStatusValue, ['partially_completed', 'not_completed'], true);
    @endphp

    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Weekly Report — Review & Submit</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('documents.weekly-reports') }}">Weekly Reports</a></li>
                                <li class="breadcrumb-item" aria-current="page">Review</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            @include('documents.partials.weekly-report-wizard-stepper', [
                'wizardStep' => 3,
                'document' => $document,
                'hasActionTasksStep' => $hasActionTasksStep,
            ])

            <div class="card shadow-none border mb-3">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0"><i class="ti ti-eye me-2"></i>Report preview</h6>
                </div>
                <div class="card-body p-0">
                    @include('documents.partials.show.content.weekly-report-inline')
                </div>
            </div>

            <form method="POST" action="{{ route('documents.update', $document) }}" id="weeklyReportReviewForm">
                @csrf
                @method('PUT')
                <input type="hidden" name="action" value="submit">
                <input type="hidden" name="wizard_review" value="1">

                <div class="card shadow-none border border-info mb-3">
                    <div class="card-header bg-light-info">
                        <h6 class="mb-0"><i class="ti ti-target me-2"></i>Target accomplishment (required to submit)</h6>
                    </div>
                    <div class="card-body">
                        @if($relatedPlan && $relatedPlan->main_target)
                            <div class="mb-3">
                                <label class="form-label text-muted">Main Target from Weekly Plan</label>
                                <div class="alert alert-light border mb-0">
                                    <p class="mb-0"><strong>{{ $relatedPlan->main_target }}</strong></p>
                                </div>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label for="target_accomplished_status" class="form-label">
                                Target accomplishment status <span class="text-danger">*</span>
                            </label>
                            <select class="form-select @error('target_accomplished_status') is-invalid @enderror"
                                name="target_accomplished_status"
                                id="target_accomplished_status"
                                required>
                                <option value="">-- Select Status --</option>
                                <option value="completed" {{ $targetStatusValue === 'completed' ? 'selected' : '' }}>Completed</option>
                                <option value="partially_completed" {{ $targetStatusValue === 'partially_completed' ? 'selected' : '' }}>Partially completed</option>
                                <option value="not_completed" {{ $targetStatusValue === 'not_completed' ? 'selected' : '' }}>Not completed</option>
                            </select>
                            @error('target_accomplished_status')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            @error('action_tasks')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-0" id="targetExplanationContainer" @if(! $showTargetExplanation) style="display:none;" @endif>
                            <label for="target_explanation" class="form-label">Why was the target not fully accomplished?</label>
                            <textarea class="form-control @error('target_explanation') is-invalid @enderror"
                                name="target_explanation"
                                id="target_explanation"
                                rows="4"
                                placeholder="Please explain why the target was not fully accomplished...">{{ old('target_explanation', $document->target_explanation) }}</textarea>
                            @error('target_explanation')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="card shadow-none border">
                    <div class="card-footer bg-light border-top">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <a href="{{ route('documents.edit', $document) }}" class="btn btn-outline-secondary">
                                <i class="ti ti-edit me-1"></i> Edit report
                            </a>
                            @if($hasActionTasksStep)
                                <a href="{{ route('documents.weekly-report.action-tasks', $document) }}" class="btn btn-outline-primary">
                                    <i class="ti ti-bolt me-1"></i> Action tasks
                                </a>
                            @endif
                            <button type="submit" class="btn btn-primary" id="weeklyReportSubmitBtn">
                                <i class="ti ti-send me-1"></i> Submit report
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const statusEl = document.getElementById('target_accomplished_status');
            const explanationContainer = document.getElementById('targetExplanationContainer');
            const explanationEl = document.getElementById('target_explanation');
            const form = document.getElementById('weeklyReportReviewForm');

            function toggleExplanation() {
                if (!statusEl || !explanationContainer) return;
                const needsExplanation = statusEl.value === 'partially_completed' || statusEl.value === 'not_completed';
                explanationContainer.style.display = needsExplanation ? '' : 'none';
                if (explanationEl) {
                    explanationEl.required = needsExplanation;
                }
            }

            if (statusEl) {
                statusEl.addEventListener('change', toggleExplanation);
                toggleExplanation();
            }

            if (form) {
                form.addEventListener('submit', function (e) {
                    if (statusEl && !statusEl.value) {
                        e.preventDefault();
                        alert('Please select target accomplishment status.');
                        statusEl.focus();
                        return;
                    }
                    if (statusEl && (statusEl.value === 'partially_completed' || statusEl.value === 'not_completed')) {
                        if (explanationEl && !explanationEl.value.trim()) {
                            e.preventDefault();
                            alert('Please explain why the target was not fully accomplished.');
                            explanationEl.focus();
                            return;
                        }
                    }
                    if (!confirm('Are you sure you want to submit this weekly report? Once submitted, it cannot be edited.')) {
                        e.preventDefault();
                        return;
                    }
                    const submitBtn = document.getElementById('weeklyReportSubmitBtn');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="ti ti-loader spinning me-1"></i> Submitting...';
                    }
                });
            }
        });
    </script>
    @endpush
    @include('documents.partials.deskora-ai-document-context')
</x-app-layout>
