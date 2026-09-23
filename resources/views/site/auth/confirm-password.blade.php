<x-layouts::site title="Confirmer le mot de passe" noindex>
    <x-site.auth-card title="Zone sécurisée" subtitle="Confirmez votre mot de passe pour continuer.">
        <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-5">
            @csrf
            <x-site.password-field required autofocus autocomplete="current-password" />
            <button type="submit" class="btn-gold w-full" data-test="confirm-password-button">Confirmer</button>
        </form>
    </x-site.auth-card>
</x-layouts::site>
