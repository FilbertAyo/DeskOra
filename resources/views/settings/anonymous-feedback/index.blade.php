<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Anonymous feedback</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="javascript: void(0)">Settings</a></li>
                                <li class="breadcrumb-item" aria-current="page">Anonymous feedback</li>
                            </ul>
                        </div>

                        <div class="col-auto d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-light" onclick="reloadPage()" title="Refresh">
                                <i class="ti ti-refresh"></i>
                            </button>
                            <a href="{{ route('anonymous-feedback.qr-download') }}" class="btn btn-primary btn-sm">
                                <i class="ti ti-download me-1"></i> Download QR (PNG)
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="ti ti-check-circle me-2"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="ti ti-alert-circle me-2"></i>{{ $errors->first() }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom">
                    <div class="row align-items-center">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <h5 class="mb-0"><i class="ti ti-qrcode me-2"></i>Feedback link &amp; QR</h5>
                            <p class="text-muted small mb-0 mt-1">Messages are submitted without identity. Infrastructure logs may still exist outside this app.</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Public URL</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i class="ti ti-link"></i></span>
                                <input type="text" class="form-control font-monospace small" id="feedback-url" readonly value="{{ $feedbackUrl }}">
                                <button type="button" class="btn btn-outline-secondary" id="copy-feedback-url">Copy</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-header bg-white border-bottom">
                    <div class="row align-items-center g-2">
                        <div class="col-md-4">
                            <h5 class="mb-0"><i class="ti ti-inbox me-2"></i>Inbox</h5>
                        </div>
                        <div class="col-md-8">
                            <div class="d-flex flex-wrap justify-content-md-end align-items-center gap-2">
                                @php
                                    $chipActive = request('status');
                                    $chips = [
                                        \App\Models\AnonymousFeedback::STATUS_NEW => ['label' => 'New', 'class' => 'btn-warning'],
                                        \App\Models\AnonymousFeedback::STATUS_IN_PROGRESS => ['label' => 'In progress', 'class' => 'btn-info'],
                                        \App\Models\AnonymousFeedback::STATUS_RESOLVED => ['label' => 'Resolved', 'class' => 'btn-success'],
                                    ];
                                @endphp
                                <a
                                    href="{{ route('anonymous-feedback.index') }}"
                                    class="btn btn-sm {{ $chipActive === null || $chipActive === '' ? 'btn-secondary' : 'btn-outline-secondary' }}"
                                >
                                    All ({{ array_sum($statusCounts) }})
                                </a>
                                @foreach ($chips as $chipStatus => $chip)
                                    <a
                                        href="{{ route('anonymous-feedback.index', ['status' => $chipStatus]) }}"
                                        class="btn btn-sm {{ $chipActive === $chipStatus ? $chip['class'] : 'btn-outline-'.str_replace('btn-', '', $chip['class']) }}"
                                    >
                                        {{ $chip['label'] }} ({{ $statusCounts[$chipStatus] ?? 0 }})
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <p class="text-muted small mb-0 mt-2">
                        Update status from the table or quick actions. Only <strong>New</strong> items appear on the floating notification badge.
                    </p>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered table-striped mb-0" id="anonymousFeedbackTable">
                            <thead class="thead-light">
                                <tr>
                                    <th class="text-center" style="width: 56px;">#</th>
                                    <th style="width: 120px;">Type</th>
                                    <th>Message</th>
                                    <th style="width: 170px;">Status</th>
                                    <th class="text-center" style="width: 120px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($items as $row)
                                    @php
                                        $rowNum = ($items->firstItem() ?? 0) + $loop->index;
                                    @endphp
                                    <tr>
                                        <td class="text-center text-muted small">{{ $rowNum }}</td>
                                        <td class="small">{{ str_replace('_', ' ', ucfirst($row->category)) }}</td>
                                        <td>
                                            <button
                                                type="button"
                                                class="btn btn-link btn-sm text-start p-0 text-decoration-none text-body"
                                                data-bs-toggle="modal"
                                                data-bs-target="#anonymousFeedbackModal{{ $row->id }}"
                                            >
                                                <div class="small text-truncate" style="max-width: 360px;">
                                                    {{ \Illuminate\Support\Str::limit($row->body, 100) }}
                                                </div>
                                            </button>
                                        </td>
                                        <td>
                                            <form
                                                method="POST"
                                                action="{{ route('anonymous-feedback.update', $row) }}"
                                                class="anonymous-feedback-status-form"
                                            >
                                                @csrf
                                                @method('PATCH')
                                                <select
                                                    name="status"
                                                    class="form-select form-select-sm"
                                                    aria-label="Update status for feedback #{{ $row->id }}"
                                                    onchange="this.form.submit()"
                                                >
                                                    @foreach ($statuses as $st)
                                                        <option value="{{ $st }}" @selected($row->status === $st)>
                                                            {{ str_replace('_', ' ', ucfirst($st)) }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        </td>
                                        <td class="text-center">
                                            <x-table-action-dropdown>
                                                <button
                                                    type="button"
                                                    class="dropdown-item"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#anonymousFeedbackModal{{ $row->id }}"
                                                >
                                                    <i class="ti ti-eye me-2"></i> View
                                                </button>
                                                @if ($row->status !== \App\Models\AnonymousFeedback::STATUS_IN_PROGRESS)
                                                    <form method="POST" action="{{ route('anonymous-feedback.update', $row) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="{{ \App\Models\AnonymousFeedback::STATUS_IN_PROGRESS }}">
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="ti ti-player-play me-2"></i> Mark in progress
                                                        </button>
                                                    </form>
                                                @endif
                                                @if ($row->status !== \App\Models\AnonymousFeedback::STATUS_RESOLVED)
                                                    <form method="POST" action="{{ route('anonymous-feedback.update', $row) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="{{ \App\Models\AnonymousFeedback::STATUS_RESOLVED }}">
                                                        <button type="submit" class="dropdown-item text-success">
                                                            <i class="ti ti-check me-2"></i> Mark resolved
                                                        </button>
                                                    </form>
                                                @endif
                                            </x-table-action-dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <i class="ti ti-mail-opened fs-1 text-muted"></i>
                                            @if (request('status') === \App\Models\AnonymousFeedback::STATUS_NEW)
                                                <p class="text-muted mb-1">No new feedback.</p>
                                                <p class="text-muted small mb-0">
                                                    {{ $statusCounts[\App\Models\AnonymousFeedback::STATUS_IN_PROGRESS] ?? 0 }} in progress ·
                                                    {{ $statusCounts[\App\Models\AnonymousFeedback::STATUS_RESOLVED] ?? 0 }} resolved
                                                </p>
                                            @else
                                                <p class="text-muted mb-0">No messages yet.</p>
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($items->hasPages())
                        <div class="p-3 border-top">
                            {{ $items->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @foreach ($items as $row)
        <div class="modal fade" id="anonymousFeedbackModal{{ $row->id }}" tabindex="-1" aria-labelledby="anonymousFeedbackModalLabel{{ $row->id }}" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="anonymousFeedbackModalLabel{{ $row->id }}">Anonymous feedback</h5>
                            <p class="text-muted small mb-0">Entry #{{ $row->id }}</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <dl class="row mb-3">
                            <dt class="col-sm-3 text-muted small">Type</dt>
                            <dd class="col-sm-9 small mb-0">{{ str_replace('_', ' ', ucfirst($row->category)) }}</dd>
                            <dt class="col-sm-3 text-muted small">Status</dt>
                            <dd class="col-sm-9 small mb-0">{{ str_replace('_', ' ', ucfirst($row->status)) }}</dd>
                        </dl>
                        <label class="form-label fw-semibold">Message</label>
                        <div class="border rounded p-3 bg-light small" style="white-space: pre-wrap; max-height: 50vh; overflow-y: auto;">{{ $row->body }}</div>

                        <hr class="my-4">

                        <p class="fw-semibold mb-2">Update status</p>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @if ($row->status !== \App\Models\AnonymousFeedback::STATUS_IN_PROGRESS)
                                <form method="POST" action="{{ route('anonymous-feedback.update', $row) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ \App\Models\AnonymousFeedback::STATUS_IN_PROGRESS }}">
                                    <button type="submit" class="btn btn-info">
                                        <i class="ti ti-player-play me-1"></i> Start / In progress
                                    </button>
                                </form>
                            @endif
                            @if ($row->status !== \App\Models\AnonymousFeedback::STATUS_RESOLVED)
                                <form method="POST" action="{{ route('anonymous-feedback.update', $row) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ \App\Models\AnonymousFeedback::STATUS_RESOLVED }}">
                                    <button type="submit" class="btn btn-success">
                                        <i class="ti ti-check me-1"></i> Mark resolved
                                    </button>
                                </form>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('anonymous-feedback.update', $row) }}">
                            @csrf
                            @method('PATCH')
                            <label class="form-label small text-muted" for="modal-status-{{ $row->id }}">Or choose status</label>
                            <div class="input-group">
                                <select name="status" id="modal-status-{{ $row->id }}" class="form-select">
                                    @foreach ($statuses as $st)
                                        <option value="{{ $st }}" @selected($row->status === $st)>
                                            {{ str_replace('_', ' ', ucfirst($st)) }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-outline-primary">Save</button>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <script>
        function reloadPage() {
            location.reload();
        }
    </script>

    @push('scripts')
        <script>
            document.getElementById('copy-feedback-url')?.addEventListener('click', function () {
                const el = document.getElementById('feedback-url');
                el.select();
                el.setSelectionRange(0, 99999);
                navigator.clipboard.writeText(el.value).then(function () {
                    this.textContent = 'Copied';
                    setTimeout(function () {
                        this.textContent = 'Copy';
                    }.bind(this), 2000);
                }.bind(this));
            });
        </script>
    @endpush

    <style>
        .table-hover tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.05);
        }
    </style>
</x-app-layout>
