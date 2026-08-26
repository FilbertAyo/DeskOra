<x-app-layout>

    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Role Management</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="javascript:void(0)">Settings</a></li>
                                <li class="breadcrumb-item" aria-current="page">Roles</li>
                            </ul>
                        </div>
                        <div class="col-auto d-flex align-items-center gap-2">
                            <a href="{{ route('roles.create') }}" class="btn btn-primary btn-sm">
                                <i class="ti ti-circle-plus me-1"></i> New Role
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-none border">
              
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 60px;">No.</th>
                                    <th>Role Name</th>
                                    <th class="text-center">Permissions</th>
                                    <th class="text-center">Users</th>
                                    <th class="text-center" style="width: 160px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($roles as $role)
                                    <tr>
                                        <td class="text-center">{{ $loop->iteration }}</td>
                                        <td>
                                            <span class="badge bg-light-primary border border-primary">
                                                {{ ucwords(str_replace('_', ' ', $role->name)) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light-info border border-info">{{ $role->permissions_count }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light-secondary border border-secondary">{{ $role->users_count }}</span>
                                        </td>
                                        <td class="text-center">
                                            <x-table-action-dropdown>
    <a class="dropdown-item" href="{{ route('roles.edit', $role) }}">
    <i class="ti ti-edit me-2"></i> {{ $role->name === config('demo.role') ? 'View' : 'Edit' }}
    </a>
    @if($role->name !== config('demo.role'))
    <form action="{{ route('roles.destroy', $role) }}" method="POST"
    onsubmit="return confirm('Delete this role?');">
    @csrf
    @method('DELETE')
    <button type="submit" class="dropdown-item text-danger">
    <i class="ti ti-trash me-2"></i> Delete
    </button>
    </form>
    @endif
</x-table-action-dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <i class="ti ti-shield-off fs-2 text-muted"></i>
                                            <p class="text-muted mb-0 mt-2">No roles found.</p>
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
