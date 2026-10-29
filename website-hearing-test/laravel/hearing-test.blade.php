{{-- Website page: resources/views/hearing-test.blade.php --}}
@extends('layouts.app')

@push('head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
