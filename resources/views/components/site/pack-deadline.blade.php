{{--
    Fin des inscriptions d'une offre d'accompagnement : « Inscriptions ouvertes jusqu'au … » et compte à rebours
    (jours, heures, minutes) calculé à partir de la date fixe enregistrée (App\Packs\PackDeadline), le même pour
    tout le monde (l'écart d'horloge du téléphone est corrigé avec l'heure du serveur). Rien sans date ; rien non
    plus une fois la date passée (la carte affiche alors « Inscriptions closes », sans bouton d'achat).
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
            <ol class="mt-1.5 grid grid-cols-3 gap-2 text-center" data-test="pack-countdown" aria-live="off">
                @foreach (['days' => ['jour', 'jours'], 'hours' => ['heure', 'heures'], 'minutes' => ['minute', 'minutes']] as $unit => $words)
                    <li class="rounded-xl bg-night-800 px-2 py-2 shadow-[inset_0_0_0_1px_var(--color-night-600)]">
                        <span class="block text-2xl leading-none font-bold text-white tabular-nums" x-text="{{ $unit }}" data-test="pack-countdown-{{ $unit }}">{{ $remaining[$unit] }}</span>
                        <span class="mt-1 block text-xs text-slate-300" x-text="{{ $unit }} > 1 ? @js($words[1]) : @js($words[0])">{{ $remaining[$unit] > 1 ? $words[1] : $words[0] }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
        <p class="mt-3 text-sm font-semibold text-white" x-show="closed" x-cloak>Inscriptions closes</p>
    </div>
@endif
