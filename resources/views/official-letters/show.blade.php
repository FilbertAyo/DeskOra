<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Official Letter</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('official-letters.index') }}">Official Letters</a></li>
                                <li class="breadcrumb-item" aria-current="page">View</li>
                            </ul>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ route('official-letters.download', $letter) }}" class="btn btn-sm btn-danger">
                                <i class="ti ti-download me-1"></i> Download PDF
                            </a>
                            @if($canUpdate)
                                <a href="{{ route('official-letters.edit', $letter) }}" class="btn btn-sm btn-light">
                                    <i class="ti ti-pencil me-1"></i> Edit
                                </a>
                            @endif
                            @if($canDelete)
                                <form method="POST" action="{{ route('official-letters.destroy', $letter) }}"
                                      onsubmit="return confirm('Delete this official letter?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="ti ti-trash me-1"></i> Delete
                                    </button>
                                </form>
                            @endif
                            <a href="{{ route('official-letters.index') }}" class="btn btn-sm btn-light">
                                <i class="ti ti-arrow-left me-1"></i> Back
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ti ti-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($isSharedWithMe)
        <div class="alert alert-info">
            <i class="ti ti-share me-1"></i> This letter was shared with you. You can view and edit it.
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-none border mb-3">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="ti ti-file-text me-2"></i>Letter Content</h5>
                    <code>{{ $letter->reference_number }}</code>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Recipient</label>
                        <div style="white-space: pre-line;">{{ $letter->recipient }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Salutation</label>
                        <div>{{ $letter->salutation }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Subject</label>
                        <div><strong>RE: {{ $letter->subject }}</strong></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Body</label>
                        <div class="official-letter-body" style="font-family: Calibri, Carlito, 'Segoe UI', sans-serif; text-align: justify;">
                            @php
                                $bodyHtml = (string) $letter->body;
                                if ($bodyHtml !== '' && $bodyHtml === strip_tags($bodyHtml)) {
                                    $bodyHtml = nl2br(e($bodyHtml));
                                }
                            @endphp
                            {!! $bodyHtml !!}
                        </div>
                    </div>
                    <div class="mt-4 text-center">
                        <div class="text-muted small">{{ $letter->closing }}</div>
                        @php
                            $signatureUrl = null;
                            if ($letter->usesDigitalSignature()) {
                                $signaturePath = $letter->signatory?->signature;
                                if (filled($signaturePath)) {
                                    if (is_file(public_path($signaturePath))) {
                                        $signatureUrl = asset($signaturePath);
                                    } elseif (is_file(storage_path('app/public/'.ltrim(preg_replace('#^storage/#', '', $signaturePath) ?? '', '/')))) {
                                        $signatureUrl = asset($signaturePath);
                                    }
                                }
                            }
                        @endphp
                        @if($signatureUrl)
                            <div class="my-2">
                                <img src="{{ $signatureUrl }}" alt="Signature" style="max-height: 72px; max-width: 220px;">
                            </div>
                        @endif
                        <div class="border-bottom mx-auto mb-2" style="width: 180px;"></div>
                        <div><strong>{{ $letter->signatory_name }}</strong></div>
                        <div>{{ $letter->signatory_title }}</div>
                        <div class="small text-muted mt-1">
                            {{ $letter->usesDigitalSignature() ? 'Digital signature' : 'Manual signature (sign after printing)' }}
                        </div>
                    </div>
                </div>
            </div>

            @if($canShare)
                <div class="card shadow-none border mb-3">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0"><i class="ti ti-share me-2"></i>Share with Staff</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('official-letters.share', $letter) }}">
                            @csrf
                            <p class="text-muted small mb-3">
                                Shared staff receive an email and can view and edit this letter. It will appear in their Official Letters list as shared.
                            </p>
                            <x-staff-checkbox-select
                                :users="$shareableUsers"
                                name="shared_user_ids[]"
                                :selected="$letter->sharedUsers->pluck('id')->all()"
                                title="Share with"
                                :show-title="false"
                                :compact="true"
                                error-key="shared_user_ids"
                                hint="Select one or more active staff members."
                            />
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="ti ti-check me-1"></i> Update Sharing
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
        <div class="col-lg-4">
            <div class="card shadow-none border mb-3">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">Details</h5>
                </div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt class="small text-muted">Reference</dt>
                        <dd><code>{{ $letter->reference_number }}</code></dd>
                        <dt class="small text-muted">Department</dt>
                        <dd>{{ $letter->department_code }} — {{ $letter->departmentLabel() }}</dd>
                        <dt class="small text-muted">Letter Date</dt>
                        <dd>{{ $letter->letter_date->format('d F Y') }}</dd>
                        <dt class="small text-muted">Sequence</dt>
                        <dd>{{ str_pad((string) $letter->sequence, 3, '0', STR_PAD_LEFT) }}</dd>
                        <dt class="small text-muted">Created By</dt>
                        <dd>{{ $letter->creator->name ?? 'N/A' }}</dd>
                        <dt class="small text-muted">Created At</dt>
                        <dd>{{ $letter->created_at->format('d M Y H:i') }}</dd>
                        <dt class="small text-muted">Shared With</dt>
                        <dd>
                            @if($letter->sharedUsers->isEmpty())
                                <span class="text-muted">Nobody yet</span>
                            @else
                                {{ $letter->sharedUsers->pluck('name')->join(', ') }}
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
