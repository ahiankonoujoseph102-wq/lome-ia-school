@props([
    'title' => 'Prêt à maîtriser l\'IA ?',
    'text' => 'Choisissez votre formation, payez par Mobile Money et commencez tout de suite.',
])

<section class="section">
    <div class="theme-island relative overflow-hidden rounded-[2rem] border border-veil/10 bg-linear-to-br from-electric-700 via-night-700 to-night-900 px-6 py-12 text-center sm:px-12 sm:py-16" data-reveal>
        <div class="glow pointer-events-none absolute inset-0"></div>
        <div class="grid-pattern pointer-events-none absolute inset-0"></div>
        <div class="relative mx-auto max-w-2xl">
            <h2 class="text-3xl font-bold tracking-tight text-balance text-white sm:text-4xl">{{ $title }}</h2>
            <p class="mt-4 text-base text-pretty text-slate-200 sm:text-lg">{{ $text }}</p>
            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                @if ($slot->isEmpty())
                    <a href="{{ route('formations.index') }}" class="btn-gold">Voir les formations <flux:icon.arrow-right class="size-5" /></a>
                    <a href="{{ route('consultation') }}" class="btn-ghost">Réserver une consultation gratuite</a>
                @else
                    {{ $slot }}
                @endif
            </div>
        </div>
    </div>
</section>
