<x-app-layout>

    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Edit User</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Users</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('admin.show', $user->id) }}">Profile</a></li>
                                <li class="breadcrumb-item" aria-current="page">Edit</li>
                            </ul>
                        </div>
                        <div class="col-auto d-flex align-items-center gap-2">
                         
                            <a href="{{ route('admin.index') }}" class="btn btn-sm btn-light">
                                <i class="ti ti-arrow-left me-1"></i> Back 
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <form method="POST" action="{{ route('admin.update', $user->id) }}" id="editUserForm">
                @csrf
                @method('PUT')

                <div class="card shadow-none border">
                    <div class="card-header bg-white border-bottom">
                        <div class="row align-items-center">
                            <div class="col-md-7 mb-2 mb-md-0">
                                <h5 class="mb-0"><i class="ti ti-edit me-2"></i>Update User Information</h5>
                            </div>
                            <div class="col-md-5 text-md-end">
                                <small class="text-muted">Make changes, then save user profile updates.</small>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">


                        <div class="row">
                            <!-- Full Name -->
                            <div class="col-md-6 col-lg-6 mb-3">
                                <div class="form-group">
                                    <label for="name" class="form-label">
                                        Full Name <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="ti ti-user"></i>
                                        </span>
                                        <input type="text"
                                            class="form-control @error('name') is-invalid @enderror"
                                            name="name"
                                            id="name"
                                            placeholder="Enter full name"
                                            value="{{ old('name', $user->name) }}"
                                            required>
                                    </div>
                                    @error('name')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="col-md-6 col-lg-6 mb-3">
                                <div class="form-group">
                                    <label for="email" class="form-label">
                                        Email Address <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="ti ti-mail"></i>
                                        </span>
                                        <input type="email"
                                            class="form-control @error('email') is-invalid @enderror"
                                            name="email"
                                            id="email"
                                            placeholder="user@example.com"
                                            value="{{ old('email', $user->email) }}"
                                            required>
                                    </div>
                                    @error('email')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Phone -->
                            <div class="col-md-6 col-lg-6 mb-3">
                                <div class="form-group">
                                    <label for="phone" class="form-label">
                                        Phone Number <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="ti ti-phone"></i>
                                        </span>
                                        <input type="text"
                                            class="form-control @error('phone') is-invalid @enderror"
                                            name="phone"
                                            id="phone"
                                            placeholder="07XXXXXXXX or 06XXXXXXXX"
                                            value="{{ old('phone', $user->phone) }}"
                                            maxlength="10"
                                            required>
                                    </div>
                                    @error('phone')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">Format: 07XXXXXXXX or 06XXXXXXXX</small>
                                </div>
                            </div>

                            <!-- Title -->
                            <div class="col-md-6 col-lg-6 mb-3">
                                <div class="form-group">
                                    <label for="title" class="form-label">
                                        Job Title
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="ti ti-id-badge"></i>
                                        </span>
                                        <input type="text"
                                            class="form-control @error('title') is-invalid @enderror"
                                            name="title"
                                            id="title"
                                            placeholder="e.g. STEM Instructor, Finance Officer"
                                            value="{{ old('title', $user->title) }}">
                                    </div>
                                    @error('title')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Department -->
                            <div class="col-md-6 col-lg-6 mb-3">
                                <div class="form-group">
                                    <label for="department_id" class="form-label">
                                        Department
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="ti ti-briefcase"></i>
                                        </span>
                                        <select class="form-control @error('department_id') is-invalid @enderror"
                                            name="department_id"
                                            id="department_id">
                                            <option value=""
                                                {{ old('department_id', $user->department_id) ? '' : 'selected' }}>
                                                -- No Department/Sub-Department (Global user) --
                                            </option>
                                            @foreach($topDepartments as $department)
                                                <option value="{{ $department->id }}"
                                                    {{ old('department_id', $user->department_id) == $department->id ? 'selected' : '' }}>
                                                    {{ $department->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    @error('department_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">Select parent department first, then sub-departments.</small>
                                </div>
                            </div>

                            <div class="col-md-6 col-lg-6 mb-3">
                                <div class="form-group">
                                    <label for="sub_department_ids" class="form-label">Sub-Departments</label>
                                    <select class="form-control @error('sub_department_ids') is-invalid @enderror @error('sub_department_ids.*') is-invalid @enderror"
                                        name="sub_department_ids[]"
                                        id="sub_department_ids"
                                        multiple
                                        size="6">
                                        @php
                                            $selectedSubDepartments = old('sub_department_ids', $user->subDepartments->pluck('id')->all());
                                        @endphp
                                        @foreach($topDepartments as $department)
                                            @foreach($department->children as $subDepartment)
                                                <option value="{{ $subDepartment->id }}"
                                                    data-parent-id="{{ $department->id }}"
                                                    {{ collect($selectedSubDepartments)->contains($subDepartment->id) ? 'selected' : '' }}>
                                                    {{ $subDepartment->name }}
                                                </option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                    @error('sub_department_ids')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    @error('sub_department_ids.*')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">You can select multiple sub-departments under one department.</small>
                                </div>
                            </div>

                            <div class="col-md-6 col-lg-6 mb-3">
                                <div class="form-group">
                                    <label class="form-label d-block">Department Leadership</label>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" value="1" id="is_department_head" name="is_department_head" {{ old('is_department_head', optional($user->department)->head_user_id == $user->id) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_department_head">
                                            Set this user as Head of selected department
                                        </label>
                                    </div>
                                    @error('is_department_head')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Role -->
                            <div class="col-md-6 col-lg-6 mb-3">
                                <div class="form-group">
                                    <label for="role" class="form-label">
                                        Role
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="ti ti-shield"></i>
                                        </span>
                                        <select class="form-control @error('role') is-invalid @enderror"
                                            name="role"
                                            id="role">
                                            <option value="">-- No Role --</option>
                                            @foreach($roles as $role)
                                                <option value="{{ $role->name }}" 
                                                    {{ old('role', $user->roles->first()?->name) == $role->name ? 'selected' : '' }}>
                                                    {{ ucwords(str_replace('_', ' ', $role->name)) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    @error('role')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">Current: {{ $user->roles->first()?->name ? ucwords(str_replace('_', ' ', $user->roles->first()->name)) : 'No role assigned' }}</small>
                                </div>
                            </div>

                        
                            <!-- Last Modified Info -->
                            <div class="col-12">
                                <div class="alert alert-light border" role="alert">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <small class="text-muted">
                                                <i class="ti ti-clock me-1"></i>
                                                <strong>Created:</strong> {{ $user->created_at->format('M d, Y h:i A') }}
                                            </small>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted">
                                                <i class="ti ti-refresh-cw me-1"></i>
                                                <strong>Last Updated:</strong> {{ $user->updated_at->format('M d, Y h:i A') }}
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{ route('admin.index') }}" class="btn btn-secondary">
                                <i class="ti ti-x me-1"></i> Cancel
                            </a>
                            <div>
                                @can('users management settings')
                                    <button type="submit" class="btn btn-primary" id="submitBtn">
                                        <i class="ti ti-save me-1"></i> Update User
                                    </button>
                                @else
                                    <button type="button" class="btn btn-primary permission-alert">
                                        <i class="ti ti-save me-1"></i> Update User
                                    </button>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <style>
        .spinning {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .input-group-text {
            background-color: #f8f9fa;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('editUserForm');
            const submitBtn = document.getElementById('submitBtn');
            const phoneInput = document.getElementById('phone');
            const departmentSelect = document.getElementById('department_id');
            const subDepartmentSelect = document.getElementById('sub_department_ids');

            function filterSubDepartmentsByDepartment() {
                if (!departmentSelect || !subDepartmentSelect) return;
                const selectedDepartmentId = departmentSelect.value;
                const options = Array.from(subDepartmentSelect.options);

                options.forEach(option => {
                    const shouldShow = selectedDepartmentId && option.dataset.parentId === selectedDepartmentId;
                    option.hidden = !shouldShow;
                    if (!shouldShow) option.selected = false;
                });
            }

            // Phone number validation
            phoneInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
                if (value.length > 10) {
                    value = value.substring(0, 10);
                }
                e.target.value = value;
            });

            // Form submission handler
            form.addEventListener('submit', function(e) {
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="ti ti-loader spinning me-1"></i> Updating User...';
                }
            });

            // Email validation
            const emailInput = document.getElementById('email');
            emailInput.addEventListener('blur', function() {
                const email = this.value;
                if (email) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(email)) {
                        this.classList.add('is-invalid');
                    } else {
                        this.classList.remove('is-invalid');
                    }
                }
            });

            if (departmentSelect) {
                departmentSelect.addEventListener('change', filterSubDepartmentsByDepartment);
                filterSubDepartmentsByDepartment();
            }
        });
    </script>

</x-app-layout>
