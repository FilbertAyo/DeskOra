<x-app-layout>
    @php
        $hasActionTasksStep = ! empty($actionLogProgress['activeTasks'] ?? []);
        $pendingCount = collect($actionLogProgress['activeTasks'] ?? [])
            ->filter(fn ($task) => is_array($task) && ! empty($task['needs_update']))
            ->count();
    @endphp

    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Weekly Report — Action Tasks</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('documents.weekly-reports') }}">Weekly Reports</a></li>
                                <li class="breadcrumb-item" aria-current="page">Action Tasks</li>
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
                'wizardStep' => 2,
                'document' => $document,
                'hasActionTasksStep' => $hasActionTasksStep,
            ])

            @include('documents.partials.weekly-report-action-tasks')

            <div class="card shadow-none border">
                <div class="card-footer bg-light border-top">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <a href="{{ route('documents.edit', $document) }}" class="btn btn-secondary">
                            <i class="ti ti-arrow-left me-1"></i> Back to report
                        </a>
                        @if($pendingCount === 0)
                            <a href="{{ route('documents.weekly-report.review', $document) }}" class="btn btn-primary">
                                Continue to review <i class="ti ti-arrow-right ms-1"></i>
                            </a>
                        @else
                            <button type="button" class="btn btn-primary" disabled title="Update all pending action tasks first">
                                Continue to review <i class="ti ti-arrow-right ms-1"></i>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('documents.partials.weekly-report-action-tasks-modal')
    @include('documents.partials.deskora-ai-document-context')
</x-app-layout>
