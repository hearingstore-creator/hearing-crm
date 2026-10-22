<x-guest-layout>
    <x-auth-shell title="Sign in" subtitle="Enter your credentials to access your workspace.">

        {{-- Session Status --}}
        @if (session('status'))
            <div class="au-alert au-alert--ok au-reveal" style="--i:2" role="status">{{ session('status') }}</div>
        @endif

        @if (session('login_notice'))
            <div class="au-alert au-alert--error au-reveal" style="--i:2" role="alert">{{ session('login_notice') }}</div>
        @endif

        {{-- Validation Errors --}}
        @if ($errors->any())
            <div class="au-alert au-alert--error au-reveal" style="--i:2" role="alert">
                <strong>Could not sign you in</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" id="loginForm" data-au-form novalidate>
            @csrf

            <div class="au-field au-reveal" style="--i:3">
                <label class="au-label" for="email">Email address</label>
                <div class="au-control">
                    <i class="au-icon fa-regular fa-envelope" aria-hidden="true"></i>
                    <input type="email" id="email" name="email"
                           class="au-input @error('email') is-invalid @enderror"
                           placeholder="you@example.com"
                           value="{{ old('email') }}"
                           autocomplete="username" inputmode="email"
                           required data-au-focus>
                </div>
            </div>

            <div class="au-field au-reveal" style="--i:4">
                <label class="au-label" for="password">Password</label>
                <div class="au-control">
                    <i class="au-icon fa-solid fa-lock" aria-hidden="true"></i>
                    <input type="password" id="password" name="password"
                           class="au-input @error('password') is-invalid @enderror"
                           placeholder="Enter your password"
                           autocomplete="current-password" required>
                    <button type="button" class="au-toggle" data-target="password" aria-label="Show password">
                        <i class="fa-regular fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <div class="au-row au-reveal" style="--i:5">
                <label class="au-check" for="remember">
                    <input type="checkbox" id="remember" name="remember">
                    Remember me
                </label>
                <a class="au-link" href="{{ route('password.request') }}">Forgot password?</a>
            </div>

            <button type="submit" class="au-btn au-reveal" style="--i:6" id="submitBtn" data-loading="Signing in…">
                <span>Sign in</span>
                <i class="fa-solid fa-arrow-right au-btn-arrow" aria-hidden="true"></i>
            </button>
        </form>
    </x-auth-shell>
</x-guest-layout>
