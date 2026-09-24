<x-layouts::site title="Règles du groupe" noindex>
    <div class="mx-auto max-w-2xl px-4 pt-6 pb-16 sm:px-6 sm:pt-10">
        <a href="{{ route('admin.chat.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
            <flux:icon.arrow-left class="size-4" /> Groupes de discussion
        </a>

        <h1 class="mt-4 text-2xl font-bold text-white">Règles du groupe</h1>
        <p class="mt-1 text-slate-400">{{ $group->formation->title }}</p>

        <form method="POST" action="{{ route('admin.chat.rules', $group) }}" class="card mt-6 space-y-5 p-5 sm:p-7">
            @csrf
            @method('PUT')
            <div class="space-y-1.5">
                <label for="rules" class="field-label">Texte affiché à l'entrée du groupe</label>
                <textarea id="rules" name="rules" rows="14" maxlength="5000" @class(['field-input text-[15px] leading-relaxed', 'field-input-error' => $errors->has('rules')])>{{ old('rules', $group->rules) }}</textarea>
                @error('rules')
                    <p class="text-sm text-rose-300">{{ $message }}</p>
                @enderror
                <p class="text-xs text-slate-500">Chaque membre verra les nouvelles règles à sa prochaine visite et devra appuyer sur « J'ai compris ».</p>
            </div>
            <label class="flex items-start gap-3 text-sm text-slate-300">
                <input type="checkbox" name="apply_to_all" value="1" class="mt-0.5 size-5 rounded accent-electric-500">
                Appliquer ces règles à tous les groupes
            </label>
            <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer les règles</button>
        </form>
    </div>
</x-layouts::site>
