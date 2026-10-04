<x-layouts::site title="Connexion" description="Connectez-vous à votre espace élève Lomé IA School." noindex>
    <x-slot:head>
        @vite('resources/js/passkeys.js')
    </x-slot:head>

    <x-site.auth-card title="Bon retour parmi nous" subtitle="Connectez-vous pour retrouver vos formations.">
        <x-slot:notice>
            @include('site.partials.free-choice-reminder', ['step' => 'login'])
        </x-slot:notice>

        @if (session('status'))
            <x-site.alert class="mb-5">{{ session('status') }}</x-site.alert>
        @endif
        @if (session('warning'))
            <x-site.alert type="error" class="mb-5">{{ session('warning') }}</x-site.alert>
        @endif

        <x-site.passkey-login />

        @include('site.auth.partials.login-form')

        <x-slot:footer>
            <p class="mt-6 text-center text-sm text-slate-400">
                Pas encore de compte ?
                <a href="{{ route('register') }}" class="font-semibold text-electric-300 hover:text-white">Créer un compte gratuitement</a>
            </p>
        </x-slot:footer>
    </x-site.auth-card>
</x-layouts::site>
