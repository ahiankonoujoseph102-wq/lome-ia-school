@php
    use App\Support\WhatsApp;

    $user = auth()->user();
    $activeTab = old('form') === 'login' ? 'login' : 'register';
    $whatsappMessage = 'Bonjour Lomé IA School, je souhaite acheter la formation « '.$formation->title.' » ('.str_replace("\u{00A0}", ' ', $formation->formatted_price).').'
        .($user ? ' Mon compte : '.$user->email.'.' : '');
    $moduleCount = count($formation->publicCurriculum());
@endphp

<x-layouts::site title="Récapitulatif de commande" :whatsapp-message="$whatsappMessage" noindex>
    <section class="relative isolate overflow-hidden">
        <div class="glow pointer-events-none absolute inset-0 -z-10 opacity-70"></div>

        <div class="mx-auto max-w-5xl px-4 pt-6 pb-16 sm:px-6 sm:pt-10">
            <a href="{{ route('formations.show', $formation) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
                <flux:icon.arrow-left class="size-4" /> Retour à la formation
            </a>

            <h1 class="mt-4 text-3xl font-bold tracking-tight text-white sm:text-4xl">Récapitulatif de commande</h1>

            {{-- Étapes du parcours --}}
            <ol class="mt-6 flex items-center gap-2 text-xs font-semibold sm:text-sm" aria-label="Étapes de la commande">
                @foreach (['Mon compte', 'Paiement', 'Accès immédiat'] as $step)
                    @php $done = $loop->first && $user; $current = ($loop->first && ! $user) || ($loop->index === 1 && $user); @endphp
                    <li class="flex items-center gap-2">
                        <span @class([
                            'grid size-7 place-items-center rounded-full ring-1',
                            'bg-emerald-400/15 text-emerald-300 ring-emerald-400/40' => $done,
                            'bg-gold-400 text-night-950 ring-gold-300' => $current,
                            'bg-white/5 text-slate-400 ring-white/15' => ! $done && ! $current,
                        ])>
                            @if ($done)
                                <flux:icon.check class="size-4" />
                            @else
                                {{ $loop->iteration }}
                            @endif
                        </span>
                        <span @class(['text-white' => $current, 'text-slate-400' => ! $current])>{{ $step }}</span>
                        @unless ($loop->last)
                            <span class="h-px w-4 bg-white/15 sm:w-10" aria-hidden="true"></span>
                        @endunless
                    </li>
                @endforeach
            </ol>

            <div class="mt-8 grid items-start gap-6 lg:grid-cols-[1fr_380px]">
                {{-- Colonne principale --}}
                <div class="order-2 space-y-6 lg:order-1">
                    @if ($alreadyEnrolled)
                        <div class="card p-6">
                            <x-site.alert>Vous avez déjà accès à cette formation.</x-site.alert>
                            <a href="{{ route('student.formations.show', $formation) }}" class="btn-gold mt-5 w-full sm:w-auto">Ouvrir la formation</a>
                        </div>
                    @elseif ($user)
                        {{-- Paiement (branché sur FedaPay à l'étape 4) --}}
                        <div class="card p-6" x-data="{ comingSoon: false }">
                            <div class="flex items-center gap-3">
                                <x-site.avatar :user="$user" class="size-11 text-sm" />
                                <div class="min-w-0">
                                    <p class="text-sm text-slate-400">Connecté en tant que</p>
                                    <p class="truncate font-semibold text-white">{{ $user->name }}</p>
                                </div>
                            </div>

                            <h2 class="mt-6 text-lg font-semibold text-white">Paiement</h2>
                            <p class="mt-1 text-sm text-slate-400">Réglez en quelques secondes avec votre compte Mobile Money.</p>
                            <div class="mt-4 grid grid-cols-2 gap-3">
                                <div class="rounded-2xl bg-white/5 p-3 text-center ring-1 ring-white/10">
                                    <p class="font-bold text-white">T-Money</p>
                                    <p class="text-xs text-slate-400">Yas</p>
                                </div>
                                <div class="rounded-2xl bg-white/5 p-3 text-center ring-1 ring-white/10">
                                    <p class="font-bold text-white">Flooz</p>
                                    <p class="text-xs text-slate-400">Moov</p>
                                </div>
                            </div>

                            <button type="button" @click="comingSoon = true" class="btn-gold mt-6 w-full" data-test="pay-button">
                                <flux:icon.device-phone-mobile class="size-5" /> Payer par Mobile Money · {{ $formation->formatted_price }}
                            </button>

                            <div x-show="comingSoon" x-cloak x-transition class="mt-4 space-y-3">
                                <x-site.alert type="info">
                                    <strong class="font-semibold">Le paiement en ligne arrive très bientôt.</strong>
                                    En attendant, écrivez-nous sur WhatsApp : nous vous indiquons comment régler et nous ouvrons votre accès aussitôt.
                                </x-site.alert>
                                <a href="{{ WhatsApp::url($whatsappMessage) }}" target="_blank" rel="noopener" class="btn-ghost w-full">
                                    <x-site.whatsapp-icon class="size-5 text-[#25D366]" /> Finaliser sur WhatsApp
                                </a>
                            </div>

                            <p class="mt-4 flex items-center justify-center gap-1.5 text-xs text-slate-500">
                                <flux:icon.lock-closed class="size-3.5" /> Paiement sécurisé par FedaPay
                            </p>
                        </div>
                    @else
                        {{-- Création de compte ou connexion sans quitter la page --}}
                        <div class="card overflow-hidden" x-data="{ tab: @js($activeTab) }">
                            <div class="p-6 pb-0">
                                <h2 class="text-lg font-semibold text-white">Votre compte</h2>
                                <p class="mt-1 text-sm text-slate-400">Il vous permet de retrouver votre formation à tout moment. Vous revenez ici juste après.</p>
                            </div>
                            <div class="mx-6 mt-5 grid grid-cols-2 gap-1 rounded-full bg-white/5 p-1 ring-1 ring-white/10" role="tablist">
                                <button type="button" role="tab" @click="tab = 'register'" :aria-selected="(tab === 'register').toString()"
                                    :class="tab === 'register' ? 'bg-electric-600 text-white shadow' : 'text-slate-300 hover:text-white'"
                                    class="rounded-full px-3 py-2.5 text-sm font-semibold transition">Créer un compte</button>
                                <button type="button" role="tab" @click="tab = 'login'" :aria-selected="(tab === 'login').toString()"
                                    :class="tab === 'login' ? 'bg-electric-600 text-white shadow' : 'text-slate-300 hover:text-white'"
                                    class="rounded-full px-3 py-2.5 text-sm font-semibold transition">J'ai déjà un compte</button>
                            </div>
                            <div class="p-6">
                                <div x-show="tab === 'register'" @if ($activeTab !== 'register') x-cloak @endif>
                                    @include('site.auth.partials.register-form', ['prefix' => 'commande-inscription', 'showErrors' => $activeTab === 'register'])
                                </div>
                                <div x-show="tab === 'login'" @if ($activeTab !== 'login') x-cloak @endif>
                                    @include('site.auth.partials.login-form', ['prefix' => 'commande-connexion', 'showErrors' => $activeTab === 'login'])
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Résumé de la commande --}}
                <aside class="order-1 lg:sticky lg:top-24 lg:order-2">
                    <div class="card overflow-hidden">
                        <x-site.formation-cover :formation="$formation" class="aspect-[16/7]" eager />
                        <div class="space-y-5 p-5">
                            <div>
                                <p class="text-xs font-semibold text-electric-300">{{ $formation->category->label() }}</p>
                                <h2 class="mt-1 text-lg leading-snug font-semibold text-white">{{ $formation->title }}</h2>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach ($formation->formats as $format)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-white/5 px-2.5 py-1 text-xs text-slate-300 ring-1 ring-white/10">
                                            <flux:icon :icon="$format->icon()" class="size-3.5" /> {{ $format->shortLabel() }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>

                            <div>
                                <p class="text-sm font-semibold text-white">Ce qui est inclus</p>
                                <ul class="mt-3 space-y-2.5 text-sm text-slate-300">
                                    <li class="flex gap-2"><flux:icon.bolt class="size-4 shrink-0 text-gold-400" /> Accès immédiat après le paiement</li>
                                    @if ($moduleCount > 0)
                                        <li class="flex gap-2"><flux:icon.play-circle class="size-4 shrink-0 text-gold-400" /> Programme complet en {{ $moduleCount }} {{ $moduleCount > 1 ? 'modules' : 'module' }}</li>
                                    @endif
                                    <li class="flex gap-2"><flux:icon.chat-bubble-left-right class="size-4 shrink-0 text-gold-400" /> Groupe privé d'accompagnement</li>
                                    @foreach ($formation->bonuses as $bonus)
                                        <li class="flex gap-2"><flux:icon.gift class="size-4 shrink-0 text-gold-400" /> {{ $bonus }}</li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="flex items-center justify-between border-t border-white/10 pt-4">
                                <span class="text-slate-300">Total à payer</span>
                                <span class="text-2xl font-bold text-gold-300">{{ $formation->formatted_price }}</span>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</x-layouts::site>
