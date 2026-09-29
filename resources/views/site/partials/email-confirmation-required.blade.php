{{--
    Paiement ou retrait impossible tant que l'adresse e-mail n'est pas confirmée : invitation à la confirmer,
    avec retour automatique sur cette page ensuite. Style stable face au mode sombre forcé (fond translucide,
    textes clairs, contour doré en « ring »).
--}}
@php $action ??= 'payer'; @endphp

<div class="card p-6" data-test="email-confirmation-required">
    <span class="grid size-11 place-items-center rounded-xl bg-gold-400/10 text-gold-300 ring-1 ring-gold-400/40 ring-inset">
        <flux:icon.envelope class="size-6" />
    </span>
    <h2 class="mt-4 text-lg font-semibold text-white">Confirmez votre adresse e-mail pour {{ $action }}</h2>
    <p class="mt-2 text-sm text-slate-300">
        Nous avons envoyé un code à <strong class="break-words text-white">{{ auth()->user()?->email }}</strong>.
        C'est à cette adresse que nous vous envoyons vos reçus et les informations sur vos formations.
    </p>
    <a href="{{ route('email-confirmation.show', ['retour' => request()->getRequestUri()]) }}" class="btn-gold mt-5 w-full sm:w-auto">
        <flux:icon.check-circle class="size-5" /> Confirmer mon adresse
    </a>
    <p class="mt-3 text-xs text-slate-400">Vous revenez ici juste après. Pensez à vérifier vos courriers indésirables (spam).</p>
</div>
