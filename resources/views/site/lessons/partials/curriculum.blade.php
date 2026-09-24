{{-- Programme d'une formation achetée : modules et leçons, avec l'état de chaque leçon. --}}
@foreach ($curriculum as $module)
    <div class="pt-3">
        <p class="px-3 text-xs font-semibold tracking-wide text-slate-500 uppercase">Module {{ $loop->iteration }} · {{ $module->title }}</p>
        <ol class="mt-1">
            @foreach ($module->lessons as $item)
                @php
                    $isCurrent = isset($current) && $current->id === $item->id;
                    $done = $summary?->isCompleted($item) ?? false;
                    $seen = $summary?->isViewed($item) ?? false;
                @endphp
                <li>
                    <a href="{{ route('lessons.show', [$formation, $item]) }}" @class([
                        'flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm transition',
                        'bg-electric-600/20 text-white ring-1 ring-electric-400/40' => $isCurrent,
                        'text-slate-200 hover:bg-white/5' => ! $isCurrent,
                    ]) @if ($isCurrent) aria-current="page" @endif>
                        @if ($done)
                            <flux:icon.check-circle class="size-5 shrink-0 text-emerald-300" variant="solid" />
                        @elseif ($seen)
                            <flux:icon.play-circle class="size-5 shrink-0 text-gold-300" />
                        @else
                            <flux:icon.play-circle class="size-5 shrink-0 text-slate-500" />
                        @endif
                        <span class="min-w-0 flex-1">{{ $item->title }}</span>
                        @if ($item->formatted_duration)
                            <span class="shrink-0 text-xs text-slate-500">{{ $item->formatted_duration }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ol>
    </div>
@endforeach
