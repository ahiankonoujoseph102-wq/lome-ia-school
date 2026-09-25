<x-admin.page title="Réglages du site" subtitle="Tout se modifie ici, sans toucher au code. Chaque modification est visible sur le site immédiatement.">
    <div class="grid gap-3 sm:grid-cols-2">
        @foreach ($sections as $slug => $section)
            <a href="{{ route('admin.settings.edit', $slug) }}" class="card flex items-start gap-3 p-4 hover:bg-veil/5">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-electric-500/15 text-electric-200 ring-1 ring-electric-400/30"><flux:icon :name="$section['icon']" class="size-5" /></span>
                <span class="min-w-0 flex-1">
                    <span class="flex flex-wrap items-center gap-2 font-semibold text-white">
                        {{ $section['title'] }}
                        @if ($section['sensitive'] ?? false)
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold text-gold-100 ring-1 ring-gold-400/60"><flux:icon.lock-closed class="size-3" /> Sensible</span>
                        @endif
                    </span>
                    <span class="mt-0.5 block text-sm text-slate-400">{{ $section['text'] }}</span>
                </span>
                <flux:icon.chevron-right class="mt-2 size-4 shrink-0 text-slate-500" />
            </a>
        @endforeach
        <a href="{{ route('admin.testimonials.index') }}" class="card flex items-start gap-3 p-4 hover:bg-veil/5">
            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-electric-500/15 text-electric-200 ring-1 ring-electric-400/30"><flux:icon.chat-bubble-bottom-center-text class="size-5" /></span>
            <span class="min-w-0 flex-1">
                <span class="block font-semibold text-white">Témoignages</span>
                <span class="mt-0.5 block text-sm text-slate-400">Texte avec photo, capture WhatsApp ou vidéo YouTube. Ajouter, modifier, masquer, ordonner.</span>
            </span>
            <flux:icon.chevron-right class="mt-2 size-4 shrink-0 text-slate-500" />
        </a>
        <a href="{{ route('admin.consultations.availability') }}" class="card flex items-start gap-3 p-4 hover:bg-veil/5">
            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-electric-500/15 text-electric-200 ring-1 ring-electric-400/30"><flux:icon.clock class="size-5" /></span>
            <span class="min-w-0 flex-1">
                <span class="block font-semibold text-white">Consultation gratuite</span>
                <span class="mt-0.5 block text-sm text-slate-400">Lien Google Meet, horaires, jours bloqués, durée et délais.</span>
            </span>
            <flux:icon.chevron-right class="mt-2 size-4 shrink-0 text-slate-500" />
        </a>
    </div>
</x-admin.page>
