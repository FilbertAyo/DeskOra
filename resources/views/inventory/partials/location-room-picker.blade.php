@php
    $fieldName = $fieldName ?? 'location_id';
    $selectedLocationId = (string) old($fieldName, $selectedLocationId ?? '');
    $required = $required ?? false;
    $emptyLabel = $emptyLabel ?? '-- Select --';
    $compact = $compact ?? false;
    $controlClass = $compact ? 'form-control form-control-sm' : 'form-control';
    $locationLabel = $locationLabel ?? 'Location';
    $roomLabel = $roomLabel ?? 'Room';
@endphp

<div class="js-location-room {{ $compact ? '' : 'row g-3' }}"
    data-selected="{{ $selectedLocationId }}"
    data-empty-label="{{ $emptyLabel }}">
    <div class="{{ $compact ? 'mb-0' : 'col-md-6' }}">
        <label class="form-label {{ $compact ? 'small text-muted' : '' }}">
            {{ $locationLabel }}
            @if($required)<span class="text-danger">*</span>@endif
        </label>
        <select class="{{ $controlClass }} js-location-branch @error($fieldName) is-invalid @enderror" @required($required)>
            <option value="">{{ $emptyLabel }}</option>
        </select>
    </div>
    <div class="{{ $compact ? 'mt-2' : 'col-md-6' }} js-location-room-wrap d-none">
        <label class="form-label {{ $compact ? 'small text-muted' : '' }}">{{ $roomLabel }}</label>
        <select class="{{ $controlClass }} js-location-room-select">
            <option value="">No specific room</option>
        </select>
        @unless($compact)
            <div class="form-text">Optional. Leave blank to keep the item at the location only.</div>
        @endunless
    </div>
    <input type="hidden" name="{{ $fieldName }}" class="js-location-id" value="{{ $selectedLocationId }}">
    @error($fieldName)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
