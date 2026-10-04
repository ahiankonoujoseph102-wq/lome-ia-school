{{--
    Inscription à une formation gratuite. États : inscrit(e) (récapitulatif, groupe, annulation), inscriptions closes,
    complet (WhatsApp), choix de l'option. Visiteur sans compte ou adresse non confirmée : les trois étapes,
    options sélectionnables tout de suite, bouton toujours actif ; le choix est gardé pendant la création du
    compte et la confirmation de l'e-mail, puis l'inscription se termine toute seule (PendingFreeChoice).
    Une option pleine n'est plus proposée. Style stable face au mode sombre forcé : fond foncé, texte clair,
    contour doré translucide pour l'option choisie (comme le sélecteur de créneaux).
--}}
@php
    use App\Support\PhoneNumber;
    use App\Support\WhatsApp;

    $user = auth()->user();
    $date = $formation->eventDateLabel();
    $whatsappNumber = PhoneNumber::format('+'.config('site.whatsapp.number'));
    $fullMessage = 'Bonjour Lomé IA School, la formation gratuite « '.$formation->title.' » est complète. Puis-je être prévenu(e) si une place se libère ?';
@endphp

<div class="card p-5 sm:p-6" data-test="free-registration">
    @if (session('status'))
        <x-site.alert class="mb-5">{{ session('status') }}</x-site.alert>
    @endif

    @if ($registration)
        {{-- Inscrit(e) : récapitulatif, groupe et annulation --}}
        @php $option = $registration->option; @endphp
        <div data-test="free-registered">
            <div class="flex items-center gap-3">
                <span class="grid size-11 shrink-0 place-items-center rounded-full bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-400/50 ring-inset">
                    <flux:icon.check class="size-6" />
                </span>
                <h2 class="text-xl font-bold text-white">Vous êtes inscrit(e)</h2>
            </div>

            <dl class="mt-5 rounded-2xl bg-night-900 px-4 ring-1 ring-night-600 [&>div:not(:last-child)]:shadow-[inset_0_-1px_0_var(--color-night-700)]">
                @if ($option)
                    <div class="flex gap-3 py-3"><dt class="w-24 shrink-0 text-sm text-slate-400">Option</dt><dd class="font-semibold text-white">{{ $option->label }}</dd></div>
                @endif
                @if ($date)
                    <div class="flex gap-3 py-3"><dt class="w-24 shrink-0 text-sm text-slate-400">Date</dt><dd class="font-semibold text-white">{{ $date }}</dd></div>
                @endif
                @if ($option?->schedule)
                    <div class="flex gap-3 py-3"><dt class="w-24 shrink-0 text-sm text-slate-400">Horaires</dt><dd class="font-semibold text-white">{{ $option->schedule }}</dd></div>
                @endif
                @if ($option?->isOnsite() && $formation->event_location)
                    <div class="flex gap-3 py-3"><dt class="w-24 shrink-0 text-sm text-slate-400">Adresse</dt><dd class="font-semibold text-white">{{ $formation->event_location }}</dd></div>
                @elseif ($option && ! $option->isOnsite())
                    <div class="flex gap-3 py-3"><dt class="w-24 shrink-0 text-sm text-slate-400">En ligne</dt><dd class="text-white"><span class="font-semibold">Sur Google Meet.</span> Le lien Google Meet sera publié dans le groupe des inscrits.</dd></div>
                @endif
            </dl>

            <p class="mt-4 text-sm text-slate-300">Un e-mail de confirmation a été envoyé à <strong class="break-words text-white">{{ $user?->email }}</strong>.</p>

            <a href="{{ route('chat.show', $formation) }}" class="btn-gold mt-5 w-full sm:w-auto" data-test="free-open-group">
                <flux:icon.chat-bubble-left-right class="size-5" /> Ouvrir le groupe des inscrits
            </a>

            @unless ($closed)
                <form method="POST" action="{{ route('free-registration.destroy', $formation) }}" class="mt-5 pt-5 shadow-[inset_0_1px_0_var(--color-night-700)]" onsubmit="return confirm('Annuler votre inscription ? Votre place sera libérée et vous quitterez le groupe des inscrits.')">
                    @csrf
                    @method('DELETE')
                    <p class="text-sm text-slate-400">Vous ne pouvez plus venir ? Annulez pour libérer votre place.</p>
                    <button type="submit" class="mt-3 inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-rose-200 ring-1 ring-rose-400/50 hover:bg-rose-500/10" data-test="free-cancel">
                        <flux:icon.x-mark class="size-4" /> Annuler mon inscription
                    </button>
                </form>
            @endunless
        </div>
    @elseif ($closed)
        <div data-test="free-closed">
            <h2 class="text-xl font-bold text-white">Les inscriptions sont closes</h2>
            <p class="mt-2 text-sm text-slate-300">Cette formation a déjà eu lieu. Retrouvez nos autres formations dans le catalogue.</p>
            <a href="{{ route('formations.index') }}" class="btn-ghost mt-5 w-full sm:w-auto">Voir les formations</a>
        </div>
    @elseif ($options->isNotEmpty() && $availableOptions->isEmpty())
        {{-- Toutes les options sont pleines --}}
        <div data-test="free-full">
            <span class="inline-flex rounded-full px-3 py-1 text-sm font-bold text-gold-200 ring-1 ring-gold-500/60">Complet</span>
            <h2 class="mt-4 text-xl font-bold text-white">Toutes les places sont prises</h2>
            <p class="mt-2 text-sm text-slate-300">Pour toute question, écrivez-nous sur WhatsApp au <strong class="whitespace-nowrap text-white">{{ $whatsappNumber }}</strong>.</p>
            <a href="{{ WhatsApp::url($fullMessage) }}" target="_blank" rel="noopener" class="btn-ghost mt-5 w-full sm:w-auto" data-test="free-full-whatsapp">
                <x-site.whatsapp-icon class="size-5 text-[#25D366]" /> Écrire sur WhatsApp
            </a>
        </div>
    @else
        @php
            $canRegister = $user && $user->hasConfirmedEmail();
            $chosen = (string) old('option', $chosenOptionId ?? '');
            // Un choix gardé pour une option devenue pleine n'est plus présélectionné.
            $chosen = $availableOptions->contains(fn ($option) => (string) $option->id === $chosen) ? $chosen : '';
            $needsOption = $availableOptions->isNotEmpty();
        @endphp

        <h2 class="text-xl font-bold text-white">Je m'inscris gratuitement</h2>
        @if ($date)
            <p class="mt-1 text-sm text-slate-300">{{ $date }}.</p>
        @endif

        @unless ($canRegister)
            {{-- Les trois étapes, avant les options : le visiteur sait tout de suite ce qui l'attend --}}
            <ol class="mt-4 grid gap-2 sm:grid-cols-3 sm:gap-3" data-test="free-steps">
                @foreach ([
                    $needsOption ? 'Choisissez votre option' : 'Touchez « Je m\'inscris »',
                    $user ? 'Compte créé' : 'Créez votre compte (1 minute)',
                    'Confirmez votre e-mail : vous êtes inscrit',
                ] as $step)
                    @php $done = $loop->iteration === 2 && $user; @endphp
                    <li class="flex items-center gap-2.5 rounded-xl bg-night-900 px-3 py-2 text-sm text-slate-200 ring-1 ring-night-600 sm:flex-col sm:items-start sm:gap-1.5 sm:py-3">
                        <span @class([
                            'grid size-6 shrink-0 place-items-center rounded-full text-xs font-bold ring-1 ring-inset',
                            'bg-emerald-500/15 text-emerald-300 ring-emerald-400/50' => $done,
                            'bg-gold-500/10 text-gold-200 ring-gold-500/70' => ! $done,
                        ])>
                            @if ($done)
                                <flux:icon.check class="size-3.5" />
                            @else
                                {{ $loop->iteration }}
                            @endif
                        </span>
                        <span><span class="sr-only">Étape {{ $loop->iteration }} : </span>{{ $step }}</span>
                    </li>
                @endforeach
            </ol>
        @endunless

        <form
            method="POST"
            action="{{ $canRegister ? route('free-registration.store', $formation) : route('free-registration.choose', $formation) }}"
            class="mt-5"
            data-test="free-form"
            x-data="{ chosen: @js($chosen), missing: false, needsOption: @js($needsOption) }"
        >
            @csrf

            @if ($needsOption)
                <fieldset data-ama-avoid data-ama-clear data-install-avoid>
                    <legend class="mb-3 text-sm font-semibold text-white">{{ $canRegister ? 'Choisissez une seule option' : '1. Choisissez votre option' }}</legend>
                    <div class="space-y-3 rounded-2xl transition" :class="missing && 'ring-2 ring-rose-400/70 ring-offset-4 ring-offset-night-950'">
                        @foreach ($availableOptions as $option)
                            <label class="group/option flex cursor-pointer items-start gap-3 rounded-2xl bg-night-900 p-4 ring-1 ring-night-600 transition has-checked:bg-gold-500/10 has-checked:ring-2 has-checked:ring-gold-500 has-focus-visible:outline-2 has-focus-visible:outline-gold-500" data-test="free-option">
                                <input type="radio" name="option" value="{{ $option->id }}" class="peer sr-only" x-model="chosen" @change="missing = false" @checked($chosen === (string) $option->id)>
                                <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full ring-2 ring-slate-400 group-has-checked/option:ring-gold-500" aria-hidden="true">
                                    <span class="size-2.5 scale-0 rounded-full bg-gold-500 transition group-has-checked/option:scale-100"></span>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold text-white">{{ $option->label }}</span>
                                    @if ($option->schedule)
                                        <span class="block text-sm text-slate-300">{{ $option->schedule }}</span>
                                    @endif
                                    <span class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                                        <span class="inline-flex items-center gap-1 text-slate-300"><flux:icon :icon="$option->mode->icon()" class="size-4 text-electric-300" /> {{ $option->isOnsite() ? 'Présentiel à Lomé' : 'En ligne (Google Meet)' }}</span>
                                        <span class="font-semibold text-gold-200" data-test="free-seats">{{ $option->seatsLeftLabel() }}</span>
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif

            <div class="mt-5" data-ama-avoid data-ama-clear data-install-avoid>
                {{-- Message près du bouton : aucune option choisie (navigateur), ou erreur renvoyée par le serveur --}}
                <p class="mb-3 flex items-start gap-2 rounded-xl bg-rose-500/10 px-3 py-2.5 text-sm font-semibold text-rose-200 ring-1 ring-rose-400/50" role="alert" x-show="missing" style="display: none" data-test="free-missing-option">
                    <flux:icon.exclamation-circle class="mt-0.5 size-4 shrink-0" /> Choisissez d'abord une option ci-dessus.
                </p>
                @error('option')
                    <p class="mb-3 flex items-start gap-2 rounded-xl bg-rose-500/10 px-3 py-2.5 text-sm font-semibold text-rose-200 ring-1 ring-rose-400/50" role="alert" x-show="! missing" data-test="free-error">
                        <flux:icon.exclamation-circle class="mt-0.5 size-4 shrink-0" /> {{ $message }}
                    </p>
                @enderror

                @if ($canRegister && ! $formation->is_active)
                    <p class="mb-3 text-sm text-gold-200">Aperçu : l'inscription fonctionnera une fois la formation publiée.</p>
                @endif

                <button
                    type="submit"
                    name="then"
                    value="register"
                    class="btn-gold w-full"
                    data-test="free-submit"
                    @click="if (needsOption && ! chosen) { $event.preventDefault(); missing = true }"
                >
                    Je m'inscris gratuitement <flux:icon.arrow-right class="size-5" />
                </button>

                @if (! $user)
                    <p class="mt-3 text-center text-sm text-slate-300">
                        <button type="submit" name="then" value="login" class="font-semibold text-electric-300 underline-offset-4 hover:text-white hover:underline" data-test="free-login">J'ai déjà un compte</button>
                    </p>
                    <p class="mt-3 text-xs text-slate-400">C'est gratuit. Votre choix est gardé pendant la création du compte ; la place est réservée dès que votre e-mail est confirmé.</p>
                @elseif (! $canRegister)
                    <p class="mt-3 text-xs text-slate-400" data-test="free-unconfirmed">Il ne reste qu'à confirmer votre adresse e-mail ({{ $user->email }}) : vous serez inscrit(e) aussitôt après.</p>
                @else
                    <p class="mt-3 text-xs text-slate-400">Vous rejoignez aussitôt le groupe des inscrits et recevez un e-mail de confirmation.</p>
                @endif
            </div>
        </form>
    @endif
</div>
