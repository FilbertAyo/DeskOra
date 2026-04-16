<!-- Document Type -->
<div class="col-md-6 mb-3">
    <div class="form-group">
        <label for="type" class="form-label">
            Document Type <span class="text-danger">*</span>
        </label>
        <div class="input-group">
            <select class="form-control @error('type') is-invalid @enderror"
                name="type"
                id="type"
                {{ isset($selectedType) ? 'disabled' : '' }}
                required>
                <option value="" disabled {{ (old('type') || isset($selectedType)) ? '' : 'selected' }}>
                    -- Select Document Type --
                </option>
                @foreach($availableTypes as $type => $name)
                    <option value="{{ $type }}"
                        {{ old('type', $selectedType ?? '') == $type ? 'selected' : '' }}>
                        {{ $name }}
                    </option>
                @endforeach
            </select>
            @if(isset($selectedType))
                <input type="hidden" name="type" value="{{ $selectedType }}">
            @endif
        </div>
        @error('type')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        <small class="form-text text-muted">
            @if(isset($selectedType))
                Document type is set to: <strong>{{ $availableTypes[$selectedType] ?? $selectedType }}</strong>
            @else
                Select the type of document you want to create
            @endif
        </small>
    </div>
</div>

<!-- Work Period (for weekly plans and reports) -->
<div class="col-md-6 mb-3" id="workPeriodContainer" @if(!isset($selectedType) || !in_array($selectedType, ['weekly_plan', 'weekly_report'])) style="display: none;" @endif>
    <div class="form-group">
        <label for="period_id" class="form-label">
            Work Period <span class="text-danger">*</span>
        </label>
        <div class="input-group">
            <select class="form-control @error('period_id') is-invalid @enderror"
                id="period_id" disabled>
                <option value="">-- Select Work Period --</option>
                @foreach($workPeriods as $workPeriod)
                    <option value="{{ $workPeriod->id }}"
                        {{ old('period_id', $defaultPeriodId ?? '') == $workPeriod->id ? 'selected' : '' }}>
                        Week {{ $workPeriod->week_number }} of {{ $workPeriod->year }}
                        ({{ \Carbon\Carbon::parse($workPeriod->week_start_date)->format('M d') }} -
                        {{ \Carbon\Carbon::parse($workPeriod->week_end_date)->format('M d, Y') }})
                    </option>
                @endforeach
            </select>
            <input type="hidden" name="period_id" id="period_id_hidden"
                value="{{ old('period_id', $defaultPeriodId ?? '') }}">
        </div>
        @error('period_id')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        <small class="form-text text-muted">Select the work period for this document</small>
    </div>
</div>

<!-- Month and Year (for monthly reports) -->
<div class="col-md-6 mb-3" id="monthYearContainer" @if(!isset($selectedType) || $selectedType !== 'monthly_report') style="display: none;" @endif>
    <div class="form-group">
        <label for="month" class="form-label">
            Month <span class="text-danger">*</span>
        </label>
        <div class="input-group">
            <span class="input-group-text">
                <i class="ti ti-calendar"></i>
            </span>
            <select class="form-control js-monthly-report-reload @error('month') is-invalid @enderror"
                name="month"
                id="month">
                <option value="">-- Select Month --</option>
                @php
                    $months = [
                        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                    ];
                    $selectedMonth = old('month', $monthlyDefaultMonth ?? (int) date('n'));
                @endphp
                @foreach($months as $num => $name)
                    <option value="{{ $num }}" {{ (int) $selectedMonth === (int) $num ? 'selected' : '' }}>
                        {{ $name }}
                    </option>
                @endforeach
            </select>
        </div>
        @error('month')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        <small class="form-text text-muted">Changing month or year reloads planned activities from the year plan.</small>
    </div>
</div>

<div class="col-md-6 mb-3" id="yearContainer" @if(!isset($selectedType) || $selectedType !== 'monthly_report') style="display: none;" @endif>
    <div class="form-group">
        <label for="year" class="form-label">
            Year <span class="text-danger">*</span>
        </label>
        <div class="input-group">
            <span class="input-group-text">
                <i class="ti ti-calendar"></i>
            </span>
            <select class="form-control js-monthly-report-reload @error('year') is-invalid @enderror"
                name="year"
                id="year">
                <option value="">-- Select Year --</option>
                @php
                    $selectedYear = old('year', $monthlyDefaultYear ?? (int) date('Y'));
                    $yearChoices = isset($monthlyYearChoices) ? $monthlyYearChoices : collect(range((int) date('Y') + 1, (int) date('Y') - 5));
                @endphp
                @foreach($yearChoices as $y)
                    <option value="{{ $y }}" {{ (int) $selectedYear === (int) $y ? 'selected' : '' }}>
                        {{ $y }}
                    </option>
                @endforeach
            </select>
        </div>
        @error('year')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        <small class="form-text text-muted">Calendar year of the annual plan</small>
    </div>
</div>
