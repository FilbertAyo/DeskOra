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
    $namePrefix = $namePrefix ?? 'entries['.$index.']';
    $showRemove = $showRemove ?? false;
    $selectedYearPlanIds = collect($entry['year_plan_activity_ids'] ?? [])->map(fn ($id) => (string) $id)->all();
    $fieldKey = str_contains($namePrefix, '[') ? str_replace(['[', ']'], ['.', ''], $namePrefix) : $namePrefix;
@endphp

<tr data-matrix-row>
    @foreach ($textFields as $field)
        <td>
            <textarea
                name="{{ $namePrefix }}[{{ $field }}]"
                class="form-control form-control-sm"
                rows="4"
                @if ($field === 'key_activity') required @endif
            >{{ old($fieldKey.'.'.$field, $entry[$field] ?? '') }}</textarea>
            @error($fieldKey.'.'.$field)
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </td>
    @endforeach
    <td>
        <select name="{{ $namePrefix }}[year_plan_activity_ids][]"
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
        @error($fieldKey.'.year_plan_activity_ids')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
        @error($fieldKey.'.year_plan_activity_ids.*')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </td>
    @if ($showRemove)
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm weekly-matrix-remove-row" title="Remove row">
                <i class="ti ti-trash"></i>
            </button>
        </td>
    @endif
</tr>
