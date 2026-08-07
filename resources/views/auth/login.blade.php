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
            margin-bottom: 24px;
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

        .form-check {
            margin-bottom: 24px;
            display: flex;
            align-items: center;
        }

        .form-check-input {
            width: 18px;
            height: 18px;
            margin-right: 10px;
            border: 2px solid #d1d5db;
            border-radius: 4px;
            cursor: pointer;
        }

        .form-check-input:checked {
            background-color: #E8383D;
            border-color: #E8383D;
        }

        .form-check-input:focus {
            border-color: #E8383D;
            box-shadow: 0 0 0 3px rgba(232, 56, 61, 0.1);
        }

        .form-check-label {
            font-weight: 500;
            color: #6b7280;
            font-size: 14px;
            cursor: pointer;
            margin-bottom: 0;
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
            margin-top: 0;
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
                <h2 class="login-title">Welcome Back</h2>
                <p class="login-subtitle">Sign in to continue to your account</p>
            </div>

            <!-- Form Section -->
            <div class="login-body">
                <!-- Session Status -->
                @if (session('status'))
                    <div class="alert alert-success" role="alert">
                        <strong>Success!</strong> {{ session('status') }}
                    </div>
                @endif

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

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <!-- Email Address -->
                    <div class="form-group">
                        <label for="email">{{ __('Email Address') }}</label>
                        <div class="input-wrapper">
                            <span class="input-icon">📧</span>
                            <input id="email" 
                                   type="email" 
                                   class="form-control @error('email') is-invalid @enderror" 
                                   name="email" 
                                   value="{{ old('email') }}" 
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
                                   type="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   name="password"
                                   required
                                   autocomplete="current-password"
                                   placeholder="Enter your password">
                        </div>
                        <div class="text-end mt-2">
                            <a href="{{ route('password.request') }}" class="text-decoration-none" style="color: #E8383D; font-size: 13px; font-weight: 600;">
                                Forgot Password?
                            </a>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-login">
                        {{ __('Sign In') }}
                    </button>
                </form>
            </div>

            <!-- Footer -->
            <div class="login-footer">
                <h6>Powered by <span class="company-name">{{ $settings->company_name ?? 'Hearing Store' }}</span></h6>
            </div>
        </div>
    </div>
</x-guest-layout>
