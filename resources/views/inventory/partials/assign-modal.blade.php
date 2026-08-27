@can('manage inventory assets')
<div class="modal fade" id="assignAssetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="assignAssetForm" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Assign asset</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        <span id="assignAssetName">Asset</span>
                        <span class="text-muted" id="assignAssetCode"></span>
                    </p>
                    @if($errors->any() && session('open_assign_modal'))
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @include('inventory.partials.ownership-cascade', [
                        'departmentField' => 'assigned_to_department_id',
                        'userField' => 'assigned_to_user_id',
                        'required' => true,
                        'showNotes' => true,
                        'notesField' => 'notes',
                        'selectedDepartmentId' => old('assigned_to_department_id', $assignPrefillDepartmentId ?? ''),
                        'selectedUserId' => old('assigned_to_user_id', $assignPrefillUserId ?? ''),
                    ])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
