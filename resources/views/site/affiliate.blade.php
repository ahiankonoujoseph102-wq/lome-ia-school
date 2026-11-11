@php
    use App\Support\Money;

    $rate = (int) config('affiliate.rate');
    $ctaUrl = auth()->check() ? route('student.affiliate.index') : route('register');
    $ctaLabel = auth()->check() ? 'Activer mon espace affilié' : 'Créer mon compte et devenir affilié';
    $figures = [
        ['value' => $rate.' %', 'label' => 'du prix payé, sur chaque achat validé passé par votre lien'],
        ['value' => 'Mobile Money', 'label' => 'pour retirer vos commissions (Togo, Bénin, Burkina Faso, Côte d\'Ivoire)'],
        ['value' => Money::fcfa((int) config('affiliate.min_withdrawal')), 'label' => 'de solde minimum pour demander un retrait'],
        ['value' => config('affiliate.availability_days').' jours', 'label' => 'après la validation du paiement, la commission devient disponible'],
    ];
    $steps = [
        ['title' => 'Activez votre espace affilié', 'text' => 'Créez votre compte, puis activez l\'affiliation en un clic dans « Mon espace ». C\'est gratuit et aucun achat n\'est nécessaire.'],
        ['title' => 'Partagez vos liens', 'text' => 'Un lien vers le site et un lien pour chaque formation, à partager sur WhatsApp, Facebook, TikTok ou à vos proches.'],
        ['title' => 'Un achat est validé', 'text' => 'Quand une personne achète dans les '.config('affiliate.cookie_days').' jours qui suivent son clic et que notre équipe valide le paiement, la commission est créée.'],
        ['title' => 'Retirez par Mobile Money', 'text' => 'Après '.config('affiliate.availability_days').' jours, la commission rejoint votre solde. Vous demandez le retrait depuis votre espace.'],
    ];
@endphp

<x-layouts::site
    title="Devenez affilié"
    description="Programme d'affiliation de Lomé IA School : recommandez nos formations en intelligence artificielle et recevez {{ $rate }} % de chaque achat validé passé par votre lien, retirable par Mobile Money."
>
    <section class="relative isolate overflow-hidden">
        <div class="glow pointer-events-none absolute inset-0 -z-10"></div>
        <div class="grid-pattern pointer-events-none absolute inset-0 -z-10"></div>
        <div class="mx-auto max-w-3xl px-4 pt-10 pb-14 text-center sm:px-6 sm:pt-16">
            <span class="eyebrow !border-gold-400/30 !bg-gold-400/10 !text-gold-200" data-reveal>Programme d'affiliation</span>
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-balance text-white sm:text-5xl" data-reveal style="--reveal-delay: 60ms">
                Devenez affilié de Lomé IA School
            </h1>
            <p class="mx-auto mt-4 max-w-2xl text-base text-pretty text-slate-300 sm:text-lg" data-reveal style="--reveal-delay: 120ms">
                Recommandez nos formations en intelligence artificielle. Pour chaque achat validé passé par votre lien, vous recevez une commission de {{ $rate }} % du prix payé, retirable par Mobile Money.
            </p>
            <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row" data-reveal style="--reveal-delay: 180ms">
                <a href="{{ $ctaUrl }}" class="btn-gold w-full sm:w-auto">{{ $ctaLabel }}</a>
                @guest
                    <a href="{{ route('login') }}" class="btn-ghost w-full sm:w-auto">J'ai déjà un compte</a>
                @endguest
            </div>
        </div>
    </section>

    <section class="section !pt-0">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($figures as $figure)
                <div class="card p-5" data-reveal>
                    <p class="text-2xl font-bold text-gold-300">{{ $figure['value'] }}</p>
                    <p class="mt-1 text-sm text-slate-400">{{ $figure['label'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="section !pt-0">
        <div class="grid gap-10 lg:grid-cols-2">
            <div data-reveal>
                <h2 class="text-2xl font-bold text-white sm:text-3xl">Comment ça marche ?</h2>
                <ol class="mt-6 space-y-4">
                    @foreach ($steps as $step)
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

            <div data-reveal style="--reveal-delay: 100ms">
                <h2 class="text-2xl font-bold text-white sm:text-3xl">Les règles du programme</h2>
                <div class="card mt-6 p-5">
                    @include('site.partials.affiliate-rules')
                </div>
                <p class="mt-4 text-sm text-slate-400">Les commissions dépendent uniquement des achats réellement validés grâce à vos liens : aucun montant n'est garanti. Conditions complètes dans les <a href="{{ route('legal.terms') }}#affiliation" class="text-electric-300 underline underline-offset-2 hover:text-white">conditions générales de vente</a>.</p>
            </div>
        </div>
    </section>

    <x-site.cta-banner
        title="Prêt à recommander nos formations ?"
        text="L'inscription est gratuite. Vos liens sont disponibles dès l'activation de votre espace affilié."
    >
        <a href="{{ $ctaUrl }}" class="btn-gold">{{ $ctaLabel }}</a>
        <a href="{{ route('formations.index') }}" class="btn-ghost">Voir les formations</a>
    </x-site.cta-banner>
</x-layouts::site>
