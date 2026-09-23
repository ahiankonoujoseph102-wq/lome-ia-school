<footer class="border-t border-white/10">
    <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-8 text-xs text-slate-500 sm:px-6 md:flex-row md:items-center md:justify-between">
        <p>© {{ now()->year }} {{ config('app.name') }} · Besoin d'aide ? WhatsApp {{ config('site.contact.phones.0') }}</p>
        <ul class="flex flex-wrap gap-x-5 gap-y-2">
            <li><a href="{{ route('home') }}" class="hover:text-white">Site</a></li>
            <li><a href="{{ route('legal.terms') }}" class="hover:text-white">CGV</a></li>
            <li><a href="{{ route('legal.privacy') }}" class="hover:text-white">Confidentialité</a></li>
        </ul>
    </div>
</footer>
