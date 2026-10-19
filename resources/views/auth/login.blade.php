<x-guest-layout>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body, html {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background-color: #0b0510;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f172a 0%, #020617 100%);
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Abstract geometric shapes in the background */
        .shape {
            position: absolute;
            border-radius: 40px;
            background: linear-gradient(135deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01));
            backdrop-filter: blur(5px);
            z-index: 0;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .shape-1 {
            width: 450px;
            height: 140px;
            transform: rotate(-35deg);
            top: 5%;
            right: -100px;
        }
        
        .shape-2 {
            width: 350px;
            height: 350px;
            border-radius: 50%;
            bottom: -150px;
            left: -50px;
            border: 2px solid rgba(139, 92, 246, 0.1);
            background: transparent;
            backdrop-filter: none;
        }

        .shape-3 {
            width: 250px;
            height: 600px;
            transform: rotate(45deg);
            top: 10%;
            left: 20%;
            border: 1.5px solid rgba(255, 255, 255, 0.04);
            background: transparent;
            backdrop-filter: none;
            border-radius: 80px;
        }
        
        .shape-4 {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            top: 15%;
            left: 10%;
            background: radial-gradient(circle, rgba(236, 72, 153, 0.05) 0%, transparent 70%);
            border: none;
            backdrop-filter: none;
        }
        
        .shape-5 {
            width: 400px;
            height: 400px;
            border-radius: 20px;
            transform: rotate(15deg);
            bottom: -50px;
            right: 15%;
            border: 2px solid rgba(255, 255, 255, 0.02);
            background: transparent;
            backdrop-filter: none;
            z-index: 0;
        }

        .login-box {
            display: flex;
            width: 100%;
            max-width: 950px;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 20px;
            box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            overflow: hidden;
            z-index: 1;
            position: relative;
        }

        .login-left {
            flex: 1;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: #ffffff;
            position: relative;
        }

        .login-left::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(circle at top left, rgba(99, 102, 241, 0.1), transparent 60%);
            z-index: -1;
        }

        .brand-logo {
            margin-bottom: 60px;
            max-width: 160px;
            filter: brightness(0) invert(1);
        }

        .login-left h1 {
            font-size: 42px;
            font-weight: 700;
            margin: 0 0 15px 0;
            line-height: 1.2;
            letter-spacing: -0.5px;
        }
        
        .welcome-line {
            width: 35px;
            height: 3px;
            background: rgba(255, 255, 255, 0.7);
            margin-bottom: 25px;
            border-radius: 2px;
        }

        .login-left p {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.6);
            line-height: 1.6;
            margin-bottom: 40px;
            max-width: 320px;
            font-weight: 300;
        }

        .btn-outline {
            display: inline-block;
            padding: 10px 24px;
            background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            width: fit-content;
            box-shadow: 0 8px 15px -5px rgba(99, 102, 241, 0.4);
        }

        .btn-outline:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px -5px rgba(99, 102, 241, 0.5);
            color: white;
        }

        .login-right {
            width: 440px;
            padding: 50px 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.02);
            position: relative;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(25px);
            border-radius: 12px;
            padding: 45px 35px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }

        .login-right h2 {
            color: #ffffff;
            font-size: 24px;
            font-weight: 600;
            text-align: center;
            margin: 0 0 35px 0;
            position: relative;
            display: inline-block;
            left: 50%;
            transform: translateX(-50%);
        }

        .login-right h2::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 50%;
            transform: translateX(-50%);
            width: 20px;
            height: 2px;
            background: rgba(255,255,255,0.6);
            border-radius: 2px;
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            color: rgba(255, 255, 255, 0.9);
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 10px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid transparent;
            border-radius: 20px;
            color: #ffffff;
            font-size: 13px;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.05);
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
            letter-spacing: 1px;
        }
        
        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .input-group i {
            position: absolute;
            left: 16px;
            color: rgba(255, 255, 255, 0.5);
            font-size: 14px;
        }
        
        .input-group .form-control {
            padding-left: 40px;
        }
        
        .toggle-password {
            position: absolute;
            right: 16px;
            cursor: pointer;
            color: rgba(255, 255, 255, 0.5);
            transition: color 0.3s ease;
        }
        
        .toggle-password:hover {
            color: rgba(255, 255, 255, 0.9);
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
            border: none;
            border-radius: 20px;
            color: white;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 15px -5px rgba(99, 102, 241, 0.4);
            margin-top: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px -5px rgba(99, 102, 241, 0.5);
        }
        
        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .social-login {
            display: flex;
            justify-content: center;
            gap: 18px;
            margin-top: 25px;
        }

        .social-icon {
            color: rgba(255, 255, 255, 0.6);
            font-size: 16px;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
        }

        .social-icon:hover {
            color: #ffffff;
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 12px;
        }
        
        .alert-danger {
            background: rgba(220, 38, 38, 0.15);
            border: 1px solid rgba(220, 38, 38, 0.3);
            color: #fca5a5;
        }
        
        .alert-success {
            background: rgba(5, 150, 105, 0.15);
            border: 1px solid rgba(5, 150, 105, 0.3);
            color: #6ee7b7;
        }
        
        .alert ul {
            margin: 5px 0 0 0;
            padding-left: 20px;
        }

        .forgot-password {
            color: rgba(255, 255, 255, 0.6);
            font-size: 12px;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .forgot-password:hover {
            color: #ffffff;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .remember-me input[type="checkbox"] {
            accent-color: #6366f1;
            width: 14px;
            height: 14px;
            cursor: pointer;
        }
        
        .remember-me label {
            margin: 0;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            cursor: pointer;
        }
        
        .form-footer-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
        }

        @media (max-width: 860px) {
            .login-box {
                flex-direction: column;
                max-width: 480px;
            }
            .login-left {
                padding: 40px;
                text-align: center;
                align-items: center;
            }
            .welcome-line {
                margin: 0 auto 20px auto;
            }
            .login-right {
                width: 100%;
                padding: 0 40px 40px 40px;
                background: transparent;
            }
        }
    </style>
    
    <!-- Font Awesome for social icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <div class="login-container">
        <!-- Abstract Background Shapes -->
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
        <div class="shape shape-4"></div>
        <div class="shape shape-5"></div>

        <div class="login-box">
            <!-- Left Side -->
            <div class="login-left">
                <a href="/">
                    <img src="{{asset('assets/images/logo-full.png')}}" alt="HearingStore Logo" class="brand-logo">
                </a>
                <h1>Welcome to<br>Hearing Store CRM</h1>
                <div class="welcome-line"></div>
                <p>A modern, enterprise-grade platform built for audiology professionals. Streamline patient management, appointments, sales, inventory, service records, and business insights through one secure and intelligent system. Empower your team, enhance patient experience, and drive operational excellence with confidence.</p>
                <a href="#" class="btn-outline">Learn More</a>
            </div>

            <!-- Right Side (Form) -->
            <div class="login-right">
                <div class="glass-card">
                    <h2>Sign in</h2>
                    
                    <!-- Session Status -->
                    @if (session('status'))
                        <div class="alert alert-success">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if (session('login_notice'))
                        <div class="alert alert-danger">{{ session('login_notice') }}</div>
                    @endif

                    <!-- Validation Errors -->
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <strong>Whoops!</strong>
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" id="loginForm">
                        @csrf
                        
                        <div class="form-group">
                            <label for="email">Email or Username</label>
                            <div class="input-group">
                                <i class="fa-regular fa-envelope"></i>
                                <input type="email" id="email" name="email" class="form-control" placeholder="admin@example.com" value="{{ old('email') }}" required autofocus>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-group">
                                <i class="fa-solid fa-lock"></i>
                                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                                <i class="fa-regular fa-eye toggle-password" onclick="togglePasswordVisibility()"></i>
                            </div>
                        </div>
                        
                        <div class="form-footer-actions">
                            <div class="remember-me">
                                <input type="checkbox" id="remember" name="remember">
                                <label for="remember">Remember me</label>
                            </div>
                            <a href="{{ route('password.request') }}" class="forgot-password">Forgot Password?</a>
                        </div>
                        
                        <button type="submit" class="btn-submit" id="submitBtn">
                            <span>Sign In</span>
                            <i class="fa-solid fa-arrow-right ml-1"></i>
                        </button>
                    </form>
                    
                    <div class="social-login">
                        <a href="#" class="social-icon"><i class="fa-brands fa-facebook-f"></i></a>
                        <a href="#" class="social-icon"><i class="fa-brands fa-instagram"></i></a>
                        <a href="#" class="social-icon"><i class="fa-brands fa-pinterest-p"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.toggle-password');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i><span>Signing in...</span>';
        });
    </script>
</x-guest-layout>
