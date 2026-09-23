<section class="relative isolate overflow-hidden">
    <div class="glow pointer-events-none absolute inset-0 -z-10 opacity-70"></div>
    <div class="grid-pattern pointer-events-none absolute inset-0 -z-10"></div>
    <div class="mx-auto max-w-4xl px-4 pt-10 pb-12 text-center sm:px-6 sm:pt-16 sm:pb-16">
        @isset($eyebrow)
            <span class="eyebrow" data-reveal>{{ $eyebrow }}</span>
        @endisset
        <h1 class="mt-4 text-3xl font-bold tracking-tight text-balance text-white sm:text-5xl" data-reveal style="--reveal-delay: 60ms">{{ $title }}</h1>
        @isset($subtitle)
            <p class="mx-auto mt-4 max-w-2xl text-base text-pretty text-slate-300 sm:text-lg" data-reveal style="--reveal-delay: 120ms">{{ $subtitle }}</p>
        @endisset
    </div>
</section>
