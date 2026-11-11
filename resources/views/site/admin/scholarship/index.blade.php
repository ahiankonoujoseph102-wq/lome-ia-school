@php
    use App\Http\Controllers\Admin\ScholarshipController;
    use App\Models\ScholarshipApplication;
    use App\Scholarships\ScholarshipCampaign;
    use App\Support\Money;

    $seats = $counter['seats'];
    $genderPlural = ['female' => 'Femmes', 'male' => 'Hommes'];
    $filterUrl = fn (array $change): string => route('admin.scholarship.index', array_filter([...[
        'statut' => $filters['status'] === 'a-verifier' ? null : $filters['status'],
        'genre' => $filters['gender'],
        'age' => $filters['age'],
        'niveau' => $filters['level'],
    ], ...$change]));
    $stateLabels = [
        ScholarshipCampaign::OFF => 'Éteinte : page invisible pour les visiteurs',
        ScholarshipCampaign::SOON => 'Pas encore ouverte',
        ScholarshipCampaign::OPEN => 'Ouverte : les candidatures arrivent',
        ScholarshipCampaign::FULL => 'Complète : toutes les places sont réservées',
        ScholarshipCampaign::CLOSED => 'Close : l\'heure de clôture est passée',
    ];
    $chip = fn (bool $active): string => $active ? 'rounded-full px-3 py-1.5 ring-1 bg-gold-400/10 text-gold-200 ring-gold-400' : 'rounded-full px-3 py-1.5 ring-1 text-slate-300 ring-veil/15 hover:bg-veil/10';
    $whatsappShown = session('scholarship_whatsapp');
@endphp
<x-admin.page title="Bourse Agents IA" subtitle="Candidatures envoyées sans compte, avec la capture du paiement des frais d'inscription." width="max-w-4xl">
    <x-slot:actions>
        <a href="{{ route('scholarship') }}" target="_blank" class="btn-ghost">Voir la page <flux:icon.arrow-up-right class="size-4" /></a>
    </x-slot:actions>

    @error('decision')
        <x-site.alert type="error">{{ $message }}</x-site.alert>
    @enderror

    {{-- Compteur des places --}}
    <section class="card space-y-4 p-5 sm:p-6" data-test="scholarship-admin-counter">
        <p class="text-sm font-semibold text-slate-200">{{ $stateLabels[$counter['state']] }}</p>
        <dl class="grid grid-cols-2 gap-3">
            @foreach (ScholarshipApplication::GENDERS as $gender => $label)
                <div class="rounded-2xl bg-night-800 p-4 ring-1 ring-night-600">
                    <dt class="text-sm text-slate-300">{{ $genderPlural[$gender] }}</dt>
                    <dd class="mt-1 text-2xl font-bold text-white tabular-nums" data-test="scholarship-validated-{{ $gender }}">Validées {{ (int) ($validatedByGender[$gender] ?? 0) }} / {{ $seats }}</dd>
                    <dd class="text-sm text-slate-400">Places occupées (validées + à vérifier) : {{ $taken[$gender] }} / {{ $seats }}</dd>
                </div>
            @endforeach
        </dl>
        <p class="text-sm text-slate-400">Premiers payés, premiers servis : chaque capture envoyée réserve une place. Vérifiez le paiement dans votre historique Mobile Money (le SMS de réception), jamais sur la seule capture : une capture peut être fausse. « Refuser » libère la place pour une autre personne.</p>
    </section>

    {{-- Filtres --}}
    <section class="space-y-3" data-test="scholarship-filters">
        <nav class="flex flex-wrap gap-2 text-sm" aria-label="Statut">
            @foreach (ScholarshipController::STATUS_FILTERS as $key => $label)
                <a href="{{ $filterUrl(['statut' => $key === 'a-verifier' ? null : $key]) }}" class="{{ $chip($filters['status'] === $key) }}">{{ $label }} ({{ $counts[$key] }})</a>
            @endforeach
        </nav>
        <nav class="flex flex-wrap gap-2 text-sm" aria-label="Genre, âge et niveau">
            <a href="{{ $filterUrl(['genre' => null]) }}" class="{{ $chip($filters['gender'] === null) }}">Tous les genres</a>
            @foreach (ScholarshipApplication::GENDERS as $key => $label)
                <a href="{{ $filterUrl(['genre' => $key]) }}" class="{{ $chip($filters['gender'] === $key) }}">{{ $label }}</a>
            @endforeach
            <a href="{{ $filterUrl(['age' => null]) }}" class="{{ $chip($filters['age'] === null) }}">Tous les âges</a>
            @foreach (array_keys(ScholarshipController::AGE_BANDS) as $band)
                <a href="{{ $filterUrl(['age' => $band]) }}" class="{{ $chip($filters['age'] === $band) }}">{{ $band }} ans</a>
            @endforeach
        </nav>
        <nav class="flex flex-wrap gap-2 text-sm" aria-label="Niveau">
            <a href="{{ $filterUrl(['niveau' => null]) }}" class="{{ $chip($filters['level'] === null) }}">Tous les niveaux</a>
            @foreach (ScholarshipApplication::LEVELS as $key => $label)
                <a href="{{ $filterUrl(['niveau' => $key]) }}" class="{{ $chip($filters['level'] === $key) }}">{{ $label }}</a>
            @endforeach
        </nav>
    </section>

    {{-- Candidatures --}}
    <ol class="space-y-4">
        @forelse ($applications as $application)
            @php
                $message = 'Bonjour '.$application->firstName().', félicitations : tu es retenu(e) pour la bourse « '.(ScholarshipCampaign::formation()->title ?? 'Créer des agents IA').' » de Lomé IA School. Tu as reçu un e-mail à '.$application->email.' pour choisir ton mot de passe et accéder à la formation et au groupe (regarde aussi dans les courriers indésirables).';
            @endphp
            <li class="card space-y-4 p-4 sm:p-5" data-test="scholarship-row">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                    <span class="text-base font-semibold text-white">n° {{ $application->id }} · {{ $application->name }}</span>
                    <span class="text-sm text-slate-300">{{ $application->genderLabel() }}, {{ $application->age }} ans</span>
                    <span @class([
                        'rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1',
                        'text-amber-100 ring-amber-400/60' => $application->isReserved(),
                        'text-emerald-200 ring-emerald-400/50' => $application->isValidated(),
                        'text-rose-200 ring-rose-400/50' => $application->isRefused(),
                    ])>{{ $application->statusLabel() }}</span>
                    <span class="text-sm text-slate-400">{{ $application->created_at?->translatedFormat('j M à H\hi') }}</span>
                </div>

                <dl class="grid gap-x-4 gap-y-1.5 text-sm sm:grid-cols-[auto_1fr]">
                    <dt class="text-slate-400">WhatsApp</dt><dd class="font-semibold text-slate-100">{{ $application->phoneLabel() }}</dd>
                    <dt class="text-slate-400">E-mail</dt><dd class="font-semibold break-words text-slate-100">{{ $application->email }}</dd>
                    <dt class="text-slate-400">Ville et pays</dt><dd class="text-slate-100">{{ $application->city }}</dd>
                    <dt class="text-slate-400">Niveau</dt><dd class="text-slate-100">{{ $application->levelLabel() }}</dd>
                    <dt class="text-slate-400">Matériel</dt><dd class="text-slate-100">{{ $application->deviceLabel() }}</dd>
                    <dt class="text-slate-400">Un mois avec les directs</dt><dd @class(['font-semibold', 'text-slate-100' => $application->available, 'text-rose-200' => ! $application->available])>{{ $application->available ? 'Oui' : 'Non' }}</dd>
                    <dt class="text-slate-400">Paiement</dt><dd class="text-slate-100">{{ Money::fcfa($application->amount) }} par {{ $application->payment_method_name }} ({{ $application->payment_number }})</dd>
                </dl>

                @foreach (['Outils déjà utilisés' => $application->tools, 'Son grand « pourquoi »' => $application->motivation, 'Premier agent IA, et pour qui' => $application->first_agent] as $label => $text)
                    <div>
                        <p class="text-sm text-slate-400">{{ $label }}</p>
                        <p class="mt-1 rounded-2xl bg-night-800 px-4 py-3 text-[15px] leading-relaxed whitespace-pre-line text-slate-100 ring-1 ring-night-600">{{ $text }}</p>
                    </div>
                @endforeach

                <a href="{{ route('admin.scholarship.proof', $application) }}" target="_blank" class="inline-flex items-center gap-2 text-sm font-semibold text-gold-200 underline underline-offset-2" data-test="scholarship-proof-link">
                    <flux:icon.photo class="size-4" /> Voir la capture du paiement
                </a>

                <div class="flex flex-wrap items-center gap-2 pt-1">
                    @if ($application->isReserved())
                        <form method="POST" action="{{ route('admin.scholarship.validate', $application) }}" onsubmit="return confirm('Valider la candidature de {{ e(addslashes($application->name)) }} ? Le compte est créé, la formation et le groupe sont ouverts, un e-mail part.')">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-emerald-500/15 px-4 py-2 text-sm font-semibold text-emerald-100 ring-1 ring-emerald-400/60 hover:bg-emerald-500/25" data-test="scholarship-validate"><flux:icon.check class="size-4" /> Valider (paiement reçu)</button>
                        </form>
                        <form method="POST" action="{{ route('admin.scholarship.refuse', $application) }}" onsubmit="return confirm('Refuser : paiement introuvable sur votre compte ? La place est libérée et un e-mail part.')">
                            @csrf
                            <input type="hidden" name="refund" value="0">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-rose-100 ring-1 ring-rose-400/60 hover:bg-rose-500/15" data-test="scholarship-refuse-unpaid"><flux:icon.x-mark class="size-4" /> Refuser : paiement non reçu</button>
                        </form>
                        <form method="POST" action="{{ route('admin.scholarship.refuse', $application) }}" onsubmit="return confirm('Refuser cette candidature et rembourser les frais ? La place est libérée et un e-mail part.')">
                            @csrf
                            <input type="hidden" name="refund" value="1">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-slate-100 ring-1 ring-slate-500 hover:bg-veil/10" data-test="scholarship-refuse-refund"><flux:icon.arrow-uturn-left class="size-4" /> Refuser et rembourser</button>
                        </form>
                    @elseif ($application->isValidated())
                        <a href="{{ $application->whatsappUrl($message) }}" target="_blank" rel="noopener" @class(['inline-flex items-center gap-2 rounded-full bg-emerald-500/15 px-4 py-2 text-sm font-semibold text-emerald-100 ring-1 ring-emerald-400/60 hover:bg-emerald-500/25', 'ring-2' => $whatsappShown === $application->id]) data-test="scholarship-whatsapp">
                            <x-site.whatsapp-icon class="size-4" /> Envoyer le message « Tu es retenu(e) »
                        </a>
                        <span class="text-sm text-slate-400">Validée {{ $application->decided_at?->translatedFormat('j M à H\hi') }}@if ($application->decider) par {{ $application->decider->name }}@endif</span>
                    @else
                        <a href="{{ $application->whatsappUrl() }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-slate-100 ring-1 ring-slate-500 hover:bg-veil/10">
                            <x-site.whatsapp-icon class="size-4" /> Écrire sur WhatsApp
                        </a>
                        @if ($application->refund_due && ! $application->refunded_at)
                            <form method="POST" action="{{ route('admin.scholarship.refunded', $application) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-gold-100 ring-1 ring-gold-400/60 hover:bg-gold-400/10" data-test="scholarship-refunded"><flux:icon.banknotes class="size-4" /> Marquer comme remboursé</button>
                            </form>
                        @endif
                    @endif
                </div>
            </li>
        @empty
            <li class="card p-5 text-sm text-slate-400">Aucune candidature pour ce filtre.</li>
        @endforelse
    </ol>

    {{ $applications->links() }}

    {{-- Réglages --}}
    <section class="card space-y-4 p-5 sm:p-6" id="reglages" data-test="scholarship-settings">
        <h2 class="font-semibold text-white">Réglages de la bourse</h2>
        <form method="POST" action="{{ route('admin.scholarship.settings') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <label class="flex cursor-pointer items-start justify-between gap-4">
                <span class="min-w-0">
                    <span class="block font-semibold text-white">Bourse allumée</span>
                    <span class="block text-sm text-slate-400">Éteinte : la page est introuvable pour les visiteurs (vous seul pouvez la relire).</span>
                </span>
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings['enabled'] ?? false)) class="peer sr-only">
                <span class="relative mt-0.5 h-7 w-12 shrink-0 rounded-full bg-veil/10 ring-1 ring-veil/20 transition peer-checked:bg-emerald-500/30 peer-checked:ring-emerald-400 peer-focus-visible:outline-2 peer-focus-visible:outline-gold-300 after:absolute after:top-1 after:left-1 after:size-5 after:rounded-full after:bg-slate-400 after:transition peer-checked:after:translate-x-5 peer-checked:after:bg-emerald-300" aria-hidden="true"></span>
            </label>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.field name="opens_at" label="Ouverture (heure de Lomé)" type="datetime-local" :value="filled($settings['opens_at'] ?? null) ? str_replace(' ', 'T', $settings['opens_at']) : null" />
                <x-admin.field name="closes_at" label="Clôture (heure de Lomé)" type="datetime-local" :value="filled($settings['closes_at'] ?? null) ? str_replace(' ', 'T', $settings['closes_at']) : null" hint="24 heures après l'ouverture, par exemple." />
            </div>
            <x-admin.field name="formation" label="Formation offerte" type="select" :options="$formations->pluck('title', 'slug')->all()" :value="$settings['formation'] ?? null" hint="La personne validée y a accès et rejoint son groupe." />
            <div class="grid gap-4 sm:grid-cols-3">
                <x-admin.field name="fee" label="Frais d'inscription" type="number" unit="FCFA" :value="$settings['fee'] ?? 5500" />
                <x-admin.field name="value" label="Valeur affichée de la formation" type="number" unit="FCFA" :value="$settings['value'] ?? 0" hint="0 = non affichée." />
                <x-admin.field name="seats_per_gender" label="Places par genre" type="number" :value="$settings['seats_per_gender'] ?? 5" />
            </div>
            <button type="submit" class="btn-gold w-full sm:w-auto" data-test="scholarship-settings-save">Enregistrer</button>
        </form>
    </section>
</x-admin.page>
