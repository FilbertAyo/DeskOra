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
                                <a href="{{ route('admin.edit', $user->id) }}" class="btn btn-sm btn-primary">
                                    <i class="ti ti-edit me-1"></i> Edit User
                                </a>
                            @endcan
                            <a href="{{ route('admin.index') }}" class="btn btn-sm btn-light">
                                <i class="ti ti-arrow-left me-1"></i> Back
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
                            
                        

                            <div class="mb-3">
                                <span
                                    class="badge bg-light-{{ $user->status === 'active' ? 'success' : 'danger' }}">
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
                                        <strong>{{ $user->name }}</strong>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small mb-1">Email Address</label>
                                    <div class="d-flex align-items-center">
                                        <strong>{{ $user->email }}</strong>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small mb-1">Job Title</label>
                                    <div class="d-flex align-items-center">
                                        <strong>{{ $user->title ?: 'N/A' }}</strong>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small mb-1">Phone Number</label>
                                    <div class="d-flex align-items-center">
                                        <strong>{{ $user->phone }}</strong>
                                    </div>
                                </div>
                            
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small mb-1">Department</label>
                                    <div class="d-flex align-items-center">
                                        <strong>{{ $user->department?->name ?? 'No Department' }}</strong>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small mb-1">Sub-Departments</label>
                                    <div class="d-flex align-items-center flex-wrap gap-1">
                                        @if ($user->subDepartments->isNotEmpty())
                                            @foreach ($user->subDepartments as $subDepartment)
                                                <span class="badge bg-light-secondary border">{{ $subDepartment->name }}</span>
                                            @endforeach
                                        @else
                                            <span class="text-muted">
                                                <i class="ti ti-building-community me-1"></i>None assigned
                                            </span>
                                        @endif
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

      
                    <!-- Current Permissions Summary -->
                    @php
                        $effectivePermissions = $user->getAllPermissions();
                    @endphp
                    <div class="card shadow-none border">
                        <div
                            class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="ti ti-shield me-2"></i>Current Permissions</h5>
                            <span class="badge bg-primary">{{ $effectivePermissions->count() }}
                                Active</span>
                        </div>
                        <div class="card-body">
                            @if ($effectivePermissions->count() > 0)
                                <div class="row">
                                    @foreach ($effectivePermissions as $permission)
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
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
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

    </style>

</x-app-layout>
