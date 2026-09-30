@props(['offer', 'hasAccess' => false])

@php
    /** @var \App\Support\LiveOffer $offer */
    $next = $offer->nextSession();
@endphp

{{--
    Encadré « Formation en direct » (config/live_offers.php).
    Style stable face au mode sombre forcé de Chrome : fonds foncés ou translucides, textes clairs,
    contours dessinés en « ring » (jamais de texte foncé sur fond clair).
--}}
<section id="formation-en-direct" {{ $attributes->class('scroll-mt-24 overflow-hidden rounded-3xl bg-night-900 ring-1 ring-gold-500/50') }} data-test="live-offer">
    <div class="p-5 sm:p-7">
        <p class="inline-flex items-center gap-2 rounded-full bg-rose-500/15 px-3 py-1 text-xs font-bold tracking-wide text-rose-200 uppercase ring-1 ring-rose-400/40">
            <span class="relative flex size-2">
                <span class="absolute inline-flex size-full animate-ping rounded-full bg-rose-400 opacity-75 motion-reduce:hidden"></span>
                <span class="relative inline-flex size-2 rounded-full bg-rose-500"></span>
            </span>
            En direct sur Google Meet
        </p>
        <h2 class="mt-4 text-2xl font-bold text-white sm:text-3xl">Formation en direct</h2>

        <dl class="mt-5 grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl bg-night-800 p-4 ring-1 ring-night-600">
                <dt class="flex items-center gap-2 text-sm text-slate-400"><flux:icon.calendar-days class="size-4 text-gold-400" /> Dates</dt>
                <dd class="mt-1 font-semibold text-white" data-test="live-offer-period">{{ $offer->period() }}</dd>
                <dd class="mt-0.5 text-sm text-slate-300">{{ $offer->sessionCount() }} séances, une par soir</dd>
            </div>
            <div class="rounded-2xl bg-night-800 p-4 ring-1 ring-night-600">
                <dt class="flex items-center gap-2 text-sm text-slate-400"><flux:icon.clock class="size-4 text-gold-400" /> Horaires</dt>
                <dd class="mt-1 font-semibold text-white" data-test="live-offer-schedule">{{ $offer->schedule() }}</dd>
                <dd class="mt-0.5 text-sm text-slate-300">{{ $offer->neighbours() }}</dd>
            </div>
        </dl>

        @if ($next)
            <p class="mt-4 rounded-2xl bg-electric-500/10 px-4 py-3 text-sm text-slate-200 ring-1 ring-electric-400/30" data-test="live-offer-next">
                <span class="font-semibold text-white">{{ $next['state'] === 'live' ? 'Séance en cours' : ($next['number'] === 1 ? 'Première séance' : 'Prochaine séance') }} :</span>
                {{ $next['day'] }}, {{ $offer->hours() }}. {{ $next['title'] }}.
                @if ($next['number'] > 1)
                    Les séances passées se rattrapent avec les enregistrements.
                @endif
            </p>
        @endif

        {{-- Déroulement --}}
        <h3 class="mt-7 text-lg font-bold text-white">Déroulement</h3>
        <ul class="mt-4 grid gap-4 sm:grid-cols-2">
            @foreach ($offer->details() as $detail)
                <li class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-gold-400/15 text-gold-300">
                        <flux:icon :icon="$detail['icon']" class="size-5" />
                    </span>
                    <span>
                        <span class="block font-semibold text-white">{{ $detail['title'] }}</span>
                        <span class="block text-sm text-slate-300">{{ $detail['text'] }}</span>
                    </span>
                </li>
            @endforeach
        </ul>

        {{-- Programme des séances --}}
        <h3 class="mt-8 text-lg font-bold text-white">Programme</h3>
        <ol class="mt-4 space-y-3" data-test="live-offer-program">
            @foreach ($offer->sessions() as $session)
                <li @class([
                    'flex gap-3 rounded-2xl p-4 ring-1',
                    'bg-night-800 ring-night-600' => $session['state'] !== 'live',
                    'bg-electric-500/10 ring-electric-400/40' => $session['state'] === 'live',
                ])>
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-electric-500/15 text-sm font-bold text-electric-300 ring-1 ring-electric-400/25">{{ $session['number'] }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm text-slate-400">{{ $session['day'] }} · {{ $offer->hours() }}</span>
                        <span class="mt-0.5 block font-semibold text-white">{{ $session['title'] }}</span>
                        @if ($session['state'] === 'past')
                            <span class="mt-1 inline-block text-xs text-slate-400">Terminée : enregistrement disponible dans le groupe d'accompagnement</span>
                        @elseif ($session['state'] === 'live')
                            <span class="mt-1 inline-block text-xs font-semibold text-electric-200">En cours</span>
                        @endif
                    </span>
                </li>
            @endforeach
        </ol>

        {{-- Appel à l'action --}}
        <div class="mt-7 pt-6 shadow-[inset_0_1px_0_var(--color-night-700)]">
            @if ($hasAccess)
                <p class="text-sm text-slate-300">Vous êtes inscrit(e) : le lien Google Meet de chaque séance se trouve dans « Mon espace », rubrique « Sessions ».</p>
                <a href="{{ route('student.sessions.index') }}" class="btn-gold mt-4 w-full sm:w-auto" data-test="live-offer-sessions">Voir mes séances et le lien Google Meet</a>
            @else
                <a href="{{ route('checkout.show', $offer->formation) }}" class="btn-gold w-full sm:w-auto" data-test="live-offer-buy">Je m'inscris · {{ $offer->formation->formatted_price }} <flux:icon.arrow-right class="size-5" /></a>
                <p class="mt-3 text-sm text-slate-400">Paiement par Mobile Money. Inscription possible pendant toute la semaine, places non limitées.</p>
            @endif
        </div>
    </div>
</section>
