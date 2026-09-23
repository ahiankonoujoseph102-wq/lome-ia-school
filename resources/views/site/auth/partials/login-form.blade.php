{{-- Formulaire de connexion, utilisé sur la page de connexion et dans le récapitulatif de commande. --}}
@php
    $prefix ??= 'connexion';
    $showErrors ??= true;
@endphp

<form method="POST" action="{{ route('login.store') }}" class="space-y-5">
    @csrf
    <input type="hidden" name="form" value="login">

    <x-site.field
        name="email"
        label="Adresse e-mail"
        type="email"
        :id="$prefix.'-email'"
        :show-errors="$showErrors"
        :value="$showErrors ? old('email') : ''"
        required
        autocomplete="email"
        inputmode="email"
        placeholder="vous@exemple.com"
    />

    <x-site.password-field :id="$prefix.'-password'" :show-errors="$showErrors" required autocomplete="current-password">
        <x-slot:action>
            <a href="{{ route('password.request') }}" class="text-sm font-medium text-electric-300 hover:text-white">Mot de passe oublié ?</a>
        </x-slot:action>
    </x-site.password-field>

    <label class="flex items-center gap-3 text-sm text-slate-300">
        <input type="checkbox" name="remember" value="1" @checked(old('remember', true)) class="size-5 rounded-md border-white/20 bg-white/5 accent-electric-500">
        Rester connecté sur cet appareil
    </label>

    <button type="submit" class="btn-gold w-full" data-test="login-button">Se connecter</button>
</form>
