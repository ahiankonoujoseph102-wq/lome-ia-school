@php
    $social = array_filter(config('site.social'));
    $footerCategories = \App\Enums\FormationCategory::cases();
@endphp

<footer class="relative border-t border-veil/10 bg-night-950">
    <div class="mx-auto grid max-w-6xl grid-cols-2 gap-x-6 gap-y-10 px-4 py-12 sm:px-6 sm:py-14 lg:grid-cols-4">
        <div class="col-span-2 space-y-4 lg:col-span-1">
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
            <ul class="mt-2 text-sm sm:mt-4 sm:space-y-2.5">
                @foreach ($footerCategories as $category)
                    <li><a href="{{ route('formations.index', ['categorie' => $category->value]) }}" class="inline-flex min-h-11 items-center py-1.5 text-slate-400 hover:text-white sm:min-h-0 sm:py-0">{{ $category->label() }}</a></li>
                @endforeach
            </ul>
        </div>

        <div>
            <h2 class="text-sm font-semibold text-white">Lomé IA School</h2>
            <ul class="mt-2 text-sm sm:mt-4 sm:space-y-2.5">
                <li><a href="{{ route('formations.index') }}" class="inline-flex min-h-11 items-center py-1.5 text-slate-400 hover:text-white sm:min-h-0 sm:py-0">Toutes les formations</a></li>
                <li><a href="{{ route('consultation') }}" class="inline-flex min-h-11 items-center py-1.5 text-slate-400 hover:text-white sm:min-h-0 sm:py-0">Consultation gratuite</a></li>
                <li><a href="{{ route('affiliate.program') }}" class="inline-flex min-h-11 items-center py-1.5 text-slate-400 hover:text-white sm:min-h-0 sm:py-0">Devenez affilié</a></li>
                <li><a href="{{ route('business') }}" class="inline-flex min-h-11 items-center py-1.5 text-slate-400 hover:text-white sm:min-h-0 sm:py-0">Entreprises et réalisations sur mesure</a></li>
                <li><a href="{{ route('about') }}" class="inline-flex min-h-11 items-center py-1.5 text-slate-400 hover:text-white sm:min-h-0 sm:py-0">À propos</a></li>
                <li><a href="{{ route('contact') }}" class="inline-flex min-h-11 items-center py-1.5 text-slate-400 hover:text-white sm:min-h-0 sm:py-0">Contact</a></li>
                <li><a href="{{ route('register') }}" class="inline-flex min-h-11 items-center py-1.5 text-slate-400 hover:text-white sm:min-h-0 sm:py-0">Créer un compte</a></li>
                <li><a href="{{ route('login') }}" class="inline-flex min-h-11 items-center py-1.5 text-slate-400 hover:text-white sm:min-h-0 sm:py-0">Connexion</a></li>
                <li>@include('site.partials.install-link')</li>
            </ul>
        </div>

        <div class="col-span-2 lg:col-span-1">
            <h2 class="text-sm font-semibold text-white">Contact</h2>
            <ul class="mt-2 text-sm text-slate-400 sm:mt-4 sm:space-y-3">
                @foreach (config('site.contact.phones') as $phone)
                    <li><a href="{{ \App\Support\WhatsApp::tel($phone) }}" class="inline-flex min-h-11 items-center gap-2 hover:text-white sm:min-h-0"><flux:icon.phone class="size-4 text-electric-400" /> {{ $phone }}</a></li>
                @endforeach
                <li><a href="mailto:{{ config('site.contact.email') }}" class="inline-flex min-h-11 items-center gap-2 break-all hover:text-white sm:min-h-0"><flux:icon.envelope class="size-4 shrink-0 text-electric-400" /> {{ config('site.contact.email') }}</a></li>
                <li><a href="{{ config('site.contact.map_url') }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-start gap-2 py-3 hover:text-white sm:min-h-0 sm:py-0"><flux:icon.map-pin class="mt-0.5 size-4 shrink-0 text-electric-400" /> {{ config('site.contact.address') }}</a></li>
            </ul>
        </div>
    </div>

    <div class="border-t border-veil/10">
        <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-6 text-xs text-slate-400 sm:px-6 md:flex-row md:items-center md:justify-between" data-test="footer-bottom">
            <div class="flex flex-col gap-1 sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-3">
                <p>© {{ now()->year }} {{ config('app.name') }}. Tous droits réservés.</p>
                <p class="whitespace-nowrap"><a href="{{ route('founder') }}" class="inline-flex min-h-11 items-center hover:text-white sm:min-h-0" data-test="footer-signature">Dev by Joseph Ahiankonou</a></p>
            </div>
            <ul class="flex flex-wrap gap-x-5">
                <li><a href="{{ route('legal.notice') }}" class="inline-flex min-h-11 items-center hover:text-white md:min-h-0">Mentions légales</a></li>
                <li><a href="{{ route('legal.terms') }}" class="inline-flex min-h-11 items-center hover:text-white md:min-h-0">Conditions générales de vente</a></li>
                <li><a href="{{ route('legal.privacy') }}" class="inline-flex min-h-11 items-center hover:text-white md:min-h-0">Politique de confidentialité</a></li>
            </ul>
        </div>
    </div>
</footer>
