@props(['eyebrow' => null, 'title', 'subtitle' => null, 'align' => 'center'])

<div {{ $attributes->class(['max-w-2xl', 'mx-auto text-center' => $align === 'center']) }} data-reveal>
    @if ($eyebrow)
        <span class="eyebrow">{{ $eyebrow }}</span>
    @endif
    <h2 class="mt-4 text-3xl font-bold tracking-tight text-balance text-white sm:text-4xl">{{ $title }}</h2>
    @if ($subtitle)
        <p class="mt-4 text-base text-pretty text-slate-400 sm:text-lg">{{ $subtitle }}</p>
    @endif
</div>
