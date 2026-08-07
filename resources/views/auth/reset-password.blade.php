<x-guest-layout>
    <style>
        .login-wrapper {
            min-height: 100vh;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        .login-wrapper::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(232, 56, 61, 0.08) 0%, transparent 70%);
            border-radius: 50%;
        }

        .login-wrapper::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(232, 56, 61, 0.05) 0%, transparent 70%);
            border-radius: 50%;
        }

        .login-card {
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            max-width: 480px;
            width: 100%;
            position: relative;
            z-index: 1;
            border: 1px solid rgba(232, 56, 61, 0.08);
        }

        .login-header {
            background: linear-gradient(135deg, #E8383D 0%, #ef5d5f 100%);
            padding: 50px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 70%);
        }

        .login-logo {
            max-width: 220px;
            height: auto;
            margin: 0 auto;
            display: block;
            position: relative;
            z-index: 1;
        }

        .login-title {
            color: white;
            font-size: 24px;
            font-weight: 700;
            margin-top: 20px;
            margin-bottom: 5px;
            position: relative;
            z-index: 1;
        }

        .login-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
            font-weight: 400;
            position: relative;
            z-index: 1;
        }

        .login-body {
            padding: 45px 40px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            font-weight: 600;
            color: #1f2937;
            font-size: 14px;
            margin-bottom: 10px;
            display: block;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 18px;
        }

        .form-control {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px 16px 14px 45px;
            font-size: 15px;
            transition: all 0.3s ease;
            height: auto;
            width: 100%;
            background: #f9fafb;
        }

        .form-control:focus {
            border-color: #E8383D;
            box-shadow: 0 0 0 4px rgba(232, 56, 61, 0.08);
            outline: none;
            background: #ffffff;
        }

        .form-control::placeholder {
            color: #9ca3af;
        }

        .btn-login {
            background: linear-gradient(135deg, #E8383D 0%, #d32f33 100%);
            border: none;
            color: white;
            padding: 15px 30px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            width: 100%;
            transition: all 0.3s ease;
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(232, 56, 61, 0.2);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(232, 56, 61, 0.3);
            color: white;
            background: linear-gradient(135deg, #d32f33 0%, #c02a2e 100%);
        }

        .btn-login:focus {
            box-shadow: 0 0 0 4px rgba(232, 56, 61, 0.2);
            outline: none;
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .login-footer {
            background: #f9fafb;
            padding: 24px 40px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }

        .login-footer h6 {
            color: #6b7280;
            font-size: 13px;
            margin: 0;
            font-weight: 500;
        }

        .login-footer .company-name {
            color: #E8383D;
            font-weight: 600;
        }

        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }

        .back-to-login a {
            color: #E8383D;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .back-to-login a:hover {
            color: #d32f33;
            text-decoration: underline;
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 14px 18px;
            margin-bottom: 24px;
        }

        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #E8383D;
        }

        .alert-success {
            background: #ecfdf5;
            color: #047857;
            border-left: 4px solid #10b981;
        }

        .alert strong {
            font-weight: 600;
        }

        .alert ul {
            margin-bottom: 0;
            margin-top: 8px;
            padding-left: 20px;
        }

        .help-text {
            color: #6b7280;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        @media (max-width: 576px) {
            .login-body {
                padding: 35px 28px;
            }

            .login-header {
                padding: 40px 28px;
            }

            .login-footer {
                padding: 20px 28px;
            }

            .login-card {
                border-radius: 20px;
            }
        }
    </style>

    <div class="login-wrapper">
        <div class="login-card">
            <!-- Logo Section -->
            <div class="login-header">
                <a href="/">
                    <img src="{{asset('assets/images/hs-logo.png')}}" alt="Logo" class="login-logo">
                </a>
                <h2 class="login-title">Reset Password</h2>
                <p class="login-subtitle">Create a new secure password</p>
            </div>

            <!-- Form Section -->
            <div class="login-body">
                <!-- Validation Errors -->
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <strong>Whoops!</strong> There were some problems with your input.
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('password.update') }}">
                    @csrf

                    <!-- Password Reset Token -->
                    <input type="hidden" name="token" value="{{ $request->route('token') }}">

                    <!-- Email Address -->
                    <div class="form-group">
                        <label for="email">{{ __('Email Address') }}</label>
                        <div class="input-wrapper">
                            <span class="input-icon">📧</span>
                            <input id="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   type="email"
                                   name="email"
                                   value="{{ old('email', $request->email) }}"
                                   required
                                   autofocus
                                   placeholder="yourmail@example.com">
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <label for="password">{{ __('Password') }}</label>
                        <div class="input-wrapper">
                            <span class="input-icon">🔒</span>
                            <input id="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   type="password"
                                   name="password"
                                   required
                                   autocomplete="new-password"
                                   placeholder="Enter new password">
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="form-group">
                        <label for="password_confirmation">{{ __('Confirm Password') }}</label>
                        <div class="input-wrapper">
                            <span class="input-icon">🔒</span>
                            <input id="password_confirmation"
                                   class="form-control"
                                   type="password"
                                   name="password_confirmation"
                                   required
                                   autocomplete="new-password"
                                   placeholder="Confirm new password">
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-login">
                        {{ __('Reset Password') }}
                    </button>
                </form>

                <!-- Back to Login -->
                <div class="back-to-login">
                    <a href="{{ route('login') }}">
                        ← Back to login
                    </a>
                </div>
            </div>

            <!-- Footer -->
            <div class="login-footer">
                <h6>Powered by <span class="company-name">{{ $settings->company_name ?? 'Hearing Store' }}</span></h6>
            </div>
        </div>
    </div>
</x-guest-layout>
