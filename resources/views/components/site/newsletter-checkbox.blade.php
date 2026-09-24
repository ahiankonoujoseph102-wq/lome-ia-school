{{-- Case « nouvelles formations » : inscription et profil. Non cochée par défaut. --}}
@props(['id' => 'wants-newsletter', 'checked' => false])

<label for="{{ $id }}" class="flex cursor-pointer items-start gap-3 rounded-2xl bg-white/[0.03] p-4 text-sm text-slate-300 ring-1 ring-white/10 transition hover:bg-white/[0.06]">
    <input
        id="{{ $id }}"
        type="checkbox"
        name="wants_newsletter"
        value="1"
        @checked($checked)
        class="mt-0.5 size-5 shrink-0 rounded-md border-white/20 bg-white/5 accent-electric-500"
    >
    <span>
        <span class="block font-medium text-slate-200">Je souhaite être informé(e) des nouvelles formations</span>
        <span class="mt-0.5 block text-xs text-slate-500">Facultatif. Vous pouvez changer d'avis à tout moment depuis votre profil.</span>
    </span>
</label>
