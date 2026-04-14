<section>
    <form method="post" action="{{ route('password.update') }}" class="mt-2">
        @csrf
        @method('put')

        <div class="row">
            <div class="col-lg-7">
                <div class='mb-3'>
                    <x-input-label for="update_password_current_password" :value="__('Current Password')" />
                    <x-text-input id="update_password_current_password" name="current_password" type="password"
                        class="form-control mt-1 block w-full" autocomplete="current-password" />
                    <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
                </div>

                <div class='mb-3'>
                    <x-input-label for="update_password_password" :value="__('New Password')" />
                    <x-text-input id="update_password_password" name="password" type="password"
                        class="form-control mt-1 block w-full" autocomplete="new-password" />
                    <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
                    <small class="text-muted">Use at least 8 characters with letters, numbers, and symbols.</small>
                </div>

                <div class='mb-3'>
                    <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" />
                    <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password"
                        class="form-control mt-1 block w-full" autocomplete="new-password" />
                    <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
                </div>

                <div class="d-flex align-items-center gap-3 mt-3">
                    <x-primary-button>{{ __('Save Password') }}</x-primary-button>

                    @if (session('status') === 'password-updated')
                        <span class="text-success small">
                            {{ __('Password updated successfully.') }}
                        </span>
                    @endif
                </div>
            </div>
            <div class="col-lg-5 mt-4 mt-lg-0">
                <div class="border rounded p-3 bg-light">
                    <h6 class="mb-2">Password Tips</h6>
                    <ul class="text-muted small mb-0 ps-3">
                        <li>Avoid using your name or phone number.</li>
                        <li>Do not reuse old passwords.</li>
                        <li>Change your password regularly.</li>
                    </ul>
                </div>
            </div>
        </div>
    </form>
</section>
