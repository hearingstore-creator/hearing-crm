@extends('admin.layouts.master')
@section('page_title', 'Review import')
@section('breadcrumb_parent', 'Membership & Rewards')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px; max-width: 900px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-file-import" style="color: var(--accent-500); margin-right: 8px;"></i>Review import</h1>
            <p>Nothing is saved until you press Apply</p>
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-1" id="import-preview">
        @if (count($plan['changes']))
            <table class="crm-table">
                <thead><tr><th>What</th><th>Setting</th><th>Now</th><th>After import</th></tr></thead>
                <tbody>
                    @foreach ($plan['changes'] as $c)
                        <tr><td>{{ $c['area'] }}</td><td>{{ $c['what'] }}</td><td>{{ $c['from'] }}</td><td><b>{{ $c['to'] }}</b></td></tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div style="padding:24px; color:var(--page-text-muted);" id="import-nothing">This site already has these amounts. There is nothing to change.</div>
        @endif

        @if (count($plan['left_alone']))
            <div style="padding:12px 16px; font-size:13px; color:var(--page-text-muted);">
                Milestones on this site that are not in the file stay as they are: {{ implode('; ', $plan['left_alone']) }}.
            </div>
        @endif
    </div>

    <div class="mt-3 d-flex" style="gap:10px;">
        <a href="{{ route('membership.settings') }}" class="btn btn-secondary btn-sm">Cancel</a>
        @if (count($plan['changes']))
            <form action="{{ route('membership.settings.import.apply') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm" id="btn-apply-import"><i class="fas fa-check mr-1"></i>Apply {{ count($plan['changes']) }} change(s)</button>
            </form>
        @endif
    </div>
</div>
@endsection
