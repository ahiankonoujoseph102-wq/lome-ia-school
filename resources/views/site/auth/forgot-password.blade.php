<x-layouts::site title="Mot de passe oublié" noindex>
    <x-site.auth-card title="Mot de passe oublié ?" subtitle="Saisissez votre e-mail : nous vous envoyons un lien pour choisir un nouveau mot de passe.">
        @if (session('status'))
            <x-site.alert class="mb-5">{{ session('status') }}</x-site.alert>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
            @csrf
            <x-site.field name="email" label="Adresse e-mail" type="email" required autofocus autocomplete="email" inputmode="email" placeholder="vous@exemple.com" />
            <button type="submit" class="btn-gold w-full">Recevoir le lien</button>
        </form>

        <x-slot:footer>
            <p class="mt-6 text-center text-sm text-slate-400">
                <a href="{{ route('login') }}" class="font-semibold text-electric-300 hover:text-white">Retour à la connexion</a>
            </p>
        </x-slot:footer>
    </x-site.auth-card>
</x-layouts::site>
