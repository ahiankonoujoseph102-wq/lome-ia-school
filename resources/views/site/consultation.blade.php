@php
    use App\Support\Placeholder;
    use App\Support\WhatsApp;

    $consultation = config('site.consultation');
    // La réservation en ligne arrivera à l'étape 9 ; en attendant, elle se fait sur WhatsApp.
    $bookingUrl = WhatsApp::url(config('site.whatsapp.consultation_message'));
@endphp

<x-layouts::site
    title="Consultation gratuite"
    description="Réservez une consultation gratuite avec Lomé IA School pour faire le point sur vos objectifs et choisir la formation en intelligence artificielle adaptée."
    :whatsapp-message="config('site.whatsapp.consultation_message')"
>
    <section class="relative isolate overflow-hidden">
        <div class="glow pointer-events-none absolute inset-0 -z-10"></div>
        <div class="grid-pattern pointer-events-none absolute inset-0 -z-10"></div>
        <div class="mx-auto max-w-3xl px-4 pt-10 pb-14 text-center sm:px-6 sm:pt-16">
            <span class="eyebrow" data-reveal>100 % gratuit, sans engagement</span>
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-balance text-white sm:text-5xl" data-reveal style="--reveal-delay: 60ms">
                Réservez votre consultation gratuite
            </h1>
            <p class="mx-auto mt-4 max-w-2xl text-base text-pretty text-slate-300 sm:text-lg" data-reveal style="--reveal-delay: 120ms">
                Un échange personnalisé avec {{ config('site.trainer.name') }} pour faire le point sur votre projet et trouver la formation qui vous fera vraiment progresser.
            </p>
            <div class="mt-8 flex flex-col items-center gap-3" data-reveal style="--reveal-delay: 180ms">
                <a href="{{ $bookingUrl }}" target="_blank" rel="noopener" class="btn-gold w-full sm:w-auto">
                    <flux:icon.calendar-days class="size-5" /> Réserver ma consultation gratuite
                </a>
                <p class="text-sm text-slate-400">Durée : {{ Placeholder::highlight($consultation['duration']) }} · {{ $consultation['mode'] }}</p>
            </div>
        </div>
    </section>

    <section class="section !pt-0">
        <div class="grid gap-10 lg:grid-cols-2">
            <div data-reveal>
                <h2 class="text-2xl font-bold text-white sm:text-3xl">Ce que vous allez obtenir</h2>
                <ul class="mt-6 space-y-3">
                    @foreach ($consultation['benefits'] as $benefit)
                        <li class="card flex items-start gap-3 p-4">
                            <span class="grid size-7 shrink-0 place-items-center rounded-full bg-gold-400/15 text-gold-300"><flux:icon.check class="size-4" /></span>
                            <span class="text-slate-200">{{ $benefit }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div data-reveal style="--reveal-delay: 100ms">
                <h2 class="text-2xl font-bold text-white sm:text-3xl">Comment ça se passe ?</h2>
                <ol class="mt-6 space-y-4">
                    @foreach ($consultation['steps'] as $step)
                        <li class="flex gap-4">
                            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-night-800 font-bold text-gold-300 ring-2 ring-gold-400/50">{{ $loop->iteration }}</span>
                            <span>
                                <span class="block font-semibold text-white">{{ $step['title'] }}</span>
                                <span class="block text-sm text-slate-400">{{ $step['text'] }}</span>
                            </span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>

    <x-site.cta-banner
        title="Faites le premier pas aujourd'hui"
        text="La consultation est gratuite et sans engagement. Réservez votre créneau en quelques secondes."
    >
        <a href="{{ $bookingUrl }}" target="_blank" rel="noopener" class="btn-gold">Réserver ma consultation gratuite</a>
        <a href="{{ route('formations.index') }}" class="btn-ghost">Voir les formations</a>
    </x-site.cta-banner>
</x-layouts::site>
