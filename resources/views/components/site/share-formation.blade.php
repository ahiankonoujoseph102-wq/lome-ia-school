{{--
    Bouton « Partager cette formation » (page publique d'une formation, visible par tous) :
    partage natif sur téléphone s'il existe, sinon menu Copier le lien / WhatsApp / Facebook.
    Menu ouvert sous le bouton, dans le flux de la page (l'en-tête de la formation masque ce qui déborde).
    Style stable face au mode sombre forcé de Chrome : fonds foncés ou translucides, textes clairs.
--}}
@props(['formation'])

@php
    $url = \App\Support\FormationShare::url($formation);
@endphp

<div
    x-data="shareFormation(@js(['url' => $url, 'title' => $formation->title]))"
    @keydown.escape.window="open = false"
    @click.outside="open = false"
    {{ $attributes }}
    data-test="share-formation"
>
    <button type="button" @click="share()" class="btn w-full bg-veil/5 text-white ring-1 ring-veil/15 backdrop-blur hover:bg-veil/10 focus-visible:outline-veil sm:w-auto" :aria-expanded="open.toString()" aria-controls="partage-{{ $formation->id }}">
        <flux:icon.share class="size-5 text-electric-300" /> Partager cette formation
    </button>

    <div
        id="partage-{{ $formation->id }}"
        x-show="open"
        x-cloak
        x-transition.origin.top
        class="mt-2 rounded-2xl bg-night-900 p-3 shadow-2xl shadow-night-950/80 ring-1 ring-veil/15 sm:max-w-sm"
    >
        <p class="px-1 text-sm font-semibold text-white">Partager « {{ $formation->title }} »</p>
        <label class="sr-only" for="lien-formation-{{ $formation->id }}">Lien de la formation</label>
        <input id="lien-formation-{{ $formation->id }}" x-ref="field" type="text" readonly value="{{ $url }}" class="mt-2 w-full rounded-2xl bg-veil/[0.06] px-3 py-2 font-mono text-base text-slate-200 ring-1 ring-veil/10 outline-none focus:ring-2 focus:ring-electric-400/60 sm:text-xs" @focus="$el.select()">

        <div class="mt-3 grid gap-2">
            <button type="button" @click="copy()" class="btn !justify-start !gap-2 bg-veil/5 !px-3 !py-2.5 !text-sm text-white ring-1 ring-veil/15 hover:bg-veil/10" data-test="share-copy">
                <flux:icon.clipboard-document class="size-5 shrink-0 text-slate-300" x-show="!copied" />
                <flux:icon.check class="size-5 shrink-0 text-emerald-300" x-show="copied" x-cloak />
                <span x-text="copied ? 'Lien copié' : 'Copier le lien'">Copier le lien</span>
            </button>
            <p x-show="failed" x-cloak class="px-1 text-xs text-gold-200">Copie impossible : sélectionnez le lien ci-dessus et copiez-le.</p>
            <a href="{{ \App\Support\FormationShare::whatsappUrl($formation) }}" target="_blank" rel="noopener" class="btn !justify-start !gap-2 !px-3 !py-2.5 !text-sm bg-[#25D366]/15 text-[#7ee2a8] ring-1 ring-[#25D366]/40 hover:bg-[#25D366]/25" data-test="share-whatsapp">
                <x-site.whatsapp-icon class="size-5 shrink-0" /> WhatsApp
            </a>
            <a href="{{ \App\Support\FormationShare::facebookUrl($formation) }}" target="_blank" rel="noopener" class="btn !justify-start !gap-2 !px-3 !py-2.5 !text-sm bg-[#1877F2]/15 text-[#9cc2ff] ring-1 ring-[#1877F2]/40 hover:bg-[#1877F2]/25" data-test="share-facebook">
                <svg viewBox="0 0 24 24" class="size-5 shrink-0" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-7.5h2.5l.4-3h-2.9V8.6c0-.9.3-1.5 1.5-1.5h1.5V4.4c-.3 0-1.2-.1-2.2-.1-2.2 0-3.7 1.3-3.7 3.8v2.4H8v3h2.6V21h2.9Z"/></svg> Facebook
            </a>
        </div>
        <p class="sr-only" aria-live="polite" x-text="copied ? 'Lien copié' : ''"></p>
    </div>
</div>
