<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Inventory Projects</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Inventory</a></li>
                                <li class="breadcrumb-item" aria-current="page">Projects</li>
                            </ul>
                        </div>
                        <div class="col-auto d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-light" onclick="window.location.reload()" title="Refresh">
                                <i class="ti ti-refresh"></i>
                            </button>
                            <button type="button" class="btn btn-primary btn-sm" id="newProjectBtn">
                                <i class="ti ti-circle-plus me-1"></i> New Project
                            </button>
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
                            <h5 class="mb-0"><i class="ti ti-briefcase me-2"></i>Project List</h5>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="ti ti-search"></i></span>
                                <input type="text" class="form-control border-start-0" id="searchInput"
                                    placeholder="Search by name, donor, or description...">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered table-striped" id="projectsTable">
                            <thead class="thead-light">
                                <tr>
                                    <th class="text-center" style="width: 50px;">No.</th>
                                    <th>Name</th>
                                    <th>Donor</th>
                                    <th>Description</th>
                                    <th class="text-center" style="width: 120px;">Status</th>
                                    <th class="text-center" style="width: 180px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($projects as $project)
                                    <tr>
                                        <td class="text-center">{{ $loop->iteration }}</td>
                                        <td class="fw-semibold">{{ $project->name }}</td>
                                        <td>
                                            @if($project->donor)
                                                <span>{{ $project->donor->name }}</span>
                                            @else
                                                <span class="text-muted">No donor linked</span>
                                            @endif
                                        </td>
                                        <td class="text-muted">{{ $project->description ?: '—' }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-light-secondary">
                                                {{ ucfirst($project->status) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <x-table-action-dropdown>
<button type="button" class="dropdown-item edit-btn"
    data-id="{{ $project->id }}"
    data-name="{{ e($project->name) }}"
    data-description="{{ e($project->description) }}"
    data-donor-id="{{ $project->donor_id }}"
    data-status="{{ $project->status }}">
    <i class="ti ti-edit me-2"></i> Edit
</button>
<button type="button" class="dropdown-item text-danger delete-btn"
    data-id="{{ $project->id }}"
    data-name="{{ e($project->name) }}">
    <i class="ti ti-trash me-2"></i> Delete
</button>
</x-table-action-dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <i class="ti ti-briefcase fs-1 text-muted"></i>
                                            <p class="text-muted mb-0">No projects found</p>
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

    <div class="modal fade" id="projectFormModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="formTitle"><i class="ti ti-circle-plus me-2"></i>Add New Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="{{ route('inventory.projects.store') }}" id="projectForm">
                        @csrf
                        <input type="hidden" name="_method" id="formMethod" value="POST">
                        <input type="hidden" name="project_id" id="projectId">

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="projectName" class="form-label">Project Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="projectName"
                                    value="{{ old('name') }}" placeholder="Enter project name" required>
                                @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="projectStatus" class="form-label">Status <span class="text-danger">*</span></label>
                                <select name="status" id="projectStatus" class="form-control @error('status') is-invalid @enderror" required>
                                    <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                </select>
                                @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="projectDonor" class="form-label">Linked Donor</label>
                                <select name="donor_id" id="projectDonor" class="form-control @error('donor_id') is-invalid @enderror">
                                    <option value="">-- No donor linked --</option>
                                    @foreach($donors as $donor)
                                        <option value="{{ $donor->id }}" {{ old('donor_id') == $donor->id ? 'selected' : '' }}>
                                            {{ $donor->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('donor_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-12 mb-0">
                                <label for="projectDescription" class="form-label">Description</label>
                                <textarea name="description" id="projectDescription" rows="4"
                                    class="form-control @error('description') is-invalid @enderror"
                                    placeholder="Optional project description">{{ old('description') }}</textarea>
                                @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i> Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i> Save Project</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ti ti-alert-triangle me-2"></i>Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Are you sure you want to delete the project <strong id="deleteProjectName"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteForm" method="POST" style="display: inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger"><i class="ti ti-trash me-1"></i> Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="projectScriptData" data-has-errors="{{ $errors->any() ? '1' : '0' }}"
        data-store-route="{{ route('inventory.projects.store') }}" class="d-none"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const scriptData = document.getElementById('projectScriptData');
            const form = document.getElementById('projectForm');
            const formTitle = document.getElementById('formTitle');
            const searchInput = document.getElementById('searchInput');
            const table = document.getElementById('projectsTable');
            const rows = Array.from(table.querySelectorAll('tbody tr'));
            const formModal = new bootstrap.Modal(document.getElementById('projectFormModal'));
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

            function resetForm() {
                form.reset();
                form.action = scriptData?.dataset?.storeRoute || '';
                document.getElementById('formMethod').value = 'POST';
                document.getElementById('projectId').value = '';
                document.getElementById('projectStatus').value = 'active';
                document.getElementById('projectDonor').value = '';
            }

            document.getElementById('newProjectBtn')?.addEventListener('click', function() {
                resetForm();
                formTitle.innerHTML = '<i class="ti ti-circle-plus me-2"></i>Add New Project';
                formModal.show();
            });

            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    form.action = `/inventory/projects/${id}`;
                    document.getElementById('formMethod').value = 'PUT';
                    document.getElementById('projectId').value = id;
                    document.getElementById('projectName').value = this.dataset.name;
                    document.getElementById('projectDescription').value = this.dataset.description || '';
                    document.getElementById('projectDonor').value = this.dataset.donorId || '';
                    document.getElementById('projectStatus').value = this.dataset.status;
                    formTitle.innerHTML = '<i class="ti ti-edit me-2"></i>Edit Project';
                    formModal.show();
                });
            });

            document.querySelectorAll('.delete-btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('deleteProjectName').textContent = this.dataset.name;
                    document.getElementById('deleteForm').action = `/inventory/projects/${this.dataset.id}`;
                    deleteModal.show();
                });
            });

            searchInput?.addEventListener('keyup', function() {
                const term = this.value.toLowerCase();
                rows.forEach(row => {
                    if (row.querySelectorAll('td').length === 1) return;
                    row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
                });
            });

            if (scriptData?.dataset?.hasErrors === '1') formModal.show();
        });
    </script>
</x-app-layout>
