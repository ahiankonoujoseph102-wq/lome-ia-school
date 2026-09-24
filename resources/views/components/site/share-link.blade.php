{{-- Lien d'affiliation : champ en lecture seule, « Copier », partage WhatsApp et Facebook. --}}
@props(['link', 'message'])

@php
    $whatsapp = 'https://wa.me/?text='.rawurlencode($message.' '.$link);
    $facebook = 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($link);
@endphp

<div x-data="copyText(@js($link))" class="space-y-2">
    <label class="sr-only" for="lien-{{ md5($link) }}">Lien à partager</label>
    <input id="lien-{{ md5($link) }}" x-ref="field" type="text" readonly value="{{ $link }}" class="field-input !py-2.5 font-mono !text-sm" @focus="$el.select()">
    <div class="grid grid-cols-3 gap-2">
        <button type="button" @click="copy()" class="btn-ghost !gap-1.5 !px-2 !py-2 !text-sm" data-test="copy-link">
            <flux:icon.clipboard-document class="size-4 shrink-0" x-show="!copied" />
            <flux:icon.check class="size-4 shrink-0 text-emerald-300" x-show="copied" x-cloak />
            <span x-text="copied ? 'Copié' : 'Copier'">Copier</span>
        </button>
        <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="btn !gap-1.5 !px-2 !py-2 !text-sm bg-[#25D366]/15 text-[#7ee2a8] ring-1 ring-[#25D366]/40 hover:bg-[#25D366]/25">
            <x-site.whatsapp-icon class="size-4 shrink-0" /> WhatsApp
        </a>
        <a href="{{ $facebook }}" target="_blank" rel="noopener" class="btn !gap-1.5 !px-2 !py-2 !text-sm bg-[#1877F2]/15 text-[#9cc2ff] ring-1 ring-[#1877F2]/40 hover:bg-[#1877F2]/25">
            <svg viewBox="0 0 24 24" class="size-4 shrink-0" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-7.5h2.5l.4-3h-2.9V8.6c0-.9.3-1.5 1.5-1.5h1.5V4.4c-.3 0-1.2-.1-2.2-.1-2.2 0-3.7 1.3-3.7 3.8v2.4H8v3h2.6V21h2.9Z"/></svg> Facebook
        </a>
    </div>
</div>
