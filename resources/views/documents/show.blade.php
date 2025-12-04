<x-app-layout>
    <div class="row align-items-center mb-3 border-bottom no-gutters">
        <div class="col">
            <ul class="nav nav-tabs border-0" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab"
                        aria-controls="home" aria-selected="true">
                        <i class="fe fe-eye me-1"></i> Document Details
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

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fe fe-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fe fe-alert-circle me-2"></i>{{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- Document Info and Actions - Top Row -->
    <div class="row mb-4">
        <!-- Document Info Card -->
        <div class="col-md-6 mb-3">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0">
                        <i class="fe fe-info me-2"></i>Document Information &amp; Actions
                    </h6>
                </div>
                <div class="card-body py-2">
                    <div class="d-flex align-items-center flex-wrap mb-2">
                        <span class="badge badge-info mr-2 mb-1">
                            <i class="fe fe-file me-1"></i>{{ $document->type_name }}
                        </span>
                        @if($document->state === 'draft')
                            <span class="badge badge-warning mr-2 mb-1">
                                <i class="fe fe-edit me-1"></i>Draft
                            </span>
                        @else
                            <span class="badge badge-success mr-2 mb-1">
                                <i class="fe fe-check-circle me-1"></i>Submitted
                            </span>
                        @endif
                    </div>
                    <div class="small text-muted mb-1">
                        <span>Created by: <strong>{{ $document->user->name }}</strong></span>
                        @if($document->period)
                            <span class="mx-2">|</span>
                            <span>
                                Week <strong>{{ $document->period->week_number }}</strong> of <strong>{{ $document->period->year }}</strong>
                            </span>
                        @endif
                    </div>
                    <div class="btn-group mt-2" role="group">
                        @if($document->canBeEdited())
                            <a href="{{ route('documents.edit', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}" class="btn btn-primary btn-sm">
                                <i class="fe fe-edit me-1"></i> Edit
                            </a>
                        @endif

                        @if($document->canBeDeleted())
                            <form action="{{ route('documents.destroy', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this document? This action cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="fe fe-trash-2 me-1"></i> Delete
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    

    <!-- Related Document Card -->
    @if($relatedDocument || $relatedAction)
            <div class="col-6">
                <div class="card shadow-none border">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="fe fe-link me-2"></i>
                            Related {{ $document->type === \App\Models\Document::TYPE_WEEKLY_PLAN ? 'Weekly Report' : 'Weekly Plan' }}
                        </h6>
                        @if($relatedDocument)
                            @if($relatedDocument->state === 'draft')
                                <span class="badge badge-warning">
                                    <i class="fe fe-edit me-1"></i>Draft
                                </span>
                            @else
                                <span class="badge badge-success">
                                    <i class="fe fe-check-circle me-1"></i>Submitted
                                </span>
                            @endif
                        @else
                            <span class="badge badge-secondary">
                                <i class="fe fe-alert-triangle me-1"></i>Not Created
                            </span>
                        @endif
                    </div>
                    <div class="card-body">
                        @if($relatedDocument)
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <div class="mb-2">
                                    <h6 class="mb-1">{{ $relatedDocument->display_title }}</h6>
                                    <small class="text-muted">
                                        <i class="fe fe-user me-1"></i>{{ $relatedDocument->user->name }} • 
                                        <i class="fe fe-calendar me-1"></i>{{ $relatedDocument->created_at->format('M d, Y') }}
                                    </small>
                                </div>
                                <div class="btn-group">
                                    <a href="{{ route('documents.show', \Vinkla\Hashids\Facades\Hashids::encode($relatedDocument->id)) }}" class="btn btn-info btn-sm text-white">
                                        <i class="fe fe-eye me-1"></i> View
                                    </a>
                                    @if($relatedDocument->canBeEdited())
                                        <a href="{{ route('documents.edit', \Vinkla\Hashids\Facades\Hashids::encode($relatedDocument->id)) }}" class="btn btn-primary btn-sm">
                                            <i class="fe fe-edit me-1"></i> Edit
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @elseif($relatedAction)
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <div class="mb-2">
                                    <p class="text-muted mb-1">No related document found.</p>
                                    <h6 class="mb-0">{{ $relatedAction['label'] }}</h6>
                                    <small class="text-muted">{{ $relatedAction['description'] ?? '' }}</small>
                                </div>
                                <a href="{{ $relatedAction['route'] }}" class="btn btn-outline-primary btn-sm">
                                    <i class="fe fe-plus-circle me-1"></i> Create
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
       
    @endif

    </div>

    <!-- Main Target Section (for Weekly Plans and Reports) -->
    @if($document->type === \App\Models\Document::TYPE_WEEKLY_PLAN || $document->type === \App\Models\Document::TYPE_WEEKLY_REPORT)
    <div class="row mb-4">
        <div class="col-12">
            @if($document->type === \App\Models\Document::TYPE_WEEKLY_PLAN)
                <!-- Weekly Plan: Show Main Target -->
                @if($document->main_target)
                <div class="card shadow-none border border-primary">
                    <div class="card-header text-white">
                        <h6 class="mb-0">
                            <i class="fe fe-target me-2"></i>Main Target/Goal for This Week
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-0 lead">{{ $document->main_target }}</p>
                    </div>
                </div>
                @endif
            @elseif($document->type === \App\Models\Document::TYPE_WEEKLY_REPORT)
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
                            <i class="fe fe-target me-2"></i>Main Target Accomplishment Status
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
                                        <span class="badge badge-success badge-pill px-3 py-2">
                                            <i class="fe fe-check-circle me-1"></i>Yes, Completed
                                        </span>
                                    @else
                                        <span class="badge badge-danger badge-pill px-3 py-2">
                                            <i class="fe fe-x-circle me-1"></i>No, Not Completed
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
                                <i class="fe fe-info me-2"></i>
                                Accomplishment status has not been set for this report.
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
    @endif

    <!-- PDF Viewer - Full Width -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-none border mb-4">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fe fe-file-text me-2"></i>{{ $document->display_title }}
                    </h5>
                    @if($document->file_path && $document->hasFile())
                        <a href="{{ $document->file_url }}" target="_blank" class="btn btn-sm btn-info">
                            <i class="fe fe-download me-1"></i> Download PDF
                        </a>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if($document->file_path && $document->hasFile())
                        <!-- PDF Viewer -->
                        <div style="min-height: 800px;">
                            <iframe 
                                src="{{ $document->file_url }}" 
                                style="width: 100%; height: 800px; border: none;"
                                type="application/pdf">
                                <p>Your browser does not support PDFs. 
                                    <a href="{{ $document->file_url }}" target="_blank">Download the PDF</a> instead.
                                </p>
                            </iframe>
                        </div>
                    @else
                        <div class="alert alert-warning m-4">
                            <i class="fe fe-alert-triangle me-2"></i>
                            <strong>No PDF file available.</strong> 
                            @if($document->canBeEdited())
                                Please <a href="{{ route('documents.edit', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}">upload a PDF file</a> to view the document.
                            @else
                                The PDF file for this document is not available.
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Comments Section -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="fe fe-message-circle me-2"></i>
                        Comments
                        <span class="badge badge-primary badge-pill ml-2">{{ $document->comments->count() }}</span>
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Comment Form (Only for Reviewers) -->
                    @can('create', \App\Models\Comment::class)
                        <div class="mb-4">
                            <form action="{{ route('comments.store', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}" method="POST" id="commentForm">
                                @csrf
                                <div class="form-group">
                                    <label for="comment" class="form-label">
                                        <i class="fe fe-edit me-1"></i> Add Comment
                                    </label>
                                    <textarea 
                                        class="form-control @error('comment') is-invalid @enderror" 
                                        name="comment" 
                                        id="comment" 
                                        rows="3" 
                                        placeholder="Enter your comment here..."
                                        required>{{ old('comment') }}</textarea>
                                    @error('comment')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fe fe-send me-1"></i> Post Comment
                                </button>
                            </form>
                        </div>
                        <hr>
                    @endcan

                    <!-- Comments List -->
                    @if($document->comments->count() > 0)
                        <div class="comments-list">
                            @foreach($document->comments as $comment)
                                <div class="comment-item mb-3 pb-3 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mr-2" style="width: 32px; height: 32px;">
                                                {{ strtoupper(substr($comment->user->name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <strong class="d-block">{{ $comment->user->name }}</strong>
                                                <small class="text-muted">
                                                    <i class="fe fe-clock me-1"></i>
                                                    {{ $comment->created_at->diffForHumans() }}
                                                    @if($comment->updated_at != $comment->created_at)
                                                        <span class="text-muted">(edited)</span>
                                                    @endif
                                                </small>
                                            </div>
                                        </div>
                                        @can('update', $comment)
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" 
                                                    class="btn btn-sm btn-light edit-comment-btn" 
                                                    data-comment-id="{{ $comment->id }}"
                                                    data-comment-text="{{ htmlspecialchars($comment->comment, ENT_QUOTES, 'UTF-8') }}"
                                                    title="Edit">
                                                    <i class="fe fe-edit"></i>
                                                </button>
                                                <form action="{{ route('comments.destroy', \Vinkla\Hashids\Facades\Hashids::encode($comment->id)) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this comment?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-light text-danger" title="Delete">
                                                        <i class="fe fe-trash-2"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        @endcan
                                    </div>
                                    <div class="comment-content">
                                        <p class="mb-0" id="comment-text-{{ $comment->id }}">{{ $comment->comment }}</p>
                                        
                                        <!-- Edit Form (Hidden by default) -->
                                        <div id="edit-form-{{ $comment->id }}" style="display: none;" class="mt-2">
                                            <form action="{{ route('comments.update', \Vinkla\Hashids\Facades\Hashids::encode($comment->id)) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <textarea class="form-control mb-2" name="comment" rows="3" required>{{ $comment->comment }}</textarea>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="submit" class="btn btn-primary btn-sm">
                                                        <i class="fe fe-check me-1"></i> Save
                                                    </button>
                                                    <button type="button" 
                                                        class="btn btn-secondary btn-sm cancel-edit-btn" 
                                                        data-comment-id="{{ $comment->id }}"
                                                        data-comment-text="{{ htmlspecialchars($comment->comment, ENT_QUOTES, 'UTF-8') }}">
                                                        <i class="fe fe-x me-1"></i> Cancel
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fe fe-message-circle fs-1 text-muted"></i>
                            <p class="text-muted mb-0 mt-2">No comments yet</p>
                            @can('create', \App\Models\Comment::class)
                                <small class="text-muted">Be the first to comment!</small>
                            @endcan
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle edit comment button clicks
            document.querySelectorAll('.edit-comment-btn').forEach(function(button) {
                button.addEventListener('click', function() {
                    const commentId = this.getAttribute('data-comment-id');
                    document.getElementById('comment-text-' + commentId).style.display = 'none';
                    document.getElementById('edit-form-' + commentId).style.display = 'block';
                });
            });

            // Handle cancel edit button clicks
            document.querySelectorAll('.cancel-edit-btn').forEach(function(button) {
                button.addEventListener('click', function() {
                    const commentId = this.getAttribute('data-comment-id');
                    const originalText = this.getAttribute('data-comment-text');
                    document.getElementById('comment-text-' + commentId).style.display = 'block';
                    document.getElementById('edit-form-' + commentId).style.display = 'none';
                    const textarea = document.querySelector('#edit-form-' + commentId + ' textarea');
                    if (textarea) {
                        textarea.value = originalText;
                    }
                });
            });
        });
    </script>
</x-app-layout>
