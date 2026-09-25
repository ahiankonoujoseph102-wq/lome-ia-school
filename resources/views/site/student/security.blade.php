@php
    $messages = [
        'password-updated' => 'Votre mot de passe a été modifié.',
        'two-factor-authentication-confirmed' => 'La double authentification est activée. Conservez vos codes de récupération en lieu sûr.',
        'two-factor-authentication-disabled' => 'La double authentification est désactivée.',
        'recovery-codes-generated' => 'De nouveaux codes de récupération ont été créés. Les anciens ne fonctionnent plus.',
        'passkey-deleted' => 'La passkey a été supprimée.',
    ];
    $status = session('status');
    $showCodes = in_array($status, ['two-factor-authentication-confirmed', 'recovery-codes-generated'], true);
@endphp

<x-layouts::site title="Sécurité du compte" variant="student">
    <x-slot:head>
        @vite('resources/js/passkeys.js')
    </x-slot:head>

    <div class="mx-auto max-w-2xl space-y-6 px-4 pt-6 pb-12 sm:px-6 sm:pt-10">
        <div>
            <a href="{{ route('student.profile') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
                <flux:icon.arrow-left class="size-4" /> Mon profil
            </a>
            <h1 class="mt-3 text-2xl font-bold text-white sm:text-3xl">Sécurité du compte</h1>
            <p class="mt-1 text-slate-400">Protégez l'accès à vos formations.</p>
        </div>

        @if (isset($messages[$status]))
            <x-site.alert>{{ $messages[$status] }}</x-site.alert>
        @endif

        @if ($user->isStaff() && ! $twoFactorEnabled)
            <div class="rounded-2xl border-2 border-gold-400 bg-gold-400/15 px-4 py-3 text-sm text-gold-100" role="alert" data-test="two-factor-required">
                <p class="font-bold text-white">Double authentification obligatoire</p>
                <p class="mt-1">{{ \App\Http\Middleware\RequireStaffTwoFactor::MESSAGE }} Installez une application d'authentification (Google Authenticator, Microsoft Authenticator…), puis suivez les étapes ci-dessous.</p>
            </div>
        @endif

        {{-- Mot de passe --}}
        <section class="card p-5 sm:p-7" aria-labelledby="titre-mot-de-passe">
            <h2 id="titre-mot-de-passe" class="flex items-center gap-2 text-lg font-semibold text-white">
                <flux:icon.lock-closed class="size-5 text-electric-300" /> Mot de passe
            </h2>
            <p class="mt-1 text-sm text-slate-400">8 caractères minimum, avec des lettres et des chiffres.</p>

            <form method="POST" action="{{ route('security.password.update') }}" class="mt-5 space-y-5">
                @csrf
                @method('PUT')
                @php $passwordErrors = $errors->updatePassword; @endphp
                <div class="space-y-1.5">
                    <label for="mot-de-passe-actuel" class="field-label">Mot de passe actuel</label>
                    <input id="mot-de-passe-actuel" name="current_password" type="password" required autocomplete="current-password" @class(['field-input', 'field-input-error' => $passwordErrors->has('current_password')])>
                    @if ($passwordErrors->has('current_password'))
                        <p class="text-sm text-rose-300">{{ $passwordErrors->first('current_password') }}</p>
                    @endif
                </div>
                <div class="space-y-1.5" x-data="{ visible: false }">
                    <label for="nouveau-mot-de-passe" class="field-label">Nouveau mot de passe</label>
                    <div class="relative">
                        <input id="nouveau-mot-de-passe" name="password" :type="visible ? 'text' : 'password'" type="password" required minlength="8" autocomplete="new-password" @class(['field-input pr-24', 'field-input-error' => $passwordErrors->has('password')])>
                        <button type="button" @click="visible = !visible" class="absolute inset-y-1.5 right-1.5 rounded-xl px-3 text-sm font-medium text-slate-300 hover:bg-veil/10" x-text="visible ? 'Masquer' : 'Afficher'">Afficher</button>
                    </div>
                    @if ($passwordErrors->has('password'))
                        <p class="text-sm text-rose-300">{{ $passwordErrors->first('password') }}</p>
                    @endif
                </div>
                <button type="submit" class="btn-gold w-full sm:w-auto" data-test="update-password-button">Modifier le mot de passe</button>
            </form>
        </section>

        {{-- Double authentification --}}
        @if ($canManageTwoFactor)
            <section class="card p-5 sm:p-7" aria-labelledby="titre-2fa">
                <div class="flex items-start justify-between gap-3">
                    <h2 id="titre-2fa" class="flex items-center gap-2 text-lg font-semibold text-white">
                        <flux:icon.shield-check class="size-5 text-electric-300" /> Double authentification
                    </h2>
                    @if ($twoFactorEnabled)
                        <span class="rounded-full border border-emerald-400/30 bg-emerald-400/10 px-2.5 py-1 text-xs font-semibold text-emerald-200">Activée</span>
                    @else
                        <span class="rounded-full border border-veil/15 bg-veil/5 px-2.5 py-1 text-xs font-semibold text-slate-300">{{ $user->isStaff() ? 'Obligatoire' : 'Facultative' }}</span>
                    @endif
                </div>

                @if ($twoFactorEnabled)
                    <p class="mt-2 text-sm text-slate-400">À chaque connexion, un code à 6 chiffres vous est demandé. Vous le trouvez dans votre application d'authentification.</p>

                    {{-- Codes de récupération : très visibles juste après l'activation ou le renouvellement. Style stable en mode sombre forcé. --}}
                    <div @class([
                        'mt-5 rounded-2xl p-4 sm:p-5',
                        'border-2 border-gold-400 bg-gold-400/10' => $showCodes,
                        'border border-veil/10 bg-veil/[0.03]' => ! $showCodes,
                    ]) x-data="{ open: @js($showCodes), copied: false, codes: @js(implode("\n", $recoveryCodes)), copy() { const done = () => { this.copied = true; setTimeout(() => this.copied = false, 2500); }; if (navigator.clipboard?.writeText) { navigator.clipboard.writeText(this.codes).then(done); } else { const area = this.$refs.codes; area.hidden = false; area.select(); document.execCommand('copy'); area.hidden = true; done(); } } }" data-test="recovery-codes">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="flex items-center gap-2 font-semibold text-white"><flux:icon.key class="size-5 text-gold-300" /> Codes de récupération</p>
                                <p class="text-sm text-slate-300">À utiliser si vous perdez votre téléphone : chacun remplace une seule fois le code à 6 chiffres.</p>
                            </div>
                            @unless ($showCodes)
                                <button type="button" @click="open = !open" class="btn-ghost !px-4 !py-2 !text-sm" x-text="open ? 'Masquer les codes' : 'Voir les codes'">Voir les codes</button>
                            @endunless
                        </div>
                        <div x-show="open" @unless ($showCodes) x-cloak @endunless class="mt-4 space-y-4">
                            <p class="rounded-xl border border-gold-400/70 bg-night-950/60 px-4 py-3 text-sm font-semibold text-gold-100" role="alert" data-test="recovery-warning">
                                <flux:icon.exclamation-triangle class="mr-1 inline size-5 align-text-bottom text-gold-300" />
                                {{ 'Notez ces codes hors de votre téléphone. Sans eux, la perte du téléphone vous empêche d\'accéder '.($user->isStaff() ? 'à l\'administration.' : 'à votre compte.') }}
                            </p>
                            <ul class="grid grid-cols-2 gap-2 rounded-xl bg-night-950/80 p-4 font-mono text-base tracking-wide text-gold-100 ring-1 ring-gold-400/40 select-all" aria-label="Codes de récupération">
                                @foreach ($recoveryCodes as $code)
                                    <li>{{ $code }}</li>
                                @endforeach
                            </ul>
                            <textarea x-ref="codes" hidden readonly class="sr-only" aria-hidden="true">{{ implode("\n", $recoveryCodes) }}</textarea>
                            <p class="text-xs text-slate-400">Par exemple : écrivez-les dans un carnet rangé chez vous, ou envoyez-les à une adresse e-mail que vous consultez depuis un autre appareil.</p>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" @click="copy()" class="inline-flex items-center gap-2 rounded-full px-4 py-2.5 text-sm font-semibold text-gold-100 ring-2 ring-gold-400 hover:bg-gold-400/15" data-test="copy-recovery-codes">
                                    <flux:icon.clipboard-document class="size-4" /> <span x-text="copied ? 'Codes copiés' : 'Copier les codes'">Copier les codes</span>
                                </button>
                                <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}" onsubmit="return confirm('Créer de nouveaux codes ? Les anciens ne fonctionneront plus.')">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-2 rounded-full px-4 py-2.5 text-sm font-semibold text-slate-200 ring-1 ring-veil/20 hover:bg-veil/10"><flux:icon.arrow-path class="size-4" /> Générer de nouveaux codes</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-5">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn w-full border border-rose-400/40 bg-rose-500/10 !py-3 !text-sm text-rose-200 hover:bg-rose-500/20 sm:w-auto">Désactiver la double authentification</button>
                    </form>
                @elseif ($settingUpTwoFactor)
                    <ol class="mt-5 space-y-6">
                        <li class="flex gap-3">
                            <span class="grid size-7 shrink-0 place-items-center rounded-full bg-gold-400 text-sm font-bold text-night-950">1</span>
                            <div>
                                <p class="font-semibold text-white">Installez une application d'authentification</p>
                                <p class="text-sm text-slate-400">Par exemple Google Authenticator ou Microsoft Authenticator, gratuites sur votre téléphone.</p>
                            </div>
                        </li>
                        <li class="flex gap-3">
                            <span class="grid size-7 shrink-0 place-items-center rounded-full bg-gold-400 text-sm font-bold text-night-950">2</span>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-white">Scannez ce QR code avec l'application</p>
                                <div class="qr-code mt-3 inline-block rounded-2xl bg-white p-3 [&_svg]:size-44">{!! $qrCodeSvg !!}</div>
                                <p class="mt-3 text-sm text-slate-400">Ou saisissez cette clé dans l'application :</p>
                                <div class="mt-2 flex items-center gap-2" x-data="{ copied: false }">
                                    <code class="min-w-0 flex-1 rounded-xl bg-night-950/80 px-3 py-2.5 font-mono text-sm break-all text-gold-200 ring-1 ring-veil/10">{{ $setupKey }}</code>
                                    <button type="button" class="btn-ghost shrink-0 !px-3 !py-2.5 !text-sm"
                                        @click="navigator.clipboard?.writeText(@js($setupKey)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                        x-text="copied ? 'Copiée' : 'Copier'">Copier</button>
                                </div>
                            </div>
                        </li>
                        <li class="flex gap-3">
                            <span class="grid size-7 shrink-0 place-items-center rounded-full bg-gold-400 text-sm font-bold text-night-950">3</span>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-white">Saisissez le code à 6 chiffres affiché</p>
                                <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-3 space-y-3">
                                    @csrf
                                    @php $confirmErrors = $errors->confirmTwoFactorAuthentication; @endphp
                                    <label for="code-2fa" class="sr-only">Code à 6 chiffres</label>
                                    <input id="code-2fa" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" required placeholder="123456"
                                        @class(['field-input max-w-[12rem] text-center font-mono text-xl tracking-[0.4em]', 'field-input-error' => $confirmErrors->has('code')])>
                                    @if ($confirmErrors->has('code'))
                                        <p class="text-sm text-rose-300">Code incorrect. Vérifiez l'heure de votre téléphone et réessayez.</p>
                                    @endif
                                    <button type="submit" class="btn-gold w-full sm:w-auto">Activer</button>
                                </form>
                            </div>
                        </li>
                    </ol>
                    <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-5">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-slate-400 hover:text-white">Annuler l'activation</button>
                    </form>
                @else
                    <p class="mt-2 text-sm text-slate-400">Ajoutez une protection supplémentaire : en plus du mot de passe, un code à 6 chiffres affiché sur votre téléphone vous sera demandé à la connexion.</p>
                    <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-5">
                        @csrf
                        <button type="submit" class="btn-violet !py-3 !text-sm">Activer la double authentification</button>
                    </form>
                @endif
            </section>
        @endif

        {{-- Passkeys --}}
        @if ($canManagePasskeys)
            <section class="card p-5 sm:p-7" aria-labelledby="titre-passkeys">
                <h2 id="titre-passkeys" class="flex items-center gap-2 text-lg font-semibold text-white">
                    <flux:icon.finger-print class="size-5 text-electric-300" /> Passkeys
                </h2>
                <p class="mt-1 text-sm text-slate-400">Connectez-vous sans mot de passe, avec l'empreinte, Face ID ou le code de votre téléphone.</p>

                <ul class="mt-5 divide-y divide-veil/10 overflow-hidden rounded-2xl border border-veil/10">
                    @forelse ($passkeys as $passkey)
                        <li class="flex items-center gap-3 p-4" x-data="{ confirming: false }">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-veil/5 text-electric-300"><flux:icon.key class="size-5" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-white">{{ $passkey->name }}</p>
                                <p class="text-xs text-slate-500">
                                    Ajoutée {{ $passkey->created_at?->diffForHumans() }}
                                    @if ($passkey->last_used_at)
                                        · Utilisée {{ $passkey->last_used_at->diffForHumans() }}
                                    @endif
                                </p>
                            </div>
                            <button type="button" x-show="!confirming" @click="confirming = true" class="grid size-10 place-items-center rounded-xl text-slate-400 hover:bg-rose-500/10 hover:text-rose-300" aria-label="Supprimer la passkey {{ $passkey->name }}">
                                <flux:icon.trash class="size-5" />
                            </button>
                            <form x-show="confirming" x-cloak method="POST" action="{{ route('passkey.destroy', $passkey) }}" class="flex items-center gap-2">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-full bg-rose-500/15 px-3 py-2 text-xs font-semibold text-rose-200 hover:bg-rose-500/25">Supprimer</button>
                                <button type="button" @click="confirming = false" class="rounded-full px-3 py-2 text-xs font-semibold text-slate-400 hover:text-white">Annuler</button>
                            </form>
                        </li>
                    @empty
                        <li class="p-6 text-center">
                            <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-veil/5 text-slate-400"><flux:icon.key class="size-6" /></span>
                            <p class="mt-3 font-medium text-white">Aucune passkey pour le moment</p>
                            <p class="mt-1 text-sm text-slate-400">Ajoutez-en une pour vous connecter en un geste.</p>
                        </li>
                    @endforelse
                </ul>

                <div class="mt-5">
                    <x-site.passkey-registration />
                </div>
            </section>
        @endif
    </div>
</x-layouts::site>
