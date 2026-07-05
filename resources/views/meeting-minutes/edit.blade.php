<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Edit Meeting Minutes</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('meeting-minutes.index') }}">Meeting Minutes</a></li>
                                <li class="breadcrumb-item" aria-current="page">Edit</li>
                            </ul>
                        </div>

                        <div class="col-auto d-flex align-items-center gap-2">
                            <a href="{{ route('meeting-minutes.show', $meetingMinute) }}" class="btn btn-sm btn-light">
                                <i class="ti ti-arrow-left me-1"></i> Back to Minutes
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="ti ti-alert-circle me-2"></i>
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-md-12">
            <form method="POST" action="{{ route('meeting-minutes.update', $meetingMinute) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="card shadow-none border">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">
                            <i class="ti ti-edit me-2"></i>Replace Minutes PDF
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Work Period</label>
                            <p class="mb-0">
                                @if($meetingMinute->period)
                                    <strong>Week {{ $meetingMinute->period->week_number }} of {{ $meetingMinute->period->year }}</strong><br>
                                    <small class="text-muted">{{ $meetingMinute->period->date_range }}</small>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </p>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Current File</label>
                            <p class="mb-0">
                                <i class="ti ti-file-type-pdf me-1 text-danger"></i>
                                {{ $meetingMinute->stored_name }}
                            </p>
                            <small class="text-muted">
                                Uploaded by {{ $meetingMinute->uploader->name ?? 'N/A' }}
                                on {{ $meetingMinute->updated_at->format('M d, Y H:i') }}
                            </small>
                        </div>

                        <div class="mb-3">
                            <label for="file" class="form-label">
                                New Minutes PDF <span class="text-danger">*</span>
                            </label>
                            <input type="file"
                                   class="form-control @error('file') is-invalid @enderror"
                                   id="file"
                                   name="file"
                                   accept="application/pdf,.pdf"
                                   required>
                            <small class="text-muted">Uploading a new file will replace the existing minutes. PDF only, maximum 10 MB.</small>
                            @error('file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-upload me-1"></i> Replace Minutes
                        </button>
                        <a href="{{ route('meeting-minutes.show', $meetingMinute) }}" class="btn btn-light">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
