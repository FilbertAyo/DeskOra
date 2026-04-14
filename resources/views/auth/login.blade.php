<x-guest-layout>


                <div class="card my-5">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-end mb-4">
                            <h3 class="mb-0">
                                <b>Login</b>
                            </h3>
                      </div>

                        <x-auth-session-status class="mb-4" :status="session('status')" />

                        <form method="POST" action="{{ route('login') }}" class="pt-3">
                            @csrf

                            <div class="form-group mb-3">
                                <label class="form-label" for="email">Email Address</label>
                                <input
                                    id="email"
                                    class="form-control"
                                    type="email"
                                    name="email"
                                    placeholder="Email Address"
                                    value="{{ old('email') }}"
                                    required
                                    autofocus
                                    autocomplete="username"
                                >
                                @error('email')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label" for="password">Password</label>
                                <input
                                    id="password"
                                    class="form-control"
                                    type="password"
                                    name="password"
                                    placeholder="Password"
                                    required
                                    autocomplete="current-password"
                                >
                                @error('password')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="d-flex mt-1 justify-content-between">
                                <div class="form-check">
                                    <input
                                        type="checkbox"
                                        class="form-check-input input-primary"
                                        id="remember"
                                        name="remember"
                                        {{ old('remember') ? 'checked' : '' }}
                                    >
                                    <label class="form-check-label text-muted" for="remember">
                                        Keep me sign in
                                    </label>
                                </div>

                                @if (Route::has('password.request'))
                                    <h5 class="text-secondary f-w-400 mb-0">
                                        <a
                                            href="{{ route('password.request') }}"
                                            class="text-secondary f-w-400 text-decoration-none"
                                        >
                                            Forgot Password?
                                        </a>
                                    </h5>
                                @endif
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary">
                                    Login
                                </button>
                            </div>

                    
                        </form>
                        <div class="auth-bottom">
                            <div class="text-center mt-4 font-weight-light">
                                Facing issues?
                                <a href="https://wa.me/255741400900" target="_blank" class="text-primary">
                                    Contact Admin
                                </a>
                            </div>
                        </div>
                    </div>
                </div>




                
</x-guest-layout>
