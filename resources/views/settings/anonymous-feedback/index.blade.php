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
                    <div class="row align-items-center">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <h5 class="mb-0"><i class="ti ti-inbox me-2"></i>Inbox</h5>
                        </div>
                        <div class="col-md-6">
                            <form method="GET" action="{{ route('anonymous-feedback.index') }}" class="d-flex justify-content-md-end">
                                <div class="input-group input-group-sm" style="max-width: 220px;">
                                    <span class="input-group-text bg-white"><i class="ti ti-filter"></i></span>
                                    <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                                        <option value="">All statuses</option>
                                        @foreach ($statuses as $st)
                                            <option value="{{ $st }}" @selected(request('status') === $st)>
                                                {{ str_replace('_', ' ', ucfirst($st)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered table-striped mb-0" id="anonymousFeedbackTable">
                            <thead class="thead-light">
                                <tr>
                                    <th class="text-center" style="width: 56px;">#</th>
                                    <th style="width: 140px;">Received</th>
                                    <th style="width: 120px;">Type</th>
                                    <th>Message</th>
                                    <th style="width: 130px;">Status</th>
                                    <th class="text-center" style="width: 100px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($items as $row)
                                    @php
                                        $rowNum = ($items->firstItem() ?? 0) + $loop->index;
                                        $statusLabel = str_replace('_', ' ', ucfirst($row->status));
                                        $statusClass = match ($row->status) {
                                            \App\Models\AnonymousFeedback::STATUS_NEW => 'bg-light-warning border border-warning text-dark',
                                            \App\Models\AnonymousFeedback::STATUS_IN_PROGRESS => 'bg-light-info border border-info text-dark',
                                            \App\Models\AnonymousFeedback::STATUS_RESOLVED => 'bg-light-success border border-success',
                                            default => 'bg-light-secondary border border-secondary',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="text-center text-muted small">{{ $rowNum }}</td>
                                        <td class="text-muted small">{{ $row->created_at->format('Y-m-d H:i') }}</td>
                                        <td class="small">{{ str_replace('_', ' ', ucfirst($row->category)) }}</td>
                                        <td>
                                            <div class="small text-truncate" style="max-width: 360px;">
                                                {{ \Illuminate\Support\Str::limit($row->body, 100) }}
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                        </td>
                                        <td class="text-center">
                                            <x-table-action-dropdown>
    <button type="button"
    class="dropdown-item"
    data-bs-toggle="modal"
    data-bs-target="#anonymousFeedbackModal{{ $row->id }}">
    <i class="ti ti-eye me-2"></i> View
    </button>
</x-table-action-dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <i class="ti ti-mail-opened fs-1 text-muted"></i>
                                            <p class="text-muted mb-0">No messages yet.</p>
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
                    <form method="POST" action="{{ route('anonymous-feedback.update', $row) }}">
                        @csrf
                        @method('PATCH')
                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title" id="anonymousFeedbackModalLabel{{ $row->id }}">Anonymous feedback</h5>
                                <p class="text-muted small mb-0">Entry #{{ $row->id }} · {{ $row->created_at->format('Y-m-d H:i') }}</p>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <dl class="row mb-3">
                                <dt class="col-sm-3 text-muted small">Type</dt>
                                <dd class="col-sm-9 small mb-0">{{ str_replace('_', ' ', ucfirst($row->category)) }}</dd>
                            </dl>
                            <label class="form-label fw-semibold">Message</label>
                            <div class="border rounded p-3 bg-light small" style="white-space: pre-wrap; max-height: 50vh; overflow-y: auto;">{{ $row->body }}</div>

                            <hr class="my-4">

                            <label class="form-label fw-semibold" for="modal-status-{{ $row->id }}">Status</label>
                            <select name="status" id="modal-status-{{ $row->id }}" class="form-select">
                                @foreach ($statuses as $st)
                                    <option value="{{ $st }}" @selected($row->status === $st)>
                                        {{ str_replace('_', ' ', ucfirst($st)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save status</button>
                        </div>
                    </form>
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
