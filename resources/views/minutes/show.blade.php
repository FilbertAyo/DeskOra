<x-app-layout>
    <div class="row align-items-center mb-3 border-bottom no-gutters">
        <div class="col">
            <ul class="nav nav-tabs border-0" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab"
                        aria-controls="home" aria-selected="true">
                        <i class="fe fe-clipboard me-1"></i> Weekly Minutes Details
                    </a>
                </li>
            </ul>
        </div>
        <div class="col-auto">
            <a href="{{ route('minutes.index') }}" class="btn btn-sm btn-light">
                <i class="fe fe-arrow-left me-1"></i> Back to Minutes
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

    <div class="row">
        <!-- Minutes Info Card -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="fe fe-info me-2"></i>Minutes Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small mb-1">Document Type</label>
                        <div class="d-flex align-items-center">
                            <span class="badge badge-info badge-pill px-3 py-2">
                                <i class="fe fe-clipboard me-1"></i>
                                Weekly Minutes
                            </span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small mb-1">State</label>
                        <div class="d-flex align-items-center">
                            @if($document->state === 'draft')
                                <span class="badge badge-warning badge-pill px-3 py-2">
                                    <i class="fe fe-edit me-1"></i>Draft
                                </span>
                            @else
                                <span class="badge badge-success badge-pill px-3 py-2">
                                    <i class="fe fe-check-circle me-1"></i>Submitted
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small mb-1">Created By</label>
                        <div class="d-flex align-items-center">
                            <i class="fe fe-user me-2 text-primary"></i>
                            <strong>{{ $document->user->name }}</strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small mb-1">Created At</label>
                        <div class="d-flex align-items-center">
                            <i class="fe fe-clock me-2 text-primary"></i>
                            <strong>{{ $document->created_at->format('M d, Y h:i A') }}</strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small mb-1">Last Updated</label>
                        <div class="d-flex align-items-center">
                            <i class="fe fe-refresh-cw me-2 text-primary"></i>
                            <strong>{{ $document->updated_at->format('M d, Y h:i A') }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions Card -->
            <div class="card shadow-none border mt-3">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0"><i class="fe fe-activity me-2"></i>Actions</h6>
                </div>
                <div class="card-body p-2">
                    <div class="d-grid gap-2">
                        @if($document->canBeEdited())
                            <a href="{{ route('minutes.edit', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}" class="btn btn-primary btn-sm">
                                <i class="fe fe-edit me-1"></i> Edit Minutes
                            </a>
                        @endif

                        @if($document->canBeDeleted())
                            <form action="{{ route('minutes.destroy', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete these minutes? This action cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm w-100">
                                    <i class="fe fe-trash-2 me-1"></i> Delete Minutes
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Minutes Content -->
        <div class="col-lg-8">
            <div class="card shadow-none border mb-4">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fe fe-clipboard me-2"></i>
                        {{ $document->display_title }}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="document-content">
                        <pre class="bg-light p-3 rounded border" style="white-space: pre-wrap; font-family: inherit; font-size: 14px; line-height: 1.6;">{{ $document->content ?? 'No content available.' }}</pre>
                    </div>
                </div>
            </div>

            <!-- Comments Section -->
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
                                    <small class="form-text text-muted">Minimum 3 characters, maximum 5000 characters</small>
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
                                                <button type="button" class="btn btn-sm btn-light" onclick="editComment({{ $comment->id }}, '{{ addslashes($comment->comment) }}')" title="Edit">
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
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="cancelEdit({{ $comment->id }}, '{{ addslashes($comment->comment) }}')">
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
        function editComment(commentId, originalText) {
            document.getElementById('comment-text-' + commentId).style.display = 'none';
            document.getElementById('edit-form-' + commentId).style.display = 'block';
        }

        function cancelEdit(commentId, originalText) {
            document.getElementById('comment-text-' + commentId).style.display = 'block';
            document.getElementById('edit-form-' + commentId).style.display = 'none';
            const textarea = document.querySelector('#edit-form-' + commentId + ' textarea');
            if (textarea) {
                textarea.value = originalText;
            }
        }
    </script>
</x-app-layout>

