<x-guest-layout>
    <x-auth-shell :narrow="true" title="Reset password" subtitle="Create a new secure password for your account.">

        {{-- Validation Errors --}}
        @if ($errors->any())
            <div class="au-alert au-alert--error au-reveal" style="--i:2" role="alert">
                <strong>Please check your input</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}" data-au-form novalidate>
            @csrf

            <!-- Password Reset Token -->
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="au-field au-reveal" style="--i:3">
                <label class="au-label" for="email">{{ __('Email Address') }}</label>
                <div class="au-control">
                    <i class="au-icon fa-regular fa-envelope" aria-hidden="true"></i>
                    <input id="email" type="email" name="email"
                           class="au-input @error('email') is-invalid @enderror"
                           value="{{ old('email', $request->email) }}"
                           placeholder="you@example.com"
                           autocomplete="username" inputmode="email"
                           required data-au-focus>
                </div>
            </div>

            <div class="au-field au-reveal" style="--i:4">
                <label class="au-label" for="password">{{ __('Password') }}</label>
                <div class="au-control">
                    <i class="au-icon fa-solid fa-lock" aria-hidden="true"></i>
                    <input id="password" type="password" name="password"
                           class="au-input @error('password') is-invalid @enderror"
                           placeholder="Enter new password"
                           autocomplete="new-password" required>
                    <button type="button" class="au-toggle" data-target="password" aria-label="Show password">
                        <i class="fa-regular fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <div class="au-field au-reveal" style="--i:5">
                <label class="au-label" for="password_confirmation">{{ __('Confirm Password') }}</label>
                <div class="au-control">
                    <i class="au-icon fa-solid fa-shield-halved" aria-hidden="true"></i>
                    <input id="password_confirmation" type="password" name="password_confirmation"
                           class="au-input"
                           placeholder="Confirm new password"
                           autocomplete="new-password" required>
                    <button type="button" class="au-toggle" data-target="password_confirmation" aria-label="Show password">
                        <i class="fa-regular fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="au-btn au-reveal" style="--i:6" data-loading="Saving…">
                <span>{{ __('Reset Password') }}</span>
                <i class="fa-solid fa-check au-btn-arrow" aria-hidden="true"></i>
            </button>
        </form>

        <div class="au-back au-reveal" style="--i:7">
            <a class="au-link" href="{{ route('login') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to login</a>
        </div>
    </x-auth-shell>
</x-guest-layout>
