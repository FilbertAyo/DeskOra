<x-app-layout>

    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Departments Management</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="javascript: void(0)">Settings</a></li>
                                <li class="breadcrumb-item" aria-current="page">Departments</li>
                            </ul>
                        </div>
                        <div class="col-auto d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-light" onclick="location.reload()" title="Refresh">
                                <i class="ti ti-refresh"></i>
                            </button>
                            @can('manage departments')
                                <button type="button" class="btn btn-primary btn-sm" id="newDepartmentBtn">
                                    <i class="ti ti-circle-plus me-1"></i> New Department
                                </button>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

   
    <!-- Departments Table -->
    <div class="row">
       
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom">
                    <div class="row align-items-center">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <h5 class="mb-0"><i class="ti ti-list me-2"></i>Departments List</h5>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="ti ti-search"></i>
                                </span>
                                <input type="text" class="form-control border-start-0" id="searchInput"
                                    placeholder="Search departments by name...">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                   
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered table-striped" id="departmentsTable">
                            <thead class="thead-light">
                                <tr>
                                    <th class="text-center" style="width: 50px;">No.</th>
                                    <th>Department Name</th>
                                    <th>Head of Department</th>
                                    <th class="text-center">Users</th>
                                    <th class="text-center" style="width: 100px;">Status</th>
                                    <th class="text-center" style="width: 180px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($departments as $index => $department)
                                    <tr>
                                        <td class="text-center">{{ $loop->iteration }}</td>
                                        <td>
                                            {{ $department->name }}
                                        </td>
                                        <td>{{ $department->head?->name ?? '-' }}</td>
                                        <td class="text-center">
                                            {{ $department->users_count }}
                                        </td>
                                        <td class="text-center">
                                            <span class="badge {{ $department->status == 'active' ? 'bg-success' : 'bg-secondary' }}">
                                                <i class="ti {{ $department->status == 'active' ? 'ti-circle-check' : 'ti-circle-x' }} me-1"></i>
                                                {{ ucfirst($department->status) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <x-table-action-dropdown>
    <a class="dropdown-item" href="{{ route('department.show', $department->id) }}">
    <i class="ti ti-eye me-2"></i> View
    </a>
    @can('manage departments')
    <button type="button" class="dropdown-item edit-btn"
    data-id="{{ $department->id }}"
    data-name="{{ $department->name }}"
    data-status="{{ $department->status }}"
    data-parent-id="{{ $department->parent_id }}"
    data-head-user-id="{{ $department->head_user_id }}">
    <i class="ti ti-edit me-2"></i> Edit
    </button>
    <button type="button" class="dropdown-item text-danger delete-btn"
    data-id="{{ $department->id }}"
    data-name="{{ $department->name }}">
    <i class="ti ti-trash me-2"></i> Delete
    </button>
    @endcan
</x-table-action-dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <i class="ti ti-briefcase fs-1 text-muted"></i>
                                            <p class="text-muted mb-0">No departments found</p>
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

    <!-- Create/Edit Department Modal -->
    <div class="modal fade" id="departmentFormModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="formTitle">
                        <i class="ti ti-circle-plus me-2"></i>Add New Department
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="{{ route('department.store') }}" id="departmentForm">
                        @csrf
                        <input type="hidden" name="_method" id="formMethod" value="POST">
                        <input type="hidden" name="department_id" id="departmentId">

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="departmentName" class="form-label">
                                    Department Name <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="ti ti-briefcase"></i>
                                    </span>
                                    <input type="text"
                                        class="form-control @error('name') is-invalid @enderror"
                                        name="name"
                                        id="departmentName"
                                        placeholder="Enter department name"
                                        required>
                                </div>
                                @error('name')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="departmentStatus" class="form-label">
                                    Status <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="ti ti-activity"></i>
                                    </span>
                                    <select name="status"
                                        id="departmentStatus"
                                        class="form-control @error('status') is-invalid @enderror"
                                        required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                @error('status')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="departmentType" class="form-label">
                                    Create As <span class="text-danger">*</span>
                                </label>
                                <select id="departmentType" class="form-control">
                                    <option value="department">Department</option>
                                    <option value="sub_department">Sub-Department</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3 d-none" id="parentDepartmentWrapper">
                                <label for="parentDepartment" class="form-label">
                                    Parent Department <span class="text-danger">*</span>
                                </label>
                                <select name="parent_id"
                                    id="parentDepartment"
                                    class="form-control @error('parent_id') is-invalid @enderror">
                                    <option value="">-- Select Parent Department --</option>
                                    @foreach($parentDepartments as $parentDepartment)
                                        <option value="{{ $parentDepartment->id }}" {{ old('parent_id') == $parentDepartment->id ? 'selected' : '' }}>
                                            {{ $parentDepartment->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('parent_id')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3 d-none" id="planSectionNumberWrapper">
                                <label for="planSectionNumber" class="form-label">Year plan section #</label>
                                <input type="number" name="plan_section_number" id="planSectionNumber" min="1" max="999"
                                    class="form-control @error('plan_section_number') is-invalid @enderror"
                                    value="{{ old('plan_section_number') }}"
                                    placeholder="e.g. 4 → codes 4.1, 4.2">
                                <p class="text-muted small mb-0">For sub-departments only: matches spreadsheet section numbers.</p>
                                @error('plan_section_number')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-12 mb-3">
                                <label for="headUserId" class="form-label">Head of Department</label>
                                <select name="head_user_id"
                                    id="headUserId"
                                    class="form-control @error('head_user_id') is-invalid @enderror">
                                    <option value="">-- Select Head (Optional) --</option>
                                    @foreach($departmentHeads as $head)
                                        <option value="{{ $head->id }}" {{ old('head_user_id') == $head->id ? 'selected' : '' }}>
                                            {{ $head->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('head_user_id')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                                <i class="ti ti-x me-1"></i> Cancel
                            </button>
                            @can('manage departments')
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="ti ti-device-floppy me-1"></i> Save Department
                                </button>
                            @else
                                <button type="button" class="btn btn-primary permission-alert">
                                    <i class="ti ti-device-floppy me-1"></i> Save Department
                                </button>
                            @endcan
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="ti ti-alert-triangle me-2"></i>Confirm Deletion
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Are you sure you want to delete the department <strong id="deleteDepartmentName"></strong>?</p>
                    <p class="text-danger small mb-0 mt-2">
                        <i class="ti ti-alert-circle me-1"></i>
                        This action cannot be undone. All associated users will also be affected.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="ti ti-x me-1"></i> Cancel
                    </button>
                    <form id="deleteForm" method="POST" style="display: inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="ti ti-trash me-1"></i> Delete Department
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <style>
        .avatar {
            width: 36px;
            height: 36px;
            font-size: 14px;
        }
        .avatar-sm {
            width: 32px;
            height: 32px;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.05);
        }
        .input-group-text {
            background-color: #f8f9fa;
        }
    </style>

    <div id="departmentScriptData"
        data-has-errors="{{ $errors->any() ? '1' : '0' }}"
        data-store-route="{{ route('department.store') }}"
        class="d-none"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const departmentScriptData = document.getElementById('departmentScriptData');
            const departmentForm = document.getElementById('departmentForm');
            const formTitle = document.getElementById('formTitle');
            const newDepartmentBtn = document.getElementById('newDepartmentBtn');
            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');
            const table = document.getElementById('departmentsTable');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const formModal = new bootstrap.Modal(document.getElementById('departmentFormModal'));
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            const departmentType = document.getElementById('departmentType');
            const parentDepartmentWrapper = document.getElementById('parentDepartmentWrapper');
            const parentDepartment = document.getElementById('parentDepartment');

            const planSectionNumberWrapper = document.getElementById('planSectionNumberWrapper');
            const planSectionNumber = document.getElementById('planSectionNumber');

            function toggleParentDepartment() {
                const isSub = departmentType && departmentType.value === 'sub_department';

                if (!parentDepartmentWrapper || !parentDepartment) return;

                parentDepartmentWrapper.classList.toggle('d-none', !isSub);
                if (planSectionNumberWrapper) {
                    planSectionNumberWrapper.classList.toggle('d-none', !isSub);
                }
                if (isSub) {
                    parentDepartment.setAttribute('required', 'required');
                } else {
                    parentDepartment.removeAttribute('required');
                    parentDepartment.value = '';
                    if (planSectionNumber) planSectionNumber.value = '';
                }
            }

            // Show form for new department
            if (newDepartmentBtn) {
                newDepartmentBtn.addEventListener('click', function() {
                    resetForm();
                    formTitle.innerHTML = '<i class="ti ti-circle-plus me-2"></i>Add New Department';
                    formModal.show();
                });
            }

            // Edit department
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    departmentForm.action = `/department/${id}`;
                    document.getElementById('formMethod').value = 'PUT';
                    document.getElementById('departmentId').value = id;
                    document.getElementById('departmentName').value = this.dataset.name;
                    document.getElementById('departmentStatus').value = this.dataset.status;
                    document.getElementById('headUserId').value = this.dataset.headUserId || '';
                    parentDepartment.value = this.dataset.parentId || '';
                    departmentType.value = this.dataset.parentId ? 'sub_department' : 'department';
                    toggleParentDepartment();
                    formTitle.innerHTML = '<i class="ti ti-edit me-2"></i>Edit Department';
                    formModal.show();
                });
            });

            // Delete department
            document.querySelectorAll('.delete-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const name = this.dataset.name;
                    document.getElementById('deleteDepartmentName').textContent = name;
                    document.getElementById('deleteForm').action = `/department/${id}`;
                    deleteModal.show();
                });
            });

            // Filter table
            function filterTable() {
                const searchTerm = searchInput.value.toLowerCase();
                const statusValue = statusFilter.value.toLowerCase();

                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length === 1) return; // Skip empty state row

                    const name = cells[1].textContent.toLowerCase();
                    const status = cells[4].textContent.toLowerCase();

                    const matchesSearch = name.includes(searchTerm);
                    const matchesStatus = !statusValue || status.includes(statusValue);

                    if (matchesSearch && matchesStatus) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });

                updateRowNumbers();
            }

            function updateRowNumbers() {
                let visibleIndex = 1;
                rows.forEach(row => {
                    if (row.style.display !== 'none' && row.querySelectorAll('td').length > 1) {
                        row.querySelector('td:first-child').textContent = visibleIndex++;
                    }
                });
            }

            function resetForm() {
                departmentForm.reset();
                departmentForm.action = departmentScriptData?.dataset?.storeRoute || '';
                document.getElementById('formMethod').value = 'POST';
                document.getElementById('departmentId').value = '';
                document.getElementById('headUserId').value = '';
                if (planSectionNumber) planSectionNumber.value = '';
                if (departmentType) {
                    departmentType.value = 'department';
                }
                toggleParentDepartment();
            }

            searchInput.addEventListener('keyup', filterTable);
            if (departmentType) {
                departmentType.addEventListener('change', toggleParentDepartment);
            }
            if (statusFilter) {
                statusFilter.addEventListener('change', filterTable);
            }

            // Show form if there are validation errors
            if (departmentScriptData?.dataset?.hasErrors === '1') {
                if (parentDepartment && parentDepartment.value) {
                    departmentType.value = 'sub_department';
                }
                toggleParentDepartment();
                formModal.show();
            }
        });

        function resetFilters() {
            document.getElementById('searchInput').value = '';
            const statusFilter = document.getElementById('statusFilter');
            if (statusFilter) {
                statusFilter.value = '';
                const event = new Event('change');
                statusFilter.dispatchEvent(event);
            }
        }
    </script>

</x-app-layout>

