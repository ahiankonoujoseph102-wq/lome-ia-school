{{--
    Aide « Premier paiement Mobile Money ? » de l'étape 2 du paiement (fermée par défaut) : le transfert expliqué
    pas à pas, avec le montant, le numéro et le bénéficiaire de l'opérateur choisi ;
    le bouton « Ouvrir … » n'est cité que si l'opérateur a un code USSD. Aucun code secret demandé.
--}}
<details class="group rounded-2xl bg-veil/5 ring-1 ring-veil/10" data-test="first-payment-help">
    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 px-3 py-2.5 text-sm font-semibold text-slate-200">
        <span class="flex items-center gap-2"><flux:icon.question-mark-circle class="size-4 text-gold-300" /> Premier paiement Mobile Money ?</span>
        <flux:icon.chevron-down class="size-4 text-slate-400 transition group-open:rotate-180" />
    </summary>
    <ol class="space-y-2.5 px-3 pb-3 text-sm text-slate-300">
        <li class="flex gap-2.5">
            <span class="grid size-6 shrink-0 place-items-center rounded-full bg-night-900 text-xs font-bold text-gold-200 ring-1 ring-gold-400/40">1</span>
            <span>
                @if ($method->usesApp())
                    Ouvrez l'application {{ $method->name }} sur votre téléphone.
                @elseif ($ussdCode !== null)
                    Ouvrez le menu {{ $method->name }} : touchez « Ouvrir {{ $method->name }} » ci-dessus s'il s'affiche, sinon composez le code affiché.
                @else
                    Ouvrez le menu {{ $method->name }} de votre téléphone.
                @endif
            </span>
        </li>
        <li class="flex gap-2.5">
            <span class="grid size-6 shrink-0 place-items-center rounded-full bg-night-900 text-xs font-bold text-gold-200 ring-1 ring-gold-400/40">2</span>
            <span>Choisissez le transfert d'argent, puis saisissez le numéro <span class="font-semibold whitespace-nowrap text-white">{{ $method->localNumber() }}</span>.</span>
        </li>
        <li class="flex gap-2.5">
            <span class="grid size-6 shrink-0 place-items-center rounded-full bg-night-900 text-xs font-bold text-gold-200 ring-1 ring-gold-400/40">3</span>
            <span>Saisissez le montant exact : <span class="font-semibold whitespace-nowrap text-white">{{ $amount }}</span>.</span>
        </li>
        <li class="flex gap-2.5">
            <span class="grid size-6 shrink-0 place-items-center rounded-full bg-night-900 text-xs font-bold text-gold-200 ring-1 ring-gold-400/40">4</span>
            <span>Vérifiez que le nom <span class="font-semibold text-white">{{ $method->beneficiary }}</span> s'affiche, puis validez avec votre code secret. Ne donnez jamais ce code à personne, même pas à nous.</span>
        </li>
        <li class="flex gap-2.5">
            <span class="grid size-6 shrink-0 place-items-center rounded-full bg-night-900 text-xs font-bold text-gold-200 ring-1 ring-gold-400/40">5</span>
            <span>Vous recevez un message de confirmation : faites-en une capture d'écran, puis touchez « J'ai payé : envoyer ma capture ».</span>
        </li>
    </ol>
</details>
