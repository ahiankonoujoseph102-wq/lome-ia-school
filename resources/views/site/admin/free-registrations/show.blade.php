{{--
    Inscriptions d'une formation gratuite : inscrits par option (nom, WhatsApp, e-mail), places prises, export CSV,
    puis réglages (date, adresse du présentiel, options : libellé, horaires, déroulement, places).
--}}
@php use App\Support\PhoneNumber; @endphp

<x-admin.page :title="'Inscriptions : '.$formation->title" subtitle="Formation gratuite : inscrits par option, places prises et réglages de l'inscription." :back="route('admin.formations.index')" back-label="Formations" width="max-w-4xl">
    <x-slot:actions>
        <a href="{{ route('admin.free-registrations.export', $formation) }}" class="btn-ghost !py-2.5 !text-sm" data-test="free-export"><flux:icon.arrow-down-tray class="size-5" /> Exporter (CSV)</a>
    </x-slot:actions>

    <div class="card flex flex-wrap items-center gap-x-6 gap-y-2 p-4 text-sm">
        <p class="text-slate-300"><span class="text-2xl font-bold text-white" data-test="free-total">{{ $total }}</span> {{ $total > 1 ? 'inscrits' : 'inscrit' }}@if ($capacity) sur {{ $capacity }} places @endif</p>
        <p class="text-slate-400">{{ $formation->eventDateLabel() ?? 'Date non renseignée' }}</p>
        <span @class([
            'rounded-full px-2 py-0.5 text-xs font-semibold ring-1',
            'text-emerald-200 ring-emerald-400/50' => $formation->is_active,
            'text-slate-300 ring-night-600' => ! $formation->is_active,
        ])>{{ $formation->is_active ? 'Visible' : 'Masquée' }}</span>
        <a href="{{ route('formations.show', $formation) }}" class="font-semibold text-electric-300 hover:text-white">Voir la page</a>
    </div>

    {{-- Inscrits par option --}}
    @php
        $groups = $options->map(fn ($option) => ['option' => $option, 'items' => $registrationsByOption->get((string) $option->id, collect())]);
        if ($registrationsByOption->has('')) {
            $groups->push(['option' => null, 'items' => $registrationsByOption->get('')]);
        }
    @endphp

    @forelse ($groups as $group)
        <section class="card overflow-hidden" data-test="free-admin-option">
            <header class="flex flex-wrap items-center justify-between gap-2 p-4">
                <div class="min-w-0">
                    <h2 class="font-semibold text-white">{{ $group['option']?->label ?? 'Sans option' }}</h2>
                    @if ($group['option'])
                        <p class="text-sm text-slate-400">{{ $group['option']->mode->label() }}@if ($group['option']->schedule) · {{ $group['option']->schedule }} @endif</p>
                    @endif
                </div>
                @if ($group['option'])
                    <span @class([
                        'rounded-full px-3 py-1 text-sm font-semibold ring-1',
                        'text-gold-200 ring-gold-500/60' => $group['option']->isFull(),
                        'text-slate-200 ring-night-600' => ! $group['option']->isFull(),
                    ]) data-test="free-admin-taken">{{ $group['option']->seatsTaken() }} / {{ $group['option']->capacity }} places prises{{ $group['option']->isFull() ? ' · Complet' : '' }}</span>
                @endif
            </header>
            @if ($group['items']->isEmpty())
                <p class="border-t border-veil/10 px-4 py-3 text-sm text-slate-400">Aucun inscrit pour le moment.</p>
            @else
                <ol class="divide-y divide-veil/10 border-t border-veil/10">
                    @foreach ($group['items'] as $registration)
                        <li class="flex flex-col gap-1 px-4 py-3 text-sm sm:flex-row sm:items-center sm:gap-4">
                            <a href="{{ route('admin.students.show', $registration->user) }}" class="min-w-0 font-semibold text-white hover:text-electric-200 sm:w-48 sm:truncate">{{ $registration->user->name }}</a>
                            <a href="https://wa.me/{{ ltrim((string) $registration->user->phone, '+') }}" target="_blank" rel="noopener" class="whitespace-nowrap text-slate-200 hover:text-white">{{ PhoneNumber::format($registration->user->phone) }}</a>
                            <span class="min-w-0 break-words text-slate-300 sm:flex-1">{{ $registration->user->email }}</span>
                            <span class="text-xs text-slate-400">{{ $registration->created_at?->translatedFormat('j M à H\hi') }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    @empty
        <p class="card p-4 text-sm text-slate-400">Aucune option : ajoutez-en ci-dessous.</p>
    @endforelse

    {{-- Réglages de l'inscription --}}
    <form method="POST" action="{{ route('admin.free-registrations.update', $formation) }}" class="card space-y-5 p-5 sm:p-6">
        @csrf
        @method('PUT')
        <h2 class="text-lg font-semibold text-white">Réglages de l'inscription</h2>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.field name="event_date" label="Date de la formation" type="date" :value="$formation->event_date?->format('Y-m-d')" hint="Les inscriptions se ferment le lendemain." />
            <x-admin.field name="event_location" label="Adresse du présentiel" :value="$formation->event_location" />
        </div>

        <div class="space-y-4">
            <p class="field-label">Options (une seule par personne)</p>
            @foreach ($options->concat([null]) as $index => $option)
                <fieldset class="space-y-3 rounded-2xl bg-night-900 p-4 ring-1 ring-night-600" data-test="free-option-row">
                    <legend class="sr-only">{{ $option ? 'Option '.$option->label : 'Nouvelle option' }}</legend>
                    @if ($option)
                        <input type="hidden" name="options[{{ $index }}][id]" value="{{ $option->id }}">
                    @else
                        <p class="text-sm font-semibold text-slate-200">Ajouter une option (laisser vide sinon)</p>
                    @endif
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-admin.field :name="'options['.$index.'][label]'" label="Libellé" :value="$option?->label" placeholder="Ex. Présentiel journée" />
                        <x-admin.field :name="'options['.$index.'][schedule]'" label="Horaires" :value="$option?->schedule" placeholder="Ex. 9 h à 16 h" />
                        <x-admin.field :name="'options['.$index.'][mode]'" label="Déroulement" type="select" :options="$modes" :value="$option?->mode->value ?? 'onsite'" />
                        <x-admin.field :name="'options['.$index.'][capacity]'" label="Places" type="number" :value="$option?->capacity" :hint="$option ? $option->seatsTaken().' déjà prise(s)' : null" />
                    </div>
                    @if ($option)
                        <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                            <input type="checkbox" name="options[{{ $index }}][remove]" value="1" class="size-4 rounded accent-rose-500">
                            Supprimer cette option (seulement si personne ne s'y est inscrit)
                        </label>
                    @endif
                </fieldset>
            @endforeach
            @if ($options->count() >= $maxOptions)
                <p class="text-xs text-slate-400">{{ $maxOptions }} options au maximum.</p>
            @endif
        </div>

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer</button>
    </form>
</x-admin.page>
