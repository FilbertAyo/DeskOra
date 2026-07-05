<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Meeting Minutes</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('meeting-minutes.index') }}">Meeting Minutes</a></li>
                                <li class="breadcrumb-item" aria-current="page">
                                    @if($meetingMinute->period)
                                        Week {{ $meetingMinute->period->week_number }} of {{ $meetingMinute->period->year }}
                                    @else
                                        View
                                    @endif
                                </li>
                            </ul>
                        </div>

                        <div class="col-auto d-flex align-items-center gap-2">
                            <a href="{{ route('meeting-minutes.index') }}" class="btn btn-sm btn-light">
                                <i class="ti ti-arrow-left me-1"></i> Back to Minutes
                            </a>
                            <a href="{{ route('meeting-minutes.download', $meetingMinute) }}" class="btn btn-sm btn-primary">
                                <i class="ti ti-download me-1"></i> Download
                            </a>
                            @can('manage minutes')
                                <a href="{{ route('meeting-minutes.edit', $meetingMinute) }}" class="btn btn-sm btn-warning">
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
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h5 class="mb-1">
                                <i class="ti ti-file-text me-2 text-primary"></i>
                                @if($meetingMinute->period)
                                    Week {{ $meetingMinute->period->week_number }} of {{ $meetingMinute->period->year }}
                                @else
                                    Meeting Minutes
                                @endif
                            </h5>
                            @if($meetingMinute->period)
                                <small class="text-muted">{{ $meetingMinute->period->date_range }}</small>
                            @endif
                        </div>
                        <div class="text-md-end">
                            <small class="text-muted d-block">{{ $meetingMinute->stored_name }}</small>
                            <small class="text-muted">
                                Uploaded by {{ $meetingMinute->uploader->name ?? 'N/A' }}
                                on {{ $meetingMinute->updated_at->format('M d, Y H:i') }}
                            </small>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div style="min-height: 800px;">
                        <iframe
                            src="{{ route('meeting-minutes.view', $meetingMinute) }}"
                            style="width: 100%; height: 800px; border: none;"
                            title="Meeting minutes PDF">
                            <p>Your browser does not support PDFs.
                                <a href="{{ route('meeting-minutes.download', $meetingMinute) }}">Download the PDF</a> instead.
                            </p>
                        </iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
