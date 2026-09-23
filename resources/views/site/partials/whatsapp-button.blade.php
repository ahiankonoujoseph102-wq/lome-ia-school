<a
    href="{{ \App\Support\WhatsApp::url($whatsappMessage ?? null) }}"
    target="_blank"
    rel="noopener"
    @class([
        'group fixed right-4 z-40 flex items-center gap-2 rounded-full bg-[#25D366] p-3.5 text-white shadow-xl shadow-black/40 ring-4 ring-[#25D366]/20 transition hover:scale-105 hover:ring-[#25D366]/30 sm:right-6',
        'bottom-24 lg:bottom-6' => $raised ?? false,
        'bottom-5 sm:bottom-6' => ! ($raised ?? false),
    ])
    aria-label="Nous écrire sur WhatsApp"
>
    <x-site.whatsapp-icon class="size-7" />
    <span class="hidden pr-1 text-sm font-semibold lg:inline">Une question ?</span>
</a>
