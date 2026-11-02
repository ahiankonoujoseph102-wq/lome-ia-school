{{--
    Page d'une formation payante : « L'essentiel » (fiche d'identité, App\Support\FormationOverview) puis la barre
    « Sur cette page » qui reste sous l'en-tête pendant le défilement (Alpine sectionNav de site.js : la partie lue
    est soulignée en doré). Uniquement des faits vrais pour toutes les formations ; aucune durée ni aucun niveau.
    Style stable face au mode sombre forcé : fonds foncés, texte clair, filets dessinés en ombre.
--}}
@php
    $essentials = \App\Support\FormationOverview::essentials($formation);
    $sections = array_filter([
        'presentation' => 'Présentation',
        'deroulement' => 'Déroulement',
        'apprendre' => $formation->outcomes ? 'Ce que vous apprendrez' : null,
        'programme' => $formation->publicCurriculum()->isNotEmpty() ? 'Programme' : null,
        'pour-qui' => $formation->audience ? 'Pour qui' : null,
        'apres' => $formation->afterTraining() ? 'Après la formation' : null,
        'formateur' => 'Formateur',
        'questions' => 'Questions',
    ]);
@endphp

<section aria-labelledby="essentiel-titre" class="bg-night-950" data-test="formation-essentials">
    <div class="mx-auto max-w-6xl px-4 pt-8 pb-10 sm:px-6">
        <h2 id="essentiel-titre" class="text-xs font-semibold tracking-[0.14em] text-gold-300 uppercase">L'essentiel</h2>
        <ul class="mt-4 grid grid-cols-2 gap-px overflow-hidden rounded-2xl bg-night-700 shadow-[0_0_0_1px_var(--color-night-700)] lg:grid-cols-4">
            @foreach ($essentials as $item)
                <li class="flex flex-col gap-3 bg-night-900 p-4 sm:flex-row sm:items-start sm:p-5" data-reveal style="--reveal-delay: {{ $loop->index * 50 }}ms">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-night-800 text-gold-300 shadow-[inset_0_0_0_1px_var(--color-night-600)]">
                        <flux:icon :icon="$item['icon']" class="size-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-white sm:text-base">{{ $item['title'] }}</span>
                        <span class="mt-0.5 block text-xs leading-snug text-slate-400 sm:text-sm">{{ $item['text'] }}</span>
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
</section>

<nav x-data="sectionNav" aria-label="Sur cette page" class="sticky top-16 z-30 bg-night-950/95 shadow-[inset_0_-1px_0_var(--color-night-700),inset_0_1px_0_var(--color-night-700)] backdrop-blur-xl" data-test="formation-section-nav">
    <ul class="scrollbar-none mx-auto flex max-w-6xl gap-1 overflow-x-auto px-2 sm:px-4" x-ref="list">
        @foreach ($sections as $id => $label)
            <li class="shrink-0">
                <a href="#{{ $id }}" data-section-link="{{ $id }}" class="section-nav-link block px-3 py-3.5 text-sm font-medium whitespace-nowrap text-slate-400 transition-colors hover:text-white">{{ $label }}</a>
            </li>
        @endforeach
    </ul>
</nav>
