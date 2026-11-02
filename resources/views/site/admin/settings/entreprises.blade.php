@php
    use App\Settings\SiteSettings;
    use App\Support\YouTube;

    $business = (array) SiteSettings::get('site.business');
    $customWork = (array) SiteSettings::get('site.custom_work');
    $workItems = array_map(fn ($item) => ['question' => $item['title'] ?? '', 'answer' => $item['text'] ?? ''], (array) ($customWork['items'] ?? []));
    $videoFormat = old('video_format', YouTube::format($business['video_url'] ?? null, $business['video_format'] ?? null));
    $formats = ['horizontal' => ['Horizontal', 'Filmée à l\'horizontale (16:9)'], 'vertical' => ['Vertical (téléphone)', 'Filmée au téléphone, tenu droit (9:16)']];
@endphp

<x-admin.page :title="$section['title']" :back="route('admin.settings.index')" back-label="Réglages du site">
    <div class="card flex flex-wrap items-center justify-between gap-3 p-4 text-sm text-slate-300">
        <p>Page « Entreprises et centres de formation », visible dans le menu, le pied de page et sur l'accueil. Son bouton « Réserver un appel gratuit » utilise la consultation gratuite (mêmes disponibilités, même lien Google Meet).</p>
        <a href="{{ route('business') }}" target="_blank" class="btn-ghost !px-4 !py-2 !text-sm">Voir la page <flux:icon.arrow-up-right class="size-4" /></a>
    </div>

    @if ($errors->any())
        <x-site.alert type="error">Vérifiez les champs signalés : {{ $errors->first() }}</x-site.alert>
    @endif

    <form method="POST" action="{{ route('admin.settings.update', $key) }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Un lien Shorts (/shorts/) choisit tout seul « Vertical » ; le choix reste modifiable. --}}
        <section class="card space-y-4 p-5 sm:p-6" x-data="{ format: @js($videoFormat) }">
            <h2 class="font-semibold text-white">Vidéo de présentation</h2>
            <x-admin.field name="video_url" label="Lien de la vidéo YouTube" type="url" :value="$business['video_url'] ?? ''" placeholder="https://www.youtube.com/watch?v=…" hint="Laissez vide pour masquer le bloc vidéo. Vidéo publique ou non répertoriée." x-on:input="format = /youtube\.com\/shorts\//i.test($event.target.value) ? 'vertical' : 'horizontal'" />

            <fieldset class="space-y-2" data-test="video-format">
                <legend class="field-label">Format de la vidéo</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($formats as $value => [$label, $hint])
                        <label class="group flex cursor-pointer items-center gap-3 rounded-2xl bg-veil/5 px-4 py-3 ring-1 ring-veil/15 transition hover:bg-veil/10 has-[:checked]:bg-gold-400/10 has-[:checked]:ring-2 has-[:checked]:ring-gold-400 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-gold-300">
                            <input type="radio" name="video_format" value="{{ $value }}" x-model="format" class="sr-only" @checked($videoFormat === $value)>
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
                <p class="text-xs text-slate-500">Un lien YouTube Shorts (…/shorts/…) choisit automatiquement « Vertical ».</p>
                @error('video_format') <p class="text-sm text-rose-300">{{ $message }}</p> @enderror
            </fieldset>
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Présentation</h2>
            <x-admin.field name="title" label="Titre de la page" type="textarea" :rows="2" :value="$business['title'] ?? ''" />
            <x-admin.list-editor name="intro" label="Texte d'introduction (un paragraphe par ligne)" :items="$business['intro'] ?? []" add-label="Ajouter un paragraphe" multiline />
            <x-admin.list-editor name="services" label="Services" :items="$business['services'] ?? []" add-label="Ajouter un service" />
        </section>


        <section class="card space-y-4 p-5 sm:p-6" data-test="admin-custom-work">
            <h2 class="font-semibold text-white">Réalisations sur mesure (« Nous le faisons pour vous »)</h2>
            <p class="text-sm text-slate-400">Affichées sur la page et sur l'accueil, avec le bouton « Demander un devis ». Les demandes arrivent par e-mail et dans « Demandes de devis ».</p>
            <input type="hidden" name="custom_work_sent" value="1">
            <x-admin.field name="custom_work_title" label="Titre" :value="$customWork['title'] ?? ''" />
            <x-admin.field name="custom_work_intro" label="Phrase d'introduction" type="textarea" :rows="2" :value="$customWork['intro'] ?? ''" />
            <x-admin.questions-editor name="custom_work_items" label="Réalisations (un titre, sa description)" :items="$workItems" question-label="Titre (ex. Site vitrine)" answer-label="Description" add-label="Ajouter une réalisation" />
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Bloc de l'accueil</h2>
            <x-admin.field name="home_title" label="Titre" :value="$business['home_title'] ?? ''" />
            <x-admin.field name="home_text" label="Texte" type="textarea" :rows="3" :value="$business['home_text'] ?? ''" />
        </section>

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer la page Entreprises</button>
    </form>
</x-admin.page>
