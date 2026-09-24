{{-- Boutons « monter » et « descendre » (module ou leçon). --}}
@foreach (['up' => ['chevron-up', 'Monter'], 'down' => ['chevron-down', 'Descendre']] as $direction => [$icon, $text])
    @php $disabled = $direction === 'up' ? $first : $last; @endphp
    <form method="POST" action="{{ route($route, [$model, $direction]) }}">
        @csrf
        <button type="submit" @disabled($disabled) class="grid size-10 place-items-center rounded-full text-slate-300 ring-1 ring-white/15 hover:bg-white/10 disabled:opacity-30" aria-label="{{ $text }} {{ $label }}">
            <flux:icon :icon="$icon" class="size-4" />
        </button>
    </form>
@endforeach
