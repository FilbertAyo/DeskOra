@props([
    'users' => collect(),
    'name' => 'staff_ids[]',
    'selected' => [],
    'title' => 'Assigned Staff',
    'showTitle' => true,
    'compact' => false,
    'disabled' => false,
    'id' => null,
    'hint' => null,
    'errorKey' => null,
])

@php
    $selectedIds = is_array($selected) ? array_map('strval', $selected) : [];
    $groupId = $id ?? 'staff-cb-' . substr(md5($name . '-' . collect($users)->count()), 0, 12);
    $errorBagKey = $errorKey ?? ($name !== '' ? rtrim(str_replace('[]', '', $name), ']') : null);
    $hasError = filled($errorBagKey) && $errors->has($errorBagKey);
@endphp

@once
    @push('scripts')
        <script src="{{ asset('assets/js/staff-checkbox-select.js') }}"></script>
    @endpush
    <style>
        .staff-checkbox-select__grid {
            margin-bottom: 0;
        }

        .staff-checkbox-select__item {
            margin-bottom: 0;
            padding: 0.35rem 0.5rem;
            border: 1px solid var(--bs-border-color, #e9ecef);
            border-radius: 0.375rem;
            background: var(--bs-body-bg, #fff);
            min-height: 2rem;
        }

        .staff-checkbox-select__item .form-check-label {
            cursor: pointer;
            user-select: none;
            line-height: 1.35;
        }

        .staff-checkbox-select__item .form-check-input:checked + .form-check-label {
            font-weight: 600;
        }

        .staff-checkbox-select--compact .staff-checkbox-select__item {
            padding: 0.25rem 0.35rem;
            font-size: 0.875rem;
        }

        .staff-checkbox-select--compact .staff-checkbox-select__grid {
            max-height: 12rem;
            overflow-y: auto;
        }

        .staff-checkbox-select.is-invalid .staff-checkbox-select__item {
            border-color: var(--bs-danger, #dc3545);
        }
    </style>
@endonce

<div
    {{ $attributes->class([
        'staff-checkbox-select',
        'staff-checkbox-select--compact' => $compact,
        'is-invalid' => $hasError,
    ]) }}
    data-staff-checkbox-select
>
    @if($showTitle)
        <div class="staff-checkbox-select__title form-label mb-2">{{ $title }}</div>
    @endif

    <div class="staff-checkbox-select__grid row g-2">
        @forelse($users as $user)
            @php $userId = (string) $user->id; @endphp
            <div @class(['col-12', 'col-sm-6 col-lg-4' => ! $compact])>
                <div class="form-check staff-checkbox-select__item">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="{{ $name }}"
                        id="{{ $groupId }}-{{ $userId }}"
                        value="{{ $user->id }}"
                        @checked(in_array($userId, $selectedIds, true))
                        @disabled($disabled)
                    >
                    <label class="form-check-label" for="{{ $groupId }}-{{ $userId }}">
                        {{ $user->name }}
                    </label>
                </div>
            </div>
        @empty
            <div class="col-12">
                <small class="text-muted">No staff available for selection.</small>
            </div>
        @endforelse
    </div>

    @if($hasError && $errorBagKey)
        <div class="invalid-feedback d-block">{{ $errors->first($errorBagKey) }}</div>
    @endif

    @if($hint)
        <small class="form-text text-muted d-block mt-2">{{ $hint }}</small>
    @endif
</div>
