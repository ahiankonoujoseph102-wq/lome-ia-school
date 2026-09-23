@props(['title', 'subtitle' => null])

<section class="relative isolate overflow-hidden">
    <div class="glow pointer-events-none absolute inset-0 -z-10 opacity-80"></div>
    <div class="grid-pattern pointer-events-none absolute inset-0 -z-10"></div>
    <div class="mx-auto w-full max-w-md px-4 pt-8 pb-16 sm:pt-14">
        <div class="text-center">
            <h1 class="text-3xl font-bold tracking-tight text-balance text-white">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-3 text-pretty text-slate-400">{{ $subtitle }}</p>
            @endif
        </div>
        <div class="card mt-8 p-5 shadow-2xl shadow-electric-900/30 sm:p-7">
            {{ $slot }}
        </div>
        {{ $footer ?? '' }}
    </div>
</section>
