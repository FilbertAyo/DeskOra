<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Assign Asset</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.assets.index') }}">Assets</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.assets.show', $asset) }}">{{ $asset->asset_code }}</a></li>
                                <li class="breadcrumb-item" aria-current="page">Assign</li>
                            </ul>
                        </div>
                        <a href="{{ route('inventory.assets.show', $asset) }}" class="btn btn-sm btn-light">
                            <i class="ti ti-arrow-left me-1"></i> Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-none border mb-3">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0">{{ $asset->name }} <span class="text-muted fw-normal">{{ $asset->asset_code }}</span></h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-4">
                        Current responsible: {{ $asset->responsibleUser?->name ?? '—' }}
                        · Department: {{ $asset->department?->name ?? '—' }}
                        · Status: {{ $statusOptions[$asset->status] ?? $asset->status }}
                    </p>

                    <form method="POST" action="{{ route('inventory.assets.assign', $asset) }}">
                        @csrf
                        <input type="hidden" name="return_url" value="{{ old('return_url', url()->previous()) }}">
                        <div class="mb-3">
                            <label class="form-label">Staff Member</label>
                            <select name="assigned_to_user_id" class="form-control">
                                <option value="">-- Select staff --</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" @selected(old('assigned_to_user_id', $asset->responsible_user_id) == $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <select name="assigned_to_department_id" class="form-control">
                                <option value="">-- Select department --</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}" @selected(old('assigned_to_department_id', $asset->department_id) == $department->id)>{{ $department->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Assign to a staff member, a department, or both.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" rows="3" class="form-control" placeholder="Optional assignment notes">{{ old('notes') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" data-busy-label="Saving…">Save Assignment</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
