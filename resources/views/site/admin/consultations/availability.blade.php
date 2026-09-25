@php
    use App\Models\ConsultationHour;

    $settingsErrors = $errors->getBag('settings');
    $hourErrors = $errors->getBag('hours');
    $blockErrors = $errors->getBag('blocks');
    $fields = [
        ['name' => 'duration_minutes', 'label' => 'Durée d\'une consultation', 'unit' => 'min'],
        ['name' => 'buffer_minutes', 'label' => 'Pause entre deux consultations', 'unit' => 'min'],
        ['name' => 'min_notice_hours', 'label' => 'Délai minimum avant un rendez-vous', 'unit' => 'h'],
        ['name' => 'max_days_ahead', 'label' => 'Réservation possible jusqu\'à', 'unit' => 'jours'],
        ['name' => 'max_per_day', 'label' => 'Maximum de consultations par jour (vide : pas de limite)', 'unit' => 'par jour'],
        ['name' => 'manage_deadline_hours', 'label' => 'Déplacement ou annulation par le visiteur jusqu\'à', 'unit' => 'h avant'],
    ];
@endphp

<x-layouts::site title="Disponibilités des consultations" noindex>
    <div class="mx-auto max-w-3xl space-y-6 px-4 pt-6 pb-16 sm:px-6 sm:pt-10">
        <div>
            <a href="{{ route('admin.consultations.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
                <flux:icon.arrow-left class="size-4" /> Consultations
            </a>
            <h1 class="mt-4 text-2xl font-bold text-white sm:text-3xl">Disponibilités</h1>
            <p class="mt-1 text-slate-400">Heures de Lomé (GMT). Les créneaux qui chevauchent une session en direct ou en présentiel sont bloqués automatiquement.</p>
        </div>

        @if (session('status'))
            <x-site.alert>{{ session('status') }}</x-site.alert>
        @endif

        {{-- Lien Google Meet et réglages --}}
        <form method="POST" action="{{ route('admin.consultations.settings') }}" class="card space-y-4 p-5 sm:p-6">
            @csrf
            @method('PUT')
            <h2 class="font-semibold text-white">Lien Google Meet et réglages</h2>
            <div class="space-y-1.5">
                <label for="meet_url" class="field-label">Lien Google Meet fixe</label>
                <input id="meet_url" name="meet_url" type="url" inputmode="url" value="{{ $settingsErrors->any() ? old('meet_url') : $settings->meet_url }}" placeholder="https://meet.google.com/abc-defg-hij" @class(['field-input font-mono', 'field-input-error' => $settingsErrors->has('meet_url')])>
                @if ($settingsErrors->has('meet_url'))
                    <p class="text-sm text-rose-300">{{ $settingsErrors->first('meet_url') }}</p>
                @else
                    <p class="text-xs text-slate-500">Toujours le même lien. Il n'apparaît jamais sur une page publique : seulement sur la confirmation, dans les e-mails et sur la page de gestion du rendez-vous.</p>
                @endif
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($fields as $field)
                    <div class="space-y-1.5">
                        <label for="{{ $field['name'] }}" class="field-label">{{ $field['label'] }}</label>
                        <div class="flex items-center gap-2">
                            <input id="{{ $field['name'] }}" name="{{ $field['name'] }}" type="number" inputmode="numeric" min="0" value="{{ $settingsErrors->any() ? old($field['name']) : $settings->{$field['name']} }}" @class(['field-input', 'field-input-error' => $settingsErrors->has($field['name'])])>
                            <span class="shrink-0 text-sm text-slate-400">{{ $field['unit'] }}</span>
                        </div>
                        @if ($settingsErrors->has($field['name']))
                            <p class="text-sm text-rose-300">{{ $settingsErrors->first($field['name']) }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
            <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer les réglages</button>
        </form>

        {{-- Horaires par jour --}}
        <section class="card p-5 sm:p-6">
            <h2 class="font-semibold text-white">Horaires de la semaine</h2>
            <ul class="mt-3 divide-y divide-veil/10">
                @foreach (ConsultationHour::WEEKDAYS as $weekday => $label)
                    <li class="flex flex-wrap items-center gap-2 py-3" data-test="weekday-{{ $weekday }}">
                        <span class="w-24 shrink-0 font-medium text-white">{{ $label }}</span>
                        @forelse ($hours->get($weekday, collect()) as $hour)
                            <form method="POST" action="{{ route('admin.consultations.hours.destroy', $hour) }}" class="inline-flex">
                                @csrf
                                @method('DELETE')
                                <span class="inline-flex items-center gap-1 rounded-full bg-veil/5 py-1 pr-1 pl-3 text-sm text-slate-200 ring-1 ring-veil/15">
                                    {{ $hour->label() }}
                                    <button type="submit" class="grid size-6 place-items-center rounded-full text-slate-400 hover:bg-veil/10 hover:text-rose-200" aria-label="Supprimer {{ $hour->label() }}"><flux:icon.x-mark class="size-4" /></button>
                                </span>
                            </form>
                        @empty
                            <span class="text-sm text-slate-500">Fermé</span>
                        @endforelse
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('admin.consultations.hours.store') }}" class="mt-4 grid grid-cols-2 gap-3 border-t border-veil/10 pt-4 sm:grid-cols-4 sm:items-end">
                @csrf
                <div class="col-span-2 space-y-1.5 sm:col-span-1">
                    <label for="weekday" class="field-label">Jour</label>
                    <select id="weekday" name="weekday" class="field-input">
                        @foreach (ConsultationHour::WEEKDAYS as $weekday => $label)
                            <option value="{{ $weekday }}" @selected((int) old('weekday') === $weekday)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label for="hour_start" class="field-label">De</label>
                    <input id="hour_start" name="starts_at" type="time" value="{{ old('starts_at', '08:00') }}" class="field-input">
                </div>
                <div class="space-y-1.5">
                    <label for="hour_end" class="field-label">À</label>
                    <input id="hour_end" name="ends_at" type="time" value="{{ old('ends_at', '20:00') }}" class="field-input">
                </div>
                <button type="submit" class="btn-ghost col-span-2 w-full sm:col-span-1">Ajouter</button>
                @if ($hourErrors->any())
                    <p class="col-span-2 text-sm text-rose-300 sm:col-span-4">{{ $hourErrors->first() }}</p>
                @endif
            </form>
        </section>

        {{-- Jours et plages bloqués --}}
        <section class="card p-5 sm:p-6">
            <h2 class="font-semibold text-white">Jours ou plages bloqués</h2>
            <p class="mt-1 text-sm text-slate-400">Congés, empêchements : aucune réservation possible pendant ces périodes.</p>
            <ul class="mt-3 space-y-2">
                @forelse ($blocks as $block)
                    <li class="flex items-center justify-between gap-3 rounded-2xl bg-veil/5 px-4 py-3 text-sm ring-1 ring-veil/10" data-test="block">
                        <span class="min-w-0">
                            <span class="block font-medium text-white">
                                @if ($block->starts_at->format('H:i') === '00:00' && $block->ends_at->format('H:i') === '00:00')
                                    {{ $block->starts_at->isSameDay($block->ends_at->subDay()) ? 'Le '.$block->starts_at->translatedFormat('l j F') : 'Du '.$block->starts_at->translatedFormat('j F').' au '.$block->ends_at->subDay()->translatedFormat('j F') }} (journée entière)
                                @else
                                    {{ $block->starts_at->translatedFormat('l j F, H\hi') }} → {{ $block->ends_at->translatedFormat($block->starts_at->isSameDay($block->ends_at) ? 'H\hi' : 'l j F, H\hi') }}
                                @endif
                            </span>
                            @if ($block->reason)
                                <span class="block text-xs text-slate-400">{{ $block->reason }}</span>
                            @endif
                        </span>
                        <form method="POST" action="{{ route('admin.consultations.blocks.destroy', $block) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-full px-3 py-1.5 text-xs font-semibold text-rose-200 ring-1 ring-rose-400/40 hover:bg-rose-500/10">Débloquer</button>
                        </form>
                    </li>
                @empty
                    <li class="text-sm text-slate-500">Aucune période bloquée.</li>
                @endforelse
            </ul>
            <form method="POST" action="{{ route('admin.consultations.blocks.store') }}" class="mt-4 grid grid-cols-2 gap-3 border-t border-veil/10 pt-4">
                @csrf
                <div class="space-y-1.5">
                    <label for="start_date" class="field-label">Du</label>
                    <input id="start_date" name="start_date" type="date" value="{{ old('start_date') }}" required class="field-input">
                </div>
                <div class="space-y-1.5">
                    <label for="end_date" class="field-label">Au (facultatif)</label>
                    <input id="end_date" name="end_date" type="date" value="{{ old('end_date') }}" class="field-input">
                </div>
                <div class="space-y-1.5">
                    <label for="start_time" class="field-label">À partir de (facultatif)</label>
                    <input id="start_time" name="start_time" type="time" value="{{ old('start_time') }}" class="field-input">
                </div>
                <div class="space-y-1.5">
                    <label for="end_time" class="field-label">Jusqu'à (facultatif)</label>
                    <input id="end_time" name="end_time" type="time" value="{{ old('end_time') }}" class="field-input">
                </div>
                <div class="col-span-2 space-y-1.5">
                    <label for="reason" class="field-label">Motif (pour vous seul, facultatif)</label>
                    <input id="reason" name="reason" type="text" maxlength="120" value="{{ old('reason') }}" class="field-input" placeholder="Congés, déplacement…">
                </div>
                <p class="col-span-2 text-xs text-slate-500">Sans heures, les journées entières sont bloquées.</p>
                <button type="submit" class="btn-ghost col-span-2 w-full">Bloquer cette période</button>
                @if ($blockErrors->any())
                    <p class="col-span-2 text-sm text-rose-300">{{ $blockErrors->first() }}</p>
                @endif
            </form>
        </section>
    </div>
</x-layouts::site>
