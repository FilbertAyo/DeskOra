<x-app-layout>


    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Users Management</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="javascript: void(0)">Settings</a></li>
                                <li class="breadcrumb-item" aria-current="page">Users</li>
                            </ul>
                        </div>

                        <div class="col-auto d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-light" onclick="reloadPage()"
                                title="Refresh">
                                <i class="ti ti-refresh"></i>
                            </button>
                            @can('users management settings')
                                <a href="{{ route('admin.create') }}" class="btn btn-primary btn-sm">
                                    <i class="ti ti-circle-plus me-1"></i> New User
                                </a>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
       
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom">
                    <div class="row align-items-center">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <h5 class="mb-0"><i class="ti ti-list me-2"></i>Users List</h5>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="ti ti-search"></i>
                                </span>
                                <input type="text" class="form-control border-start-0" id="searchInput"
                                    placeholder="Search users by name, email, phone...">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                 

                    <div class="table-responsive">
                        <table class="table table-hover table-bordered table-striped" id="usersTable">
                            <thead class="thead-light">
                                <tr>
                                    <th class="text-center" style="width: 50px;">No.</th>
                                    <th>Name</th>
                                    <th>Title</th>
                                    <th>Contacts</th>
                                    <th>Department</th>
                                    <th>Sub-Departments</th>
                                    <th>Roles</th>
                                    <th class="text-center" style="width: 100px;">Status</th>
                                    <th class="text-center" style="width: 150px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($user as $index => $u)
                                    <tr>
                                        <td class="text-center">{{ $loop->iteration }}</td>
                                        <td style="min-width: 150px;">
                                        {{ $u->name }}
                                        </td>
                                        <td>
                                            <small>
                                            {{ $u->title ?: 'N/A' }}
                                            </small>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <div>{{ $u->email }}</div>
                                                <div class="text-muted">{{ $u->phone }}</div>
                                            </div>
                                        </td>

                                        <td>
                                            <span class="badge bg-light-light border border-light text-dark">
                                                {{ $u->department->name ?? 'N/A' }}
                                            </span>
                                            @if(optional($u->department)->head_user_id === $u->id)
                                                <span class="badge bg-light-warning border border-warning text-dark ms-1">HOD</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($u->subDepartments->isNotEmpty())
                                                <span class="small">{{ $u->subDepartments->pluck('name')->implode(', ') }}</span>
                                            @else
                                                <span class="text-muted small">All / Not assigned</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($u->roles->count() > 0)
                                                @foreach($u->roles as $role)
                                                    <span class="badge bg-light-primary border border-primary mb-1 me-1 small">{{ ucwords(str_replace('_', ' ', $role->name)) }}
                                                    </span>
                                                @endforeach
                                            @else
                                                <span class="text-muted small">
                                                    <i class="ti ti-shield-off me-1"></i>
                                                    No role
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="badge {{ $u->status === 'active' ? 'bg-light-success border border-success' : 'bg-light-danger border border-danger' }}">
                                                <i class="ti {{ $u->status === 'active' ? 'ti-circle-check' : 'ti-circle-x' }} me-1"></i>
                                                {{ ucfirst($u->status) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <x-table-action-dropdown>
    <a class="dropdown-item" href="{{ route('admin.show', $u->id) }}">
    <i class="ti ti-eye me-2"></i> View
    </a>
    @can('users management settings')
    <a class="dropdown-item" href="{{ route('admin.edit', $u->id) }}">
    <i class="ti ti-edit me-2"></i> Edit
    </a>
    @endcan
</x-table-action-dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center py-4">
                                            <i class="ti ti-users fs-1 text-muted"></i>
                                            <p class="text-muted mb-0">No users found</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
</x-app-layout>
