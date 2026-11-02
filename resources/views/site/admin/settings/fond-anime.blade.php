@php
    use App\Settings\SiteSettings;

    $background = (array) SiteSettings::get('site.animated_background');
    $enabled = (bool) old('enabled', $background['enabled'] ?? true);
    $theme = (string) old('theme', \App\Support\SiteDesign::theme());
@endphp

<x-admin.page :title="$section['title']" :back="route('admin.settings.index')" back-label="Réglages du site">
    <div class="card flex flex-wrap items-center justify-between gap-3 p-4 text-sm text-slate-300">
        <p>Halo lumineux, réseau de points et petites icônes (robot, bulles, livre, engrenage, téléphone, éclair) qui bougent très lentement derrière le haut de l'accueil et du catalogue. Jamais sur le paiement, la connexion, les cours, Mon espace, les groupes ni l'admin. Les téléphones réglés sur « moins d'animations » ou « économie de données » voient un fond fixe.</p>
        <a href="{{ route('home') }}" target="_blank" class="btn-ghost !px-4 !py-2 !text-sm">Voir l'accueil <flux:icon.arrow-up-right class="size-4" /></a>
    </div>

    @if ($errors->any())
        <x-site.alert type="error">{{ $errors->first() }}</x-site.alert>
    @endif

    <form method="POST" action="{{ route('admin.settings.update', $key) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="card space-y-4 p-5 sm:p-6" data-test="site-design-choice">
            <div>
                <h2 class="font-semibold text-white">Design du site</h2>
                <p class="text-sm text-slate-400">Le changement est immédiat, sans nouvelle mise en ligne. Les conversations des groupes restent sombres.</p>
            </div>
            @foreach (['light' => ['Design clair', 'Fond clair, cartes blanches et blocs bleu nuit, dans le style de Talkami.'], 'dark' => ['Ancien design sombre', 'Le site tout en bleu nuit, comme avant novembre 2026.']] as $value => [$label, $help])
                <label class="flex cursor-pointer items-start gap-3 rounded-2xl p-3 ring-1 ring-veil/10 has-checked:ring-2 has-checked:ring-gold-500">
                    <input type="radio" name="theme" value="{{ $value }}" @checked($theme === $value) class="mt-1 size-4 accent-[#d98a06]">
                    <span>
                        <span class="block font-semibold text-white">{{ $label }}</span>
                        <span class="block text-sm text-slate-400">{{ $help }}</span>
                    </span>
                </label>
            @endforeach
        </section>

        <section class="card space-y-5 p-5 sm:p-6">
            <label class="flex cursor-pointer items-start justify-between gap-4" data-test="animated-background-toggle">
                <span class="min-w-0">
                    <span class="block font-semibold text-white">Fond animé</span>
                    <span class="block text-sm text-slate-400">Éteint : le fond fixe d'origine revient tout de suite, sans nouvelle mise en ligne.</span>
                </span>
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" @checked($enabled) class="peer sr-only">
                <span class="relative mt-0.5 h-7 w-12 shrink-0 rounded-full bg-veil/10 ring-1 ring-veil/20 transition peer-checked:bg-emerald-500/30 peer-checked:ring-emerald-400 peer-focus-visible:outline-2 peer-focus-visible:outline-gold-300 after:absolute after:top-1 after:left-1 after:size-5 after:rounded-full after:bg-slate-400 after:transition peer-checked:after:translate-x-5 peer-checked:after:bg-emerald-300" aria-hidden="true"></span>
            </label>
        </section>

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer</button>
    </form>
</x-admin.page>
