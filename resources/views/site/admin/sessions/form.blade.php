@php
    use App\Enums\SessionFormat;
    use App\Enums\SessionPriceType;

    $editing = $session->exists;
    $format = old('format', $session->format?->value ?? SessionFormat::Online->value);
    $priceType = old('price_type', $session->price_type?->value ?? SessionPriceType::Included->value);
    $durations = [30 => '30 min', 45 => '45 min', 60 => '1 h', 90 => '1 h 30', 120 => '2 h', 150 => '2 h 30', 180 => '3 h', 240 => '4 h', 300 => '5 h', 360 => '6 h', 480 => '8 h'];
    $duration = (int) old('duration', $session->duration_minutes);
    if (! array_key_exists($duration, $durations)) {
        $durations[$duration] = $session->exists ? $session->durationLabel() : $duration.' min';
        ksort($durations);
    }
@endphp

<x-layouts::site :title="$editing ? 'Modifier la session' : 'Nouvelle session'" noindex>
    <div class="mx-auto max-w-2xl px-4 pt-6 pb-16 sm:px-6 sm:pt-10">
        <a href="{{ $editing ? route('admin.sessions.show', $session) : route('admin.sessions.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
            <flux:icon.arrow-left class="size-4" /> {{ $editing ? 'Retour à la session' : 'Sessions' }}
        </a>
        <h1 class="mt-4 text-2xl font-bold text-white sm:text-3xl">{{ $editing ? 'Modifier la session' : 'Nouvelle session' }}</h1>
        <p class="mt-1 text-slate-400">
            @if ($editing)
                Si vous changez la date, l'heure, la durée, le lieu ou le lien, les élèves concernés sont prévenus par e-mail et notification.
            @else
                L'annonce sera publiée et épinglée automatiquement dans le groupe de la formation.
            @endif
        </p>

        @if ($errors->any())
            <x-site.alert type="error" class="mt-5">Vérifiez les champs signalés en rouge.</x-site.alert>
        @endif

        <form
            method="POST"
            action="{{ $editing ? route('admin.sessions.update', $session) : route('admin.sessions.store') }}"
            class="mt-6 space-y-6"
            x-data="{ format: @js($format), priceType: @js($priceType), meet: @js(old('meet_url', $session->meet_url)) }"
            data-test="session-form"
        >
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            {{-- Type de session --}}
            <fieldset class="card p-5 sm:p-6">
                <legend class="sr-only">Type de session</legend>
                <p class="field-label">Type de session</p>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    @foreach (SessionFormat::cases() as $case)
                        <label class="flex cursor-pointer flex-col items-center gap-2 rounded-2xl p-4 text-center ring-1 ring-veil/15 transition has-[:checked]:bg-electric-500/15 has-[:checked]:ring-2 has-[:checked]:ring-electric-400">
                            <input type="radio" name="format" value="{{ $case->value }}" x-model="format" @checked($format === $case->value) class="sr-only">
                            <flux:icon :icon="$case->icon()" class="size-7 text-electric-300" />
                            <span class="font-semibold text-white">{{ $case->label() }}</span>
                            <span class="text-xs text-slate-400">{{ $case === SessionFormat::Online ? 'Google Meet' : 'À Lomé, sur inscription' }}</span>
                        </label>
                    @endforeach
                </div>
                @error('format')
                    <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                @enderror
            </fieldset>

            <div class="card space-y-5 p-5 sm:p-6">
                <div class="space-y-1.5">
                    <label for="formation_id" class="field-label">Formation concernée</label>
                    <select id="formation_id" name="formation_id" required @class(['field-input', 'field-input-error' => $errors->has('formation_id')])>
                        <option value="">Choisir…</option>
                        @foreach ($formations as $formation)
                            <option value="{{ $formation->id }}" @selected((int) old('formation_id', $session->formation_id) === $formation->id)>{{ $formation->title }}</option>
                        @endforeach
                    </select>
                    @error('formation_id')
                        <p class="text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                <x-site.field name="title" label="Titre (facultatif)" :value="old('title', $session->title)" hint="Exemple : Questions-réponses sur la leçon 3. Sans titre : « Session en direct » ou « Session en présentiel »." />

                <div class="grid grid-cols-2 gap-3">
                    <x-site.field name="date" label="Date" type="date" :value="old('date', $session->starts_at?->format('Y-m-d'))" required />
                    <x-site.field name="time" label="Heure de début" type="time" :value="old('time', $session->starts_at?->format('H:i'))" required />
                </div>
                <p class="-mt-3 text-xs text-slate-500">Heure de Lomé (GMT). Les élèves du Bénin voient aussi leur heure (GMT+1).</p>

                <div class="space-y-1.5">
                    <label for="duration" class="field-label">Durée</label>
                    <select id="duration" name="duration" required @class(['field-input', 'field-input-error' => $errors->has('duration')])>
                        @foreach ($durations as $minutes => $label)
                            <option value="{{ $minutes }}" @selected($duration === $minutes)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('duration')
                        <p class="text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- En direct --}}
            <div class="card space-y-3 p-5 sm:p-6" x-show="format === 'online'" @if ($format !== 'online') style="display: none" @endif>
                <div class="space-y-1.5">
                    <label for="meet_url" class="field-label">Lien Google Meet</label>
                    <input id="meet_url" name="meet_url" type="text" inputmode="url" autocapitalize="none" autocorrect="off" spellcheck="false" x-model="meet" value="{{ old('meet_url', $session->meet_url) }}" placeholder="https://meet.google.com/abc-defg-hij" @class(['field-input', 'field-input-error' => $errors->has('meet_url')])>
                    @error('meet_url')
                        <p class="text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                    <p class="text-xs" :class="! meet || /meet\.google\.com\/[a-z]{3}-[a-z]{4}-[a-z]{3}/i.test(meet) ? 'text-slate-500' : 'text-rose-300'" x-text="! meet || /meet\.google\.com\/[a-z]{3}-[a-z]{4}-[a-z]{3}/i.test(meet) ? 'Visible uniquement par les élèves qui ont accès à la formation, jamais sur les pages publiques.' : 'Ce n\'est pas un lien de réunion Google Meet (exemple : https://meet.google.com/abc-defg-hij).'">Visible uniquement par les élèves qui ont accès à la formation, jamais sur les pages publiques.</p>
                </div>
            </div>

            {{-- Présentiel --}}
            <div class="card space-y-5 p-5 sm:p-6" x-show="format === 'onsite'" @if ($format !== 'onsite') style="display: none" @endif>
                <x-site.field name="location" label="Lieu" :value="old('location', $session->location ?? config('live_sessions.default_location'))" />
                <x-site.field name="capacity" label="Nombre de places" type="number" inputmode="numeric" min="1" :value="old('capacity', $session->capacity)" />

                <fieldset>
                    <legend class="field-label">Tarif</legend>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach (SessionPriceType::cases() as $case)
                            <label class="flex cursor-pointer items-center gap-3 rounded-2xl px-4 py-3 text-sm text-slate-100 ring-1 ring-veil/15 has-[:checked]:bg-electric-500/15 has-[:checked]:ring-electric-400">
                                <input type="radio" name="price_type" value="{{ $case->value }}" x-model="priceType" @checked($priceType === $case->value) class="size-4 accent-electric-500">
                                {{ $case->label() }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div x-show="priceType === 'supplement'" @if ($priceType !== 'supplement') style="display: none" @endif class="space-y-1.5">
                    <label for="supplement_amount" class="field-label">Montant du supplément</label>
                    <div class="relative">
                        <input id="supplement_amount" name="supplement_amount" type="number" inputmode="numeric" min="100" step="100" value="{{ old('supplement_amount', $session->supplement_amount) }}" @class(['field-input pr-20', 'field-input-error' => $errors->has('supplement_amount')])>
                        <span class="pointer-events-none absolute inset-y-0 right-4 grid place-items-center text-sm font-semibold text-slate-400">FCFA</span>
                    </div>
                    @error('supplement_amount')
                        <p class="text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                    <p class="text-xs text-slate-500">Payé par Mobile Money avec preuve, comme une formation (vérification dans « Paiements à vérifier »). La place est réservée pendant la vérification.</p>
                </div>
            </div>

            <div class="card space-y-1.5 p-5 sm:p-6">
                <label for="description" class="field-label">Description (facultatif)</label>
                <textarea id="description" name="description" rows="4" maxlength="3000" class="field-input" placeholder="Ce qui sera abordé, ce qu'il faut préparer…">{{ old('description', $session->description) }}</textarea>
            </div>

            <button type="submit" class="btn-gold w-full">{{ $editing ? 'Enregistrer les modifications' : 'Créer et annoncer la session' }}</button>
        </form>
    </div>
</x-layouts::site>
