    @php
        $isWeeklyDocument = in_array($document->type, [\App\Models\Document::TYPE_WEEKLY_PLAN, \App\Models\Document::TYPE_WEEKLY_REPORT], true);
        $hasRelatedWeeklyPanel = ($showWeeklyRelatedPanel ?? false)
            || (($relatedDocument ?? null) || ($relatedAction ?? null))
            && $isWeeklyDocument;
        $relatedShowRoute = ($reviewerPresentation ?? false)
            ? 'documents.reviewer-show'
            : 'documents.show';
        $relatedTypeLabel = $document->type === \App\Models\Document::TYPE_WEEKLY_PLAN ? 'Weekly Report' : 'Weekly Plan';
    @endphp
    @if($hasRelatedWeeklyPanel)
            <div class="col-md-6">
                <div class="card shadow-none border">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="ti ti-link me-2"></i>
                            Related {{ $relatedTypeLabel }}
                        </h6>
                        @if($relatedDocument)
                            @if($relatedDocument->state === 'draft')
                                <span class="badge bg-light-warning border border-warning">Draft
                                </span>
                            @else
                                <span class="badge bg-light-success border border-success">Submitted
                                </span>
                            @endif
                        @else
                            <span class="badge bg-light-secondary border border-secondary">Not Created
                            </span>
                        @endif
                    </div>
                    <div class="card-body">
                        @if($relatedDocument)
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <div class="mb-2">
                                    <h6 class="mb-1">{{ $relatedDocument->display_title }}</h6>
                                    <small class="text-muted">
                                        <i class="ti ti-user me-1"></i>{{ $relatedDocument->user->name }} •
                                        <i class="ti ti-calendar me-1"></i>{{ $relatedDocument->created_at->format('M d, Y') }}
                                    </small>
                                </div>
                                <div class="btn-group">
                                    <a href="{{ route($relatedShowRoute, $relatedDocument->id) }}" class="btn btn-info btn-sm text-white">
                                        <i class="ti ti-eye me-1"></i> View
                                    </a>
                                    @if(!($reviewerPresentation ?? false) && $relatedDocument->canBeEdited())
                                        <a href="{{ route('documents.edit', $relatedDocument->id) }}" class="btn btn-primary btn-sm">
                                            <i class="ti ti-edit me-1"></i> Edit
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @elseif($relatedAction)
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <div>
                                    <h6 class="mb-0">{{ $relatedAction['label'] }}</h6>
                                    <small class="text-muted">{{ $relatedAction['description'] ?? '' }}</small>
                                </div>
                                <a href="{{ $relatedAction['route'] }}" class="btn btn-outline-primary btn-sm">
                                    <i class="ti ti-plus-circle me-1"></i> Create
                                </a>
                            </div>
                        @else
                            <p class="text-muted mb-0 small">
                                No {{ strtolower($relatedTypeLabel) }} has been created for this week yet.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
    @endif
