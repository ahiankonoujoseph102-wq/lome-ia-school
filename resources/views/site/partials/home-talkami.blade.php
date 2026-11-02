{{--
    Talkami sur l'accueil (#talkami), novembre 2026 : pas de page à part (décision de Joseph), une grande section avec
    les vrais éléments de talkami.net : logo blanc, vraies captures d'écran de l'application, promesse, quatre étapes, formules
    (réglages de l'admin), phrase de Joseph. Couleurs de Talkami (bleu nuit, orange). Liens vers talkami.net
    (?utm_source=lome-ia-school), nouvel onglet. Aucun chiffre d'élèves, avis, note ni promesse de résultat.
    Style stable face au mode sombre forcé : fonds foncés, texte clair, contours en ombre, images jamais recolorées.
--}}
@php
    use App\Support\Money;
    use App\Support\Talkami;

    $talkamiUrl = Talkami::url();
    $talkamiPlans = Talkami::plans();
@endphp

<section id="talkami" class="section scroll-mt-20 !pt-4" data-test="home-talkami">
    <div class="relative overflow-hidden rounded-[2rem] bg-[#101c45] p-5 shadow-[inset_0_0_0_1px_#24346e] sm:p-10 lg:p-12" data-reveal>
        <div class="pointer-events-none absolute -top-28 -right-24 size-80 rounded-full bg-[#f97316]/15 blur-3xl"></div>

        <div class="relative flex flex-wrap items-center justify-between gap-3">
            <img src="{{ asset(Talkami::LOGO) }}" alt="Talkami" width="320" height="89" class="h-8 w-auto sm:h-10" loading="lazy" decoding="async" data-test="home-talkami-logo">
            <span class="rounded-full bg-[#0b1433] px-3 py-1 text-xs font-semibold text-orange-200 shadow-[inset_0_0_0_1px_#f97316]">Une création de Joseph Ahiankonou</span>
        </div>

        <div class="relative mt-8 grid items-center gap-8 lg:grid-cols-[1.15fr_1fr]">
            <div>
                <p class="text-sm font-semibold text-orange-200">{{ Talkami::HEADLINE }}</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-balance text-white sm:text-5xl">
                    Parlez anglais avec <span class="whitespace-nowrap text-[#fb923c]">Teacher Joe</span>, chaque jour
                </h2>
                <p class="mt-4 text-pretty text-slate-200 sm:text-lg">{{ Talkami::PROMISE_TEXT }}</p>
                <a href="{{ $talkamiUrl }}" target="_blank" rel="noopener" class="mt-7 inline-flex w-full items-center justify-center gap-2 rounded-full bg-[#ea580c] px-6 py-4 text-base font-bold text-white shadow-[0_12px_30px_-10px_rgb(234_88_12/0.7)] transition hover:bg-[#f97316] sm:w-auto sm:text-lg" data-test="home-talkami-start">
                    <flux:icon.microphone class="size-5" variant="solid" /> {{ Talkami::BUTTON }}
                </a>
                <p class="mt-4 flex items-start gap-2 text-sm text-slate-200">
                    <flux:icon.check-circle class="mt-0.5 size-5 shrink-0 text-[#fb923c]" /> Votre niveau de A1 à C2, puis une séance d'essai gratuite de 15 minutes (une fois par compte).
                </p>
                <p class="mt-2 flex items-start gap-2 text-sm text-slate-200">
                    <flux:icon.check-circle class="mt-0.5 size-5 shrink-0 text-[#fb923c]" /> Avec Teacher Joe ou Teacher Rose, au choix.
                </p>
            </div>

            {{-- Vraie capture d'écran de Talkami (aucune simulation de l'application). --}}
            <figure class="mx-auto w-full max-w-[17rem]" data-test="home-talkami-screen">
                <img src="{{ asset(Talkami::SCREENS['seance']['image']) }}" alt="Capture d'écran de Talkami : {{ Talkami::SCREENS['seance']['caption'] }}" width="540" height="960" class="aspect-[9/16] w-full rounded-[1.75rem] object-cover shadow-[0_0_0_6px_#0b1433,0_0_0_7px_#24346e,0_30px_60px_-24px_rgb(0_0_0/0.9)]" loading="lazy" decoding="async">
                <figcaption class="mt-4 text-center text-sm text-slate-300">{{ Talkami::SCREENS['seance']['caption'] }}</figcaption>
            </figure>
        </div>

        {{-- Quatre étapes simples (talkami.net) --}}
        <ol class="relative mt-10 grid grid-cols-2 gap-3 lg:grid-cols-4" data-test="home-talkami-steps">
            @foreach (Talkami::STEPS as $step)
                <li class="rounded-2xl bg-[#0b1433] p-3.5 shadow-[inset_0_0_0_1px_#24346e] sm:p-4">
                    <span class="grid size-8 place-items-center rounded-full bg-[#101c45] text-sm font-bold text-[#fb923c] shadow-[0_0_0_1.5px_#f97316]">{{ $loop->iteration }}</span>
                    <p class="mt-3 text-sm leading-snug font-semibold text-white sm:text-base">{{ $step['title'] }}</p>
                    <p class="mt-1 text-xs leading-relaxed break-words hyphens-auto text-slate-300 sm:text-sm">{{ $step['text'] }}</p>
                </li>
            @endforeach
        </ol>

        {{-- Autres vraies captures d'écran : défilement sur le côté sur téléphone. --}}
        <div class="relative mt-10" data-test="home-talkami-screens">
            <p class="text-center text-lg font-bold text-white">Talkami en images</p>
            <ul class="-mx-5 mt-5 flex snap-x snap-mandatory gap-4 overflow-x-auto px-5 pb-2 sm:mx-0 sm:grid sm:grid-cols-3 sm:overflow-visible sm:px-0">
                @foreach (['niveau', 'programme', 'accueil'] as $screen)
                    <li class="w-[62%] shrink-0 snap-center sm:w-auto">
                        <img src="{{ asset(Talkami::SCREENS[$screen]['image']) }}" alt="Capture d'écran de Talkami : {{ Talkami::SCREENS[$screen]['caption'] }}" width="540" height="960" class="aspect-[9/16] w-full rounded-3xl object-cover shadow-[0_0_0_1px_#24346e]" loading="lazy" decoding="async">
                        <p class="mt-3 text-center text-sm text-slate-300">{{ Talkami::SCREENS[$screen]['caption'] }}</p>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Formules (réglages de l'admin, Réglages du site > Talkami) --}}
        @if ($talkamiPlans)
            <div class="relative mt-10" data-test="home-talkami-plans">
                <p class="text-center text-lg font-bold text-white">Des formules à la semaine ou au mois</p>
                <p class="mt-1 text-center text-sm text-slate-300">L'évaluation est offerte. Paiement par Mobile Money, rien n'est prélevé automatiquement.</p>
                {{-- Téléphone : une ligne par formule (nom et détail à gauche, prix à droite) ; dès sm : trois cartes. --}}
                <ul class="mt-5 grid gap-2 sm:grid-cols-3 sm:gap-3">
                    @foreach ($talkamiPlans as $plan)
                        <li class="flex items-center justify-between gap-3 rounded-2xl bg-[#0b1433] px-4 py-3 shadow-[inset_0_0_0_1px_#24346e] sm:block sm:p-4 sm:text-center">
                            <span class="min-w-0">
                                <span class="block text-xs font-semibold tracking-wide text-orange-200 uppercase sm:text-sm">{{ $plan['name'] }}</span>
                                <span class="mt-0.5 block text-xs leading-snug text-slate-300 sm:order-last sm:mt-1 sm:text-sm">{{ trim($plan['period'].($plan['detail'] !== '' ? ' · '.$plan['detail'] : ''), ' ·') }}</span>
                            </span>
                            <span class="shrink-0 text-lg font-bold whitespace-nowrap text-white sm:mt-1 sm:block sm:text-2xl">{{ Money::fcfa($plan['price']) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Le créateur --}}
        <figure class="relative mt-10 flex flex-col items-center gap-4 rounded-2xl bg-[#0b1433] p-5 text-center shadow-[inset_0_0_0_1px_#24346e] sm:flex-row sm:text-left">
            @include('site.partials.trainer-photo', ['size' => 'size-16'])
            <div class="min-w-0 flex-1">
                <blockquote class="text-slate-100">« {{ Talkami::FOUNDER_QUOTE }} »</blockquote>
                <figcaption class="mt-1 text-sm text-slate-300">Joseph Ahiankonou, fondateur de Lomé IA School et créateur de Talkami</figcaption>
            </div>
            <a href="{{ $talkamiUrl }}" target="_blank" rel="noopener" class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-4 py-2.5 text-sm font-semibold text-white shadow-[inset_0_0_0_1.5px_#f97316] hover:bg-[#101c45]" data-test="home-talkami-site">
                Découvrir Talkami <flux:icon.arrow-up-right class="size-4" />
            </a>
        </figure>
    </div>
</section>
