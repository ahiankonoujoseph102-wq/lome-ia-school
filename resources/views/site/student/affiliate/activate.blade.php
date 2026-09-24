<x-layouts::site title="Affiliation" variant="student">
    <div class="mx-auto max-w-2xl px-4 pt-6 pb-12 sm:px-6 sm:pt-10">
        <span class="eyebrow !border-gold-400/30 !bg-gold-400/10 !text-gold-200">Programme d'affiliation</span>
        <h1 class="mt-4 text-2xl font-bold text-white sm:text-3xl">Recommandez nos formations</h1>
        <p class="mt-2 text-slate-400">Partagez vos liens personnels. Pour chaque achat validé passé par l'un de vos liens, vous recevez une commission de {{ config('affiliate.rate') }} % du prix payé, retirable par Mobile Money. Aucun achat n'est nécessaire pour devenir affilié.</p>

        <form method="POST" action="{{ route('student.affiliate.activate') }}" class="card mt-6 space-y-5 p-5 sm:p-6">
            @csrf
            <h2 class="font-semibold text-white">Conditions du programme</h2>
            @include('site.partials.affiliate-rules')
            <p class="text-sm text-slate-400">Conditions complètes : article « Programme d'affiliation » des <a href="{{ route('legal.terms') }}#affiliation" class="text-electric-300 underline underline-offset-2 hover:text-white">conditions générales de vente</a>.</p>

            <label class="flex items-start gap-3 rounded-2xl bg-veil/[0.03] p-4 text-sm text-slate-200 ring-1 ring-veil/10">
                <input type="checkbox" name="terms" value="1" class="mt-0.5 size-5 shrink-0 accent-gold-400" @checked(old('terms')) required>
                <span>J'ai lu et j'accepte les conditions du programme d'affiliation.</span>
            </label>
            @error('terms')
                <p class="text-sm text-rose-300">{{ $message }}</p>
            @enderror

            <button type="submit" class="btn-gold w-full">Activer mon compte affilié</button>
        </form>
    </div>
</x-layouts::site>
