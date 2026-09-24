<x-layouts::site title="Groupe réservé" variant="student">
    <div class="mx-auto max-w-md px-4 pt-10 pb-16 text-center sm:px-6">
        <span class="mx-auto grid size-16 place-items-center rounded-2xl bg-rose-500/15 text-rose-300"><flux:icon.lock-closed class="size-8" /></span>
        @if ($excluded)
            <h1 class="mt-5 text-2xl font-bold text-white">Vous avez été retiré de ce groupe</h1>
            <p class="mt-2 text-slate-400">L'équipe vous a retiré du groupe « {{ $formation->title }} ». Votre accès aux leçons n'est pas concerné. Pour toute question, écrivez-nous sur WhatsApp.</p>
        @else
            <h1 class="mt-5 text-2xl font-bold text-white">Groupe réservé aux élèves</h1>
            <p class="mt-2 text-slate-400">Le groupe de « {{ $formation->title }} » est réservé aux élèves qui ont acheté la formation.</p>
            <a href="{{ route('formations.show', $formation) }}" class="btn-gold mt-6">Découvrir la formation</a>
        @endif
        <a href="{{ route('chat.index') }}" class="mt-4 block text-sm font-semibold text-electric-300">Retour à mes groupes</a>
    </div>
</x-layouts::site>
