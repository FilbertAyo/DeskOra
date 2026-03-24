<x-app-layout>

    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">User Profile</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Users</a></li>
                                <li class="breadcrumb-item" aria-current="page">Profile</li>
                            </ul>
                        </div>
                        <div class="col-auto d-flex align-items-center gap-2">
                            @can('users management settings')
                                <a href="{{ route('admin.edit', Hashids::encode($user->id)) }}" class="btn btn-sm btn-primary">
                                    <i class="ti ti-edit me-1"></i> Edit User
                                </a>
                            @endcan
                            <a href="{{ route('admin.index') }}" class="btn btn-sm btn-light">
                                <i class="ti ti-arrow-left me-1"></i> Back to Users
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-content" id="myTabContent">
        <!-- Profile Tab -->
        <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
            <div class="row">
                <!-- Profile Card -->
                <div class="col-lg-4 mb-4">
                    <div class="card shadow-none border">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                @if ($user->file)
                                    <img src="{{ asset($user->file) }}" alt="{{ $user->name }}"
                                        class="rounded-circle shadow"
                                        style="width: 150px; height: 150px; object-fit: cover;">
                                @else
                                    <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center shadow"
                                        style="width: 150px; height: 150px; font-size: 60px; font-weight: 600;">
                                        {{ strtoupper(substr($user->name, 0, 2)) }}
                                    </div>
                                @endif
                            </div>
                            <h4 class="mb-1">{{ $user->name }}</h4>
                            <p class="text-muted mb-2">{{ $user->department?->name ?? 'No Department' }}</p>
                            
                            @if($user->roles->count() > 0)
                                <div class="mb-2">
                                    @foreach($user->roles as $role)
                                        <span class="badge bg-primary rounded-pill mb-1">
                                            <i class="ti ti-shield me-1"></i>
                                            {{ ucwords(str_replace('_', ' ', $role->name)) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            <div class="mb-3">
                                <span
                                    class="badge bg-light-{{ $user->status === 'active' ? 'success' : 'danger' }} px-3 py-2">
                                    <i
                                        class="ti ti-{{ $user->status === 'active' ? 'circle-check' : 'circle-x' }} me-1"></i>
                                    {{ ucfirst($user->status) }}
                                </span>
                            </div>

                            <div class="d-grid gap-2">
                                @if ($user->status == 'active')
                                    @can('users management settings')
                                        @if ($user->email !== 'marscommunication.team@gmail.com')
                                            <form action="{{ route('admin.destroy', $user->id) }}" method="POST"
                                                onsubmit="return confirm('Are you sure you want to deactivate this user?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger w-100">
                                                    <i class="ti ti-user-x me-1"></i> Deactivate User
                                                </button>
                                            </form>
                                        @else
                                            <button class="btn btn-secondary w-100" disabled
                                                title="This account cannot be deactivated">
                                                <i class="ti ti-lock me-1"></i> Protected Account
                                            </button>
                                        @endif
                                    @endcan
                                @else
                                    @can('users management settings')
                                        <form action="{{ route('admin.activate', $user->id) }}" method="POST"
                                            onsubmit="return confirm('Are you sure you want to activate this user?');">
                                            @csrf
                                            <button type="submit" class="btn btn-success w-100">
                                                <i class="ti ti-user-check me-1"></i> Activate User
                                            </button>
                                        </form>
                                    @endcan
                                @endif

                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card shadow-none border mt-3">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="ti ti-activity me-2"></i>Quick Actions</h6>
                        </div>
                        <div class="card-body p-2">
                            <div class="list-group list-group-flush">
                                <a class="list-group-item list-group-item-action" href="mailto:{{ $user->email }}">
                                    <i class="ti ti-mail me-2 text-info"></i> Send Email
                                </a>
                                <a class="list-group-item list-group-item-action"
                                    href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $user->phone) }}"
                                    target="_blank">
                                    <i class="ti ti-message-circle me-2 text-success"></i> WhatsApp
                                </a>
                                <a class="list-group-item list-group-item-action" href="tel:{{ $user->phone }}">
                                    <i class="ti ti-phone me-2 text-primary"></i> Call User
                                </a>
                                @can('users management settings')
                                    <button type="button" class="list-group-item list-group-item-action"
                                        data-bs-toggle="offcanvas" data-bs-target="#addRole">
                                        <i class="ti ti-shield me-2 text-warning"></i> Manage Permissions
                                    </button>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Details & Statistics -->
                <div class="col-lg-8">
                    <!-- User Information Card -->
                    <div class="card shadow-none border mb-4">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0"><i class="ti ti-info-circle me-2"></i>User Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small mb-1">Full Name</label>
                                    <div class="d-flex align-items-center">
                                        <i class="ti ti-user me-2 text-primary"></i>
                                        <strong>{{ $user->name }}</strong>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small mb-1">Email Address</label>
                                    <div class="d-flex align-items-center">
                                        <i class="ti ti-mail me-2 text-primary"></i>
                                        <strong>{{ $user->email }}</strong>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small mb-1">Phone Number</label>
                                    <div class="d-flex align-items-center">
                                        <i class="ti ti-phone me-2 text-primary"></i>
                                        <strong>{{ $user->phone }}</strong>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small mb-1">Branch</label>
                                    <div class="d-flex align-items-center">
                                        <i class="ti ti-git-branch me-2 text-primary"></i>
                                        <strong>{{ $user->branch?->name ?? 'No Branch' }}</strong>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small mb-1">Department</label>
                                    <div class="d-flex align-items-center">
                                        <i class="ti ti-briefcase me-2 text-primary"></i>
                                        <strong>{{ $user->department?->name ?? 'No Department' }}</strong>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small mb-1">Account Status</label>
                                    <div class="d-flex align-items-center">
                                        <i
                                            class="ti ti-{{ $user->status === 'active' ? 'circle-check' : 'circle-x' }} me-2 text-{{ $user->status === 'active' ? 'success' : 'danger' }}"></i>
                                        <strong class="text-{{ $user->status === 'active' ? 'success' : 'danger' }}">
                                            {{ ucfirst($user->status) }}
                                        </strong>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small mb-1">Role(s)</label>
                                    <div class="d-flex align-items-center flex-wrap">
                                        @if($user->roles->count() > 0)
                                            @foreach($user->roles as $role)
                                                <span class="badge bg-primary rounded-pill me-2 mb-1">
                                                    <i class="ti ti-shield me-1"></i>
                                                    {{ ucwords(str_replace('_', ' ', $role->name)) }}
                                                </span>
                                            @endforeach
                                        @else
                                            <span class="text-muted">
                                                <i class="ti ti-shield-off me-2"></i>
                                                No role assigned
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Account Timeline -->
                    <div class="card shadow-none border mb-4">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0"><i class="ti ti-clock me-2"></i>Account Timeline</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="flex-shrink-0">
                                            <div
                                                class="avatar avatar-sm bg-success text-white rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="ti ti-user-plus"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <label class="text-muted small mb-1">Account Created</label>
                                            <div><strong>{{ $user->created_at->format('M d, Y') }}</strong></div>
                                            <small class="text-muted">{{ $user->created_at->diffForHumans() }}</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="flex-shrink-0">
                                            <div
                                                class="avatar avatar-sm bg-info text-white rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="ti ti-refresh"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <label class="text-muted small mb-1">Last Updated</label>
                                            <div><strong>{{ $user->updated_at->format('M d, Y') }}</strong></div>
                                            <small class="text-muted">{{ $user->updated_at->diffForHumans() }}</small>
                                        </div>
                                    </div>
                                </div>
                                @if ($user->email_verified_at)
                                    <div class="col-md-6 mb-3">
                                        <div class="d-flex align-items-start">
                                            <div class="flex-shrink-0">
                                                <div
                                                    class="avatar avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center">
                                                    <i class="ti ti-circle-check"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <label class="text-muted small mb-1">Email Verified</label>
                                                <div><strong>{{ $user->email_verified_at->format('M d, Y') }}</strong>
                                                </div>
                                                <small
                                                    class="text-muted">{{ $user->email_verified_at->diffForHumans() }}</small>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Current Permissions Summary -->
                    <div class="card shadow-none border">
                        <div
                            class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="ti ti-shield me-2"></i>Current Permissions</h5>
                            <span class="badge bg-primary">{{ $user->permissions->count() }}
                                Active</span>
                        </div>
                        <div class="card-body">
                            @if ($user->permissions->count() > 0)
                                <div class="row">
                                    @foreach ($user->permissions as $permission)
                                        <div class="col-md-6 mb-2">
                                            <span class="badge bg-light-secondary border px-3 py-2">
                                                {{ ucwords(str_replace('_', ' ', $permission->name)) }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-4">
                                    <i class="ti ti-shield text-muted" style="font-size: 48px;"></i>
                                    <p class="text-muted mb-0 mt-2">No permissions assigned yet</p>
                                    @can('users management settings')
                                        <button type="button" class="btn btn-sm btn-primary mt-3" data-bs-toggle="offcanvas"
                                            data-bs-target="#addRole">
                                            <i class="ti ti-plus me-1"></i> Assign Permissions
                                        </button>
                                    @endcan
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Permissions Tab -->
        <div class="tab-pane fade" id="permissions" role="tabpanel" aria-labelledby="permissions-tab">
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-none border">
                        <div
                            class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1"><i class="ti ti-shield me-2"></i>Manage User Permissions</h5>
                                <small class="text-muted">Assign or revoke permissions for this user</small>
                            </div>
                            @can('users management settings')
                                <button type="button" class="btn btn-primary" data-bs-toggle="offcanvas"
                                    data-bs-target="#addRole">
                                    <i class="ti ti-edit me-1"></i> Edit Permissions
                                </button>
                            @endcan
                        </div>
                        <div class="card-body">
                            <div class="row">
                                @foreach ($permissions as $permission)
                                    <div class="col-md-4 col-lg-3 mb-3">
                                        <div
                                            class="card h-100 {{ $user->hasPermissionTo($permission->name) ? 'border-success' : 'border-light' }}">
                                            <div class="card-body p-3">
                                                <div class="d-flex align-items-start">
                                                    <div class="flex-shrink-0">
                                                        @if ($user->hasPermissionTo($permission->name))
                                                            <i class="ti ti-circle-check text-success fs-4"></i>
                                                        @else
                                                            <i class="ti ti-circle text-muted fs-4"></i>
                                                        @endif
                                                    </div>
                                                    <div class="flex-grow-1 ms-2">
                                                        <h6 class="mb-0">
                                                            {{ ucwords(str_replace('_', ' ', $permission->name)) }}
                                                        </h6>
                                                        <small class="text-muted">
                                                            @if ($user->hasPermissionTo($permission->name))
                                                                <span class="text-success">Granted</span>
                                                            @else
                                                                <span class="text-muted">Not Granted</span>
                                                            @endif
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Permissions Offcanvas -->
    <div class="offcanvas offcanvas-top permissions-offcanvas" tabindex="-1" id="addRole" aria-labelledby="addRoleLabel"
        aria-hidden="true">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="addRoleLabel">
                <i class="ti ti-shield me-2"></i>Assign Permissions to {{ $user->name }}
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <form action="{{ route('assign.permissions', $user->id) }}" method="POST" id="permissionsForm">
                @csrf

                <div class="alert alert-info d-flex align-items-start mb-3">
                    <i class="ti ti-info-circle me-2 mt-1"></i>
                    <div>
                        <strong>Note:</strong> Select the permissions you want to grant to this user.
                        Unchecked permissions will be revoked.
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h6 class="mb-0">Available Permissions</h6>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary rounded-pill">{{ $permissions->count() }}</span>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll()">
                                Select All
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAll()">
                                Deselect All
                            </button>
                        </div>
                    </div>
                    <div class="row g-2">
                        @foreach ($permissions as $permission)
                            <div class="col-md-4 col-lg-3">
                                <label class="list-group-item permission-item d-flex align-items-center gap-2 h-100">
                                    <input class="form-check-input permission-checkbox"
                                        type="checkbox"
                                        name="permissions[]"
                                        value="{{ $permission->name }}"
                                        id="perm_{{ $permission->id }}"
                                        {{ $user->hasPermissionTo($permission->name) ? 'checked' : '' }}>
                                    <span class="text-truncate">
                                        {{ ucwords(str_replace('_', ' ', $permission->name)) }}
                                    </span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-3 border-top sticky-actions">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="offcanvas">
                        <i class="ti ti-x me-1"></i> Cancel
                    </button>
                    @can('users management settings')
                        <button type="submit" class="btn btn-primary" id="assignBtn">
                            <i class="ti ti-check me-1"></i> Save Permissions
                        </button>
                    @else
                        <button type="button" class="btn btn-primary permission-alert">
                            <i class="ti ti-check me-1"></i> Save Permissions
                        </button>
                    @endcan
                </div>
            </form>
        </div>
    </div>

    <style>
        .avatar {
            width: 40px;
            height: 40px;
        }

        .avatar-sm {
            width: 32px;
            height: 32px;
            font-size: 14px;
        }

        .form-check:hover {
            background-color: #f8f9fa !important;
        }

        .permissions-offcanvas {
            --bs-offcanvas-height: min(90vh, 760px);
        }

        .permission-item {
            min-height: 72px;
            transition: all 0.2s ease;
        }

        .permission-item:hover {
            border-color: #0d6efd !important;
            box-shadow: 0 0 0 0.08rem rgba(13, 110, 253, 0.15);
        }

        .sticky-actions {
            position: sticky;
            bottom: 0;
            background: #fff;
            z-index: 2;
        }
    </style>

    <script>
        function selectAll() {
            document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
                checkbox.checked = true;
            });
        }

        function deselectAll() {
            document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
        }

        // Form submission handler
        const permissionsForm = document.getElementById('permissionsForm');
        const assignBtn = document.getElementById('assignBtn');

        if (permissionsForm && assignBtn) {
            permissionsForm.addEventListener('submit', function() {
                assignBtn.disabled = true;
                assignBtn.innerHTML = '<i class="ti ti-loader-2 spinning me-1"></i> Saving...';
            });
        }
    </script>

</x-app-layout>
