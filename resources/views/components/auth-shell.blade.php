@props([
    'title' => '',
    'subtitle' => '',
    'narrow' => false,
])

@push('styles')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/auth-theme.css') }}?v=1">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/auth-theme.js') }}?v=1"></script>
@endpush

<div class="au-page">
    {{-- Decorative background: never receives clicks --}}
    <div class="au-bg" aria-hidden="true">
        <span class="au-blob au-blob--a"></span>
        <span class="au-blob au-blob--b"></span>
        <span class="au-blob au-blob--c"></span>
        <div class="au-waves">
            <svg class="au-wave-1" viewBox="0 0 2880 220" preserveAspectRatio="none"><path d="M0 110 C 240 40, 480 180, 720 110 S 1200 40, 1440 110 S 1920 180, 2160 110 S 2640 40, 2880 110 V220 H0 Z"/></svg>
            <svg class="au-wave-2" viewBox="0 0 2880 220" preserveAspectRatio="none" style="position:absolute;left:0;bottom:0"><path d="M0 140 C 300 80, 540 200, 840 140 S 1380 80, 1440 140 S 2040 200, 2340 140 S 2820 80, 2880 140 V220 H0 Z"/></svg>
        </div>
    </div>

    <main class="au-shell {{ $narrow ? 'au-shell--narrow' : '' }}">
        @unless ($narrow)
            <aside class="au-brand">
                <a href="/" class="au-logo au-reveal" style="--i:0" aria-label="Hearing Store home">
                    <img src="{{ asset('assets/images/logo-full.png') }}" alt="Hearing Store">
                </a>
                <div class="au-eq au-reveal" style="--i:1" aria-hidden="true">
                    <i></i><i></i><i></i><i></i><i></i><i></i><i></i>
                </div>
                <h1 class="au-reveal" style="--i:2">Welcome to<br><span>Hearing Store CRM</span></h1>
                <p class="au-reveal" style="--i:3">One secure place for patients, appointments, sales, inventory and service records. Built for audiology teams.</p>
                <div class="au-chips au-reveal" style="--i:4">
                    <span class="au-chip"><i class="fa-solid fa-user-doctor"></i> Patients</span>
                    <span class="au-chip"><i class="fa-solid fa-receipt"></i> Sales</span>
                    <span class="au-chip"><i class="fa-solid fa-boxes-stacked"></i> Inventory</span>
                </div>
            </aside>
        @endunless

        <section class="au-form-side">
            @if ($narrow)
                <a href="/" class="au-narrow-logo au-reveal" style="--i:0" aria-label="Hearing Store home">
                    <img src="{{ asset('assets/images/logo-full.png') }}" alt="Hearing Store">
                </a>
            @endif

            <h2 class="au-title au-reveal" style="--i:1">{{ $title }}</h2>
            @if ($subtitle)
                <p class="au-subtitle au-reveal" style="--i:2">{{ $subtitle }}</p>
            @endif

            {{ $slot }}

            <div class="au-foot au-reveal" style="--i:9">
                Powered by <b>{{ $settings->company_name ?? 'Hearing Store' }}</b>
            </div>
        </section>
    </main>
</div>
