{{-- Bouton flottant, sur grand écran seulement : sur téléphone et tablette, WhatsApp est dans l'en-tête pour ne cacher aucun contenu. --}}
<a
    href="{{ \App\Support\WhatsApp::url($whatsappMessage ?? null) }}"
    target="_blank"
    rel="noopener"
    class="group fixed right-6 bottom-6 z-40 hidden items-center gap-2 rounded-full bg-[#25D366] p-3.5 text-white shadow-xl shadow-black/40 ring-4 ring-[#25D366]/20 transition hover:scale-105 hover:ring-[#25D366]/30 lg:flex"
    aria-label="Nous écrire sur WhatsApp"
>
    <x-site.whatsapp-icon class="size-7" />
    <span class="hidden pr-1 text-sm font-semibold lg:inline">Une question ?</span>
</a>
