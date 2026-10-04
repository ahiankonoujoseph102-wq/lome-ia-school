{{--
    « Dernière étape : choisissez votre option » (formation gratuite) : écran où arrive la personne juste après la
    confirmation de son e-mail ou sa connexion. Une seule action : toucher une option (gros boutons), puis
    « Je confirme mon inscription » (FreeRegistrar : une seule option par personne, verrou sur l'option).
    Une option pleine n'est pas proposée. Aucune bulle d'Ama ni bannière d'installation sur cette page (hors des
    listes fermées). Style stable face au mode sombre forcé : fond foncé, texte clair, option choisie en doré
    translucide avec contour doré (comme le sélecteur de créneaux).
--}}
@php
    $date = $formation->eventDateLabel();
@endphp

<x-layouts::site title="Choisissez votre option" :noindex="true" :whatsapp-message="\App\Support\WhatsAppMessages::free($formation)">
    <div class="mx-auto max-w-lg px-4 pt-6 pb-16 sm:pt-10" data-test="free-options-page">
        <p class="inline-flex items-center gap-2 rounded-full bg-night-900 px-3 py-1 text-sm font-semibold text-gold-200 ring-1 ring-gold-500/60">
            <flux:icon.check-circle class="size-4" /> Compte créé · Étape 3 sur 3
        </p>

        <h1 class="mt-4 text-2xl font-bold text-balance text-white sm:text-3xl">Dernière étape : choisissez votre option</h1>
        <p class="mt-2 text-base text-pretty text-slate-300">
            {{ $formation->title }}@if ($date) · {{ $date }}@endif
        </p>

        @unless ($formation->is_active)
            <p class="mt-3 text-sm text-gold-200">Aperçu : l'inscription fonctionnera une fois la formation publiée.</p>
        @endunless

        <form
            method="POST"
            action="{{ route('free-registration.store', $formation) }}"
            class="mt-6"
            data-test="free-options-form"
            x-data="{ chosen: @js((string) old('option', '')), missing: false }"
        >
            @csrf

            <fieldset data-ama-avoid data-ama-clear data-install-avoid>
                <legend class="sr-only">Choisissez une seule option</legend>
                <div class="space-y-3 rounded-2xl transition" :class="missing && 'ring-2 ring-rose-400/70 ring-offset-4 ring-offset-night-950'">
                    @foreach ($availableOptions as $option)
                        <label class="group/option flex min-h-20 cursor-pointer items-center gap-4 rounded-2xl bg-night-900 p-4 ring-1 ring-night-600 transition has-checked:bg-gold-500/10 has-checked:ring-2 has-checked:ring-gold-500 has-focus-visible:outline-2 has-focus-visible:outline-gold-500" data-test="free-option">
                            <input type="radio" name="option" value="{{ $option->id }}" class="peer sr-only" x-model="chosen" @change="missing = false" @checked((string) old('option') === (string) $option->id)>
                            <span class="grid size-7 shrink-0 place-items-center rounded-full ring-2 ring-slate-400 group-has-checked/option:ring-gold-500" aria-hidden="true">
                                <span class="size-3.5 scale-0 rounded-full bg-gold-500 transition group-has-checked/option:scale-100"></span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-lg font-semibold text-white">{{ $option->label }}</span>
                                @if ($option->schedule)
                                    <span class="block text-base text-slate-300">{{ $option->schedule }}</span>
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

            <div class="mt-6" data-ama-avoid data-ama-clear data-install-avoid>
                <p class="mb-3 flex items-start gap-2 rounded-xl bg-rose-500/10 px-3 py-2.5 text-sm font-semibold text-rose-200 ring-1 ring-rose-400/50" role="alert" x-show="missing" style="display: none" data-test="free-missing-option">
                    <flux:icon.exclamation-circle class="mt-0.5 size-4 shrink-0" /> Touchez d'abord une option ci-dessus.
                </p>
                @error('option')
                    <p class="mb-3 flex items-start gap-2 rounded-xl bg-rose-500/10 px-3 py-2.5 text-sm font-semibold text-rose-200 ring-1 ring-rose-400/50" role="alert" x-show="! missing" data-test="free-error">
                        <flux:icon.exclamation-circle class="mt-0.5 size-4 shrink-0" /> {{ $message }}
                    </p>
                @enderror

                <button
                    type="submit"
                    class="btn-gold w-full !py-4 text-lg"
                    data-test="free-submit"
                    @click="if (! chosen) { $event.preventDefault(); missing = true }"
                >
                    Je confirme mon inscription
                </button>
                <p class="mt-3 text-sm text-slate-400">Une seule option par personne. C'est gratuit.</p>
            </div>
        </form>
    </div>
</x-layouts::site>
