{{-- Réglage sensible : le mot de passe est redemandé, et un e-mail d'alerte est envoyé à l'admin. --}}
@props(['bag' => 'default'])

@php $errorBag = $errors->getBag($bag); @endphp

<div class="space-y-2 rounded-2xl border border-gold-400/50 bg-gold-400/10 p-4">
    <p class="flex items-center gap-2 text-sm font-semibold text-gold-100"><flux:icon.lock-closed class="size-4" /> Réglage sensible</p>
    <p class="text-sm text-slate-300">Pour enregistrer, confirmez avec votre mot de passe. Un e-mail d'alerte vous est envoyé à chaque modification.</p>
    <label for="confirm-current-password" class="field-label">Votre mot de passe</label>
    <input id="confirm-current-password" name="current_password" type="password" required autocomplete="current-password" @class(['field-input', 'field-input-error' => $errorBag->has('current_password')])>
    @if ($errorBag->has('current_password'))
        <p class="text-sm text-rose-300">{{ $errorBag->first('current_password') }}</p>
    @endif
</div>
