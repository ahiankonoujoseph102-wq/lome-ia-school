@php
    use App\Chat\ChatFeatures;
    use App\Settings\SiteSettings;

    $features = (array) SiteSettings::get('site.chat_features');
@endphp

<x-admin.page :title="$section['title']" :back="route('admin.settings.index')" back-label="Réglages du site">
    <div class="card p-4 text-sm text-slate-300">
        <p>Chaque nouvelle fonction des groupes de discussion a son interrupteur. Éteinte, son bouton n'apparaît pas et le groupe fonctionne exactement comme avant. Les messages déjà envoyés restent affichés.</p>
    </div>

    @if ($errors->any())
        <x-site.alert type="error">{{ $errors->first() }}</x-site.alert>
    @endif

    <form method="POST" action="{{ route('admin.settings.update', $key) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="card divide-y divide-veil/10 p-0">
            @foreach (ChatFeatures::LABELS as $feature => $label)
                <label class="flex cursor-pointer items-start justify-between gap-4 p-5 sm:p-6" data-test="chat-feature-{{ $feature }}">
                    <span class="min-w-0">
                        <span class="block font-semibold text-white">{{ $label['title'] }}</span>
                        <span class="mt-0.5 block text-sm text-slate-400">{{ $label['text'] }}</span>
                    </span>
                    <input type="hidden" name="features[{{ $feature }}]" value="0">
                    <input type="checkbox" name="features[{{ $feature }}]" value="1" @checked((bool) old('features.'.$feature, $features[$feature] ?? false)) class="peer sr-only">
                    <span class="relative mt-0.5 h-7 w-12 shrink-0 rounded-full bg-veil/10 ring-1 ring-veil/20 transition peer-checked:bg-emerald-500/30 peer-checked:ring-emerald-400 peer-focus-visible:outline-2 peer-focus-visible:outline-gold-300 after:absolute after:top-1 after:left-1 after:size-5 after:rounded-full after:bg-slate-400 after:transition peer-checked:after:translate-x-5 peer-checked:after:bg-emerald-300" aria-hidden="true"></span>
                </label>
            @endforeach
        </section>

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer</button>
    </form>
</x-admin.page>
