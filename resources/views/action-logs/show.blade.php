<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Action Log Details</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('action-logs.index') }}">Action Logs</a></li>
                                <li class="breadcrumb-item" aria-current="page">Details</li>
                            </ul>
                        </div>
                        <div class="col-auto d-flex align-items-center gap-2">
                            <a href="{{ route('action-logs.index') }}" class="btn btn-sm btn-light">
                                <i class="ti ti-arrow-left me-1"></i> Back
                            </a>
                            @can('update', $document)
                                <a href="{{ route('action-logs.edit', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}" class="btn btn-primary btn-sm">
                                    <i class="ti ti-edit me-1"></i> Edit
                                </a>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ti ti-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="ti ti-alert-circle me-2"></i>{{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <div class="row">
        <div class="col-12 mb-3">
            <div class="row g-3">
                <!-- Compact Info Card -->
                <div class="col-12 col-lg-8">
                    <div class="card shadow-none border h-100">
                        <div class="card-header bg-white border-bottom py-2">
                            <h6 class="mb-0"><i class="ti ti-info me-2"></i>Action Log Information</h6>
                        </div>
                        <div class="card-body py-3">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="text-muted small mb-1 d-block">Document Type</label>
                                    <span class="badge bg-info  px-3 py-2">
                                        <i class="ti ti-clipboard me-1"></i>Action Log
                                    </span>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="text-muted small mb-1 d-block">State</label>
                                    @if($document->state === 'draft')
                                        <span class="badge bg-warning px-3 py-2">
                                            <i class="ti ti-edit me-1"></i>Draft
                                        </span>
                                    @else
                                        <span class="badge bg-success  px-3 py-2">
                                            <i class="ti ti-check-circle me-1"></i>Submitted
                                        </span>
                                    @endif
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="text-muted small mb-1 d-block">Created By</label>
                                    <div class="d-flex align-items-center">
                                        <i class="ti ti-user me-2 text-primary"></i>
                                        <strong>{{ $document->user->name }}</strong>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="text-muted small mb-1 d-block">Created At</label>
                                    <div class="d-flex align-items-center">
                                        <i class="ti ti-clock me-2 text-primary"></i>
                                        <strong>{{ $document->created_at->format('M d, Y h:i A') }}</strong>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="text-muted small mb-1 d-block">Last Updated</label>
                                    <div class="d-flex align-items-center">
                                        <i class="ti ti-refresh-cw me-2 text-primary"></i>
                                        <strong>{{ $document->updated_at->format('M d, Y h:i A') }}</strong>
                                    </div>
                                </div>

                                @if($document->period)
                                    <div class="col-12 col-md-6">
                                        <label class="text-muted small mb-1 d-block">Work Period</label>
                                        <div class="d-flex align-items-start">
                                            <i class="ti ti-calendar me-2 text-primary mt-1"></i>
                                            <div>
                                                <strong>Week {{ $document->period->week_number }} of {{ $document->period->year }}</strong><br>
                                                <small class="text-muted">
                                                    {{ \Carbon\Carbon::parse($document->period->week_start_date)->format('M d') }} -
                                                    {{ \Carbon\Carbon::parse($document->period->week_end_date)->format('M d, Y') }}
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Compact Actions Card -->
                <div class="col-12 col-lg-4">
                    <div class="card shadow-none border h-100">
                        <div class="card-header bg-white border-bottom py-2">
                            <h6 class="mb-0"><i class="ti ti-activity me-2"></i>Actions</h6>
                        </div>
                        <div class="card-body py-3">
                            <div class="row g-2">
                                @can('update', $document)
                                    <div class="col-12 col-md-6">
                                        <a href="{{ route('action-logs.edit', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}" class="btn btn-primary btn-sm w-100">
                                            <i class="ti ti-edit me-1"></i> Edit Action Log
                                        </a>
                                    </div>
                                @endcan

                                @if($document->canBeDeleted())
                                    <div class="col-12 col-md-6">
                                        <form action="{{ route('action-logs.destroy', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this action log? This action cannot be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm w-100">
                                                <i class="ti ti-trash-2 me-1"></i> Delete Action Log
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Log Content -->
        <div class="col-12">
            <div class="card shadow-none border mb-4">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="ti ti-clipboard me-2"></i>
                        {{ $document->display_title }}
                    </h5>
                </div>
                <div class="card-body">
                    @php
                        $content = is_array($document->content) ? $document->content : [];
                        $tasks = is_array($content['tasks'] ?? null) ? $content['tasks'] : [];
                        $responsibleIds = collect($tasks)
                            ->filter(fn($row) => is_array($row) && is_array($row['responsible_user_ids'] ?? null))
                            ->flatMap(fn($row) => $row['responsible_user_ids'])
                            ->filter()
                            ->map(fn($id) => (int) $id)
                            ->unique()
                            ->values()
                            ->all();
                        $responsibleNameMap = \App\Models\User::whereIn('id', $responsibleIds)->pluck('name', 'id')->toArray();
                    @endphp

                    @if(!empty($tasks))
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 70px;">No</th>
                                        <th style="min-width: 520px;">Task</th>
                                        <th style="width: 250px;">Responsible Person</th>
                                        <th style="width: 160px;">Progress</th>
                                        <th style="width: 140px;">Due Date</th>
                                        <th style="width: 170px;">Compilation Date</th>
                                        <th>Notes/Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($tasks as $row)
                                        @php
                                            $row = is_array($row) ? $row : [];
                                            $ids = is_array($row['responsible_user_ids'] ?? null) ? array_map('intval', $row['responsible_user_ids']) : [];
                                            $names = collect($ids)->map(fn($id) => $responsibleNameMap[$id] ?? null)->filter()->values()->all();
                                        @endphp
                                        <tr>
                                            <td class="text-center">{{ $row['task_no'] ?? $loop->iteration }}</td>
                                            <td class="text-wrap">{{ $row['task'] ?? '' }}</td>
                                            <td>{{ !empty($names) ? implode(', ', $names) : '-' }}</td>
                                            <td>
                                                @php $progress = $row['progress'] ?? 'Not Started'; @endphp
                                                @if($progress === 'Done')
                                                    <span class="badge bg-success">{{ $progress }}</span>
                                                @elseif($progress === 'In Progress')
                                                    <span class="badge bg-info">{{ $progress }}</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ $progress }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $row['due_date'] ?? '-' }}</td>
                                            <td>{{ $row['compilation_date'] ?? '-' }}</td>
                                            <td>{{ $row['notes_progress'] ?? '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-warning">
                            <i class="ti ti-alert-circle me-2"></i>
                            No structured action log tasks found for this document.
                        </div>
                    @endif
                </div>
            </div>

            <!-- Comments Section -->
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="ti ti-message-circle me-2"></i>
                        Comments
                        <span class="badge bg-primary  ml-2">{{ $document->comments->count() }}</span>
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
                                        <i class="ti ti-edit me-1"></i> Add Comment
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
                                    <i class="ti ti-send me-1"></i> Post Comment
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
                                                    <i class="ti ti-clock me-1"></i>
                                                    {{ $comment->created_at->diffForHumans() }}
                                                    @if($comment->updated_at != $comment->created_at)
                                                        <span class="text-muted">(edited)</span>
                                                    @endif
                                                </small>
                                            </div>
                                        </div>
                                        @can('update', $comment)
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-sm btn-light js-edit-comment" data-comment-id="{{ $comment->id }}" title="Edit">
                                                    <i class="ti ti-edit"></i>
                                                </button>
                                                <form action="{{ route('comments.destroy', \Vinkla\Hashids\Facades\Hashids::encode($comment->id)) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this comment?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-light text-danger" title="Delete">
                                                        <i class="ti ti-trash-2"></i>
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
                                                        <i class="ti ti-check me-1"></i> Save
                                                    </button>
                                                    <button type="button" class="btn btn-secondary btn-sm js-cancel-edit" data-comment-id="{{ $comment->id }}">
                                                        <i class="ti ti-x me-1"></i> Cancel
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
                            <i class="ti ti-message-circle fs-1 text-muted"></i>
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
        function editComment(commentId) {
            const textElement = document.getElementById('comment-text-' + commentId);
            const editForm = document.getElementById('edit-form-' + commentId);
            const textarea = document.querySelector('#edit-form-' + commentId + ' textarea');
            if (textarea && textElement) {
                textarea.dataset.original = textElement.textContent || '';
            }
            if (textElement) textElement.style.display = 'none';
            if (editForm) editForm.style.display = 'block';
        }

        function cancelEdit(commentId) {
            const textElement = document.getElementById('comment-text-' + commentId);
            const editForm = document.getElementById('edit-form-' + commentId);
            if (textElement) textElement.style.display = 'block';
            if (editForm) editForm.style.display = 'none';
            const textarea = document.querySelector('#edit-form-' + commentId + ' textarea');
            if (textarea) {
                textarea.value = textarea.dataset.original || textarea.value;
            }
        }

        document.addEventListener('click', function (event) {
            const editBtn = event.target.closest('.js-edit-comment');
            if (editBtn) {
                const id = Number(editBtn.getAttribute('data-comment-id'));
                if (id) editComment(id);
            }

            const cancelBtn = event.target.closest('.js-cancel-edit');
            if (cancelBtn) {
                const id = Number(cancelBtn.getAttribute('data-comment-id'));
                if (id) cancelEdit(id);
            }
        });
    </script>
</x-app-layout>

