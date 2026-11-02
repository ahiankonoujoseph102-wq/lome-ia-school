@props(['formation', 'delay' => 0])

<article
    {{ $attributes->class('card card-hover group relative flex flex-col overflow-hidden') }}
    data-reveal
    style="--reveal-delay: {{ $delay }}ms"
>
    <x-site.formation-cover :formation="$formation" class="aspect-[16/10]" />

    <div class="flex flex-1 flex-col gap-3 p-5">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold text-electric-300">{{ $formation->category->label() }}</span>
        </div>

        <h3 class="text-lg leading-snug font-semibold text-white">
            <a href="{{ route('formations.show', $formation) }}" class="after:absolute after:inset-0 focus:outline-none">
                {{ $formation->title }}
            </a>
        </h3>

        @if ($formation->subtitle)
            <p class="line-clamp-2 text-sm text-slate-400">{{ $formation->subtitle }}</p>
        @endif

        <x-site.formation-formats :formation="$formation" />

        <div class="mt-auto flex items-center justify-between gap-3 border-t border-veil/10 pt-4">
            <span class="text-xl font-bold text-gold-300">{{ $formation->formatted_price }}</span>
            <span class="inline-flex items-center gap-1 text-sm font-semibold text-white transition group-hover:gap-2">
                Découvrir <flux:icon.arrow-right class="size-4" />
            </span>
        </div>
    </div>
</article>
