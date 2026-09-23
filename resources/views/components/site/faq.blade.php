@props(['items'])

<div {{ $attributes->class('divide-y divide-white/10 overflow-hidden rounded-3xl border border-white/10 bg-white/[0.03]') }}>
    @foreach ($items as $item)
        <details class="group" data-reveal>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-5 text-left font-semibold text-white transition hover:bg-white/[0.03] sm:px-6">
                <span>{{ $item['question'] }}</span>
                <flux:icon.chevron-down class="size-5 shrink-0 text-electric-300 transition duration-300 group-open:rotate-180" />
            </summary>
            <div class="px-5 pb-5 text-slate-300 sm:px-6">
                {{ \App\Support\Placeholder::highlight($item['answer']) }}
            </div>
        </details>
    @endforeach
</div>
