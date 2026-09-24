@props(['icon', 'title', 'text'])

<div {{ $attributes->class('card relative overflow-hidden p-5') }}>
    <div class="pointer-events-none absolute -top-10 -right-10 size-28 rounded-full bg-electric-500/10 blur-2xl"></div>
    <div class="flex items-start justify-between gap-3">
        <span class="grid size-11 place-items-center rounded-2xl bg-veil/5 text-electric-300 ring-1 ring-veil/10">
            <flux:icon :icon="$icon" class="size-5" />
        </span>
        <span class="rounded-full border border-gold-400/30 bg-gold-400/10 px-2.5 py-1 text-[11px] font-semibold tracking-wide text-gold-200 uppercase">Bientôt disponible</span>
    </div>
    <h3 class="mt-4 font-semibold text-white">{{ $title }}</h3>
    <p class="mt-1 text-sm leading-relaxed text-slate-400">{{ $text }}</p>
    <div class="mt-4 space-y-2" aria-hidden="true">
        <div class="h-2 w-3/4 rounded-full bg-veil/5"></div>
        <div class="h-2 w-1/2 rounded-full bg-veil/5"></div>
    </div>
</div>
