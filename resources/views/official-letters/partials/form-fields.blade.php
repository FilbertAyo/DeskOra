@php
    $isEdit = isset($letter) && $letter;
@endphp
<div class="card shadow-none border">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0">
            <i class="ti ti-{{ $isEdit ? 'pencil' : 'mail' }} me-2"></i>
            {{ $isEdit ? 'Edit Letter' : 'Letter Details' }}
        </h5>
    </div>
    <div class="card-body">
        @if($isEdit)
            <div class="mb-3">
                <label class="form-label">Reference Number</label>
                <input type="text" class="form-control" value="{{ $letter->reference_number }}" readonly disabled>
                <small class="text-muted">
                    Read-only. Changing department or letter month will allocate a new reference automatically.
                </small>
            </div>
        @else
            <div class="alert alert-info mb-4">
                <i class="ti ti-info-circle me-1"></i>
                The reference number is generated automatically from the department and letter date after you save. It cannot be edited manually.
            </div>
        @endif

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="department_code" class="form-label">Department <span class="text-danger">*</span></label>
                <select name="department_code" id="department_code"
                        class="form-control @error('department_code') is-invalid @enderror" required>
                    @unless($isEdit)
                        <option value="">Select department</option>
                    @endunless
                    @foreach($departments as $code => $label)
                        <option value="{{ $code }}" {{ (string) old('department_code', $isEdit ? $letter->department_code : '') === (string) $code ? 'selected' : '' }}>
                            {{ $code }} — {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('department_code')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6 mb-3">
                <label for="letter_date" class="form-label">Letter Date <span class="text-danger">*</span></label>
                <input type="date" name="letter_date" id="letter_date"
                       class="form-control @error('letter_date') is-invalid @enderror"
                       value="{{ old('letter_date', $isEdit ? $letter->letter_date->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
                @error('letter_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mb-3">
            <label for="recipient" class="form-label">Recipient <span class="text-danger">*</span></label>
            <textarea name="recipient" id="recipient" rows="4"
                      class="form-control text-uppercase @error('recipient') is-invalid @enderror"
                      style="text-transform: uppercase;"
                      placeholder="ORGANIZATION / TITLE&#10;ADDRESS LINE&#10;CITY"
                      required>{{ old('recipient', $isEdit ? $letter->recipient : '') }}</textarea>
            <small class="text-muted">Entered in uppercase.</small>
            @error('recipient')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="salutation" class="form-label">Salutation <span class="text-danger">*</span></label>
                <select name="salutation" id="salutation"
                        class="form-control @error('salutation') is-invalid @enderror" required>
                    @foreach($salutations as $option)
                        <option value="{{ $option }}" {{ old('salutation', $isEdit ? $letter->salutation : 'Dear Sir/Madam,') === $option ? 'selected' : '' }}>
                            {{ $option }}
                        </option>
                    @endforeach
                </select>
                @error('salutation')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6 mb-3">
                <label for="closing" class="form-label">Closing <span class="text-danger">*</span></label>
                <select name="closing" id="closing"
                        class="form-control @error('closing') is-invalid @enderror" required>
                    @foreach($closings as $option)
                        <option value="{{ $option }}" {{ old('closing', $isEdit ? $letter->closing : 'In service,') === $option ? 'selected' : '' }}>
                            {{ $option }}
                        </option>
                    @endforeach
                </select>
                @error('closing')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mb-3">
            <label for="subject" class="form-label">Subject (RE:) <span class="text-danger">*</span></label>
            <input type="text" name="subject" id="subject"
                   class="form-control text-uppercase @error('subject') is-invalid @enderror"
                   style="text-transform: uppercase;"
                   value="{{ old('subject', $isEdit ? $letter->subject : '') }}" required>
            <small class="text-muted">Entered in uppercase.</small>
            @error('subject')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="body" class="form-label">Body <span class="text-danger">*</span></label>
            <textarea name="body" id="body" rows="12"
                      class="form-control @error('body') is-invalid @enderror">{{ old('body', $isEdit ? $letter->body : '') }}</textarea>
            <small class="text-muted">Formatting is preserved as written. Font: Calibri.</small>
            @error('body')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="row">
            <div class="col-md-12 mb-3">
                <label for="signatory_id" class="form-label">Signatory <span class="text-danger">*</span></label>
                <select name="signatory_id" id="signatory_id"
                        class="form-control @error('signatory_id') is-invalid @enderror" required>
                    <option value="">Select signatory</option>
                    @foreach($signatories as $signatory)
                        <option value="{{ $signatory->id }}"
                                data-name="{{ $signatory->name }}"
                                data-title="{{ $signatory->title }}"
                                {{ (string) old('signatory_id', $isEdit ? $letter->signatory_id : '') === (string) $signatory->id ? 'selected' : '' }}>
                            {{ $signatory->name }}@if($signatory->title) — {{ $signatory->title }}@endif
                        </option>
                    @endforeach
                </select>
                <small class="text-muted">Name and title are taken from the selected user’s profile.</small>
                @error('signatory_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Signatory Name</label>
                <input type="text" id="signatory_name_preview" class="form-control" readonly disabled>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Signatory Title</label>
                <input type="text" id="signatory_title_preview" class="form-control" readonly disabled>
            </div>
            <div class="col-md-12 mb-3">
                <label class="form-label d-block">Signature Type <span class="text-danger">*</span></label>
                @php
                    $selectedSignatureMode = old(
                        'signature_mode',
                        $isEdit ? ($letter->signature_mode ?: 'digital') : 'digital'
                    );
                @endphp
                <div class="form-check mb-2">
                    <input class="form-check-input @error('signature_mode') is-invalid @enderror"
                           type="radio"
                           name="signature_mode"
                           id="signature_mode_digital"
                           value="digital"
                           {{ $selectedSignatureMode === 'digital' ? 'checked' : '' }}
                           required>
                    <label class="form-check-label" for="signature_mode_digital">
                        Digital signature
                        <small class="text-muted d-block">Use the selected staff member’s uploaded signature on the PDF.</small>
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input @error('signature_mode') is-invalid @enderror"
                           type="radio"
                           name="signature_mode"
                           id="signature_mode_manual"
                           value="manual"
                           {{ $selectedSignatureMode === 'manual' ? 'checked' : '' }}>
                    <label class="form-check-label" for="signature_mode_manual">
                        Manual signature
                        <small class="text-muted d-block">Leave a blank signature line so it can be signed by hand after printing.</small>
                    </label>
                </div>
                @error('signature_mode')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
    <div class="card-footer bg-white border-top d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="ti ti-device-floppy me-1"></i> {{ $isEdit ? 'Save Changes' : 'Create Letter' }}
        </button>
        <a href="{{ $isEdit ? route('official-letters.show', $letter) : route('official-letters.index') }}" class="btn btn-light">Cancel</a>
    </div>
</div>
