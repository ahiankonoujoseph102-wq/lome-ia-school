{{--
    Rappel de la formation gratuite commencée avant la création du compte (création du compte, connexion,
    confirmation de l'e-mail). Parcours actuel : aucune option encore (« Vous créez votre compte pour vous inscrire… »,
    le choix vient après la confirmation de l'e-mail). Ancien parcours : option déjà choisie, rappelée avec
    « Changer d'option ». Style stable face au mode sombre forcé : fond foncé, textes clairs, contour doré en « ring ».
--}}
@php
    use App\FreeRegistrations\PendingFreeChoice;

    $freeChoice ??= app(PendingFreeChoice::class)->current(request(), auth()->user());
    $step ??= 'register';
    $withOption = $freeChoice && $freeChoice['option'] !== null;
@endphp

@if ($freeChoice && $step === 'confirm')
    {{-- Page du code : rappel court, pour garder le champ et le bouton « Confirmer » à l'écran --}}
    <div class="flex items-start gap-2.5 rounded-2xl bg-night-900 px-4 py-3 text-left text-sm ring-1 ring-gold-500/60" data-test="free-choice-reminder">
        <flux:icon.gift class="mt-0.5 size-5 shrink-0 text-gold-200" />
        <p class="text-slate-300">
            <strong class="text-white">{{ PendingFreeChoice::reminder($freeChoice['formation'], $freeChoice['option'], 'confirm') }}</strong>
            @if ($withOption)
                Dernière étape : saisissez le code, vous serez inscrit(e) aussitôt.
            @else
                Saisissez le code. Ensuite, vous choisirez votre option.
            @endif
        </p>
    </div>
@elseif ($freeChoice)
    <div class="flex items-start gap-3 rounded-2xl bg-night-900 p-4 text-left ring-1 ring-gold-500/60" data-test="free-choice-reminder">
        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-gold-500/10 text-gold-200 ring-1 ring-gold-500/50 ring-inset">
            <flux:icon.gift class="size-5" />
        </span>
        <div class="min-w-0">
            <p class="font-semibold text-white">{{ PendingFreeChoice::reminder($freeChoice['formation'], $freeChoice['option'], $step) }}</p>
            <p class="mt-1 text-sm text-slate-300">
                @if ($withOption)
                    @if ($step === 'login')
                        Connectez-vous : votre inscription se terminera juste après.
                    @else
                        Créez votre compte, puis confirmez votre e-mail : vous serez inscrit(e) aussitôt.
                    @endif
                @elseif ($step === 'login')
                    Ensuite, vous choisirez votre option.
                @else
                    Ensuite : confirmez votre e-mail, puis choisissez votre option.
                @endif
            </p>
            @if ($withOption)
                <a href="{{ route('formations.show', $freeChoice['formation']) }}#inscription" class="mt-2 inline-block text-sm font-semibold text-electric-300 hover:text-white" data-test="free-choice-change">Changer d'option</a>
            @endif
        </div>
    </div>
@endif
