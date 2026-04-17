    <!-- Related Document Card (hidden in reviewer presentation) -->
    @if(!($reviewerPresentation ?? false) && ($relatedDocument || $relatedAction))
            <div class="col-6">
                <div class="card shadow-none border">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="ti ti-link me-2"></i>
                            Related {{ $document->type === \App\Models\Document::TYPE_WEEKLY_PLAN ? 'Weekly Report' : 'Weekly Plan' }}
                        </h6>
                        @if($relatedDocument)
                            @if($relatedDocument->state === 'draft')
                                <span class="badge bg-warning">
                                    <i class="ti ti-edit me-1"></i>Draft
                                </span>
                            @else
                                <span class="badge bg-success">
                                    <i class="ti ti-check-circle me-1"></i>Submitted
                                </span>
                            @endif
                        @else
                            <span class="badge bg-secondary">
                                <i class="ti ti-alert-triangle me-1"></i>Not Created
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
                                    <a href="{{ route('documents.show', $relatedDocument->id) }}" class="btn btn-info btn-sm text-white">
                                        <i class="ti ti-eye me-1"></i> View
                                    </a>
                                    @if($relatedDocument->canBeEdited())
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
                        @endif
                    </div>
                </div>
            </div>
       
    @endif
