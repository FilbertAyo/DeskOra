<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Edit Work Period</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('work-periods.index') }}">Work Periods</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('work-periods.show', $period) }}">Week {{ $period->week_number }}</a></li>
                                <li class="breadcrumb-item" aria-current="page">Edit</li>
                            </ul>
                        </div>
                        <div class="d-flex gap-2">
                           
                            <a href="{{ route('work-periods.index') }}" class="btn btn-sm btn-light">
                                <i class="ti ti-arrow-left me-1"></i> Back
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-12">
            <div class="card border shadow-none">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">Week {{ $period->week_number }} of {{ $period->year }}</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('work-periods.update', $period) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="week_start_date" class="form-label">Week start (Monday) <span class="text-danger">*</span></label>
                            <input type="date" name="week_start_date" id="week_start_date" class="form-control @error('week_start_date') is-invalid @enderror"
                                   value="{{ old('week_start_date', $period->week_start_date?->format('Y-m-d')) }}" required>
                            @error('week_start_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Week end is recalculated as six days after the start.</small>
                        </div>

                        <div class="mb-3">
                            <label for="plan_deadline" class="form-label">Plan deadline <span class="text-danger">*</span></label>
                            <input type="date" name="plan_deadline" id="plan_deadline" class="form-control @error('plan_deadline') is-invalid @enderror"
                                   value="{{ old('plan_deadline', $period->plan_deadline?->format('Y-m-d')) }}" readonly required>
                            @error('plan_deadline')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Auto-set to Saturday 23:59 before the selected week.</small>
                        </div>

                        <div class="mb-3">
                            <label for="report_deadline" class="form-label">Report deadline <span class="text-danger">*</span></label>
                            <input type="date" name="report_deadline" id="report_deadline" class="form-control @error('report_deadline') is-invalid @enderror"
                                   value="{{ old('report_deadline', $period->report_deadline?->format('Y-m-d')) }}" readonly required>
                            @error('report_deadline')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Auto-set to Saturday 23:59 of the selected week.</small>
                        </div>

                        <div class="mb-4">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-control @error('status') is-invalid @enderror" required>
                                <option value="open" @selected(old('status', $period->status) === 'open')>Open</option>
                                <option value="closed" @selected(old('status', $period->status) === 'closed')>Closed</option>
                                <option value="archived" @selected(old('status', $period->status) === 'archived')>Archived</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-device-floppy me-1"></i> Save changes
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
