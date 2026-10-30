{{-- Website page: resources/views/hearing-test.blade.php --}}
@extends('layouts.app')

@push('head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="{{ asset('hearing-test/hearing-test.css') }}">
@endpush

@section('content')
    <div id="hearing-test"></div>
@endsection

@push('scripts')
    <script src="{{ asset('hearing-test/hearing-test.js') }}"
            data-lang="{{ app()->getLocale() === 'bn' ? 'bn' : 'en' }}"
            data-phone="{{ config('hearing_test.phone') }}"
            data-whatsapp="{{ config('hearing_test.whatsapp') }}"
            data-booking-url="{{ config('hearing_test.booking_url') }}"
            @if (config('hearing_test.crm_enabled')) data-lead-url="{{ route('hearing-test.lead') }}" @endif
            defer></script>
@endpush
