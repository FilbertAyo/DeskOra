    <!-- Main Target Section (for Weekly Reports) -->
    @if($document->type === \App\Models\Document::TYPE_WEEKLY_REPORT)
    <div class="row">
        <div class="col-12">
            <!-- Weekly Report: Show Plan's Main Target and Accomplishment Status -->
            @php
                $relatedPlan = \App\Models\Document::findWeeklyPlanForUserAndPeriod(
                    $document->period_id,
                    (string) $document->user_id
                );
                $reportContent = is_array($document->content) ? $document->content : [];
                $targetStatus = trim((string) ($reportContent['target_accomplished_status'] ?? ''));
                if (! in_array($targetStatus, ['completed', 'partially_completed', 'not_completed'], true)) {
                    if ($document->target_accomplished === true || $document->target_accomplished === 1 || $document->target_accomplished === '1') {
                        $targetStatus = 'completed';
                    } elseif ($document->target_accomplished === false || $document->target_accomplished === 0 || $document->target_accomplished === '0') {
                        $targetStatus = 'not_completed';
                    } else {
                        $targetStatus = '';
                    }
                }
            @endphp
            
            <div class="card shadow-none border border-info">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="ti ti-target me-2"></i>Main Target Accomplishment Status
                    </h6>
                </div>
                <div class="card-body">
                    @if($relatedPlan && $relatedPlan->main_target)
                        <div class="mb-3">
                            <label class="form-label text-muted font-weight-bold">Main Target from Weekly Plan:</label>
                            <div class="alert alert-light border">
                                <p class="mb-0"><strong>{{ $relatedPlan->main_target }}</strong></p>
                            </div>
                        </div>
                    @endif

                    @if($targetStatus !== '')
                        <div class="mb-3">
                            <label class="form-label text-muted font-weight-bold">Accomplishment Status:</label>
                            <div>
                                @if($targetStatus === 'completed')
                                    <span class="badge bg-light-success border border-success rounded-pill px-3 py-2">Completed
                                    </span>
                                @elseif($targetStatus === 'partially_completed')
                                    <span class="badge bg-light-warning border border-warning rounded-pill px-3 py-2 text-dark">Partially completed
                                    </span>
                                @else
                                    <span class="badge bg-light-danger border border-danger rounded-pill px-3 py-2">Not completed
                                    </span>
                                @endif
                            </div>
                        </div>

                        @if(in_array($targetStatus, ['partially_completed', 'not_completed'], true) && $document->target_explanation)
                            <div class="mb-0">
                                <label class="form-label text-muted font-weight-bold">Explanation:</label>
                                <div class="alert alert-warning border">
                                    <p class="mb-0">{{ $document->target_explanation }}</p>
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="alert alert-secondary">
                            <i class="ti ti-info me-2"></i>
                            Accomplishment status has not been set for this report.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif
