{{--
    Inscription à une formation gratuite. États : inscrit(e) (récapitulatif, groupe, annulation), inscriptions closes,
    complet (WhatsApp), puis « compte d'abord » : visiteur sans compte (« Créer mon compte », « J'ai déjà un compte »),
    compte dont l'e-mail n'est pas confirmé (« Confirmer mon e-mail »), compte confirmé (« Choisir mon option »,
    écran séparé free-options.blade.php). Une seule action par état ; les options sont affichées en simple
    information (rien de cliquable). Formation sans option : « Je confirme mon inscription » directement.
    Style stable face au mode sombre forcé : fond foncé, texte clair, contours en « ring ».
--}}
@php
    use App\Support\PhoneNumber;
    use App\Support\WhatsApp;

    $user = auth()->user();
    $date = $formation->eventDateLabel();
    $whatsappNumber = PhoneNumber::format('+'.config('site.whatsapp.number'));
    $fullMessage = \App\Support\WhatsAppMessages::freeFull($formation);
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
            $confirmed = $user && $user->hasConfirmedEmail();
            $needsOption = $options->isNotEmpty();
            $steps = [
                'Créez votre compte',
                'Confirmez votre e-mail',
                $needsOption ? 'Choisissez votre option : vous êtes inscrit' : 'Confirmez votre inscription : vous êtes inscrit',
            ];
            $doneSteps = $confirmed ? 2 : ($user ? 1 : 0);
        @endphp

        @if (! $user)
            <h2 class="text-2xl font-bold text-balance text-white" data-test="free-start-title">Pour vous inscrire, créez d'abord votre compte</h2>
            <p class="mt-2 text-base text-slate-300">C'est gratuit et cela prend 1 minute.</p>
        @elseif (! $confirmed)
            <h2 class="text-2xl font-bold text-balance text-white" data-test="free-start-title">Confirmez votre e-mail</h2>
            <p class="mt-2 text-base text-slate-300">Votre compte est créé. Confirmez votre e-mail, puis choisissez votre option.</p>
        @elseif ($needsOption)
            <h2 class="text-2xl font-bold text-balance text-white" data-test="free-start-title">Dernière étape : choisissez votre option</h2>
            <p class="mt-2 text-base text-slate-300">Votre compte est prêt. Il ne reste qu'à choisir votre option.</p>
        @else
            <h2 class="text-2xl font-bold text-balance text-white" data-test="free-start-title">Je m'inscris gratuitement</h2>
        @endif

        @if ($date)
            <p class="mt-1 text-sm text-slate-400">{{ $date }}.</p>
        @endif

        @unless ($confirmed && ! $needsOption)
            {{-- Les trois étapes : le visiteur sait tout de suite ce qui l'attend --}}
            <ol class="mt-5 space-y-2" data-test="free-steps">
                @foreach ($steps as $step)
                    @php $done = $loop->iteration <= $doneSteps; @endphp
                    <li @class([
                        'flex items-center gap-3 rounded-xl bg-night-900 px-3.5 py-3 text-base ring-1',
                        'text-slate-300 ring-night-700' => $done,
                        'text-white ring-night-600' => ! $done,
                    ])>
                        <span @class([
                            'grid size-7 shrink-0 place-items-center rounded-full text-sm font-bold ring-1 ring-inset',
                            'bg-emerald-500/15 text-emerald-300 ring-emerald-400/50' => $done,
                            'bg-gold-500/10 text-gold-200 ring-gold-500/70' => ! $done,
                        ])>
                            @if ($done)
                                <flux:icon.check class="size-4" />
                            @else
                                {{ $loop->iteration }}
                            @endif
                        </span>
                        <span><span class="sr-only">Étape {{ $loop->iteration }}{{ $done ? ' (faite)' : '' }} : </span>{{ $step }}</span>
                    </li>
                @endforeach
            </ol>
        @endunless

        <div class="mt-6" data-ama-avoid data-ama-clear data-install-avoid>
            @error('option')
                <p class="mb-3 flex items-start gap-2 rounded-xl bg-rose-500/10 px-3 py-2.5 text-sm font-semibold text-rose-200 ring-1 ring-rose-400/50" role="alert" data-test="free-error">
                    <flux:icon.exclamation-circle class="mt-0.5 size-4 shrink-0" /> {{ $message }}
                </p>
            @enderror

            @if ($confirmed && ! $formation->is_active)
                <p class="mb-3 text-sm text-gold-200">Aperçu : l'inscription fonctionnera une fois la formation publiée.</p>
            @endif

            @if (! $user)
                <a href="{{ route('free-registration.start', $formation) }}" class="btn-gold w-full !py-4 text-lg" data-test="free-create-account">
                    Créer mon compte <flux:icon.arrow-right class="size-5" />
                </a>
                <p class="mt-4 text-center">
                    <a href="{{ route('free-registration.start', ['formation' => $formation, 'compte' => 'existant']) }}" class="text-sm font-semibold text-electric-300 underline-offset-4 hover:text-white hover:underline" data-test="free-login">J'ai déjà un compte</a>
                </p>
            @elseif (! $confirmed)
                <a href="{{ route('free-registration.start', $formation) }}" class="btn-gold w-full !py-4 text-lg" data-test="free-confirm-email">
                    Confirmer mon e-mail <flux:icon.arrow-right class="size-5" />
                </a>
                <p class="mt-3 text-center text-sm text-slate-400" data-test="free-unconfirmed">Le code est envoyé à {{ $user->email }}.</p>
            @elseif ($needsOption)
                <a href="{{ route('free-registration.options', $formation) }}" class="btn-gold w-full !py-4 text-lg" data-test="free-choose-option">
                    Choisir mon option <flux:icon.arrow-right class="size-5" />
                </a>
            @else
                <form method="POST" action="{{ route('free-registration.store', $formation) }}" data-test="free-form">
                    @csrf
                    <button type="submit" class="btn-gold w-full !py-4 text-lg" data-test="free-submit">Je confirme mon inscription</button>
                </form>
                <p class="mt-3 text-sm text-slate-400">Vous rejoignez aussitôt le groupe des inscrits et recevez un e-mail de confirmation.</p>
            @endif
        </div>

        @if ($needsOption)
            {{-- Les options, en simple information : rien à cocher ni à toucher ici --}}
            <div class="mt-8" data-test="free-options-info" data-ama-avoid data-ama-clear data-install-avoid>
                <h3 class="text-base font-semibold text-white">Les options proposées</h3>
                <p class="mt-1 text-sm text-slate-400">
                    {{ $confirmed ? 'Touchez « Choisir mon option » pour en choisir une.' : 'Vous choisirez votre option après avoir confirmé votre e-mail.' }}
                </p>
                <ul class="mt-3 rounded-2xl bg-night-900/60 px-4 ring-1 ring-night-700 [&>li:not(:last-child)]:shadow-[inset_0_-1px_0_var(--color-night-700)]">
                    @foreach ($options as $option)
                        <li class="py-3.5" data-test="free-option-info">
                            <p class="font-semibold text-white">{{ $option->label }}</p>
                            @if ($option->schedule)
                                <p class="text-sm text-slate-300">{{ $option->schedule }}</p>
                            @endif
                            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                                <span class="inline-flex items-center gap-1 text-slate-300"><flux:icon :icon="$option->mode->icon()" class="size-4 text-electric-300" /> {{ $option->isOnsite() ? 'Présentiel à Lomé' : 'En ligne (Google Meet)' }}</span>
                                @if ($option->isFull())
                                    <span class="font-semibold text-slate-400" data-test="free-seats">Complet</span>
                                @else
                                    <span class="font-semibold text-gold-200" data-test="free-seats">{{ $option->seatsLeftLabel() }}</span>
                                @endif
                            </p>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
</div>
