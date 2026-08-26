<x-app-layout>

    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Edit Role</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('roles.index') }}">Roles</a></li>
                                <li class="breadcrumb-item" aria-current="page">Edit</li>
                            </ul>
                        </div>
                        <a href="{{ route('roles.index') }}" class="btn btn-sm btn-light">
                            <i class="ti ti-arrow-left me-1"></i> Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <form method="POST" action="{{ route('roles.update', $role) }}">
                @csrf
                @method('PUT')
                <div class="card shadow-none border">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0"><i class="ti ti-shield me-2"></i>Role Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Role Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name"
                                class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $role->name) }}" required
                                @if($isSystemManaged ?? false) readonly @endif>
                            @error('name')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        @if($isSystemManaged ?? false)
                            <div class="alert alert-info">
                                This role is system-managed for the shared demo account. Permissions stay in sync with the full catalog and cannot be edited here.
                            </div>
                        @endif

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">Permissions</h6>
                            @unless($isSystemManaged ?? false)
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="togglePermissions(true)">Select All</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="togglePermissions(false)">Clear</button>
                            </div>
                            @endunless
                        </div>

                        <div class="row g-2">
                            @foreach($permissions as $permission)
                                @php
                                    $isChecked = in_array($permission->name, old('permissions', $selectedPermissions), true);
                                @endphp
                                <div class="col-md-4 col-lg-3">
                                    <label class="list-group-item d-flex align-items-center gap-2 h-100">
                                        <input type="checkbox"
                                            class="form-check-input permission-checkbox"
                                            name="permissions[]"
                                            value="{{ $permission->name }}"
                                            {{ $isChecked ? 'checked' : '' }}
                                            @if($isSystemManaged ?? false) disabled @endif>
                                        <span>{{ ucwords(str_replace('_', ' ', $permission->name)) }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="card-footer bg-light d-flex justify-content-between">
                        <a href="{{ route('roles.index') }}" class="btn btn-secondary">
                            <i class="ti ti-x me-1"></i> {{ ($isSystemManaged ?? false) ? 'Back' : 'Cancel' }}
                        </a>
                        @unless($isSystemManaged ?? false)
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-check me-1"></i> Update Role
                        </button>
                        @endunless
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePermissions(checked) {
            document.querySelectorAll('.permission-checkbox').forEach((checkbox) => {
                checkbox.checked = checked;
            });
        }
    </script>

</x-app-layout>
