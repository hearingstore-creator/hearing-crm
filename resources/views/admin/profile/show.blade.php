@extends('admin.layouts.master')
@section('page_title', 'My Profile')

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <style>
        .pf-grid { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:20px; }
        @media (max-width:900px) { .pf-grid { grid-template-columns:1fr; } }
        .pf-card { background:var(--card-bg); border:1px solid var(--card-border); border-radius:14px; padding:16px; margin-bottom:16px; }
        .pf-card h3 { font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--page-text-muted); margin:0 0 10px; }
        .pf-row { display:flex; justify-content:space-between; gap:10px; padding:6px 0; border-bottom:1px solid var(--card-border); font-size:13px; }
        .pf-row:last-child { border-bottom:0; }
        .pf-row span:first-child { color:var(--page-text-muted); }
    </style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-user-circle" style="color: var(--accent-500); margin-right: 8px;"></i>My Profile</h1>
            <p>Your account and sign-in security</p>
        </div>
    </div>

    @include('admin.alert_message')
    @if ($forced)
        <div class="alert alert-danger m-2" id="forced-notice">You must choose a new password before you can use the CRM.</div>
    @endif

    <div class="pf-grid">
        <div>
            <div class="pf-card">
                <h3>Account</h3>
                <div class="pf-row"><span>Name</span><span>{{ $user->name }}</span></div>
                <div class="pf-row"><span>Email</span><span>{{ $user->email }}</span></div>
                <div class="pf-row"><span>Role</span><span>{{ $user->roles->pluck('name')->join(', ') ?: '—' }}</span></div>
                <div class="pf-row"><span>Branch</span><span>{{ $user->isVendor() ? $user->name : ($user->branch->name ?? 'Head office') }}</span></div>
            </div>

            <div class="pf-card" id="recent-signins">
                <h3>Recent sign-ins</h3>
                @forelse ($events as $e)
                    <div class="pf-row">
                        <span>{{ $e->created_at->format('d M, h:i A') }}</span>
                        <span>{{ ['login' => 'Signed in', 'failed' => 'Wrong password', 'blocked' => 'Blocked'][$e->event] ?? $e->event }} · {{ $e->ip }}</span>
                    </div>
                @empty
                    <div style="color:var(--page-text-muted); font-size:13px;">Nothing recorded yet.</div>
                @endforelse
            </div>
        </div>

        <div class="pf-card">
            <h3>Change password</h3>
            <form method="POST" action="{{ route('profile.password') }}" autocomplete="off">
                @csrf @method('PUT')
                <div class="form-group">
                    <label for="current_password">Current password</label>
                    <input type="password" class="form-control @error('current_password') is-invalid @enderror" name="current_password" id="current_password" autocomplete="current-password" required>
                    @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label for="password">New password</label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" id="password" minlength="8" autocomplete="new-password" required>
                    <small class="form-text text-muted">At least 8 characters.</small>
                    @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label for="password_confirmation">Confirm new password</label>
                    <input type="password" class="form-control" name="password_confirmation" id="password_confirmation" minlength="8" autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-sm" id="btn-change-password"><i class="fas fa-key mr-1"></i>Change password</button>
            </form>
        </div>
    </div>
</div>
@endsection
