<x-admin.page title="Formations et prix" subtitle="Ordre d'affichage sur le site, prix, visibilité. Un changement de prix ne modifie jamais les achats déjà faits." width="max-w-4xl">
    <x-slot:actions>
        <a href="{{ route('admin.formations.create') }}" class="btn-gold"><flux:icon.plus class="size-5" /> Nouvelle formation</a>
    </x-slot:actions>

    <ol class="space-y-2">
        @foreach ($formations as $formation)
            <li class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center" data-test="formation-row">
                <x-site.formation-cover :formation="$formation" class="aspect-[16/10] w-full shrink-0 rounded-xl sm:w-28" />
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-white">{{ $formation->title }}</p>
                    <p class="mt-0.5 flex flex-wrap items-center gap-2 text-sm">
                        <span class="font-semibold text-gold-200">{{ $formation->formatted_price }}</span>
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs font-semibold ring-1',
                            'text-emerald-200 ring-emerald-400/50' => $formation->is_active,
                            'text-slate-400 ring-veil/20' => ! $formation->is_active,
                        ])>{{ $formation->is_active ? 'Visible' : 'Masquée' }}</span>
                        @if ($formation->is_featured)
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold text-gold-100 ring-1 ring-gold-400/60">Mise en avant</span>
                        @endif
                        @unless ($formation->isFree())
                            <span class="text-slate-400">{{ $formation->sales_count }} {{ $formation->sales_count > 1 ? 'ventes validées' : 'vente validée' }}</span>
                        @endunless
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('admin.formations.move', [$formation, 'up']) }}">@csrf<button class="grid size-9 place-items-center rounded-full text-slate-300 ring-1 ring-veil/15 hover:bg-veil/10 disabled:opacity-30" @disabled($loop->first) aria-label="Monter {{ $formation->title }}"><flux:icon.chevron-up class="size-4" /></button></form>
                    <form method="POST" action="{{ route('admin.formations.move', [$formation, 'down']) }}">@csrf<button class="grid size-9 place-items-center rounded-full text-slate-300 ring-1 ring-veil/15 hover:bg-veil/10 disabled:opacity-30" @disabled($loop->last) aria-label="Descendre {{ $formation->title }}"><flux:icon.chevron-down class="size-4" /></button></form>
                    @if ($formation->is_active)
                        <button type="button" x-data="copyText(@js(\App\Support\FormationShare::url($formation)))" @click="copy()" class="inline-flex items-center gap-1.5 rounded-full px-3 py-2 text-sm font-semibold text-slate-200 ring-1 ring-veil/15 hover:bg-veil/10" aria-label="Copier le lien de {{ $formation->title }}" data-test="copy-formation-link">
                            <flux:icon.link class="size-4 shrink-0" x-show="!copied" />
                            <flux:icon.check class="size-4 shrink-0 text-emerald-300" x-show="copied" x-cloak />
                            <span x-text="copied ? 'Lien copié' : 'Copier le lien'">Copier le lien</span>
                        </button>
                    @else
                        <span class="rounded-full px-3 py-2 text-sm text-slate-400 ring-1 ring-veil/10" title="Une formation masquée n'est pas visible par les visiteurs">Lien inactif (masquée)</span>
                    @endif
                    @if ($formation->isFree())
                        <a href="{{ route('admin.free-registrations.show', $formation) }}" class="rounded-full px-3 py-2 text-sm font-semibold text-gold-200 ring-1 ring-gold-500/50 hover:bg-gold-500/10" data-test="free-registrations-link">Inscriptions</a>
                    @endif
                    <a href="{{ route('admin.content.show', $formation) }}" class="rounded-full px-3 py-2 text-sm font-semibold text-slate-200 ring-1 ring-veil/15 hover:bg-veil/10">Contenu</a>
                    <a href="{{ route('admin.formations.edit', $formation) }}" class="rounded-full px-3 py-2 text-sm font-semibold text-electric-200 ring-1 ring-electric-400/40 hover:bg-electric-500/10">Modifier</a>
                </div>
            </li>
        @endforeach
    </ol>

    @if ($packs->isNotEmpty())
        <section class="mt-10" data-test="admin-packs">
            <h2 class="text-lg font-semibold text-white">Offres d'accompagnement</h2>
            <p class="mt-1 text-sm text-slate-400">Présentées ensemble sur la page {{ route('packs') }}, dans cet ordre (niveau 1, 2, 3). Tant qu'aucune offre n'est visible, cette page n'est pas accessible au public.</p>

            @php $posterUrl = \App\Packs\PacksPage::posterUrl(); @endphp
            <form method="POST" action="{{ route('admin.formations.packs-poster') }}" enctype="multipart/form-data" class="card mt-4 space-y-4 p-5 sm:p-6" data-test="packs-poster-form">
                @csrf
                @method('PUT')
                <div>
                    <h3 class="font-semibold text-white">Affiche de la page des offres</h3>
                    <p class="mt-1 text-sm text-slate-400">Grande image affichée en haut de la page {{ route('packs') }}, comme l'affiche d'une formation. Sans affiche, la page s'affiche sans image. L'affiche propre à chaque offre se règle dans sa fiche (« Modifier et formations incluses », partie « Image ») et s'affiche en haut de sa carte.</p>
                </div>
                @if ($posterUrl)
                    <div class="relative aspect-[16/10] w-full max-w-md overflow-hidden rounded-2xl bg-night-800 ring-1 ring-veil/10">
                        <img src="{{ $posterUrl }}" alt="Affiche actuelle de la page des offres" class="absolute inset-0 size-full object-cover" data-test="packs-poster-current">
                    </div>
                @else
                    <p class="text-sm text-slate-400" data-test="packs-poster-none">Aucune affiche pour le moment.</p>
                @endif
                <x-admin.field name="poster" :label="$posterUrl ? 'Nouvelle affiche' : 'Affiche'" type="file" accept="image/png,image/jpeg,image/webp" hint="JPEG, PNG ou WebP, 8 Mo au plus. Format conseillé : 1600 × 1000 pixels. Depuis votre téléphone : l'image est recadrée au format 16:10 et compressée automatiquement." />
                @if ($posterUrl)
                    <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="remove_poster" value="1" class="size-4 accent-gold-400"> Retirer l'affiche</label>
                @endif
                <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer l'affiche</button>
            </form>
            <ol class="mt-4 space-y-2">
                @foreach ($packs as $pack)
                    @php $courses = $pack->includedFormations->reject->isGroupOnly(); @endphp
                    <li class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center" data-test="pack-row">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-white">{{ $pack->title }}</p>
                            <p class="mt-0.5 flex flex-wrap items-center gap-2 text-sm">
                                <span class="font-semibold text-gold-200">{{ $pack->formatted_price }}</span>
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs font-semibold ring-1',
                                    'text-emerald-200 ring-emerald-400/50' => $pack->is_active,
                                    'text-slate-400 ring-veil/20' => ! $pack->is_active,
                                ])>{{ $pack->is_active ? 'Visible' : 'Masquée' }}</span>
                                <span class="text-slate-400">{{ $courses->count() }} {{ $courses->count() > 1 ? 'formations incluses' : 'formation incluse' }}{{ $pack->includedFormations->contains->isGroupOnly() ? ' + Groupe VIP' : '' }}</span>
                                <span class="text-slate-400">{{ $pack->sales_count }} {{ $pack->sales_count > 1 ? 'ventes validées' : 'vente validée' }}</span>
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('admin.formations.move', [$pack, 'up']) }}">@csrf<button class="grid size-9 place-items-center rounded-full text-slate-300 ring-1 ring-veil/15 hover:bg-veil/10 disabled:opacity-30" @disabled($loop->first) aria-label="Monter {{ $pack->title }}"><flux:icon.chevron-up class="size-4" /></button></form>
                            <form method="POST" action="{{ route('admin.formations.move', [$pack, 'down']) }}">@csrf<button class="grid size-9 place-items-center rounded-full text-slate-300 ring-1 ring-veil/15 hover:bg-veil/10 disabled:opacity-30" @disabled($loop->last) aria-label="Descendre {{ $pack->title }}"><flux:icon.chevron-down class="size-4" /></button></form>
                            <a href="{{ route('admin.formations.edit', $pack) }}" class="rounded-full px-3 py-2 text-sm font-semibold text-electric-200 ring-1 ring-electric-400/40 hover:bg-electric-500/10" data-test="pack-edit">Modifier et formations incluses</a>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif
</x-admin.page>
