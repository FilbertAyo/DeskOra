<section>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h6 class="mb-1">{{ __('Upload your signature') }}</h6>
            <p class="text-muted small mb-0">{{ __('Use a clear image (jpeg, png, jpg, gif), max 2MB.') }}</p>
        </div>
    </div>

    @if (Auth::user()->signature)
        <div class="mb-4">
            <img src="{{ asset(Auth::user()->signature) }}" alt="Signature" class="h-12 border rounded" style="height: 200px; width: auto;">
        </div>
    @endif

    <form action="{{ route('profile.image') }}" method="POST" enctype="multipart/form-data" class="mt-2">
        @csrf

        <div class="mb-3 col-lg-7 ps-0">
            <x-input-label for="signature" :value="__('Signature')" />
            <x-text-input id="signature" name="signature" type="file" class="form-control mt-1 block w-full" />
            <x-input-error :messages="$errors->get('signature')" class="mt-2" />
        </div>

        <div class="d-flex align-items-center gap-3">
            <x-primary-button label="Upload Signature"/>

            @if (session('status') === 'signature-updated')
                <span class="text-success small">
                    {{ __('Signature updated successfully.') }}
                </span>
            @endif
        </div>
    </form>
</section>
