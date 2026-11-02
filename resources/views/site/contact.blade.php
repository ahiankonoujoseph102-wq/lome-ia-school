@php
    use App\Support\Placeholder;
    use App\Support\WhatsApp;

    $contact = config('site.contact');
@endphp

<x-layouts::site
    title="Contact"
    :description="'Contactez Lomé IA School par téléphone, WhatsApp ou e-mail. '.config('site.contact.address').'.'"
>
    @include('site.partials.page-header', [
        'eyebrow' => 'Contact',
        'title' => 'Parlons de votre projet',
        'subtitle' => 'Une question sur une formation, un paiement ou votre inscription ? Nous vous répondons rapidement.',
    ])

    <section class="section !pt-4">
        <div class="grid gap-5 lg:grid-cols-3">
            <a href="{{ WhatsApp::url() }}" target="_blank" rel="noopener" class="card card-hover flex flex-col gap-4 border-[#25D366]/30 p-6 lg:col-span-1" data-reveal>
                <span class="grid size-12 place-items-center rounded-2xl bg-[#25D366]/15 text-[#25D366]"><x-site.whatsapp-icon class="size-6" /></span>
                <span>
                    <span class="block text-lg font-semibold text-white">WhatsApp</span>
                    <span class="mt-1 block text-slate-400">Le moyen le plus rapide pour nous joindre.</span>
                </span>
                <span class="mt-auto inline-flex items-center gap-1 font-semibold text-[#25D366]">{{ $contact['phones'][0] }} <flux:icon.arrow-up-right class="size-4" /></span>
            </a>

            <div class="card flex flex-col gap-4 p-6" data-reveal style="--reveal-delay: 80ms">
                <span class="grid size-12 place-items-center rounded-2xl bg-electric-500/15 text-electric-300"><flux:icon.phone class="size-6" /></span>
                <span class="text-lg font-semibold text-white">Téléphone</span>
                <ul class="space-y-2">
                    @foreach ($contact['phones'] as $phone)
                        <li><a href="{{ WhatsApp::tel($phone) }}" class="font-semibold text-slate-200 hover:text-white">{{ $phone }}</a></li>
                    @endforeach
                </ul>
            </div>

            <div class="card flex flex-col gap-4 p-6" data-reveal style="--reveal-delay: 160ms">
                <span class="grid size-12 place-items-center rounded-2xl bg-electric-500/15 text-electric-300"><flux:icon.envelope class="size-6" /></span>
                <span class="text-lg font-semibold text-white">E-mail</span>
                <a href="mailto:{{ $contact['email'] }}" class="font-semibold break-all text-slate-200 hover:text-white">{{ $contact['email'] }}</a>
            </div>
        </div>

        <div class="card mt-5 grid gap-6 overflow-hidden p-6 sm:p-8 md:grid-cols-[1fr_auto] md:items-center" data-reveal>
            <div class="flex gap-4">
                <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-gold-400/15 text-gold-300"><flux:icon.map-pin class="size-6" /></span>
                <div>
                    <p class="text-lg font-semibold text-white">Notre adresse</p>
                    <p class="mt-1 text-slate-300">{{ $contact['address'] }}</p>
                    <p class="mt-3 flex items-center gap-2 text-sm text-slate-400"><flux:icon.clock class="size-4" /> {{ Placeholder::highlight($contact['hours']) }}</p>
                </div>
            </div>
            <a href="{{ $contact['map_url'] }}" target="_blank" rel="noopener" class="btn-ghost">Voir sur Google Maps <flux:icon.arrow-up-right class="size-4" /></a>
        </div>
    </section>

    {{-- Devis : formulaire de la page Entreprises et réalisations sur mesure --}}
    <section class="section !pt-0" data-test="contact-quote">
        <div class="card flex flex-col gap-5 p-6 sm:flex-row sm:items-center sm:p-8" data-reveal>
            <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-night-800 text-gold-300 shadow-[0_0_0_1.5px_var(--color-gold-500)]"><flux:icon.document-text class="size-6" /></span>
            <div class="min-w-0 flex-1">
                <p class="text-lg font-semibold text-white">Un site, un agent IA ou une automatisation à réaliser ?</p>
                <p class="mt-1 text-slate-300">Décrivez votre projet dans notre formulaire : nous vous répondons avec une proposition.</p>
            </div>
            <a href="{{ route('business') }}#devis" class="btn-gold w-full shrink-0 sm:w-auto">Demander un devis</a>
        </div>
    </section>

    <x-site.cta-banner
        title="Pas encore sûr de votre choix ?"
        text="Réservez une consultation gratuite : nous vous aidons à choisir la formation adaptée à votre objectif."
    >
        <a href="{{ route('consultation') }}" class="btn-gold">Réserver une consultation gratuite</a>
    </x-site.cta-banner>
</x-layouts::site>
