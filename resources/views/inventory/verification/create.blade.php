<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">New Verification Session</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('inventory.verification.index') }}">Verification</a></li>
                                <li class="breadcrumb-item" aria-current="page">Create</li>
                            </ul>
                        </div>
                        <a href="{{ route('inventory.verification.index') }}" class="btn btn-sm btn-light">Back</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-none border">
                <div class="card-body">
                    <p class="text-muted">Optionally limit the session to a location or department. Disposed assets are excluded.</p>
                    <form method="POST" action="{{ route('inventory.verification.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Session name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required value="{{ old('name') }}" placeholder="e.g. Q3 Dar office count">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                @include('inventory.partials.location-room-picker', [
                                    'fieldName' => 'location_id',
                                    'selectedLocationId' => old('location_id'),
                                    'emptyLabel' => 'All locations',
                                ])
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department</label>
                                <select name="department_id" class="form-control">
                                    <option value="">All departments</option>
                                    @foreach($departments as $department)
                                        <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>{{ $department->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" rows="3" class="form-control">{{ old('notes') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" data-busy-label="Starting…">Start session</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @include('inventory.partials.picker-scripts')
</x-app-layout>
