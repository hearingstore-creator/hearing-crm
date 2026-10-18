{{-- Deal card on the board. Params: $deal (with lead, lead.customer.membershipTier, lead.nextFollowUp, owner, vendor, stage), $can --}}
@php
    $lead = $deal->lead;
    $temp = \App\Enums\LeadTemperature::tryFrom((string) $deal->temperature);
    $tier = $lead?->customer?->membershipTier;
    $next = $deal->nextActivity;
    $nextAt = $next ? \Illuminate\Support\Carbon::parse($next->follow_up_date) : null;
    $chip = !$nextAt ? 'none' : ($nextAt->isToday() ? 'today' : ($nextAt->isPast() ? 'overdue' : 'upcoming'));
    $chipText = match ($chip) {
        'none'     => 'No activity',
        'today'    => 'Today ' . $nextAt->format('h:i A'),
        'overdue'  => 'Overdue · ' . $nextAt->format('d M'),
        default    => $nextAt->format('d M'),
    };
    $chipIcon = $next ? $next->type->icon() : 'fas fa-exclamation-triangle';
    $days = $deal->daysInStage();
    $rotting = $deal->isRotting();
    $source = $deal->source ? \App\Enums\SourceOfEntry::tryFrom($deal->source) : null;
    $ownerName = $deal->owner->name ?? 'Unassigned';
    $branchName = $deal->vendor->name ?? 'Head Office';
    $canAct = $can['move'] || $can['won'] || $can['lost'] || $can['transfer'];
@endphp
<article class="deal-card{{ $rotting ? ' is-rotting' : '' }}{{ $canAct ? '' : ' is-locked' }}" id="deal-{{ $deal->id }}"
         data-id="{{ $deal->id }}" data-stage="{{ $deal->stage_id }}" data-updated="{{ $deal->updated_at?->toIso8601String() }}"
         data-value="{{ (float) $deal->value }}" data-name="{{ $lead->full_name ?? 'Deal' }}"
         data-inquiry='@json($deal->inquiry_for ?? [])' data-vendor="{{ $deal->vendor_id }}" data-owner="{{ $deal->owner_id }}">
    <div class="dc-top">
        @if ($temp)
            <span class="dc-temp t-{{ $temp->value }}" title="{{ $temp->label() }}"><i class="{{ $temp->icon() }}"></i></span>
        @endif
        <div class="dc-who">
            @if ($can['lead'] && $lead)
                <a href="{{ url('admin/leads/' . $lead->id) }}" class="dc-name" title="Open lead profile">{{ $lead->full_name }}</a>
            @else
                <span class="dc-name">{{ $lead->full_name ?? '—' }}</span>
            @endif
            <span class="dc-phone">{{ $lead->phone_number ?? '' }}</span>
        </div>
        @if ($canAct)
            <button type="button" class="dc-menu-btn" id="deal-menu-{{ $deal->id }}" title="Move, win, lose or transfer" aria-label="Deal actions">
                <i class="fas fa-ellipsis-v"></i>
            </button>
        @endif
    </div>

    <div class="dc-title">
        {{ $deal->display_title }}
        @if ($tier)
            <span class="dc-tier" title="Member tier"><i class="fas fa-crown"></i> {{ $tier->name }}</span>
        @endif
    </div>

    <div class="dc-meta">
        <span class="dc-value{{ $deal->value ? '' : ' is-empty' }}">{{ $deal->value ? '৳' . number_format((float) $deal->value) : 'No value' }}</span>
        <span class="dc-chip chip-{{ $chip }}" title="{{ $next ? $next->type->label() . ($next->follow_up_notes ? ': ' . $next->follow_up_notes : '') : 'Nothing scheduled – plan the next step' }}"><i class="{{ $chipIcon }}"></i> {{ $chipText }}</span>
    </div>

    <div class="dc-foot">
        <span class="dc-days{{ $rotting ? ' is-rotting' : '' }}" title="{{ $rotting ? 'No progress for ' . $days . ' days' : 'Days in this stage' }}">
            <i class="far fa-clock"></i> {{ $days }}d
        </span>
        @if ($source)
            <span class="dc-source" title="Source">{{ $source->label() }}</span>
        @endif
        @if ($lead?->phone_number && $can['move'] && $deal->isOpen())
            <span class="dc-contact">
                <a href="tel:{{ $lead->phone_number }}" class="js-contact" data-deal="{{ $deal->id }}" data-channel="call" title="Call" id="dc-call-{{ $deal->id }}"><i class="fas fa-phone"></i></a>
                <a href="https://wa.me/{{ \App\Classes\Helper::normalizePhone($lead->phone_number) }}" target="_blank" rel="noopener" class="js-contact is-wa" data-deal="{{ $deal->id }}" data-channel="whatsapp" title="WhatsApp" id="dc-wa-{{ $deal->id }}"><i class="fab fa-whatsapp"></i></a>
            </span>
        @endif
        <span class="dc-owner" title="{{ $branchName }} · {{ $ownerName }}">
            @if ($deal->vendor_id)<i class="fas fa-store-alt"></i>@endif
            <span class="dc-avatar">{{ strtoupper(mb_substr($ownerName, 0, 1)) }}</span>
        </span>
    </div>
</article>
