    <!-- Main Target Section (for Weekly Reports) -->
    @if($document->type === \App\Models\Document::TYPE_WEEKLY_REPORT)
    <div class="row">
        <div class="col-12">
            <!-- Weekly Report: Show Plan's Main Target and Accomplishment Status -->
            @php
                $relatedPlan = \App\Models\Document::where('user_id', $document->user_id)
                    ->where('period_id', $document->period_id)
                    ->where('type', \App\Models\Document::TYPE_WEEKLY_PLAN)
                    ->first();
            @endphp
            
            <div class="card shadow-none border border-info">
                <div class="card-header bg-info text-white">
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

                    @if($document->target_accomplished !== null)
                        <div class="mb-3">
                            <label class="form-label text-muted font-weight-bold">Accomplishment Status:</label>
                            <div>
                                @if($document->target_accomplished)
                                    <span class="badge bg-success rounded-pill px-3 py-2">
                                        <i class="ti ti-check-circle me-1"></i>Yes, Completed
                                    </span>
                                @else
                                    <span class="badge bg-danger rounded-pill px-3 py-2">
                                        <i class="ti ti-x-circle me-1"></i>No, Not Completed
                                    </span>
                                @endif
                            </div>
                        </div>

                        @if(!$document->target_accomplished && $document->target_explanation)
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
