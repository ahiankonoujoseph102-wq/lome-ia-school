@php
    $social = array_filter(config('site.social'));
    $footerCategories = \App\Enums\FormationCategory::cases();
@endphp

<footer class="relative border-t border-veil/10 bg-night-950">
    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-2 lg:grid-cols-4">
        <div class="space-y-4">
            <x-site.logo height="h-12" />
            <p class="text-sm leading-relaxed text-slate-400">
                Formations pratiques en intelligence artificielle, en français, à Lomé et en ligne. Paiement par Mobile Money.
            </p>
            @if ($social)
                <div class="flex gap-2">
                    @foreach ($social as $network => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="rounded-full px-3 py-1.5 text-xs font-semibold text-slate-300 capitalize ring-1 ring-veil/15 hover:bg-veil/10 hover:text-white">{{ $network }}</a>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <h2 class="text-sm font-semibold text-white">Formations</h2>
            <ul class="mt-4 space-y-2.5 text-sm">
                @foreach ($footerCategories as $category)
                    <li><a href="{{ route('formations.index', ['categorie' => $category->value]) }}" class="text-slate-400 hover:text-white">{{ $category->label() }}</a></li>
                @endforeach
            </ul>
        </div>

        <div>
            <h2 class="text-sm font-semibold text-white">Lomé IA School</h2>
            <ul class="mt-4 space-y-2.5 text-sm">
                <li><a href="{{ route('formations.index') }}" class="text-slate-400 hover:text-white">Toutes les formations</a></li>
                <li><a href="{{ route('consultation') }}" class="text-slate-400 hover:text-white">Consultation gratuite</a></li>
                <li><a href="{{ route('affiliate.program') }}" class="text-slate-400 hover:text-white">Devenez affilié</a></li>
                <li><a href="{{ route('about') }}" class="text-slate-400 hover:text-white">À propos</a></li>
                <li><a href="{{ route('contact') }}" class="text-slate-400 hover:text-white">Contact</a></li>
                <li><a href="{{ route('register') }}" class="text-slate-400 hover:text-white">Créer un compte</a></li>
                <li><a href="{{ route('login') }}" class="text-slate-400 hover:text-white">Connexion</a></li>
            </ul>
        </div>

        <div>
            <h2 class="text-sm font-semibold text-white">Contact</h2>
            <ul class="mt-4 space-y-3 text-sm text-slate-400">
                @foreach (config('site.contact.phones') as $phone)
                    <li><a href="{{ \App\Support\WhatsApp::tel($phone) }}" class="inline-flex items-center gap-2 hover:text-white"><flux:icon.phone class="size-4 text-electric-400" /> {{ $phone }}</a></li>
                @endforeach
                <li><a href="mailto:{{ config('site.contact.email') }}" class="inline-flex items-center gap-2 break-all hover:text-white"><flux:icon.envelope class="size-4 shrink-0 text-electric-400" /> {{ config('site.contact.email') }}</a></li>
                <li><a href="{{ config('site.contact.map_url') }}" target="_blank" rel="noopener" class="inline-flex items-start gap-2 hover:text-white"><flux:icon.map-pin class="mt-0.5 size-4 shrink-0 text-electric-400" /> {{ config('site.contact.address') }}</a></li>
            </ul>
        </div>
    </div>

    <div class="border-t border-veil/10">
        <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-6 text-xs text-slate-500 sm:px-6 md:flex-row md:items-center md:justify-between">
            <p>© {{ now()->year }} {{ config('app.name') }}. Tous droits réservés.</p>
            <ul class="flex flex-wrap gap-x-5 gap-y-2">
                <li><a href="{{ route('legal.notice') }}" class="hover:text-white">Mentions légales</a></li>
                <li><a href="{{ route('legal.terms') }}" class="hover:text-white">Conditions générales de vente</a></li>
                <li><a href="{{ route('legal.privacy') }}" class="hover:text-white">Politique de confidentialité</a></li>
            </ul>
        </div>
    </div>
</footer>
