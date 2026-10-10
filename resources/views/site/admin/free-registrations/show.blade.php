{{--
    Inscriptions d'une formation gratuite : inscrits par option (nom, WhatsApp, e-mail), places prises, export CSV,
    puis réglages (date, adresse du présentiel, options : libellé, horaires, déroulement, places).
--}}
@php
    use App\Support\PhoneNumber;
    use App\Support\YouTube;

    $videoUrl = (string) $formation->presentation_video_url;
    $videoEnabled = (bool) old('presentation_video_enabled', $formation->presentation_video_enabled);
    $videoFormat = old('presentation_video_format', YouTube::format($videoUrl, $formation->presentation_video_format));
    $videoFormats = ['horizontal' => ['Horizontal', 'Filmée à l\'horizontale (16:9)'], 'vertical' => ['Vertical (téléphone)', 'Filmée au téléphone, tenu droit (9:16)']];
    $videoShown = $formation->presentationVideoId() !== null;
    $ended = (bool) old('free_ended', $formation->free_ended);
    $endedButton = $formation->endedButton();
@endphp

<x-admin.page :title="'Inscriptions : '.$formation->title" subtitle="Formation gratuite : inscrits par option, places prises et réglages de l'inscription." :back="route('admin.formations.index')" back-label="Formations" width="max-w-4xl">
    <x-slot:actions>
        <a href="{{ route('admin.free-registrations.export', $formation) }}" class="btn-ghost !py-2.5 !text-sm" data-test="free-export"><flux:icon.arrow-down-tray class="size-5" /> Exporter (CSV)</a>
    </x-slot:actions>

    <div class="card flex flex-wrap items-center gap-x-6 gap-y-2 p-4 text-sm">
        <p class="text-slate-300"><span class="text-2xl font-bold text-white" data-test="free-total">{{ $total }}</span> {{ $total > 1 ? 'inscrits' : 'inscrit' }}@if ($capacity) sur {{ $capacity }} places @endif</p>
        <p class="text-slate-400">{{ $formation->eventDateLabel() ?? 'Date non renseignée' }}</p>
        <span @class([
            'rounded-full px-2 py-0.5 text-xs font-semibold ring-1',
            'text-emerald-200 ring-emerald-400/50' => $formation->is_active,
            'text-slate-300 ring-night-600' => ! $formation->is_active,
        ])>{{ $formation->is_active ? 'Visible' : 'Masquée' }}</span>
        <a href="{{ route('formations.show', $formation) }}" class="font-semibold text-electric-300 hover:text-white">Voir la page</a>
    </div>

    {{-- Formation terminée : page au passé, inscriptions et annulations fermées, bouton vers l'accompagnement --}}
    <form method="POST" action="{{ route('admin.free-registrations.ended', $formation) }}" class="card space-y-5 p-5 sm:p-6" data-test="free-ended-settings">
        @csrf
        @method('PUT')
        <div>
            <h2 class="text-lg font-semibold text-white">Formation terminée</h2>
            <p class="mt-1 text-sm text-slate-400">Allumé : la page reste en ligne mais n'invite plus à s'inscrire (pastille « Formation terminée », ni prix, ni options, ni places, ni annulation) et affiche un grand bouton à la place. Personne ne peut plus s'inscrire ni annuler. Les inscrits et le groupe des inscrits ne changent pas.</p>
        </div>

        @if ($errors->has('free_ended_button_url') || $errors->has('free_ended_button_label'))
            <x-site.alert type="error">{{ $errors->first('free_ended_button_url') ?: $errors->first('free_ended_button_label') }}</x-site.alert>
        @endif

        <label class="flex cursor-pointer items-start justify-between gap-4" data-test="free-ended-toggle">
            <span class="min-w-0">
                <span class="block font-semibold text-white">Formation terminée</span>
                <span class="block text-sm text-slate-400">Éteint : la page se comporte comme avant.</span>
            </span>
            <input type="hidden" name="free_ended" value="0">
            <input type="checkbox" name="free_ended" value="1" @checked($ended) class="peer sr-only">
            <span class="relative mt-0.5 h-7 w-12 shrink-0 rounded-full bg-veil/10 ring-1 ring-veil/20 transition peer-checked:bg-emerald-500/30 peer-checked:ring-emerald-400 peer-focus-visible:outline-2 peer-focus-visible:outline-gold-300 after:absolute after:top-1 after:left-1 after:size-5 after:rounded-full after:bg-slate-400 after:transition peer-checked:after:translate-x-5 peer-checked:after:bg-emerald-300" aria-hidden="true"></span>
        </label>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.field name="free_ended_button_label" label="Texte du bouton" :value="$formation->free_ended_button_label" :placeholder="\App\Models\Formation::ENDED_BUTTON_LABEL" maxlength="80" hint="Vide : « Découvrir l'accompagnement »." />
            <x-admin.field name="free_ended_button_url" label="Destination du bouton" type="url" :value="$formation->free_ended_button_url" :placeholder="\App\Models\Formation::ENDED_BUTTON_PATH" hint="Une page de ce site seulement, par exemple /accompagnement ou /formations. Vide : /accompagnement." />
        </div>

        @if ($formation->isFreeEnded() && $endedButton === null)
            <p class="rounded-xl bg-night-900 px-3 py-2.5 text-sm text-gold-200 ring-1 ring-gold-500/60" data-test="free-ended-hidden-button">Aucune offre d'accompagnement n'est en vente : le bouton est masqué sur la page tant qu'aucune offre n'est publiée.</p>
        @elseif ($formation->isFreeEnded())
            <p class="text-sm text-emerald-200" data-test="free-ended-state">La page affiche « Formation terminée » et le bouton « {{ $endedButton['label'] }} ».</p>
        @endif

        <button type="submit" class="btn-gold w-full sm:w-auto" data-test="free-ended-save">Enregistrer</button>
    </form>

    {{-- Vidéo de présentation : au-dessus du bloc d'inscription de la page publique. Un lien Shorts choisit « Vertical ». --}}
    <form method="POST" action="{{ route('admin.free-registrations.video', $formation) }}" class="card space-y-5 p-5 sm:p-6" x-data="{ format: @js($videoFormat) }" data-test="free-video-settings">
        @csrf
        @method('PUT')
        <div>
            <h2 class="text-lg font-semibold text-white">Vidéo de présentation</h2>
            <p class="mt-1 text-sm text-slate-400">Affichée sur la page, juste au-dessus du bloc d'inscription, avec le titre « Coach Joseph vous présente la formation ». Seule l'image d'aperçu se charge ; YouTube ne démarre que si le visiteur touche lecture.</p>
        </div>

        @if ($errors->has('presentation_video_url') || $errors->has('presentation_video_format'))
            <x-site.alert type="error">{{ $errors->first('presentation_video_url') ?: $errors->first('presentation_video_format') }}</x-site.alert>
        @endif

        <label class="flex cursor-pointer items-start justify-between gap-4" data-test="free-video-toggle">
            <span class="min-w-0">
                <span class="block font-semibold text-white">Afficher la vidéo</span>
                <span class="block text-sm text-slate-400">Éteint : la page s'affiche sans vidéo, le lien reste enregistré.</span>
            </span>
            <input type="hidden" name="presentation_video_enabled" value="0">
            <input type="checkbox" name="presentation_video_enabled" value="1" @checked($videoEnabled) class="peer sr-only">
            <span class="relative mt-0.5 h-7 w-12 shrink-0 rounded-full bg-veil/10 ring-1 ring-veil/20 transition peer-checked:bg-emerald-500/30 peer-checked:ring-emerald-400 peer-focus-visible:outline-2 peer-focus-visible:outline-gold-300 after:absolute after:top-1 after:left-1 after:size-5 after:rounded-full after:bg-slate-400 after:transition peer-checked:after:translate-x-5 peer-checked:after:bg-emerald-300" aria-hidden="true"></span>
        </label>

        <x-admin.field name="presentation_video_url" label="Vidéo de présentation (lien YouTube)" type="url" :value="$videoUrl" placeholder="https://youtu.be/…" hint="Accepte youtube.com/watch, youtu.be et youtube.com/shorts. Vidéo publique ou non répertoriée, intégration autorisée. Laissez vide pour ne pas afficher de vidéo." x-on:input="format = /youtube\.com\/shorts\//i.test($event.target.value) ? 'vertical' : 'horizontal'" />

        <fieldset class="space-y-2" data-test="free-video-format">
            <legend class="field-label">Format de la vidéo</legend>
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($videoFormats as $value => [$label, $hint])
                    <label class="group flex cursor-pointer items-center gap-3 rounded-2xl bg-veil/5 px-4 py-3 ring-1 ring-veil/15 transition hover:bg-veil/10 has-[:checked]:bg-gold-400/10 has-[:checked]:ring-2 has-[:checked]:ring-gold-400 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-gold-300">
                        <input type="radio" name="presentation_video_format" value="{{ $value }}" x-model="format" class="sr-only" @checked($videoFormat === $value)>
                        <span @class(['grid shrink-0 place-items-center rounded-md ring-1 ring-veil/30 group-has-[:checked]:ring-gold-400', 'h-5 w-8' => $value === 'horizontal', 'h-8 w-5' => $value === 'vertical'])>
                            <flux:icon.play class="size-3 text-slate-400 group-has-[:checked]:text-gold-300" variant="solid" />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-slate-100">{{ $label }}</span>
                            <span class="block text-xs text-slate-400">{{ $hint }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-slate-400">Un lien YouTube Shorts (…/shorts/…) choisit automatiquement « Vertical ».</p>
        </fieldset>

        <p @class(['text-sm', 'text-emerald-200' => $videoShown, 'text-slate-400' => ! $videoShown]) data-test="free-video-state">
            {{ $videoShown ? 'La vidéo est affichée sur la page.' : 'Aucune vidéo affichée sur la page pour le moment.' }}
        </p>

        <button type="submit" class="btn-gold w-full sm:w-auto" data-test="free-video-save">Enregistrer la vidéo</button>
    </form>

    {{-- Inscrits par option --}}
    @php
        $groups = $options->map(fn ($option) => ['option' => $option, 'items' => $registrationsByOption->get((string) $option->id, collect())]);
        if ($registrationsByOption->has('')) {
            $groups->push(['option' => null, 'items' => $registrationsByOption->get('')]);
        }
    @endphp

    @forelse ($groups as $group)
        <section class="card overflow-hidden" data-test="free-admin-option">
            <header class="flex flex-wrap items-center justify-between gap-2 p-4">
                <div class="min-w-0">
                    <h2 class="font-semibold text-white">{{ $group['option']?->label ?? 'Sans option' }}</h2>
                    @if ($group['option'])
                        <p class="text-sm text-slate-400">{{ $group['option']->mode->label() }}@if ($group['option']->schedule) · {{ $group['option']->schedule }} @endif</p>
                    @endif
                </div>
                @if ($group['option'])
                    <span @class([
                        'rounded-full px-3 py-1 text-sm font-semibold ring-1',
                        'text-gold-200 ring-gold-500/60' => $group['option']->isFull(),
                        'text-slate-200 ring-night-600' => ! $group['option']->isFull(),
                    ]) data-test="free-admin-taken">{{ $group['option']->seatsTaken() }} / {{ $group['option']->capacity }} places prises{{ $group['option']->isFull() ? ' · Complet' : '' }}</span>
                @endif
            </header>
            @if ($group['items']->isEmpty())
                <p class="border-t border-veil/10 px-4 py-3 text-sm text-slate-400">Aucun inscrit pour le moment.</p>
            @else
                <ol class="divide-y divide-veil/10 border-t border-veil/10">
                    @foreach ($group['items'] as $registration)
                        <li class="flex flex-col gap-1 px-4 py-3 text-sm sm:flex-row sm:items-center sm:gap-4">
                            <a href="{{ route('admin.students.show', $registration->user) }}" class="min-w-0 font-semibold text-white hover:text-electric-200 sm:w-48 sm:truncate">{{ $registration->user->name }}</a>
                            <a href="https://wa.me/{{ ltrim((string) $registration->user->phone, '+') }}" target="_blank" rel="noopener" class="whitespace-nowrap text-slate-200 hover:text-white">{{ PhoneNumber::format($registration->user->phone) }}</a>
                            <span class="min-w-0 break-words text-slate-300 sm:flex-1">{{ $registration->user->email }}</span>
                            <span class="text-xs text-slate-400">{{ $registration->created_at?->translatedFormat('j M à H\hi') }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    @empty
        <p class="card p-4 text-sm text-slate-400">Aucune option : ajoutez-en ci-dessous.</p>
    @endforelse

    {{-- Réglages de l'inscription --}}
    <form method="POST" action="{{ route('admin.free-registrations.update', $formation) }}" class="card space-y-5 p-5 sm:p-6">
        @csrf
        @method('PUT')
        <h2 class="text-lg font-semibold text-white">Réglages de l'inscription</h2>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.field name="event_date" label="Date de la formation" type="date" :value="$formation->event_date?->format('Y-m-d')" hint="Les inscriptions se ferment le lendemain." />
            <x-admin.field name="event_location" label="Adresse du présentiel" :value="$formation->event_location" />
        </div>

        <div class="space-y-4">
            <p class="field-label">Options (une seule par personne)</p>
            @foreach ($options->concat([null]) as $index => $option)
                <fieldset class="space-y-3 rounded-2xl bg-night-900 p-4 ring-1 ring-night-600" data-test="free-option-row">
                    <legend class="sr-only">{{ $option ? 'Option '.$option->label : 'Nouvelle option' }}</legend>
                    @if ($option)
                        <input type="hidden" name="options[{{ $index }}][id]" value="{{ $option->id }}">
                    @else
                        <p class="text-sm font-semibold text-slate-200">Ajouter une option (laisser vide sinon)</p>
                    @endif
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-admin.field :name="'options['.$index.'][label]'" label="Libellé" :value="$option?->label" placeholder="Ex. Présentiel journée" />
                        <x-admin.field :name="'options['.$index.'][schedule]'" label="Horaires" :value="$option?->schedule" placeholder="Ex. 9 h à 16 h" />
                        <x-admin.field :name="'options['.$index.'][mode]'" label="Déroulement" type="select" :options="$modes" :value="$option?->mode->value ?? 'onsite'" />
                        <x-admin.field :name="'options['.$index.'][capacity]'" label="Places" type="number" :value="$option?->capacity" :hint="$option ? $option->seatsTaken().' déjà prise(s)' : null" />
                    </div>
                    @if ($option)
                        <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                            <input type="checkbox" name="options[{{ $index }}][remove]" value="1" class="size-4 rounded accent-rose-500">
                            Supprimer cette option (seulement si personne ne s'y est inscrit)
                        </label>
                    @endif
                </fieldset>
            @endforeach
            @if ($options->count() >= $maxOptions)
                <p class="text-xs text-slate-400">{{ $maxOptions }} options au maximum.</p>
            @endif
        </div>

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer</button>
    </form>
</x-admin.page>
