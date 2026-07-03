@php
    $fieldLabels = [
        'key_activity' => 'Key Activities',
        'output' => 'Output',
        'outcome' => 'Outcome',
        'impact' => 'Impact',
        'indicators' => 'Indicators',
        'baseline' => 'Baseline',
        'target' => 'Target',
        'means_of_verification' => 'Means of Verification',
    ];
    $namePrefix = $namePrefix ?? 'entry';
    $fieldKey = str_contains($namePrefix, '[')
        ? str_replace(['[', ']'], ['.', ''], $namePrefix)
        : $namePrefix;
    $textareaRows = [
        'key_activity' => 5,
        'output' => 4,
        'outcome' => 4,
        'impact' => 4,
        'indicators' => 4,
        'baseline' => 3,
        'target' => 3,
        'means_of_verification' => 4,
    ];
@endphp

<div class="weekly-matrix-fields-vertical">
    <div class="row g-4">
        @foreach ($fieldLabels as $field => $label)
            <div class="col-xl-6 col-md-6 col-12">
                <label class="form-label fw-semibold" for="{{ $namePrefix }}_{{ $field }}">
                    {{ $label }}
                    @if ($field === 'key_activity')
                        <span class="text-danger">*</span>
                    @endif
                </label>
                <textarea
                    id="{{ $namePrefix }}_{{ $field }}"
                    name="{{ $namePrefix }}[{{ $field }}]"
                    class="form-control weekly-matrix-field-input"
                    rows="{{ $textareaRows[$field] }}"
                    @if ($field === 'key_activity') required @endif
                    placeholder="Enter {{ strtolower($label) }}…"
                >{{ old($fieldKey.'.'.$field, $entry[$field] ?? '') }}</textarea>
                @error($fieldKey.'.'.$field)
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>
        @endforeach
    </div>
</div>
