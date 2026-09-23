{{-- Formulaire d'inscription, utilisé sur la page d'inscription et dans le récapitulatif de commande. --}}
@php
    $prefix ??= 'inscription';
    $showErrors ??= true;
@endphp

<form method="POST" action="{{ route('register.store') }}" class="space-y-5">
    @csrf
    <input type="hidden" name="form" value="register">

    <x-site.field
        name="name"
        label="Nom complet"
        :id="$prefix.'-name'"
        :show-errors="$showErrors"
        :value="$showErrors ? old('name') : ''"
        required
        autocomplete="name"
        autocapitalize="words"
        placeholder="Ex. : Kossi Mensah"
    />

    <x-site.phone-field :id="$prefix.'-phone'" :show-errors="$showErrors" :number="$showErrors ? null : ''" />

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

    <x-site.password-field
        :id="$prefix.'-password'"
        :show-errors="$showErrors"
        required
        autocomplete="new-password"
        minlength="8"
        hint="8 caractères minimum, avec des lettres et des chiffres."
    />

    <button type="submit" class="btn-gold w-full" data-test="register-user-button">Créer mon compte</button>

    <p class="text-center text-xs leading-relaxed text-slate-500">
        En créant votre compte, vous acceptez nos <a href="{{ route('legal.terms') }}" class="underline hover:text-slate-300">conditions générales de vente</a>
        et notre <a href="{{ route('legal.privacy') }}" class="underline hover:text-slate-300">politique de confidentialité</a>.
    </p>
</form>
