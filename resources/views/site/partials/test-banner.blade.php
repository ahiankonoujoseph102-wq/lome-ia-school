{{--
    Bandeau « Version de test » (TEST_BANNER=true dans .env), en haut de toutes les pages.
    Style stable face au mode sombre forcé de Chrome : fond foncé, texte clair doré, aucun fond clair. Traits dorés
    dessinés en ombre (ring, shadow) et non en bordure : Chrome assombrit les bordures dorées (vérifié par captures).
--}}
@if (config('app.test_banner'))
    <div role="status" data-test="test-banner" class="relative z-[55] shrink-0 bg-night-900 shadow-[inset_0_-2px_0_var(--color-gold-400)] px-4 pt-[max(0.375rem,env(safe-area-inset-top))] pb-2 text-center text-[13px] leading-5 font-medium text-gold-100">
        <span class="mr-1.5 inline-block rounded-full px-2 ring-1 ring-gold-400 ring-inset text-[11px] leading-[18px] font-bold tracking-wide text-gold-300 uppercase">Test</span>{{ config('app.test_banner_text') }}
    </div>
@endif
