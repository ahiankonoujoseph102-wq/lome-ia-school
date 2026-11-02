{{--
    « Comment se déroule la formation » : les vraies étapes, du paiement Mobile Money à l'attestation
    (App\Support\FormationOverview::journey). Lignes entre les étapes qui se tracent au défilement (.journey-line, transform
    seulement, coupée si « moins d'animations »). Style stable face au mode sombre forcé.
--}}
@php($journey = \App\Support\FormationOverview::journey($formation))

<section id="deroulement" class="scroll-mt-32" data-test="formation-journey">
    <h2 class="text-2xl font-bold text-white sm:text-3xl" data-reveal>Comment se déroule la formation</h2>
    <ol class="relative mt-7">
        @foreach ($journey as $step)
            <li class="relative flex gap-4 pb-7 last:pb-0 sm:gap-5" data-reveal style="--reveal-delay: {{ $loop->index * 90 }}ms">
                @unless ($loop->last)
                    <span aria-hidden="true" class="journey-line absolute top-10 bottom-0 left-5 w-px bg-linear-to-b from-gold-500 to-electric-500" data-reveal style="--reveal-delay: {{ $loop->index * 90 + 150 }}ms"></span>
                @endunless
                <span class="relative z-10 grid size-10 shrink-0 place-items-center rounded-full bg-night-900 text-gold-300 shadow-[0_0_0_1.5px_var(--color-gold-500),0_0_0_6px_var(--color-night-950)]">
                    <flux:icon :icon="$step['icon']" class="size-5" />
                </span>
                <div class="min-w-0 pt-1.5">
                    <p class="text-xs font-semibold tracking-wide text-slate-400 uppercase">Étape {{ $loop->iteration }}</p>
                    <h3 class="mt-0.5 font-semibold text-white sm:text-lg">{{ $step['title'] }}</h3>
                    <p class="mt-1 text-sm leading-relaxed text-slate-300 sm:text-base">{{ $step['text'] }}</p>
                </div>
            </li>
        @endforeach
    </ol>
</section>
