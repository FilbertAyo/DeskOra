<x-app-layout>

    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Department Details</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('departments') }}">Departments</a></li>
                                <li class="breadcrumb-item" aria-current="page">{{ $department->name }}</li>
                            </ul>
                        </div>
                        <div class="d-flex gap-2">
                            @can('update other settings')
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addSubDepartmentModal">
                                    <i class="ti ti-circle-plus me-1"></i> Add Sub-Department
                                </button>
                            @endcan
                            <a href="{{ route('departments') }}" class="btn btn-sm btn-light">
                                <i class="ti ti-arrow-left me-1"></i> Back
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card border shadow-none">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="ti ti-building me-2"></i>Summary</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong>Name:</strong> {{ $department->name }}</p>
                    <p class="mb-2"><strong>Parent:</strong> {{ $department->parent?->name ?? 'Top-Level' }}</p>
                    <p class="mb-2"><strong>Head:</strong> {{ $department->head?->name ?? 'Not assigned' }}</p>
                    <p class="mb-2"><strong>Users:</strong> {{ $department->users_count }}</p>
                    <p class="mb-2"><strong>Sub-Departments:</strong> {{ $department->children_count }}</p>
                    @if ($department->parent_id)
                        <p class="mb-0"><strong>Year plan section #:</strong> {{ $department->plan_section_number ?? '—' }}</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card border shadow-none">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="ti ti-git-branch me-2"></i>Sub-Departments</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th class="text-center">Code</th>
                                    <th class="text-center">Users</th>
                                    <th class="text-center">Children</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($department->children as $child)
                                    <tr>
                                        <td>{{ $child->name }}</td>
                                        <td class="text-center text-muted">{{ $child->plan_section_number ?? '—' }}</td>
                                        <td class="text-center">{{ $child->users_count }}</td>
                                        <td class="text-center">{{ $child->children_count }}</td>
                                        <td class="text-center">
                                            <span class="badge {{ $child->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                                {{ ucfirst($child->status) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="{{ route('department.show', $child->id) }}" class="btn btn-info text-white" title="View">
                                                    <i class="ti ti-eye"></i>
                                                </a>
                                                @can('update other settings')
                                                    <button type="button" class="btn btn-primary edit-sub-dept-btn" title="Edit"
                                                        data-bs-toggle="modal" data-bs-target="#editSubDepartmentModal"
                                                        data-hashid="{{ $child->id }}"
                                                        data-name="{{ e($child->name) }}"
                                                        data-status="{{ $child->status }}"
                                                        data-head-user-id="{{ $child->head_user_id ?? '' }}"
                                                        data-plan-section-number="{{ $child->plan_section_number ?? '' }}"
                                                        data-parent-id="{{ $child->parent_id }}">
                                                        <i class="ti ti-edit"></i>
                                                    </button>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No sub-departments available.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($department->parent_id)
        @can('update other settings')
            <div class="row">
                <div class="col-md-12">
                    <div class="card border shadow-none">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0"><i class="ti ti-chart-line me-2"></i>Year plan coding</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">Section number is the prefix for activity codes (e.g. <strong>4</strong> → 4.1, 4.2) on the Year Plan.</p>
                            <form method="POST" action="{{ route('department.update', $department->id) }}" class="row g-2 align-items-end">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="name" value="{{ $department->name }}">
                                <input type="hidden" name="status" value="{{ $department->status }}">
                                <input type="hidden" name="parent_id" value="{{ $department->parent_id }}">
                                <input type="hidden" name="head_user_id" value="{{ $department->head_user_id ?? '' }}">
                                <div class="col-auto">
                                    <label class="form-label mb-0">Section #</label>
                                    <input type="number" name="plan_section_number" class="form-control" style="width: 120px;" min="1" max="999"
                                        value="{{ old('plan_section_number', $department->plan_section_number) }}" placeholder="e.g. 4">
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-primary">Save</button>
                                </div>
                            </form>
                            @error('plan_section_number')
                                <div class="text-danger small mt-2">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        @endcan
    @endif

    <div class="row">
        <div class="col-md-12">
            <div class="card border shadow-none">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="ti ti-users me-2"></i>Users in This Department</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($users as $user)
                                    <tr>
                                        <td>{{ $user->name }}</td>
                                        <td>{{ $user->email }}</td>
                                        <td>{{ $user->phone }}</td>
                                        <td>
                                            <span class="badge {{ $user->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                                {{ ucfirst($user->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No users assigned to this department.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addSubDepartmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ti ti-circle-plus me-2"></i>Add Sub-Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('department.store') }}">
                    @csrf
                    <input type="hidden" name="parent_id" value="{{ $department->id }}">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Department Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-control" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="mt-3 mb-0">
                            <label class="form-label">Head of Department</label>
                            <select name="head_user_id" class="form-control">
                                <option value="">-- Select Head (Optional) --</option>
                                @foreach($departmentHeads as $head)
                                    <option value="{{ $head->id }}">{{ $head->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mt-3 mb-0">
                            <label class="form-label">Year plan section #</label>
                            <input type="number" name="plan_section_number" class="form-control" min="1" max="999" placeholder="e.g. 4 for codes 4.1, 4.2">
                            <p class="text-muted small mb-0 mt-1">Optional; used on the Year Plan panel.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Sub-Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @can('update other settings')
        <div class="modal fade" id="editSubDepartmentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="ti ti-edit me-2"></i>Edit sub-department</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" id="editSubDepartmentForm" action="">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="parent_id" id="editSubDeptParentId" value="">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="editSubDeptName" class="form-control @error('name') is-invalid @enderror" required>
                                @error('name')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select name="status" id="editSubDeptStatus" class="form-control @error('status') is-invalid @enderror" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Head of department</label>
                                <select name="head_user_id" id="editSubDeptHeadUserId" class="form-control @error('head_user_id') is-invalid @enderror">
                                    <option value="">— None —</option>
                                    @foreach ($departmentHeads as $head)
                                        <option value="{{ $head->id }}">{{ $head->name }}</option>
                                    @endforeach
                                </select>
                                @error('head_user_id')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Year plan code (section #)</label>
                                <input type="number" name="plan_section_number" id="editSubDeptPlanSection" class="form-control @error('plan_section_number') is-invalid @enderror" min="1" max="999" placeholder="e.g. 4 → 4.1, 4.2 on Year Plan">
                                <p class="text-muted small mb-0 mt-1">Prefix for activity line codes on the Year Plan.</p>
                                @error('plan_section_number')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="departmentViewScriptData" class="d-none" data-department-update-base="{{ url('/department') }}"></div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const scriptData = document.getElementById('departmentViewScriptData');
                const base = scriptData && scriptData.dataset.departmentUpdateBase ? scriptData.dataset.departmentUpdateBase : '';
                const form = document.getElementById('editSubDepartmentForm');
                if (!form || !base) return;

                document.querySelectorAll('.edit-sub-dept-btn').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        const hashid = this.getAttribute('data-hashid');
                        form.action = base + '/' + encodeURIComponent(hashid);
                        document.getElementById('editSubDeptParentId').value = this.getAttribute('data-parent-id') || '';
                        document.getElementById('editSubDeptName').value = this.getAttribute('data-name') || '';
                        document.getElementById('editSubDeptStatus').value = this.getAttribute('data-status') || 'active';
                        const headId = this.getAttribute('data-head-user-id');
                        document.getElementById('editSubDeptHeadUserId').value = headId ? headId : '';
                        const code = this.getAttribute('data-plan-section-number');
                        document.getElementById('editSubDeptPlanSection').value = code !== null && code !== '' ? code : '';
                    });
                });
            });
        </script>
    @endcan

</x-app-layout>
