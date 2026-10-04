{{--
    « Confirmez votre e-mail » : code à 6 chiffres, « Renvoyer le code » (une fois par minute, 5 fois par heure),
    correction d'une adresse mal tapée. Style stable face au mode sombre forcé : fonds foncés ou translucides,
    textes clairs, contours colorés (aucun texte foncé sur fond clair, sauf le bouton doré déjà compensé).
--}}
@php
    use App\Accounts\EmailConfirmation;

    $changeErrors = $errors->getBag('changeEmail');
@endphp

<x-layouts::site title="Confirmez votre e-mail" variant="student">
    <div class="mx-auto max-w-lg px-4 pt-6 pb-12 sm:pt-10">
        <div class="text-center">
            <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-electric-500/15 text-electric-200 ring-1 ring-electric-400/40">
                <flux:icon.envelope class="size-7" />
            </span>
            <h1 class="mt-4 text-2xl font-bold text-white sm:text-3xl">Confirmez votre e-mail</h1>
            <p class="mt-2 text-pretty text-slate-300">
                @if ($hasPendingCode)
                    Nous avons envoyé un code à 6 chiffres à
                @else
                    Demandez un code à 6 chiffres : il sera envoyé à
                @endif
                <strong class="break-words text-white" data-test="confirmation-email">{{ $user->email }}</strong>.
            </p>
        </div>

        @if ($freeChoice)
            <div class="mt-6">
                @include('site.partials.free-choice-reminder', ['step' => 'confirm'])
            </div>
        @endif

        @if (session('status'))
            <x-site.alert type="info" class="mt-6">{{ session('status') }}</x-site.alert>
        @endif

        <div class="card mt-6 space-y-5 p-5 sm:p-7">
            @if ($lockedFor > 0)
                <x-site.alert type="error" data-test="confirmation-locked">
                    Trop de codes erronés. Pour votre sécurité, la saisie est bloquée pendant encore {{ EmailConfirmation::duration($lockedFor) }}. Demandez ensuite un nouveau code.
                </x-site.alert>
            @endif

            <form method="POST" action="{{ route('email-confirmation.confirm') }}" class="space-y-4">
                @csrf
                <div class="space-y-1.5">
                    <label for="code" class="field-label">Code de confirmation</label>
                    <input
                        id="code"
                        name="code"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        pattern="[0-9 ]*"
                        maxlength="7"
                        placeholder="123456"
                        required
                        @disabled($lockedFor > 0)
                        @if ($errors->has('code')) aria-invalid="true" aria-describedby="code-erreur" @endif
                        @class(['field-input text-center font-mono text-2xl tracking-[0.4em] placeholder:tracking-[0.4em] disabled:opacity-50', 'field-input-error' => $errors->has('code')])
                    >
                    @error('code')
                        <p id="code-erreur" class="text-sm text-rose-300" data-test="code-error">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" data-ama-clear class="btn-gold w-full disabled:cursor-not-allowed disabled:opacity-40 disabled:shadow-none disabled:brightness-75" @disabled($lockedFor > 0)>
                    <flux:icon.check-circle class="size-5" /> Confirmer mon adresse
                </button>
                <p class="text-center text-xs text-slate-400">Vous pouvez aussi cliquer sur le bouton « Confirmer mon adresse e-mail » de l'e-mail. Code et bouton sont valables {{ $minutes }} minutes et ne servent qu'une fois.</p>
            </form>

            {{-- Message indispensable : beaucoup d'e-mails arrivent dans les indésirables --}}
            <div class="flex items-start gap-3 rounded-2xl px-4 py-3 text-sm text-gold-100 ring-1 ring-gold-400/40 ring-inset bg-gold-400/10" data-test="spam-hint">
                <flux:icon.inbox class="mt-0.5 size-5 shrink-0 text-gold-300" />
                <p><strong class="text-gold-200">Vous ne trouvez pas l'e-mail ?</strong> Vérifiez aussi vos courriers indésirables (spam). Il vient de Lomé IA School et peut mettre une minute à arriver.</p>
            </div>

            {{-- Nouveau code (limité) --}}
            <form method="POST" action="{{ route('email-confirmation.resend') }}" class="border-t border-veil/10 pt-5 text-center"
                x-data="{ wait: {{ (int) $resendIn }}, tick() { if (this.wait > 0) setTimeout(() => { this.wait--; this.tick() }, 1000) } }" x-init="tick()">
                @csrf
                <button type="submit" class="btn-ghost w-full disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto" :disabled="wait > 0" @disabled($resendIn > 0) data-test="resend-button">
                    <flux:icon.arrow-path class="size-4" /> Renvoyer le code
                </button>
                <p class="mt-2 text-xs text-slate-400" x-show="wait > 0" @if ($resendIn <= 0) x-cloak @endif>
                    Nouveau code possible dans <span x-text="wait >= 60 ? Math.ceil(wait / 60) + ' min' : wait + ' s'">{{ $resendIn >= 60 ? (int) ceil($resendIn / 60).' min' : $resendIn.' s' }}</span>.
                </p>
                @error('resend')
                    <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                @enderror
            </form>
        </div>

        {{-- Adresse mal tapée --}}
        <details class="card group mt-6 p-5 sm:p-6" @if ($changeErrors->any()) open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-semibold text-white">
                <span class="flex items-center gap-2"><flux:icon.pencil-square class="size-5 text-electric-300" /> Adresse mal tapée ? Corrigez-la</span>
                <flux:icon.chevron-down class="size-5 shrink-0 text-slate-400 transition group-open:rotate-180" />
            </summary>
            <form method="POST" action="{{ route('email-confirmation.email') }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')
                <div class="space-y-1.5">
                    <label for="nouvelle-adresse" class="field-label">Nouvelle adresse e-mail</label>
                    <input id="nouvelle-adresse" name="email" type="email" inputmode="email" autocomplete="email" required value="{{ old('email') }}"
                        @class(['field-input', 'field-input-error' => $changeErrors->has('email')])>
                    @if ($changeErrors->has('email'))
                        <p class="text-sm text-rose-300">{{ $changeErrors->first('email') }}</p>
                    @endif
                </div>
                <button type="submit" class="btn-ghost w-full sm:w-auto">Enregistrer et recevoir un nouveau code</button>
            </form>
        </details>

        <p class="mt-6 text-center text-sm text-slate-400">
            En attendant, vous pouvez visiter le site et votre espace. La confirmation est nécessaire pour payer une formation ou demander un retrait.
        </p>
    </div>
</x-layouts::site>
