{{-- Liste d'alertes pour l'admin : [['level' => danger|warning|info, 'text' => …], …] --}}
@if ($alerts !== [])
    <ul class="space-y-2" data-test="alerts">
        @foreach ($alerts as $alert)
            <li @class([
                'flex items-start gap-2 rounded-xl px-3 py-2 text-sm ring-1',
                'bg-rose-400/10 text-rose-200 ring-rose-400/30' => $alert['level'] === 'danger',
                'bg-amber-400/10 text-amber-100 ring-amber-400/30' => $alert['level'] === 'warning',
                'bg-veil/5 text-slate-300 ring-veil/10' => $alert['level'] === 'info',
            ])>
                <flux:icon.exclamation-triangle class="mt-0.5 size-4 shrink-0" /> {{ $alert['text'] }}
            </li>
        @endforeach
    </ul>
@endif
