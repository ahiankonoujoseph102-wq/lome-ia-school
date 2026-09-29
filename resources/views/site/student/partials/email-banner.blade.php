{{--
    Bandeau discret dans « Mon espace » tant que l'adresse e-mail n'est pas confirmée (caché sur la page de confirmation).
    Style stable face au mode sombre forcé : fond translucide, texte clair, contour en « ring ».
--}}
@php $bannerUser = auth()->user(); @endphp

@if ($bannerUser && ! $bannerUser->hasConfirmedEmail() && ! request()->routeIs('email-confirmation.*'))
    <div class="mx-auto max-w-6xl px-4 pt-4 sm:px-6" data-test="email-banner">
        <div class="flex items-center gap-3 rounded-2xl bg-electric-500/10 px-4 py-2.5 text-sm text-slate-200 ring-1 ring-electric-400/30 ring-inset" role="status">
            <flux:icon.envelope class="size-5 shrink-0 text-electric-300" />
            <p class="min-w-0 flex-1">Confirmez votre adresse e‑mail pour pouvoir payer une formation.</p>
            <a href="{{ route('email-confirmation.show') }}" class="inline-flex shrink-0 items-center gap-1 font-semibold text-gold-300 hover:text-gold-200">Confirmer <flux:icon.arrow-right class="size-4" /></a>
        </div>
    </div>
@endif
