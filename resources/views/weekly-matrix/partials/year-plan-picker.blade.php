@php
    $namePrefix = $namePrefix ?? 'entry';
    $fieldKey = str_contains($namePrefix, '[')
        ? str_replace(['[', ']'], ['.', ''], $namePrefix)
        : $namePrefix;
    $selectedYearPlanIds = collect($entry['year_plan_activity_ids'] ?? [])
        ->map(fn ($id) => (string) $id)
        ->all();
    $pickerId = 'yearPlanPicker_'.md5($namePrefix);
@endphp

<div class="weekly-matrix-year-plan-picker" id="{{ $pickerId }}" data-name-prefix="{{ $namePrefix }}">
    <label class="form-label">Year plan ({{ $period->year }})</label>
    <p class="text-muted small mb-2">Link this weekly activity to one or more year plan objectives for impact tracking.</p>

    @if ($yearPlanOptions->isEmpty())
        <div class="alert alert-light border mb-0 py-2 px-3 small">
            <i class="ti ti-info-circle me-1"></i>
            No year plan activities found for this sub-department in {{ $period->year }}.
        </div>
    @else
        <button type="button"
                class="btn btn-outline-primary btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#{{ $pickerId }}_modal">
            <i class="ti ti-chart-line me-1"></i> Select year plan activities
        </button>
        <div class="mt-2 weekly-matrix-year-plan-selected d-flex flex-wrap gap-1" id="{{ $pickerId }}_chips">
            @foreach ($yearPlanOptions as $option)
                @if (in_array((string) $option->id, $selectedYearPlanIds, true))
                    <span class="badge bg-light text-dark border" data-activity-id="{{ $option->id }}">
                        {{ $option->displayCode() }}
                    </span>
                @endif
            @endforeach
        </div>
        <div class="weekly-matrix-year-plan-hidden-inputs" id="{{ $pickerId }}_hidden">
            @foreach ($selectedYearPlanIds as $activityId)
                <input type="hidden" name="{{ $namePrefix }}[year_plan_activity_ids][]" value="{{ $activityId }}">
            @endforeach
        </div>
        @error($fieldKey.'.year_plan_activity_ids')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
        @error($fieldKey.'.year_plan_activity_ids.*')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    @endif
</div>

@if ($yearPlanOptions->isNotEmpty())
    <div class="modal fade" id="{{ $pickerId }}_modal" tabindex="-1" aria-labelledby="{{ $pickerId }}_modal_label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="{{ $pickerId }}_modal_label">Year plan activities — {{ $period->year }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 48px;"></th>
                                    <th style="width: 90px;">Code</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($yearPlanOptions as $option)
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox"
                                                   class="form-check-input {{ $pickerId }}_checkbox"
                                                   value="{{ $option->id }}"
                                                   data-code="{{ $option->displayCode() }}"
                                                   data-description="{{ $option->description }}"
                                                   @checked(in_array((string) $option->id, $selectedYearPlanIds, true))>
                                        </td>
                                        <td class="fw-semibold">{{ $option->displayCode() }}</td>
                                        <td class="text-wrap">{{ $option->description }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary {{ $pickerId }}_apply">
                        <i class="ti ti-check me-1"></i> Apply selection
                    </button>
                </div>
            </div>
        </div>
    </div>

    @once
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    document.querySelectorAll('.weekly-matrix-year-plan-picker').forEach(function (picker) {
                        const pickerId = picker.id;
                        const namePrefix = picker.getAttribute('data-name-prefix');
                        const chipsEl = document.getElementById(pickerId + '_chips');
                        const hiddenEl = document.getElementById(pickerId + '_hidden');
                        const applyBtn = document.querySelector('.' + pickerId + '_apply');

                        if (!chipsEl || !hiddenEl || !applyBtn) {
                            return;
                        }

                        applyBtn.addEventListener('click', function () {
                            const checked = document.querySelectorAll('.' + pickerId + '_checkbox:checked');
                            chipsEl.innerHTML = '';
                            hiddenEl.innerHTML = '';

                            checked.forEach(function (checkbox) {
                                const id = checkbox.value;
                                const code = checkbox.getAttribute('data-code') || id;

                                const chip = document.createElement('span');
                                chip.className = 'badge bg-light text-dark border';
                                chip.setAttribute('data-activity-id', id);
                                chip.textContent = code;
                                chipsEl.appendChild(chip);

                                const input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = namePrefix + '[year_plan_activity_ids][]';
                                input.value = id;
                                hiddenEl.appendChild(input);
                            });

                            const modalEl = document.getElementById(pickerId + '_modal');
                            if (modalEl && window.bootstrap) {
                                bootstrap.Modal.getInstance(modalEl)?.hide();
                            }
                        });
                    });
                });
            </script>
        @endpush
    @endonce
@endif
