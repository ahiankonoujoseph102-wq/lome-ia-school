<x-layouts::site title="Créer un compte" description="Créez votre compte Lomé IA School en moins d'une minute." noindex>
    <x-site.auth-card title="Créez votre compte" subtitle="Moins d'une minute, et vous accédez à votre espace élève.">
        @include('site.auth.partials.register-form')

        <x-slot:footer>
            <p class="mt-6 text-center text-sm text-slate-400">
                Vous avez déjà un compte ?
                <a href="{{ route('login') }}" class="font-semibold text-electric-300 hover:text-white">Se connecter</a>
            </p>
            <ul class="mt-8 grid gap-2 text-sm text-slate-400">
                <li class="flex items-center gap-2"><flux:icon.check-circle class="size-4 text-gold-400" /> Inscription gratuite, sans engagement</li>
                <li class="flex items-center gap-2"><flux:icon.check-circle class="size-4 text-gold-400" /> Paiement par Mobile Money au moment de l'achat</li>
                <li class="flex items-center gap-2"><flux:icon.check-circle class="size-4 text-gold-400" /> Votre lien d'affiliation : {{ config('affiliate.rate') }} % sur chaque achat validé</li>
            </ul>
        </x-slot:footer>
    </x-site.auth-card>
</x-layouts::site>
