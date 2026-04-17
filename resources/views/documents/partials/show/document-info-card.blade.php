        <div class="{{ ($reviewerPresentation ?? false) ? 'col-12' : 'col-md-6' }}">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        Document Information &amp; Actions
                    </h6>
                    <div class="d-flex align-items-center flex-wrap gap-1">
                     
                        @if($document->state === 'draft')
                            <span class="badge bg-warning">
                                Draft
                            </span>
                        @else
                            <span class="badge bg-success">
                                Submitted
                            </span>
                        @endif
                        @if($document->isReturnedForRevision())
                            <span class="badge bg-danger">
                                Returned for Revision
                            </span>
                        @endif
                    </div>
                </div>
                <div class="card-body py-2">
                   
                    <div class="small text-muted mb-1">
                        <span>Created by: <strong>{{ $document->user->name }}</strong></span>
                        @if($document->period)
                            <span class="mx-2">|</span>
                            <span>
                                Week <strong>{{ $document->period->week_number }}</strong> of <strong>{{ $document->period->year }}</strong>
                            </span>
                        @elseif($document->type === \App\Models\Document::TYPE_MONTHLY_REPORT && $document->month && $document->year)
                            <span class="mx-2">|</span>
                            <span>
                                <strong>{{ \Carbon\Carbon::create((int) $document->year, (int) $document->month, 1)->format('F Y') }}</strong>
                            </span>
                        @endif
                    </div>
                    <div class="btn-group mt-2 gap-1" role="group">
                        @if($document->canBeEdited())
                            <a href="{{ route('documents.edit', $document->id) }}" class="btn btn-primary btn-sm">
                                Edit
                            </a>
                        @endif

                        @if($document->canBeDeleted())
                            <form action="{{ route('documents.destroy', $document->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this document? This action cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">
                                    Delete
                                </button>
                            </form>
                        @endif

                        @if($reviewerPresentation ?? false)
                            @can('reject', $document)
                                <button
                                    type="button"
                                    class="btn btn-warning btn-sm text-dark"
                                    data-bs-toggle="modal"
                                    data-bs-target="#rejectDocumentModal">
                                    <i class="ti ti-arrow-back-up me-1"></i> Return to Draft
                                </button>
                            @endcan
                        @endif
                    </div>
                </div>
            </div>
        </div>
