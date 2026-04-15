<x-guest-layout>
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>

    <div class="auth-main">
        <div class="auth-wrapper v3">
            <div class="auth-form">
                <div class="d-flex flex-column align-items-center justify-content-center flex-grow-1 w-100 py-3">
                  

                    <div class="card my-5">
                        <div class="card-body">
                            <h3 class="mb-1"><b>Anonymous feedback</b></h3>
                            <p class="text-muted small mb-4">
                                Your message is anonymous. Do not include your name or other identifying details if you want to stay anonymous.
                            </p>

                            @if (session('status') || session('success'))
                                <div class="alert alert-success mb-4" role="alert">
                                    {{ session('success') ?? session('status') }}
                                </div>
                            @endif

                            <form method="POST" action="{{ route('anonymous-feedback.store', ['token' => request()->route('token')]) }}" class="pt-1">
                                @csrf

                                <div class="mb-3" style="position: absolute; left: -9999px;" aria-hidden="true">
                                    <label for="website">Website</label>
                                    <input type="text" name="website" id="website" tabindex="-1" autocomplete="off" value="">
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label" for="category">Type</label>
                                    <select name="category" id="category" class="form-select @error('category') is-invalid @enderror" required>
                                        <option value="" disabled {{ old('category') ? '' : 'selected' }}>Select…</option>
                                        @foreach ($categories as $value => $label)
                                            <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('category')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="form-group mb-3">
                                    <label class="form-label" for="body">Message</label>
                                    <textarea
                                        name="body"
                                        id="body"
                                        class="form-control @error('body') is-invalid @enderror"
                                        rows="6"
                                        maxlength="5000"
                                        required
                                        placeholder="Complaint, suggestion, or how we can help…"
                                    >{{ old('body') }}</textarea>
                                    @error('body')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">Submit anonymously</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="auth-footer row">
                    <div class="col my-1">
                        <p class="m-0">
                            &copy; {{ date('Y') }} {{ config('app.name') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
