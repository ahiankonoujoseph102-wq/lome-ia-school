{{--
    Résumé d'une session : type, titre, date et heure de Lomé (avec l'heure du Bénin), lieu, places.
    Ne montre JAMAIS le lien Google Meet (utilisable sur les pages publiques).
--}}
@props(['session', 'showFormation' => false, 'showSeats' => true, 'headingTag' => 'h3'])

@php
    use App\LiveSessions\SessionTime;

    $neighbours = SessionTime::neighbours($session);
@endphp

<div {{ $attributes->class('min-w-0') }}>
    <div class="flex flex-wrap items-center gap-2">
        <span @class([
            'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1',
            'bg-electric-500/15 text-electric-200 ring-electric-400/40' => $session->isOnline(),
            'bg-gold-400/15 text-gold-200 ring-gold-400/40' => $session->isOnsite(),
        ])>
            <flux:icon :icon="$session->format->icon()" class="size-3.5" /> {{ $session->format->longLabel() }}
        </span>
        @if ($session->isCancelled())
            <span class="rounded-full bg-rose-500/15 px-2.5 py-1 text-xs font-semibold text-rose-200 ring-1 ring-rose-400/30">Annulée</span>
        @elseif ($session->isOnsite())
            <span class="rounded-full bg-white/[0.06] px-2.5 py-1 text-xs font-semibold text-slate-200 ring-1 ring-white/10">{{ $session->priceLabel() }}</span>
        @endif
    </div>

    <{{ $headingTag }} class="mt-2 text-lg leading-snug font-semibold text-balance text-white">{{ $session->displayTitle() }}</{{ $headingTag }}>
    @if ($showFormation)
        <p class="text-sm text-slate-400">{{ $session->formation->title }}</p>
    @endif

    <dl class="mt-3 space-y-1.5 text-sm text-slate-300">
        <div class="flex items-start gap-2">
            <dt class="sr-only">Date</dt>
            <flux:icon.calendar-days class="mt-0.5 size-4 shrink-0 text-slate-500" />
            <dd>{{ SessionTime::dayTitle($session) }}</dd>
        </div>
        <div class="flex items-start gap-2">
            <dt class="sr-only">Heure</dt>
            <flux:icon.clock class="mt-0.5 size-4 shrink-0 text-slate-500" />
            <dd>
                {{ \Illuminate\Support\Str::ucfirst(SessionTime::range($session)) }} · {{ $session->durationLabel() }}
                @if ($neighbours !== '')
                    <span class="block text-xs text-slate-500">Soit {{ $neighbours }} · même heure qu'à Lomé au Burkina Faso</span>
                @endif
            </dd>
        </div>
        @if ($session->isOnsite())
            <div class="flex items-start gap-2">
                <dt class="sr-only">Lieu</dt>
                <flux:icon.map-pin class="mt-0.5 size-4 shrink-0 text-slate-500" />
                <dd>{{ $session->location }}</dd>
            </div>
            @if ($showSeats && ! $session->isCancelled())
                @php $left = $session->seatsLeft(); @endphp
                <div class="flex items-start gap-2">
                    <dt class="sr-only">Places</dt>
                    <flux:icon.user-group class="mt-0.5 size-4 shrink-0 text-slate-500" />
                    <dd @class(['font-semibold', 'text-emerald-300' => $left > 3, 'text-gold-300' => $left > 0 && $left <= 3, 'text-rose-300' => $left === 0])>
                        {{ $left === 0 ? 'Complet · liste d\'attente ouverte' : ($left === 1 ? 'Dernière place disponible' : $left.' places restantes sur '.$session->capacity) }}
                    </dd>
                </div>
            @endif
        @endif
    </dl>
</div>
