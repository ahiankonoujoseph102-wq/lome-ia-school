@php
    use App\Support\Placeholder;
    use App\Support\WhatsApp;

    $consultation = config('site.consultation');
    $whatsappUrl = WhatsApp::url(\App\Support\WhatsAppMessages::consultation());
@endphp

<x-layouts::site
    title="Consultation gratuite"
    description="Réservez une consultation gratuite avec Lomé IA School pour faire le point sur vos objectifs et choisir la formation en intelligence artificielle adaptée."
    :whatsapp-message="\App\Support\WhatsAppMessages::consultation()"
>
    <section class="relative isolate overflow-hidden">
        <div class="glow pointer-events-none absolute inset-0 -z-10"></div>
        <div class="grid-pattern pointer-events-none absolute inset-0 -z-10"></div>
        <div class="mx-auto max-w-3xl px-4 pt-10 pb-14 text-center sm:px-6 sm:pt-16">
            <span class="eyebrow" data-reveal>Consultation gratuite, sans compte</span>
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-balance text-white sm:text-5xl" data-reveal style="--reveal-delay: 60ms">
                Réservez votre consultation gratuite
            </h1>
            <p class="mx-auto mt-4 max-w-2xl text-base text-pretty text-slate-300 sm:text-lg" data-reveal style="--reveal-delay: 120ms">
                Un échange personnalisé avec {{ config('site.trainer.name') }} pour faire le point sur votre projet et trouver la formation qui vous fera vraiment progresser.
            </p>
            <div class="mt-8 flex flex-col items-center gap-3" data-reveal style="--reveal-delay: 180ms">
                <a href="#reserver" class="btn-gold w-full sm:w-auto">
                    <flux:icon.calendar-days class="size-5" /> Choisir mon créneau
                </a>
                <p class="text-sm text-slate-400">Durée : {{ Placeholder::highlight($consultation['duration']) }} · {{ $consultation['mode'] }}</p>
            </div>
        </div>
    </section>

    {{-- Réservation : jour, créneau, coordonnées (sans compte) --}}
    <section id="reserver" class="mx-auto max-w-2xl scroll-mt-24 px-4 pb-16 sm:px-6">
        @if (session('status'))
            <x-site.alert class="mb-5">{{ session('status') }}</x-site.alert>
        @endif

        <div class="card p-5 sm:p-6">
            <h2 class="text-xl font-bold text-white sm:text-2xl">Réservez votre créneau</h2>
            <p class="mt-1 text-sm text-slate-400">Consultation de {{ $consultation['duration'] }}, {{ mb_lcfirst($consultation['mode']) }}. Aucun compte n'est nécessaire.</p>

            @if ($days === [])
                <div class="mt-5 rounded-2xl bg-veil/5 p-5 text-sm text-slate-300 ring-1 ring-veil/10">
                    Aucun créneau n'est disponible pour le moment. Écrivez-nous sur WhatsApp : nous trouverons un moment ensemble.
                    <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="btn-ghost mt-4 w-full">Nous écrire sur WhatsApp</a>
                </div>
            @else
                <form method="POST" action="{{ route('consultation.store') }}" class="mt-5 space-y-6" x-data="slotPicker(@js(['days' => $days, 'selected' => old('starts_at')]))">
                    @csrf
                    @include('site.partials.slot-picker', ['selected' => old('starts_at')])

                    <div class="space-y-4 border-t border-veil/10 pt-6" x-ref="next">
                        <p class="font-semibold text-white">Vos coordonnées</p>

                        {{-- Profil : obligatoire ; « Une entreprise » présélectionné depuis la page Entreprises (modifiable). --}}
                        <div class="space-y-4" x-data="{ requester: @js((string) old('requester_type', $requesterType?->value ?? '')) }">
                            <fieldset class="space-y-2" data-test="requester-type">
                                <legend class="field-label">Vous êtes</legend>
                                <div class="grid gap-2 sm:grid-cols-3">
                                    @foreach (\App\Enums\ConsultationRequesterType::cases() as $type)
                                        <label class="group flex cursor-pointer items-center gap-3 rounded-2xl bg-veil/5 px-4 py-3 text-sm font-semibold text-slate-200 ring-1 ring-veil/15 transition hover:bg-veil/10 has-[:checked]:bg-gold-400/10 has-[:checked]:text-white has-[:checked]:ring-2 has-[:checked]:ring-gold-400 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-gold-300">
                                            <input type="radio" name="requester_type" value="{{ $type->value }}" x-model="requester" class="sr-only" required @checked(old('requester_type', $requesterType?->value) === $type->value)>
                                            <span class="grid size-5 shrink-0 place-items-center rounded-full ring-1 ring-veil/30 group-has-[:checked]:ring-gold-400">
                                                <flux:icon.check class="size-3.5 text-gold-300 opacity-0 group-has-[:checked]:opacity-100" />
                                            </span>
                                            {{ $type->choiceLabel() }}
                                        </label>
                                    @endforeach
                                </div>
                                @error('requester_type') <p class="text-sm text-rose-300">{{ $message }}</p> @enderror
                            </fieldset>

                            <div class="space-y-1.5" x-show="requester !== '' && requester !== 'individual'" x-cloak data-test="organization-field">
                                <label for="champ-organization" class="field-label">Nom de votre centre ou de votre entreprise</label>
                                <input id="champ-organization" type="text" name="organization_name" maxlength="150" autocomplete="organization" value="{{ old('organization_name') }}" :required="requester !== '' && requester !== 'individual'" :disabled="requester === '' || requester === 'individual'" @class(['field-input', 'field-input-error' => $errors->has('organization_name')])>
                                @error('organization_name') <p class="text-sm text-rose-300">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <x-site.field name="name" label="Nom et prénom" autocomplete="name" :value="old('name', $user?->name)" required />
                        <x-site.phone-field :dial-code="$user?->phone ? \App\Support\PhoneNumber::split($user->phone)[0] : null" :number="$user?->phone ? \App\Support\PhoneNumber::groupDigits(\App\Support\PhoneNumber::split($user->phone)[1]) : null" />
                        <x-site.field name="email" label="Adresse e-mail" type="email" autocomplete="email" :value="old('email', $user?->email)" hint="La confirmation et le lien de l'appel vidéo y sont envoyés." required />

                        <div class="space-y-1.5">
                            <label for="champ-topic" class="field-label">De quoi voulez-vous parler ?</label>
                            <textarea id="champ-topic" name="topic" rows="3" maxlength="600" required @class(['field-input', 'field-input-error' => $errors->has('topic')]) placeholder="Exemple : je veux apprendre à créer des visuels pour mon commerce.">{{ old('topic') }}</textarea>
                            @error('topic') <p class="text-sm text-rose-300">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-1.5">
                            <label for="champ-formation" class="field-label">Formation qui vous intéresse (facultatif)</label>
                            <select id="champ-formation" name="formation_id" class="field-input">
                                <option value="">Je ne sais pas encore</option>
                                @foreach ($formations as $formation)
                                    <option value="{{ $formation->id }}" @selected((string) old('formation_id') === (string) $formation->id)>{{ $formation->title }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Champ piège : invisible pour les personnes, rempli par les robots. --}}
                        <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                            <label for="champ-website">Ne pas remplir ce champ</label>
                            <input id="champ-website" type="text" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <button type="submit" class="btn-gold w-full" :disabled="!slot">Confirmer ma consultation gratuite</button>
                        <p class="text-center text-xs text-slate-500">Vous pourrez déplacer ou annuler votre rendez-vous jusqu'à {{ \App\Models\ConsultationSettings::current()->manage_deadline_hours }} heures avant. Vos informations servent uniquement à organiser la consultation (voir la <a href="{{ route('legal.privacy') }}" class="underline underline-offset-2 hover:text-white">politique de confidentialité</a>).</p>
                    </div>
                </form>
            @endif
        </div>
    </section>

    <section class="section !pt-0">
        <div class="grid gap-10 lg:grid-cols-2">
            <div data-reveal>
                <h2 class="text-2xl font-bold text-white sm:text-3xl">Ce que vous allez obtenir</h2>
                <ul class="mt-6 space-y-3">
                    @foreach ($consultation['benefits'] as $benefit)
                        <li class="card flex items-start gap-3 p-4">
                            <span class="grid size-7 shrink-0 place-items-center rounded-full bg-gold-400/15 text-gold-300"><flux:icon.check class="size-4" /></span>
                            <span class="text-slate-200">{{ $benefit }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div data-reveal style="--reveal-delay: 100ms">
                <h2 class="text-2xl font-bold text-white sm:text-3xl">Comment ça se passe ?</h2>
                <ol class="mt-6 space-y-4">
                    @foreach ($consultation['steps'] as $step)
                        <li class="flex gap-4">
                            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-night-800 font-bold text-gold-300 ring-2 ring-gold-400/50">{{ $loop->iteration }}</span>
                            <span>
                                <span class="block font-semibold text-white">{{ $step['title'] }}</span>
                                <span class="block text-sm text-slate-400">{{ $step['text'] }}</span>
                            </span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>

    <x-site.cta-banner
        title="Faites le premier pas aujourd'hui"
        text="La consultation est gratuite, et la réservation se fait sans créer de compte. Réservez votre créneau en quelques secondes."
    >
        <a href="#reserver" class="btn-gold">Choisir mon créneau</a>
        <a href="{{ route('formations.index') }}" class="btn-ghost">Voir les formations</a>
    </x-site.cta-banner>
</x-layouts::site>
