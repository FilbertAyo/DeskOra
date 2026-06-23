@php
    $wizardStep = (int) ($wizardStep ?? 1);
    $wizardDocument = $document ?? null;
    $hasActionTasksStep = $hasActionTasksStep ?? true;
    $steps = [
        1 => ['label' => 'Report', 'short' => 'Fill in your weekly report'],
        2 => ['label' => 'Action Tasks', 'short' => 'Update assigned tasks'],
        3 => ['label' => 'Review & Submit', 'short' => 'Review and submit'],
    ];
@endphp

<div class="col-12 mb-3">
    <div class="card border-0 bg-light">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                @foreach($steps as $stepNumber => $step)
                    @php
                        $isActive = $wizardStep === $stepNumber;
                        $isComplete = $wizardStep > $stepNumber;
                        $isDisabled = $stepNumber === 2 && ! $hasActionTasksStep;
                        $stepUrl = null;
                        if ($wizardDocument && ($isComplete || $stepNumber < $wizardStep)) {
                            if ($stepNumber === 1) {
                                $stepUrl = route('documents.edit', $wizardDocument);
                            } elseif ($stepNumber === 2 && $hasActionTasksStep) {
                                $stepUrl = route('documents.weekly-report.action-tasks', $wizardDocument);
                            } elseif ($stepNumber === 3) {
                                $stepUrl = route('documents.weekly-report.review', $wizardDocument);
                            }
                        }
                    @endphp
                    @if($isDisabled)
                        @continue
                    @endif
                    <div class="d-flex align-items-center gap-2 {{ $stepNumber < count($steps) ? 'flex-grow-1' : '' }}">
                        <div class="text-center text-md-start">
                            @if($stepUrl && ! $isActive)
                                <a href="{{ $stepUrl }}" class="text-decoration-none d-inline-flex align-items-center gap-2">
                                    <span class="badge rounded-pill {{ $isComplete ? 'bg-success' : 'bg-secondary' }}">{{ $stepNumber }}</span>
                                    <span class="small fw-semibold text-dark">{{ $step['label'] }}</span>
                                </a>
                            @else
                                <span class="d-inline-flex align-items-center gap-2">
                                    <span class="badge rounded-pill {{ $isActive ? 'bg-primary' : ($isComplete ? 'bg-success' : 'bg-secondary') }}">{{ $stepNumber }}</span>
                                    <span class="small fw-semibold {{ $isActive ? 'text-primary' : 'text-muted' }}">{{ $step['label'] }}</span>
                                </span>
                            @endif
                            <div class="small text-muted d-none d-md-block">{{ $step['short'] }}</div>
                        </div>
                        @if($stepNumber < count($steps) && ($stepNumber !== 2 || $hasActionTasksStep))
                            <div class="flex-grow-1 border-top d-none d-md-block mx-2 opacity-50"></div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
