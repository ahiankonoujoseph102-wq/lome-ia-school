@props(['formation', 'delay' => 0, 'listOnMobile' => false])

{{--
    Carte d'une formation. listOnMobile (catalogue) : sur téléphone, carte compacte en ligne (photo à gauche, titre et
    prix à droite) pour raccourcir la page ; dès sm, carte habituelle (photo en haut).
--}}
<article
    {{ $attributes->class(['card card-hover group relative flex overflow-hidden', 'flex-col' => ! $listOnMobile, 'flex-row sm:flex-col' => $listOnMobile]) }}
    data-reveal
    style="--reveal-delay: {{ $delay }}ms"
>
    <x-site.formation-cover :formation="$formation" @class(['aspect-[16/10]' => ! $listOnMobile, 'w-28 shrink-0 self-stretch sm:aspect-[16/10] sm:w-auto' => $listOnMobile]) />

    <div @class(['flex min-w-0 flex-1 flex-col', 'gap-3 p-5' => ! $listOnMobile, 'gap-1.5 p-3.5 sm:gap-3 sm:p-5' => $listOnMobile])>
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold text-electric-300">{{ $formation->category->label() }}</span>
        </div>

        <h3 @class(['leading-snug font-semibold text-white', 'text-lg' => ! $listOnMobile, 'text-[0.95rem] sm:text-lg' => $listOnMobile])>
            <a href="{{ route('formations.show', $formation) }}" class="after:absolute after:inset-0 focus:outline-none">
                {{ $formation->title }}
            </a>
        </h3>

        @if ($formation->subtitle)
            <p @class(['line-clamp-2 text-sm text-slate-400', 'hidden sm:block' => $listOnMobile])>{{ $formation->subtitle }}</p>
        @endif

        <div @class(['hidden sm:block' => $listOnMobile])>
            <x-site.formation-formats :formation="$formation" />
        </div>

        <div @class(['mt-auto flex items-center justify-between gap-3 border-t border-veil/10', 'pt-4' => ! $listOnMobile, 'pt-2 sm:pt-4' => $listOnMobile])>
            <span @class(['font-bold text-gold-300', 'text-xl' => ! $listOnMobile, 'text-base sm:text-xl' => $listOnMobile])>{{ $formation->formatted_price }}</span>
            <span class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-white transition group-hover:gap-2">
                <span @class(['hidden sm:inline' => $listOnMobile])>Découvrir</span> <flux:icon.arrow-right class="size-4" />
            </span>
        </div>
    </div>
</article>
