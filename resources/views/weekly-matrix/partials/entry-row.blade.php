@php
    $textFields = [
        'key_activity',
        'output',
        'outcome',
        'impact',
        'indicators',
        'baseline',
        'target',
        'means_of_verification',
    ];
    $selectedYearPlanIds = collect($entry['year_plan_activity_ids'] ?? [])->map(fn ($id) => (string) $id)->all();
@endphp

<tr data-matrix-row>
    @foreach ($textFields as $field)
        <td>
            <textarea
                name="entries[{{ $index }}][{{ $field }}]"
                class="form-control form-control-sm"
                rows="4"
                @if ($field === 'key_activity') required @endif
            >{{ old("entries.{$index}.{$field}", $entry[$field] ?? '') }}</textarea>
            @error("entries.{$index}.{$field}")
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </td>
    @endforeach
    <td>
        <select name="entries[{{ $index }}][year_plan_activity_ids][]"
                class="form-select form-select-sm"
                multiple
                size="4">
            @forelse ($yearPlanOptions as $option)
                <option value="{{ $option->id }}"
                    @selected(in_array((string) $option->id, $selectedYearPlanIds, true))>
                    {{ $option->displayCode() }} — {{ Str::limit($option->description, 60) }}
                </option>
            @empty
                <option disabled>No year plan activities for this sub-department</option>
            @endforelse
        </select>
        @error("entries.{$index}.year_plan_activity_ids")
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
        @error("entries.{$index}.year_plan_activity_ids.*")
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </td>
    <td class="text-center">
        <button type="button" class="btn btn-outline-danger btn-sm weekly-matrix-remove-row" title="Remove row">
            <i class="ti ti-trash"></i>
        </button>
    </td>
</tr>
