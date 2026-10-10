{{--
    Fin des inscriptions d'une offre d'accompagnement : « Inscriptions ouvertes jusqu'au … » et compte à rebours
    (jours, heures, minutes et secondes, mis à jour à chaque seconde) calculé à partir de la date fixe enregistrée
    (App\Packs\PackDeadline), le même pour tout le monde (l'écart d'horloge du téléphone est corrigé avec l'heure
    du serveur, resources/js/countdown.js). Les chiffres sont cachés aux lecteurs d'écran, qui lisent à la place
    un texte à la minute près, jamais annoncé tout seul ; le léger fondu des secondes est coupé si le téléphone
    demande moins d'animations. Rien sans date. Une fois la
    date passée : mention « Inscriptions closes » à la place de l'encadré (la carte n'a plus de bouton d'achat).
    À la fin du compte à rebours, l'événement « pack-registrations-closed » masque le bouton d'achat sans attendre
    (le serveur refuse de toute façon les nouvelles commandes).
    Style stable face au mode sombre forcé : fond foncé, textes clairs, contour doré dessiné en ombre.
--}}
@props(['pack'])

@php
    use App\Packs\PackDeadline;

    $deadline = $pack->registrationDeadline();
    $remaining = $deadline ? PackDeadline::remaining($deadline) : null;
@endphp

@if ($deadline && ! $remaining['closed'])
    <div
        {{ $attributes->class('rounded-2xl bg-night-900 p-4 shadow-[inset_0_0_0_1px_var(--color-gold-500)]') }}
        x-data="packCountdown(@js(['deadline' => $deadline->getTimestampMs(), 'serverNow' => now()->getTimestampMs(), 'slug' => $pack->slug]))"
        data-test="pack-deadline"
    >
        <p class="flex items-start gap-2 text-sm font-semibold text-gold-100" data-test="pack-deadline-label">
            <flux:icon.clock class="mt-0.5 size-4 shrink-0 text-gold-400" />
            <span>Inscriptions ouvertes jusqu'au {{ PackDeadline::label($deadline) }}</span>
        </p>
        <div x-show="! closed">
            <p class="mt-3 text-xs text-slate-400">Clôture dans</p>
            <p class="sr-only" x-text="label" data-test="pack-countdown-spoken">{{ PackDeadline::spokenRemaining($remaining) }}</p>
            <ol class="mt-1.5 grid grid-cols-4 gap-1.5 text-center sm:gap-2" data-test="pack-countdown" aria-hidden="true">
                @foreach (['days' => ['jour', 'jours'], 'hours' => ['heure', 'heures'], 'minutes' => ['minute', 'minutes'], 'seconds' => ['seconde', 'secondes']] as $unit => $words)
                    <li class="min-w-0 rounded-xl bg-night-800 px-1 py-2 shadow-[inset_0_0_0_1px_var(--color-night-600)]">
                        <span
                            @class(['block text-2xl leading-none font-bold text-white tabular-nums', 'countdown-second' => $unit === 'seconds'])
                            x-text="{{ $unit === 'days' ? 'days' : 'pad('.$unit.')' }}"
                            @if ($unit === 'seconds') :class="seconds % 2 ? 'countdown-tick-a' : 'countdown-tick-b'" @endif
                            data-test="pack-countdown-{{ $unit }}">{{ $unit === 'days' ? $remaining[$unit] : str_pad((string) $remaining[$unit], 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="mt-1 block truncate text-[11px] text-slate-300 sm:text-xs" x-text="{{ $unit }} > 1 ? @js($words[1]) : @js($words[0])">{{ $remaining[$unit] > 1 ? $words[1] : $words[0] }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
        <p class="mt-3 text-sm font-semibold text-white" x-show="closed" x-cloak>Inscriptions closes</p>
    </div>
@elseif ($deadline)
    {{-- Inscriptions closes : mention à la place du compte à rebours, en haut de la carte. --}}
    <p {{ $attributes->class('flex items-center gap-2 rounded-2xl bg-night-900 px-4 py-3 text-sm font-semibold text-slate-200 shadow-[inset_0_0_0_1px_var(--color-night-600)]') }} data-test="pack-closed-notice">
        <flux:icon.lock-closed class="size-4 shrink-0 text-slate-400" /> Inscriptions closes
    </p>
@endif
