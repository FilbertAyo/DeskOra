@php
    $dispatch = $dispatch ?? null;
    $selectedResponsible = old('responsible_user_ids', $dispatch?->responsibleUsers->pluck('id')->all() ?? []);
@endphp

<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label">Dispatch name <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control" required
            value="{{ old('title', $dispatch?->title) }}"
            placeholder="e.g. Mwanza STEM Workshop">
    </div>
    <div class="col-md-4">
        <label class="form-label">Destination <span class="text-danger">*</span></label>
        <input type="text" name="destination" class="form-control" required
            value="{{ old('destination', $dispatch?->destination) }}"
            placeholder="e.g. Mwanza">
    </div>
    <div class="col-md-12">
        <label class="form-label">Purpose <span class="text-danger">*</span></label>
        <textarea name="purpose" rows="2" class="form-control" required placeholder="Workshop, training, field activity, outreach…">{{ old('purpose', $dispatch?->purpose) }}</textarea>
    </div>
    <div class="col-md-4">
        <label class="form-label">Dispatch date <span class="text-danger">*</span></label>
        <input type="date" name="dispatch_date" class="form-control" required
            value="{{ old('dispatch_date', optional($dispatch?->dispatch_date)->toDateString() ?? now()->toDateString()) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Expected return date <span class="text-danger">*</span></label>
        <input type="date" name="expected_return_date" class="form-control" required
            value="{{ old('expected_return_date', optional($dispatch?->expected_return_date)->toDateString()) }}">
    </div>
    <div class="col-md-12">
        <label class="form-label">Temporary responsible people <span class="text-danger">*</span></label>
        <p class="small text-muted mb-2">These people are jointly responsible for the whole dispatch, not for individual items. Permanent asset custodians will not change.</p>
        <x-staff-checkbox-select
            :users="$users"
            name="responsible_user_ids[]"
            :selected="$selectedResponsible"
            title="Responsible people"
            :show-title="false"
            error-key="responsible_user_ids"
            hint="Select one or more staff members."
        />
    </div>
    <div class="col-md-12">
        <label class="form-label">Notes</label>
        <textarea name="notes" rows="2" class="form-control">{{ old('notes', $dispatch?->notes) }}</textarea>
    </div>
</div>
