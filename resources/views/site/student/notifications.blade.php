<x-layouts::site title="Notifications" variant="student">
    <div class="mx-auto max-w-2xl space-y-6 px-4 pt-6 pb-12 sm:px-6 sm:pt-10">
        <div>
            <a href="{{ route('student.profile') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
                <flux:icon.arrow-left class="size-4" /> Mon profil
            </a>
            <h1 class="mt-4 text-2xl font-bold text-white sm:text-3xl">Notifications</h1>
            <p class="mt-1 text-slate-400">Choisissez comment être prévenu des nouveaux messages de vos groupes.</p>
        </div>

        @if (session('status'))
            <x-site.alert>{{ session('status') }}</x-site.alert>
        @endif

        {{-- Activation sur cet appareil --}}
        <section
            class="card p-5 sm:p-6"
            x-data="pushNotifications(@js([
                'publicKey' => $vapidPublicKey,
                'subscribeUrl' => route('student.notifications.subscribe'),
                'unsubscribeUrl' => route('student.notifications.unsubscribe'),
            ]))"
            data-test="push-card"
        >
            <div class="flex items-start gap-4">
                <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-electric-500/15 text-electric-300"><flux:icon.device-phone-mobile class="size-6" /></span>
                <div class="min-w-0 flex-1">
                    <h2 class="font-semibold text-white">Notifications sur ce téléphone</h2>
                    <p class="mt-1 text-sm text-slate-400">Pour ne manquer aucune réponse du formateur, aucune annonce ni aucun lien de session en direct, même quand le site est fermé. Vous pouvez les couper à tout moment.</p>
                </div>
            </div>

            @if (! $pushAvailable)
                <p class="mt-4 rounded-2xl bg-veil/[0.04] px-4 py-3 text-sm text-slate-400 ring-1 ring-veil/10">Les notifications sur téléphone seront bientôt disponibles. En attendant, vous recevez un e-mail récapitulatif.</p>
            @else
                {{-- iPhone : le site doit d'abord être ajouté à l'écran d'accueil --}}
                <div x-show="ios && !standalone" x-cloak class="mt-4 rounded-2xl bg-gold-400/10 p-4 text-sm text-gold-100 ring-1 ring-gold-400/30" data-test="ios-help">
                    <p class="font-semibold">Sur iPhone, une étape de plus :</p>
                    <ol class="mt-2 list-decimal space-y-1 pl-5">
                        <li>Ouvrez ce site dans <strong>Safari</strong>.</li>
                        <li>Appuyez sur le bouton <strong>Partager</strong> <flux:icon.arrow-up-on-square class="inline size-4 align-[-3px]" /> en bas de l'écran.</li>
                        <li>Choisissez <strong>« Sur l'écran d'accueil »</strong>, puis <strong>Ajouter</strong>.</li>
                        <li>Ouvrez Lomé IA School depuis la nouvelle icône, revenez ici et appuyez sur « Activer ».</li>
                    </ol>
                    <p class="mt-2 text-xs text-gold-200/80">Nécessite iOS 16.4 ou plus récent.</p>
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <template x-if="supported && !subscribed">
                        <button type="button" class="btn-gold" @click="enable()" :disabled="busy || (ios && !standalone)">
                            <flux:icon.bell-alert class="size-5" /> Activer les notifications
                        </button>
                    </template>
                    <template x-if="supported && subscribed">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex items-center gap-2 rounded-full bg-emerald-400/15 px-4 py-2 text-sm font-semibold text-emerald-200 ring-1 ring-emerald-400/30"><flux:icon.check-circle class="size-5" /> Activées sur cet appareil</span>
                            <button type="button" class="text-sm font-semibold text-slate-400 hover:text-white" @click="disable()" :disabled="busy">Désactiver</button>
                        </div>
                    </template>
                    <p x-show="!supported && !(ios && !standalone)" x-cloak class="text-sm text-slate-400">Ce navigateur ne permet pas les notifications. Essayez avec Chrome (Android) ou Safari (iPhone).</p>
                </div>
                <p x-show="message" x-text="message" x-cloak class="mt-3 text-sm text-slate-300" role="status"></p>
                @if ($devicesCount > 0)
                    <p class="mt-3 text-xs text-slate-500">{{ $devicesCount }} {{ $devicesCount > 1 ? 'appareils enregistrés' : 'appareil enregistré' }} sur votre compte.</p>
                @endif
            @endif
        </section>

        {{-- Réglages --}}
        <form method="POST" action="{{ route('student.notifications.update') }}" class="card space-y-5 p-5 sm:p-6" data-test="notification-settings">
            @csrf
            @method('PUT')

            <label class="flex items-start gap-4">
                <input type="checkbox" name="chat_push_enabled" value="1" @checked($user->chat_push_enabled) class="mt-1 size-5 shrink-0 rounded accent-electric-500">
                <span>
                    <span class="block font-semibold text-white">Notifications sur le téléphone</span>
                    <span class="block text-sm text-slate-400">Nouveaux messages de vos groupes et rappels des sessions (sur les appareils activés).</span>
                </span>
            </label>

            <label class="flex items-start gap-4">
                <input type="checkbox" name="chat_digest_enabled" value="1" @checked($user->chat_digest_enabled) class="mt-1 size-5 shrink-0 rounded accent-electric-500">
                <span>
                    <span class="block font-semibold text-white">E-mail récapitulatif</span>
                    <span class="block text-sm text-slate-400">Au plus un e-mail par jour, seulement si des messages vous attendent, avec un lien direct vers le groupe.</span>
                </span>
            </label>

            <label class="flex items-start gap-4">
                <input type="checkbox" name="session_email_enabled" value="1" @checked($user->session_email_enabled) class="mt-1 size-5 shrink-0 rounded accent-electric-500">
                <span>
                    <span class="block font-semibold text-white">Rappels des sessions par e-mail</span>
                    <span class="block text-sm text-slate-400">La veille et 1 h avant chaque session en direct ou à Lomé qui vous concerne. Les annulations et changements importants vous sont toujours envoyés.</span>
                </span>
            </label>

            @if ($memberships->isNotEmpty())
                <fieldset class="border-t border-veil/10 pt-5">
                    <legend class="font-semibold text-white">Couper un groupe</legend>
                    <p class="mt-1 text-sm text-slate-400">Aucune notification (téléphone ni e-mail) pour les groupes cochés.</p>
                    <div class="mt-3 space-y-2">
                        @foreach ($memberships as $member)
                            <label class="flex items-center gap-3 rounded-2xl px-3 py-2.5 ring-1 ring-veil/10 has-[:checked]:bg-veil/[0.04]">
                                <input type="checkbox" name="muted_groups[]" value="{{ $member->chat_group_id }}" @checked($member->notifications_muted) class="size-5 shrink-0 rounded accent-electric-500">
                                <span class="min-w-0 flex-1 truncate text-sm text-slate-200">{{ $member->group->formation->title }}</span>
                                <flux:icon.bell-slash class="size-4 shrink-0 text-slate-500" />
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif

            <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer</button>
        </form>
    </div>
</x-layouts::site>
