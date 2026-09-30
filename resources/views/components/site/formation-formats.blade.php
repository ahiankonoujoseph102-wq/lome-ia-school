@props(['formation'])

@php
    // Formation proposée en direct (config/live_offers.php) : un seul badge avec les dates.
    $liveOffer = \App\Support\LiveOffer::for($formation);
@endphp

<div {{ $attributes->class('flex flex-wrap gap-1.5') }}>
    @if ($liveOffer)
        <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-500/15 px-2.5 py-1 text-xs font-semibold text-rose-100 ring-1 ring-rose-400/40" data-test="live-offer-badge">
            <span class="size-1.5 rounded-full bg-rose-400"></span> En direct · {{ \Illuminate\Support\Str::lcfirst($liveOffer->shortPeriod()) }}
        </span>
    @else
        @foreach ($formation->formats as $format)
            <span class="inline-flex items-center gap-1 rounded-full bg-veil/5 px-2.5 py-1 text-xs text-slate-300 ring-1 ring-veil/10">
                <flux:icon :icon="$format->icon()" class="size-3.5" /> {{ $format->shortLabel() }}
            </span>
        @endforeach
    @endif
</div>
