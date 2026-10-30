<x-guest-layout>
    <x-auth-shell :narrow="true" title="Forgot password?" subtitle="Enter your email and we will send you a link to choose a new password.">

        {{-- Session Status --}}
        @if (session('status'))
            <div class="au-alert au-alert--ok au-reveal" style="--i:2" role="status">{{ session('status') }}</div>
        @endif

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

        <form method="POST" action="{{ route('password.email') }}" data-au-form novalidate>
            @csrf

            <div class="au-field au-reveal" style="--i:3">
                <label class="au-label" for="email">{{ __('Email Address') }}</label>
                <div class="au-control">
                    <i class="au-icon fa-regular fa-envelope" aria-hidden="true"></i>
                    <input id="email" type="email" name="email"
                           class="au-input @error('email') is-invalid @enderror"
                           value="{{ old('email') }}"
                           placeholder="you@example.com"
                           autocomplete="email" inputmode="email"
                           required data-au-focus>
                </div>
            </div>

            <button type="submit" class="au-btn au-reveal" style="--i:4" data-loading="Sending link…">
                <span>{{ __('Email Password Reset Link') }}</span>
                <i class="fa-solid fa-paper-plane au-btn-arrow" aria-hidden="true"></i>
            </button>
        </form>

        <div class="au-back au-reveal" style="--i:5">
            <a class="au-link" href="{{ route('login') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to login</a>
        </div>
    </x-auth-shell>
</x-guest-layout>
