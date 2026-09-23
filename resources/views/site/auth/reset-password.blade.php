<x-layouts::site title="Nouveau mot de passe" noindex>
    <x-site.auth-card title="Choisissez un nouveau mot de passe">
        <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ request()->route('token') }}">
            <x-site.field name="email" label="Adresse e-mail" type="email" :value="old('email', request('email'))" required autocomplete="email" />
            <x-site.password-field label="Nouveau mot de passe" required autocomplete="new-password" hint="8 caractères minimum, avec des lettres et des chiffres." />
            <x-site.password-field name="password_confirmation" label="Confirmer le mot de passe" required autocomplete="new-password" />
            <button type="submit" class="btn-gold w-full">Enregistrer le mot de passe</button>
        </form>
    </x-site.auth-card>
</x-layouts::site>
