<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ActivityLog;
use App\Assistant\AmaKnowledge;
use App\Assistant\AmaPrompt;
use App\Assistant\NaturalVoice;
use App\Branding\BrandAssets;
use App\Enums\AdminAction;
use App\Http\Controllers\Controller;
use App\Jobs\PrepareAmaVoicePhrases;
use App\Models\AssistantUsage;
use App\Services\ImageUploader;
use App\Settings\SiteSettings;
use App\Support\Founder;
use App\Support\HomeVideo;
use App\Support\PastedLink;
use App\Support\PhoneNumber;
use App\Support\SearchConsole;
use App\Support\VoiceAssistant;
use App\Support\WhatsAppMessages;
use App\Support\YouTube;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * « Réglages du site » (admin uniquement) : tout ce qui était dans les fichiers de configuration se
 * modifie ici et s'applique immédiatement. Les moyens de paiement et les réglages d'affiliation sont
 * sensibles : mot de passe redemandé et e-mail d'alerte à chaque modification.
 */
class SiteSettingsController extends Controller
{
    /**
     * @var array<string, array{title: string, text: string, icon: string, sensitive?: bool}>
     */
    public const array SECTIONS = [
        'coordonnees' => ['title' => 'Coordonnées et chiffres clés', 'text' => 'Téléphones, e-mail, adresse, horaires, WhatsApp, réseaux sociaux, personnes formées, année de création.', 'icon' => 'phone'],
        'image' => ['title' => 'Logo, icône et image de partage', 'text' => 'Logo du site, icône (onglet du navigateur, écran d\'accueil du téléphone) et image affichée lors d\'un partage sur WhatsApp ou Facebook.', 'icon' => 'photo'],
        'paiements' => ['title' => 'Moyens de paiement', 'text' => 'Numéros Mobile Money, bénéficiaires, pays, mention du représentant, adresse des alertes.', 'icon' => 'banknotes', 'sensitive' => true],
        'regles' => ['title' => 'Seuils et délais', 'text' => 'Seuil d\'accès provisoire, délai de vérification, réglages des sessions.', 'icon' => 'adjustments-horizontal'],
        'affiliation' => ['title' => 'Affiliation', 'text' => 'Taux de commission, durée des liens, délai de disponibilité, retrait minimum.', 'icon' => 'share', 'sensitive' => true],
        'textes' => ['title' => 'Textes du site', 'text' => 'Accroche de l\'accueil, présentation du formateur, questions fréquentes.', 'icon' => 'document-text'],
        'video-accueil' => ['title' => 'Vidéo d\'accueil', 'text' => 'Vidéo YouTube verticale de l\'accueil : lien, image d\'aperçu, titre, phrase, bouton TikTok et interrupteur.', 'icon' => 'play-circle'],
        'fond-anime' => ['title' => 'Fond animé', 'text' => 'Halo lumineux, réseau de points et icônes qui bougent doucement derrière le haut de l\'accueil et du catalogue : interrupteur.', 'icon' => 'sparkles'],
        'messages-whatsapp' => ['title' => 'Messages WhatsApp', 'text' => 'Message prérempli de chaque bouton WhatsApp selon la page : formation, paiement, formation gratuite, consultation, autres pages.', 'icon' => 'chat-bubble-left-right'],
        'page-joseph' => ['title' => 'Page Joseph Ahiankonou', 'text' => 'Votre page personnelle (/joseph-ahiankonou) : textes, photo, parcours, services, liens YouTube, TikTok, Facebook et chaînes WhatsApp, contact.', 'icon' => 'user-circle'],
        'google' => ['title' => 'Référencement Google', 'text' => 'Code de vérification Google Search Console et adresse du plan du site à déclarer.', 'icon' => 'magnifying-glass'],
        'entreprises' => ['title' => 'Page Entreprises', 'text' => 'Vidéo YouTube, présentation, services et bloc de l\'accueil.', 'icon' => 'building-office-2'],
        'assistante' => ['title' => 'Assistante Ama', 'text' => 'Bouton « Parler avec notre assistante » : activation, Ama (Gemini) ou ElevenLabs, instructions, limites, lecteur de cours et aide proactive.', 'icon' => 'chat-bubble-oval-left-ellipsis'],
        'emails' => ['title' => 'E-mails envoyés', 'text' => 'Quota quotidien du fournisseur d\'e-mails (Brevo), réserve gardée pour les codes et les paiements, e-mails partis aujourd\'hui.', 'icon' => 'envelope'],
        'legal' => ['title' => 'Pages légales', 'text' => 'Hébergeur du site et lieu de stockage des données (mentions légales, confidentialité).', 'icon' => 'scale'],
    ];

    public function index(): View
    {
        return view('site.admin.settings.index', ['sections' => self::SECTIONS]);
    }

    public function edit(string $section): View
    {
        abort_unless(isset(self::SECTIONS[$section]), 404);

        return view('site.admin.settings.'.$section, [
            'section' => self::SECTIONS[$section],
            'key' => $section,
        ]);
    }

    public function update(Request $request, string $section, ImageUploader $images): RedirectResponse
    {
        abort_unless(isset(self::SECTIONS[$section]), 404);

        if (self::SECTIONS[$section]['sensitive'] ?? false) {
            $request->validate(['current_password' => ['required', 'current_password']], [
                'current_password.required' => 'Confirmez avec votre mot de passe.',
                'current_password.current_password' => 'Mot de passe incorrect.',
            ]);
        }

        match ($section) {
            'coordonnees' => $this->updateContact($request),
            'image' => $this->updateImages($request, $images),
            'paiements' => $this->updatePayments($request),
            'regles' => $this->updateRules($request),
            'affiliation' => $this->updateAffiliate($request),
            'textes' => $this->updateTexts($request),
            'video-accueil' => $this->updateHomeVideo($request, $images),
            'fond-anime' => $this->updateAnimatedBackground($request),
            'messages-whatsapp' => $this->updateWhatsAppMessages($request),
            'page-joseph' => $this->updateFounder($request, $images),
            'google' => $this->updateSearchConsole($request),
            'entreprises' => $this->updateBusiness($request),
            'assistante' => $this->updateVoiceAssistant($request),
            'emails' => $this->updateMailQuota($request),
            'legal' => $this->updateLegal($request),
        };

        return to_route('admin.settings.edit', $section)->with('status', 'Réglages enregistrés : ils sont déjà visibles sur le site.');
    }

    /**
     * Bouton « Écouter » de la voix naturelle d'Ama : phrase d'exemple lue par la voix et le modèle choisis dans le
     * formulaire (même avant d'enregistrer). Gardée en mémoire : une seule demande à Google par voix et par modèle.
     */
    public function previewVoice(Request $request, NaturalVoice $voice): SymfonyResponse
    {
        $validated = $request->validate([
            'voice' => ['required', Rule::in(array_keys(NaturalVoice::VOICES))],
            'model' => ['required', Rule::in(array_keys(NaturalVoice::MODELS))],
        ]);

        if (! $voice->isConfigured()) {
            return response()->json(['message' => 'Clé Gemini absente sur le serveur : ajoutez la variable GEMINI_API_KEY.'], 422);
        }

        $audio = $voice->preview((string) $validated['voice'], (string) $validated['model']);

        if ($audio === null) {
            $message = match ($voice->lastReason) {
                'daily_quota' => 'Google a épuisé le quota de voix du jour. Réessayez après '.AssistantUsage::nextReset()->translatedFormat('H \\h i').' (heure de Lomé).',
                'site_minute_limit', 'google_quota' => 'Limite de demandes par minute atteinte. Réessayez dans une minute.',
                default => 'Google n\'a pas pu fabriquer la voix pour le moment (refus ou délai dépassé). Réessayez plus tard.',
            };

            return response()->json(['message' => $message], 422);
        }

        return response($audio->bytes, 200, ['Content-Type' => $audio->mime, 'Cache-Control' => 'no-store, private']);
    }

    private function updateContact(Request $request): void
    {
        PastedLink::cleanRequest($request, ['map_url', 'social.*']);

        $validated = $request->validate([
            'phones' => ['array', 'max:5'],
            'phones.*' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'map_url' => ['nullable', 'url:https', 'max:500'],
            'hours' => ['required', 'string', 'max:120'],
            'whatsapp_number' => ['required', 'string', 'max:30'],
            'social' => ['array'],
            'social.facebook' => ['nullable', 'url:https', 'max:255'],
            'social.tiktok' => ['nullable', 'url:https', 'max:255'],
            'social.youtube' => ['nullable', 'url:https', 'max:255'],
            'trained' => ['required', 'string', 'max:20'],
            'trainer_since' => ['required', 'integer', 'between:2000,2100'],
        ], [
            'map_url.url' => 'Le lien de la carte doit ressembler à https://maps.app.goo.gl/… (dans Google Maps : « Partager », puis « Copier le lien »).',
            'social.facebook.url' => 'Le lien Facebook doit ressembler à https://www.facebook.com/votre-page.',
            'social.tiktok.url' => 'Le lien TikTok doit ressembler à https://www.tiktok.com/@votre-compte.',
            'social.youtube.url' => 'Le lien YouTube doit ressembler à https://www.youtube.com/@votre-chaine.',
        ]);

        $whatsapp = PhoneNumber::normalize((string) $validated['whatsapp_number']);

        if ($whatsapp === null) {
            throw ValidationException::withMessages(['whatsapp_number' => 'Numéro WhatsApp invalide. Exemple : +228 72 24 02 31.']);
        }

        $contact = (array) SiteSettings::get('site.contact');
        $stats = (array) SiteSettings::get('site.stats');
        $stats[0] = ['value' => trim((string) $validated['trained']), 'label' => (string) ($stats[0]['label'] ?? 'personnes formées')];
        // Deuxième chiffre clé : l'année où Joseph a commencé à former (pas la création de l'école, qui est récente).
        $stats[1] = ['value' => (string) $validated['trainer_since'], 'label' => 'année où Joseph a commencé à former'];

        SiteSettings::save([
            'site.contact' => [
                ...$contact,
                'phones' => self::lines($validated['phones'] ?? []),
                'email' => strtolower(trim((string) $validated['email'])),
                'address' => trim((string) $validated['address']),
                'map_url' => $validated['map_url'] ?: 'https://www.google.com/maps/search/?api=1&query='.rawurlencode((string) $validated['address']),
                'hours' => trim((string) $validated['hours']),
            ],
            // Les messages préremplis se règlent dans « Messages WhatsApp » (WhatsAppMessages).
            'site.whatsapp' => [
                ...(array) SiteSettings::get('site.whatsapp'),
                'number' => ltrim($whatsapp, '+'),
            ],
            'site.social' => [
                'facebook' => (string) ($validated['social']['facebook'] ?? ''),
                'tiktok' => (string) ($validated['social']['tiktok'] ?? ''),
                'youtube' => (string) ($validated['social']['youtube'] ?? ''),
            ],
            'site.stats' => array_values($stats),
        ]);

        ActivityLog::record(AdminAction::SettingsUpdated, 'Coordonnées et chiffres clés modifiés.');
    }

    private function updateImages(Request $request, ImageUploader $images): void
    {
        $request->validate([
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'icon' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192', 'dimensions:min_width=512,min_height=512'],
            'og_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'reset' => ['nullable', 'array'],
            'reset.*' => [Rule::in(['logo', 'icons', 'og_image'])],
        ], [
            'logo.image' => 'Le logo doit être une image (PNG, JPEG ou WebP).',
            'icon.image' => 'L\'icône doit être une image (PNG, JPEG ou WebP).',
            'icon.dimensions' => 'L\'icône doit mesurer au moins 512 × 512 pixels (carrée de préférence).',
            'og_image.image' => 'L\'image de partage doit être une image (JPEG, PNG ou WebP).',
        ]);

        $brand = (array) SiteSettings::get('site.brand');
        $defaults = (array) SiteSettings::default('site.brand');
        $labels = ['logo' => 'logo', 'icons' => 'icône', 'og_image' => 'image de partage'];
        $changes = [];

        // Revenir aux fichiers d'origine (logo et icône de Lomé IA School livrés avec le site).
        foreach ((array) $request->input('reset', []) as $key) {
            if (($brand[$key] ?? null) !== ($defaults[$key] ?? null)) {
                $this->deleteUploaded($key, (string) ($brand[$key] ?? ''), $images);
                $brand[$key] = $defaults[$key] ?? null;
                $changes[] = $labels[$key].' d\'origine rétabli';
            }
        }

        if ($request->hasFile('logo')) {
            $this->deleteUploaded('logo', (string) ($brand['logo'] ?? ''), $images);
            // Logo horizontal : hauteur 120 px (affiché à 40 px sur les écrans 3x), transparence conservée.
            $brand['logo'] = $images->fit($request->file('logo'), 'site', 600, 120, keepTransparency: true, field: 'logo');
            $changes[] = 'nouveau logo';
        }

        if ($request->hasFile('icon')) {
            $this->deleteUploaded('icons', (string) ($brand['icons'] ?? ''), $images);
            $directory = 'site/icones-'.Str::lower(Str::random(12));
            $this->storeIcons((string) $request->file('icon')->getRealPath(), $directory);
            $brand['icons'] = $directory;
            $changes[] = 'nouvelle icône (favicon et écran d\'accueil)';
        }

        if ($request->hasFile('og_image')) {
            $this->deleteUploaded('og_image', (string) ($brand['og_image'] ?? ''), $images);
            $brand['og_image'] = $images->cover($request->file('og_image'), 'site', 1200, 630, field: 'og_image');
            $changes[] = 'nouvelle image de partage';
        }

        if ($changes === []) {
            throw ValidationException::withMessages(['logo' => 'Choisissez une image à envoyer.']);
        }

        SiteSettings::save(['site.brand' => $brand]);
        ActivityLog::record(AdminAction::SettingsUpdated, 'Logo, icône et image de partage : '.implode(', ', $changes).'.');
    }

    /**
     * Jeu d'icônes créé dans un dossier temporaire du serveur, puis copié sur le disque « public »
     * (dossier du serveur sur Hostinger, stockage objet sur Laravel Cloud).
     */
    private function storeIcons(string $source, string $directory): void
    {
        $temporary = storage_path('app/tmp/'.Str::lower(Str::random(16)));

        try {
            foreach (app(BrandAssets::class)->icons($source, $temporary) as $file) {
                Storage::disk('public')->put($directory.'/'.basename($file), (string) file_get_contents($file));
            }
        } finally {
            File::deleteDirectory($temporary);
        }
    }

    /**
     * Supprime un fichier envoyé depuis l'admin (jamais les fichiers d'origine livrés dans public/).
     */
    private function deleteUploaded(string $key, string $path, ImageUploader $images): void
    {
        if ($path === '' || ! str_starts_with($path, 'site/')) {
            return;
        }

        $key === 'icons' ? Storage::disk('public')->deleteDirectory($path) : $images->delete($path);
    }

    private function updatePayments(Request $request): void
    {
        $validated = $request->validate([
            'admin_email' => ['required', 'email', 'max:255'],
            'countries' => ['required', 'array', 'min:1'],
            'countries.*.code' => ['required', 'string', 'regex:/^[a-z]{2}$/'],
            'countries.*.name' => ['required', 'string', 'max:60'],
            'countries.*.flag' => ['nullable', 'string', 'max:16'],
            'countries.*.dial_code' => ['required', 'string', 'regex:/^\+\d{1,4}$/'],
            'countries.*.note' => ['nullable', 'string', 'max:200'],
            'methods' => ['required', 'array', 'min:1'],
            'methods.*.key' => ['nullable', 'string', 'max:40'],
            'methods.*.country' => ['required', 'string'],
            'methods.*.name' => ['required', 'string', 'max:60'],
            'methods.*.number' => ['required', 'string', 'max:30'],
            'methods.*.beneficiary' => ['required', 'string', 'max:120'],
            'methods.*.color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'methods.*.ussd' => ['nullable', 'string', 'max:60'],
            'methods.*.enabled' => ['nullable', 'boolean'],
        ], [
            'countries.*.code.regex' => 'Code pays : deux lettres minuscules (ex. tg).',
            'countries.*.dial_code.regex' => 'Indicatif : + suivi de chiffres (ex. +228).',
            'methods.*.color.regex' => 'Couleur au format #RRGGBB.',
            'methods.*.name.required' => 'Indiquez le nom du moyen de paiement.',
            'methods.*.number.required' => 'Indiquez le numéro.',
            'methods.*.beneficiary.required' => 'Indiquez le nom exact du bénéficiaire.',
        ]);

        $countries = [];

        foreach ($validated['countries'] as $country) {
            $code = (string) $country['code'];

            if (isset($countries[$code])) {
                throw ValidationException::withMessages(['countries' => 'Le pays « '.$code.' » apparaît deux fois.']);
            }

            $countries[$code] = [
                'name' => trim((string) $country['name']),
                'flag' => trim((string) ($country['flag'] ?? '')),
                'dial_code' => (string) $country['dial_code'],
                'note' => filled($country['note'] ?? null) ? trim((string) $country['note']) : null,
            ];
        }

        $previous = (array) SiteSettings::get('site.payments.methods');
        $methods = [];

        foreach ($validated['methods'] as $index => $method) {
            if (! isset($countries[$method['country']])) {
                throw ValidationException::withMessages(["methods.{$index}.country" => 'Choisissez un pays de la liste.']);
            }

            // Un moyen existant garde sa clé (elle est enregistrée dans les anciens achats) ; un nouveau en reçoit une.
            $key = filled($method['key'] ?? null) && isset($previous[$method['key']]) ? (string) $method['key'] : self::newMethodKey((string) $method['country'], (string) $method['name'], [...array_keys($previous), ...array_keys($methods)]);

            $ussd = self::cleanUssd((string) ($method['ussd'] ?? ''));

            if ($ussd !== '' && ! self::isValidUssd($ussd)) {
                throw ValidationException::withMessages(["methods.{$index}.ussd" => 'Code USSD : uniquement des chiffres, * et #, commençant par * et finissant par # (ex. *145*1#). Repères autorisés : {numero} et {montant}.']);
            }

            $methods[$key] = [
                'country' => (string) $method['country'],
                'name' => trim((string) $method['name']),
                'number' => trim((string) $method['number']),
                'beneficiary' => trim((string) $method['beneficiary']),
                'color' => strtolower((string) $method['color']),
                'ussd' => $ussd,
                'enabled' => (bool) ($method['enabled'] ?? false),
            ];
        }

        // Un moyen retiré du formulaire reste enregistré, désactivé : les anciens achats l'affichent toujours.
        foreach ($previous as $key => $method) {
            if (! isset($methods[$key]) && is_array($method)) {
                $methods[$key] = [...$method, 'enabled' => false];
            }
        }

        if (! collect($methods)->contains(fn (array $method): bool => $method['enabled'])) {
            throw ValidationException::withMessages(['methods' => 'Gardez au moins un moyen de paiement actif : sinon, plus personne ne peut payer.']);
        }

        $previousEmail = (string) SiteSettings::get('site.payments.admin_email');
        $newEmail = strtolower(trim((string) $validated['admin_email']));
        $changes = self::describeMethodChanges($previous, $methods);

        if ($previousEmail !== $newEmail) {
            $changes[] = 'adresse des alertes : '.$previousEmail.' → '.$newEmail;
        }

        SiteSettings::save([
            'site.payments.countries' => $countries,
            'site.payments.methods' => $methods,
            'site.payments.admin_email' => $newEmail,
        ]);

        ActivityLog::record(
            AdminAction::PaymentMethodsUpdated,
            'Moyens de paiement modifiés'.($changes === [] ? ' (aucun changement de numéro ni de bénéficiaire).' : ' : '.implode(' ; ', $changes).'.'),
            properties: ['previous_admin_email' => $previousEmail !== $newEmail ? $previousEmail : null],
        );
    }

    private function updateRules(Request $request): void
    {
        $validated = $request->validate([
            'verification_threshold' => ['required', 'integer', 'min:0', 'max:100000000'],
            'verification_hours' => ['required', 'string', 'max:120'],
            'fees_notice' => ['required', 'string', 'max:200'],
            'default_location' => ['required', 'string', 'max:200'],
            'default_duration' => ['required', 'integer', 'min:15', 'max:600'],
            'cancel_deadline_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'join_window_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'payment_hold_hours' => ['required', 'integer', 'min:1', 'max:168'],
        ]);

        $previous = (int) config('site.payments.verification_threshold');

        SiteSettings::save([
            'site.payments.verification_threshold' => (int) $validated['verification_threshold'],
            'site.payments.verification_hours' => trim((string) $validated['verification_hours']),
            'site.payments.fees_notice' => trim((string) $validated['fees_notice']),
            'live_sessions.default_location' => trim((string) $validated['default_location']),
            'live_sessions.default_duration' => (int) $validated['default_duration'],
            'live_sessions.cancel_deadline_hours' => (int) $validated['cancel_deadline_hours'],
            'live_sessions.join_window_minutes' => (int) $validated['join_window_minutes'],
            'live_sessions.payment_hold_hours' => (int) $validated['payment_hold_hours'],
        ]);

        ActivityLog::record(AdminAction::SettingsUpdated, 'Seuils et délais modifiés'.($previous !== (int) $validated['verification_threshold'] ? ' (seuil d\'accès provisoire : '.$previous.' → '.$validated['verification_threshold'].' FCFA)' : '').'.');
    }

    private function updateAffiliate(Request $request): void
    {
        $validated = $request->validate([
            'rate' => ['required', 'integer', 'min:0', 'max:90'],
            'cookie_days' => ['required', 'integer', 'min:1', 'max:365'],
            'availability_days' => ['required', 'integer', 'min:0', 'max:90'],
            'min_withdrawal' => ['required', 'integer', 'min:0', 'max:10000000'],
            'alert_signups_without_purchase' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $changes = [];

        foreach ($validated as $key => $value) {
            $old = (int) config('affiliate.'.$key);

            if ($old !== (int) $value) {
                $changes[] = $key.' : '.$old.' → '.$value;
            }
        }

        $values = [];

        foreach ($validated as $key => $value) {
            $values['affiliate.'.$key] = (int) $value;
        }

        SiteSettings::save($values);

        ActivityLog::record(AdminAction::AffiliateSettingsUpdated, 'Réglages d\'affiliation modifiés'.($changes === [] ? ' (aucune valeur changée).' : ' : '.implode(', ', $changes).'.'));
    }

    private function updateTexts(Request $request): void
    {
        $validated = $request->validate([
            'hero.badge' => ['required', 'string', 'max:120'],
            'hero.title' => ['required', 'string', 'max:160'],
            'hero.subtitle' => ['required', 'string', 'max:500'],
            'hero.points' => ['array', 'max:6'],
            'hero.points.*' => ['nullable', 'string', 'max:60'],
            'trainer.name' => ['required', 'string', 'max:120'],
            'trainer.role' => ['required', 'string', 'max:160'],
            'trainer.short_bio' => ['required', 'string', 'max:500'],
            'trainer.bio' => ['array', 'max:8'],
            'trainer.bio.*' => ['nullable', 'string', 'max:1500'],
            'trainer.highlights' => ['array', 'max:8'],
            'trainer.highlights.*' => ['nullable', 'string', 'max:200'],
            'faq_home' => ['array', 'max:30'],
            'faq_home.*.question' => ['nullable', 'string', 'max:200'],
            'faq_home.*.answer' => ['nullable', 'string', 'max:2000'],
            'faq_formation' => ['array', 'max:30'],
            'faq_formation.*.question' => ['nullable', 'string', 'max:200'],
            'faq_formation.*.answer' => ['nullable', 'string', 'max:2000'],
        ]);

        $hero = (array) SiteSettings::get('site.hero');
        $trainer = (array) SiteSettings::get('site.trainer');

        SiteSettings::save([
            'site.hero' => [
                ...$hero,
                'badge' => trim((string) $validated['hero']['badge']),
                'title' => trim((string) $validated['hero']['title']),
                'subtitle' => trim((string) $validated['hero']['subtitle']),
                'points' => self::lines($validated['hero']['points'] ?? []),
            ],
            'site.trainer' => [
                ...$trainer,
                'name' => trim((string) $validated['trainer']['name']),
                'role' => trim((string) $validated['trainer']['role']),
                'short_bio' => trim((string) $validated['trainer']['short_bio']),
                'bio' => self::lines($validated['trainer']['bio'] ?? []),
                'highlights' => self::lines($validated['trainer']['highlights'] ?? []),
            ],
            'site.faq' => [
                'home' => self::questions($validated['faq_home'] ?? []),
                'formation' => self::questions($validated['faq_formation'] ?? []),
            ],
        ]);

        ActivityLog::record(AdminAction::SettingsUpdated, 'Textes du site modifiés (accueil, formateur, questions fréquentes).');
    }

    private function updateHomeVideo(Request $request, ImageUploader $images): void
    {
        PastedLink::cleanRequest($request, ['video_url', 'tiktok_url']);

        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'video_url' => ['nullable', 'string', 'max:500'],
            'title' => ['nullable', 'string', 'max:160'],
            'subtitle' => ['nullable', 'string', 'max:200'],
            'tiktok_url' => ['nullable', 'string', 'max:500'],
            'poster' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'remove_poster' => ['nullable', 'boolean'],
        ], [
            'poster.image' => 'L\'image d\'aperçu doit être une image (JPEG, PNG ou WebP).',
            'poster.mimes' => 'L\'image d\'aperçu doit être une image (JPEG, PNG ou WebP).',
            'poster.max' => 'L\'image d\'aperçu est trop lourde (8 Mo au maximum).',
        ]);

        $video = trim((string) ($validated['video_url'] ?? ''));
        $tiktok = trim((string) ($validated['tiktok_url'] ?? ''));

        if ($video !== '' && YouTube::id($video) === null) {
            throw ValidationException::withMessages(['video_url' => YouTube::invalidMessage().' Un lien Shorts (https://www.youtube.com/shorts/…) fonctionne aussi.']);
        }

        if ($tiktok !== '' && ! HomeVideo::isTikTokUrl($tiktok)) {
            throw ValidationException::withMessages(['tiktok_url' => HomeVideo::tiktokInvalidMessage()]);
        }

        $enabled = $request->boolean('enabled');

        // Image d'aperçu : gardée telle quelle, remplacée par un nouvel envoi, ou retirée (vignette YouTube).
        $poster = (string) (((array) SiteSettings::get('site.home_video'))['poster'] ?? '');
        $posterChange = null;

        if ($request->hasFile('poster')) {
            $new = $images->cover($request->file('poster'), HomeVideo::POSTER_DIRECTORY, HomeVideo::POSTER_WIDTH, HomeVideo::POSTER_HEIGHT, field: 'poster', format: 'webp');
            $this->deleteUploaded('poster', $poster, $images);
            $poster = $new;
            $posterChange = 'nouvelle image d\'aperçu';
        } elseif ($request->boolean('remove_poster') && $poster !== '') {
            $this->deleteUploaded('poster', $poster, $images);
            $poster = '';
            $posterChange = 'image d\'aperçu retirée';
        }

        SiteSettings::save(['site.home_video' => [
            'enabled' => $enabled,
            'video_url' => $video,
            'title' => trim((string) ($validated['title'] ?? '')) ?: HomeVideo::DEFAULT_TITLE,
            'subtitle' => trim((string) ($validated['subtitle'] ?? '')) ?: HomeVideo::DEFAULT_SUBTITLE,
            'tiktok_url' => $tiktok,
            'poster' => $poster,
        ]]);

        ActivityLog::record(AdminAction::SettingsUpdated, 'Vidéo d\'accueil modifiée ('.match (true) {
            $video === '' => 'sans lien : section masquée',
            ! $enabled => 'vidéo masquée par l\'interrupteur',
            default => 'vidéo affichée',
        }.($posterChange ? ', '.$posterChange : '').').');
    }

    private function updateAnimatedBackground(Request $request): void
    {
        $request->validate(['enabled' => ['nullable', 'boolean']]);
        $enabled = $request->boolean('enabled');

        SiteSettings::save(['site.animated_background' => ['enabled' => $enabled]]);

        ActivityLog::record(AdminAction::SettingsUpdated, $enabled ? 'Fond animé activé (accueil et catalogue).' : 'Fond animé désactivé : fond fixe d\'origine.');
    }

    /**
     * Quota d'e-mails : limite du fournisseur (Brevo) et réserve qu'une annonce de groupe par e-mail ne peut
     * jamais entamer. Les autres e-mails (codes, paiements…) ne sont jamais bloqués par ces réglages.
     */
    private function updateMailQuota(Request $request): void
    {
        $validated = $request->validate([
            'daily_limit' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reserve' => ['required', 'integer', 'min:0', 'lt:daily_limit'],
        ], [
            'daily_limit.required' => 'Indiquez le nombre d\'e-mails par jour permis par votre offre Brevo.',
            'daily_limit.integer' => 'Le quota doit être un nombre entier, par exemple 300.',
            'daily_limit.min' => 'Le quota doit être d\'au moins 1 e-mail par jour.',
            'reserve.required' => 'Indiquez la réserve à garder, par exemple 100.',
            'reserve.integer' => 'La réserve doit être un nombre entier, par exemple 100.',
            'reserve.min' => 'La réserve ne peut pas être négative.',
            'reserve.lt' => 'La réserve doit être plus petite que le quota quotidien.',
        ]);

        $limit = (int) $validated['daily_limit'];
        $reserve = (int) $validated['reserve'];

        SiteSettings::save(['site.mail_quota' => ['daily_limit' => $limit, 'reserve' => $reserve]]);

        ActivityLog::record(AdminAction::SettingsUpdated, 'Quota d\'e-mails : '.$limit.' par jour, réserve de '.$reserve.' pour les codes, inscriptions et paiements.');
    }

    /**
     * Messages préremplis des boutons WhatsApp : un champ vidé reprend le texte d'origine ; chaque message se
     * termine par « : » (la personne écrit juste après) et n'utilise que les repères prévus pour lui.
     */
    private function updateWhatsAppMessages(Request $request): void
    {
        $rules = [];

        foreach (array_keys(WhatsAppMessages::TEMPLATES) as $key) {
            $rules['messages.'.$key] = ['nullable', 'string', 'max:400'];
        }

        $validated = $request->validate($rules + ['messages' => ['array']]);
        $messages = [];
        $errors = [];

        foreach (WhatsAppMessages::TEMPLATES as $key => $template) {
            $text = trim((string) preg_replace('/\s+/u', ' ', (string) ($validated['messages'][$key] ?? '')));

            if ($text === '') {
                $messages[$key] = (string) SiteSettings::default('site.whatsapp_messages.'.$key);

                continue;
            }

            if (! str_ends_with($text, ':')) {
                $errors['messages.'.$key] = '« '.$template['label'].' » : le message doit se terminer par « Ma question : » (ou « Mon problème : »), pour que la personne écrive juste après.';
            }

            if (($unknown = WhatsAppMessages::unknownPlaceholders($key, $text)) !== []) {
                $errors['messages.'.$key] = '« '.$template['label'].' » : repère inconnu '.implode(', ', $unknown).'. Repères possibles : '.($template['placeholders'] === [] ? 'aucun' : implode(', ', $template['placeholders'])).'.';
            }

            $messages[$key] = $text;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        SiteSettings::save(['site.whatsapp_messages' => $messages]);

        ActivityLog::record(AdminAction::SettingsUpdated, 'Messages WhatsApp modifiés.');
    }

    private function updateBusiness(Request $request): void
    {
        PastedLink::cleanRequest($request, ['video_url']);

        $validated = $request->validate([
            'video_url' => ['nullable', 'string', 'max:500'],
            'video_format' => ['nullable', Rule::in(YouTube::FORMATS)],
            'title' => ['required', 'string', 'max:160'],
            'intro' => ['array', 'max:6'],
            'intro.*' => ['nullable', 'string', 'max:1500'],
            'services' => ['array', 'max:30'],
            'services.*' => ['nullable', 'string', 'max:200'],
            'home_title' => ['required', 'string', 'max:160'],
            'home_text' => ['required', 'string', 'max:400'],
        ]);

        $video = trim((string) ($validated['video_url'] ?? ''));

        if ($video !== '' && YouTube::id($video) === null) {
            throw ValidationException::withMessages(['video_url' => YouTube::invalidMessage().' Un lien Shorts (https://www.youtube.com/shorts/…) fonctionne aussi.']);
        }

        $services = self::lines($validated['services'] ?? []);

        if ($services === []) {
            throw ValidationException::withMessages(['services' => 'Indiquez au moins un service.']);
        }

        SiteSettings::save(['site.business' => [
            'video_url' => $video,
            // Sans choix, le format reste automatique (vertical pour un lien Shorts).
            'video_format' => (string) ($validated['video_format'] ?? ''),
            'title' => trim((string) $validated['title']),
            'intro' => self::lines($validated['intro'] ?? []),
            'services' => $services,
            'home_title' => trim((string) $validated['home_title']),
            'home_text' => trim((string) $validated['home_text']),
        ]]);

        ActivityLog::record(AdminAction::SettingsUpdated, 'Page « Entreprises et centres de formation » modifiée'.($video === '' ? ' (sans vidéo).' : ' (vidéo au format '.YouTube::format($video, $validated['video_format'] ?? null).').'));
    }

    /**
     * Page personnelle de Joseph Ahiankonou. Liens nettoyés (PastedLink) puis vérifiés : chaque réseau doit
     * pointer vers son propre site ; un lien vide est masqué. Photo recadrée en carré (800 × 800).
     */
    private function updateFounder(Request $request, ImageUploader $images): void
    {
        PastedLink::cleanRequest($request, ['links.*', 'whatsapp_channels.*']);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'nickname' => ['nullable', 'string', 'max:60'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:300'],
            'job_title' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:300'],
            'intro' => ['array', 'max:10'],
            'intro.*' => ['nullable', 'string', 'max:1500'],
            'journey' => ['array', 'max:15'],
            'journey.*' => ['nullable', 'string', 'max:600'],
            'services' => ['array', 'max:10'],
            'services.*.question' => ['nullable', 'string', 'max:120'],
            'services.*.answer' => ['nullable', 'string', 'max:600'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'remove_photo' => ['nullable', 'boolean'],
            'photo_alt' => ['nullable', 'string', 'max:200'],
            'links' => ['array'],
            'links.*' => ['nullable', 'string', 'max:500'],
            'whatsapp_channels' => ['array', 'max:5'],
            'whatsapp_channels.*' => ['nullable', 'string', 'max:500'],
            'contact.whatsapp' => ['nullable', 'string', 'max:30'],
            'contact.email' => ['nullable', 'email', 'max:150'],
            'contact.office' => ['nullable', 'string', 'max:200'],
        ], [
            'title.required' => 'Indiquez le titre de la page.',
            'description.required' => 'Indiquez la description (texte affiché par Google sous le titre).',
            'photo.image' => 'La photo doit être une image (JPEG, PNG ou WebP).',
            'photo.mimes' => 'La photo doit être une image (JPEG, PNG ou WebP).',
            'photo.max' => 'La photo est trop lourde (8 Mo au maximum).',
        ]);

        $links = [];

        foreach (array_keys(Founder::NETWORKS) as $network) {
            $url = trim((string) ($validated['links'][$network] ?? ''));

            if ($url !== '' && ! Founder::isValidLink($network, $url)) {
                throw ValidationException::withMessages(['links.'.$network => Founder::invalidLinkMessage($network)]);
            }

            $links[$network] = $url;
        }

        $channels = self::lines($validated['whatsapp_channels'] ?? []);

        foreach ($channels as $index => $url) {
            if (! Founder::isValidLink('whatsapp_channel', $url)) {
                throw ValidationException::withMessages(['whatsapp_channels' => 'Chaîne WhatsApp '.($index + 1).' : '.Founder::invalidLinkMessage('whatsapp_channel')]);
            }
        }

        // Photo : gardée, remplacée par un nouvel envoi (l'ancienne est effacée) ou retirée.
        $photo = (string) (((array) SiteSettings::get('site.founder'))['photo'] ?? '');
        $photoChange = null;

        if ($request->hasFile('photo')) {
            $new = $images->cover($request->file('photo'), Founder::PHOTO_DIRECTORY, Founder::PHOTO_SIZE, Founder::PHOTO_SIZE, field: 'photo');
            $this->deleteUploaded('photo', $photo, $images);
            $photo = $new;
            $photoChange = 'nouvelle photo';
        } elseif ($request->boolean('remove_photo') && $photo !== '') {
            $this->deleteUploaded('photo', $photo, $images);
            $photo = '';
            $photoChange = 'photo retirée';
        }

        $services = [];

        foreach ($validated['services'] ?? [] as $service) {
            $title = trim((string) ($service['question'] ?? ''));

            if ($title !== '') {
                $services[] = ['title' => $title, 'text' => trim((string) ($service['answer'] ?? ''))];
            }
        }

        SiteSettings::save(['site.founder' => [
            'name' => trim((string) $validated['name']),
            'nickname' => trim((string) ($validated['nickname'] ?? '')),
            'title' => trim((string) $validated['title']),
            'description' => trim((string) $validated['description']),
            'job_title' => trim((string) $validated['job_title']),
            'tagline' => trim((string) ($validated['tagline'] ?? '')),
            'intro' => self::lines($validated['intro'] ?? []),
            'journey' => self::lines($validated['journey'] ?? []),
            'services' => $services,
            'photo' => $photo,
            'photo_alt' => trim((string) ($validated['photo_alt'] ?? '')) ?: (string) SiteSettings::default('site.founder.photo_alt'),
            'links' => $links,
            'whatsapp_channels' => $channels,
            'contact' => [
                'whatsapp' => trim((string) ($validated['contact']['whatsapp'] ?? '')),
                'email' => trim((string) ($validated['contact']['email'] ?? '')),
                'office' => trim((string) ($validated['contact']['office'] ?? '')),
            ],
        ]]);

        $count = count(array_filter($links)) + count($channels);

        ActivityLog::record(AdminAction::SettingsUpdated, 'Page « Joseph Ahiankonou » modifiée ('.$count.' lien'.($count > 1 ? 's' : '').($photoChange ? ', '.$photoChange : '').').');
    }

    /**
     * Code de vérification de Google Search Console : la balise entière ou le code seul sont acceptés.
     */
    private function updateSearchConsole(Request $request): void
    {
        $code = SearchConsole::clean(is_string($request->input('google_verification')) ? $request->input('google_verification') : '');

        if ($code !== '' && ! SearchConsole::isValid($code)) {
            throw ValidationException::withMessages(['google_verification' => SearchConsole::invalidMessage()]);
        }

        SiteSettings::save(['site.seo' => ['google_verification' => $code]]);

        ActivityLog::record(AdminAction::SettingsUpdated, $code === '' ? 'Code de vérification Google Search Console retiré.' : 'Code de vérification Google Search Console enregistré.');
    }

    private function updateVoiceAssistant(Request $request): void
    {
        $request->merge(['agent_id' => VoiceAssistant::cleanAgentId(is_string($request->input('agent_id')) ? $request->input('agent_id') : null)]);

        $rules = [
            'enabled' => ['nullable', 'boolean'],
            'provider' => ['nullable', Rule::in([VoiceAssistant::GEMINI, VoiceAssistant::ELEVENLABS])],
            'instructions' => ['nullable', 'string', 'max:12000'],
            'knowledge_school' => ['nullable', 'string', 'max:'.AmaKnowledge::MAX_SCHOOL],
            'knowledge_faq' => ['array', 'max:'.AmaKnowledge::MAX_QUESTIONS],
            'knowledge_faq.*.question' => ['nullable', 'string', 'max:200'],
            'knowledge_faq.*.answer' => ['nullable', 'string', 'max:'.AmaKnowledge::MAX_ANSWER],
            'knowledge_other' => ['nullable', 'string', 'max:'.AmaKnowledge::MAX_OTHER],
            'agent_id' => ['nullable', 'string', 'max:80'],
            'lessons' => ['nullable', 'boolean'],
            'proactive' => ['nullable', 'boolean'],
            'voice_interrupt' => ['nullable', 'boolean'],
            'character' => ['nullable', 'boolean'],
            'nudge' => ['nullable', 'boolean'],
            'character_style' => ['nullable', Rule::in(VoiceAssistant::CHARACTER_STYLES)],
            'nudges' => ['array'],
            'nudges.*' => ['nullable', 'string', 'max:'.VoiceAssistant::NUDGE_MAX_LENGTH],
            'help_delay' => ['nullable', 'integer', 'min:'.VoiceAssistant::MIN_HELP_DELAY, 'max:'.VoiceAssistant::MAX_HELP_DELAY],
            'natural_voice' => ['nullable', 'boolean'],
            'natural_voice_name' => ['nullable', Rule::in(array_keys(NaturalVoice::VOICES))],
            'natural_voice_model' => ['nullable', Rule::in(array_keys(NaturalVoice::MODELS))],
            'natural_voice_minute_limit' => ['nullable', 'integer', 'min:'.NaturalVoice::MINUTE_LIMIT[1], 'max:'.NaturalVoice::MINUTE_LIMIT[2]],
            'natural_voice_split_length' => ['nullable', 'integer', 'min:'.NaturalVoice::SPLIT_LENGTH[1], 'max:'.NaturalVoice::SPLIT_LENGTH[2]],
            'natural_voice_timeout' => ['nullable', 'integer', 'min:'.NaturalVoice::TIMEOUT[1], 'max:'.NaturalVoice::TIMEOUT[2]],
            'natural_voice_phone_fallback' => ['nullable', 'boolean'],
        ];
        $messages = [
            'instructions.max' => 'Les instructions sont trop longues (12 000 caractères au maximum).',
            'knowledge_school.max' => 'Le texte « L\'école et son fondateur » est trop long ('.number_format(AmaKnowledge::MAX_SCHOOL, 0, ',', ' ').' caractères au maximum).',
            'knowledge_faq.max' => 'Trop de questions (au maximum '.AmaKnowledge::MAX_QUESTIONS.').',
            'knowledge_faq.*.question.max' => 'Une question est trop longue (200 caractères au maximum).',
            'knowledge_faq.*.answer.max' => 'Une réponse est trop longue ('.number_format(AmaKnowledge::MAX_ANSWER, 0, ',', ' ').' caractères au maximum).',
            'knowledge_other.max' => 'Le texte « Autres informations » est trop long ('.number_format(AmaKnowledge::MAX_OTHER, 0, ',', ' ').' caractères au maximum).',
            'nudges.*.max' => 'Un message de présentation est trop long ('.VoiceAssistant::NUDGE_MAX_LENGTH.' caractères au maximum).',
            'help_delay.integer' => 'Le délai s\'écrit en secondes, sans virgule (par exemple 90).',
            'help_delay.min' => 'Le délai doit être d\'au moins '.VoiceAssistant::MIN_HELP_DELAY.' secondes.',
            'help_delay.max' => 'Le délai ne peut pas dépasser '.VoiceAssistant::MAX_HELP_DELAY.' secondes.',
            'natural_voice_name.in' => 'Choisissez une des voix proposées.',
            'natural_voice_model.in' => 'Choisissez un des modèles proposés.',
            'natural_voice_minute_limit.integer' => 'Écrivez un nombre entier, sans espace ni virgule.',
            'natural_voice_minute_limit.min' => 'Le nombre doit être d\'au moins '.NaturalVoice::MINUTE_LIMIT[1].'.',
            'natural_voice_minute_limit.max' => 'Le nombre ne peut pas dépasser '.NaturalVoice::MINUTE_LIMIT[2].'.',
            'natural_voice_split_length.integer' => 'Écrivez un nombre entier, sans espace ni virgule.',
            'natural_voice_split_length.min' => 'La longueur doit être d\'au moins '.NaturalVoice::SPLIT_LENGTH[1].' caractères.',
            'natural_voice_split_length.max' => 'La longueur ne peut pas dépasser '.NaturalVoice::SPLIT_LENGTH[2].' caractères.',
            'natural_voice_timeout.integer' => 'Écrivez un nombre entier de secondes, sans virgule.',
            'natural_voice_timeout.min' => 'Le délai doit être d\'au moins '.NaturalVoice::TIMEOUT[1].' secondes.',
            'natural_voice_timeout.max' => 'Le délai ne peut pas dépasser '.NaturalVoice::TIMEOUT[2].' secondes.',
        ];

        foreach (VoiceAssistant::LIMITS as $name => [, $min, $max]) {
            $rules[$name] = ['nullable', 'integer', 'min:'.$min, 'max:'.$max];
            $messages[$name.'.integer'] = 'Écrivez un nombre entier, sans espace ni virgule.';
            $messages[$name.'.min'] = 'Le nombre doit être d\'au moins '.$min.'.';
            $messages[$name.'.max'] = 'Le nombre ne peut pas dépasser '.$max.'.';
        }

        $validated = $request->validate($rules, $messages);

        $provider = (string) ($validated['provider'] ?? VoiceAssistant::GEMINI);
        $agentId = (string) ($validated['agent_id'] ?? '');
        $previous = (array) SiteSettings::get('site.voice_assistant');

        if ($provider === VoiceAssistant::ELEVENLABS) {
            if ($agentId === '') {
                throw ValidationException::withMessages(['agent_id' => 'Indiquez l\'identifiant de l\'agent ElevenLabs. Exemple : '.VoiceAssistant::EXAMPLE_ID.'.']);
            }

            if (! VoiceAssistant::isValidAgentId($agentId)) {
                throw ValidationException::withMessages(['agent_id' => VoiceAssistant::invalidMessage($agentId)]);
            }
        } elseif (! VoiceAssistant::isValidAgentId($agentId)) {
            // Ama (Gemini) choisie : l'identifiant de l'agent ElevenLabs n'est pas utilisé, l'ancien est gardé.
            $agentId = (string) ($previous['agent_id'] ?? VoiceAssistant::EXAMPLE_ID);
        }

        $enabled = (bool) ($validated['enabled'] ?? false);
        $lessons = (bool) ($validated['lessons'] ?? false);
        $proactive = (bool) ($validated['proactive'] ?? false);
        $voiceInterrupt = (bool) ($validated['voice_interrupt'] ?? false);
        $character = (bool) ($validated['character'] ?? false);
        $nudge = (bool) ($validated['nudge'] ?? false);
        $characterStyle = (string) ($validated['character_style'] ?? 'a');
        // Messages de présentation : un champ vidé ou identique au texte d'origine revient au texte d'origine.
        $nudges = [];
        foreach (VoiceAssistant::NUDGES as $type => $default) {
            $text = trim((string) preg_replace('/\s+/u', ' ', (string) ($validated['nudges'][$type] ?? '')));
            if ($text !== '' && $text !== $default) {
                $nudges[$type] = $text;
            }
        }
        $delay = (int) ($validated['help_delay'] ?? VoiceAssistant::DEFAULT_HELP_DELAY);
        // Voix naturelle (modèles « TTS » de Gemini) : éteinte par défaut.
        $naturalVoice = [
            'enabled' => (bool) ($validated['natural_voice'] ?? false),
            'voice' => (string) ($validated['natural_voice_name'] ?? NaturalVoice::DEFAULT_VOICE),
            'model' => (string) ($validated['natural_voice_model'] ?? array_key_first(NaturalVoice::MODELS)),
            'minute_limit' => (int) ($validated['natural_voice_minute_limit'] ?? NaturalVoice::MINUTE_LIMIT[0]),
            'split_length' => (int) ($validated['natural_voice_split_length'] ?? NaturalVoice::SPLIT_LENGTH[0]),
            'timeout' => (int) ($validated['natural_voice_timeout'] ?? NaturalVoice::TIMEOUT[0]),
            // « Voix du téléphone en secours » : allumé par défaut ; éteint, une réponse non dite par Gemini est affichée.
            'phone_fallback' => (bool) ($validated['natural_voice_phone_fallback'] ?? true),
        ];
        // Instructions vidées : retour au texte d'origine.
        $instructions = AmaPrompt::effectiveInstructions($validated['instructions'] ?? '');

        // Base de connaissances : « L'école et son fondateur » vidé = texte de départ.
        $previousKnowledge = (array) SiteSettings::get('site.assistant_knowledge');
        $school = trim(str_replace("\r\n", "\n", (string) ($validated['knowledge_school'] ?? '')));
        $knowledge = [
            'school' => $school !== '' ? $school : AmaKnowledge::DEFAULT_SCHOOL,
            'faq' => self::questions($validated['knowledge_faq'] ?? []),
            'other' => trim(str_replace("\r\n", "\n", (string) ($validated['knowledge_other'] ?? ''))),
        ];

        /** @var array<string, int> $limits */
        $limits = [];
        foreach (VoiceAssistant::LIMITS as $name => [$default]) {
            $limits[$name] = (int) ($validated[$name] ?? $default);
        }

        SiteSettings::save(['site.assistant_knowledge' => $knowledge, 'site.voice_assistant' => [
            'enabled' => $enabled,
            'provider' => $provider,
            'instructions' => $instructions,
            ...$limits,
            'agent_id' => $agentId,
            'lessons' => $lessons,
            'proactive' => $proactive,
            'voice_interrupt' => $voiceInterrupt,
            'character' => $character,
            'character_style' => $characterStyle,
            'nudge' => $nudge,
            'nudges' => $nudges,
            'help_delay' => $delay,
            'natural_voice' => $naturalVoice,
        ]]);

        $summary = $provider === VoiceAssistant::GEMINI
            ? 'Ama (Gemini), '.$limits['visitor_daily_limit'].' messages par visiteur et par jour, '.$limits['daily_limit'].' demandes par jour pour le site'.($instructions !== (string) ($previous['instructions'] ?? AmaPrompt::DEFAULT_INSTRUCTIONS) ? ', instructions modifiées' : '').($knowledge !== ($previousKnowledge ?: ['school' => AmaKnowledge::DEFAULT_SCHOOL, 'faq' => [], 'other' => '']) ? ', base de connaissances modifiée' : '')
            : 'agent ElevenLabs '.$agentId;

        // Voix naturelle allumée : les phrases écrites à l'avance sont préparées en mémoire en arrière-plan (sans effet si
        // elles le sont déjà pour cette voix et ce modèle).
        if ($naturalVoice['enabled'] && $enabled && $provider === VoiceAssistant::GEMINI) {
            PrepareAmaVoicePhrases::dispatch();
        }

        ActivityLog::record(AdminAction::SettingsUpdated, 'Assistante '.($enabled ? 'activée' : 'désactivée').' ('.$summary.', lecteur de cours : '.($lessons ? 'oui' : 'non').', aide proactive : '.($proactive ? 'oui, après '.$delay.' s' : 'non').', interruption à la voix : '.($voiceInterrupt ? 'oui' : 'non').', personnage animé : '.($character ? 'oui (visage '.$characterStyle.')' : 'non').', Ama se présente toute seule : '.($nudge ? 'oui' : 'non').', voix naturelle : '.($naturalVoice['enabled'] ? 'oui ('.$naturalVoice['voice'].', '.$naturalVoice['model'].', '.$naturalVoice['minute_limit'].' par minute, deux demandes au-delà de '.$naturalVoice['split_length'].' caractères, '.($naturalVoice['phone_fallback'] ? 'voix du téléphone après '.$naturalVoice['timeout'].' s' : 'sans voix du téléphone en secours, réponse affichée après '.$naturalVoice['timeout'].' s').')' : 'non').').');
    }

    private function updateLegal(Request $request): void
    {
        $validated = $request->validate([
            'host' => ['required', 'string', 'max:300'],
            'data_location' => ['required', 'string', 'max:200'],
        ], [
            'host.required' => 'Indiquez l\'hébergeur du site.',
            'data_location.required' => 'Indiquez le lieu de stockage des données.',
        ]);

        $before = [(string) config('site.legal.host'), (string) config('site.legal.data_location')];

        SiteSettings::save([
            'site.legal.host' => trim((string) $validated['host']),
            'site.legal.data_location' => trim((string) $validated['data_location']),
        ]);

        ActivityLog::record(AdminAction::SettingsUpdated, 'Pages légales : hébergeur « '.$before[0].' » → « '.trim((string) $validated['host']).' », lieu de stockage « '.$before[1].' » → « '.trim((string) $validated['data_location']).' ».');
    }

    /**
     * Lignes non vides, sans espaces autour.
     *
     * @param  array<array-key, mixed>  $lines
     * @return list<string>
     */
    public static function lines(array $lines): array
    {
        return array_values(array_filter(array_map(fn ($line): string => trim((string) $line), $lines), fn (string $line): bool => $line !== ''));
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @return list<array{question: string, answer: string}>
     */
    private static function questions(array $items): array
    {
        $questions = [];

        foreach ($items as $item) {
            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));

            if ($question !== '' && $answer !== '') {
                $questions[] = ['question' => $question, 'answer' => $answer];
            }
        }

        return $questions;
    }

    /**
     * @param  list<string>  $taken
     */
    private static function newMethodKey(string $country, string $name, array $taken): string
    {
        $base = Str::limit($country.'-'.(Str::slug($name) ?: 'mobile-money'), 34, '');
        $key = $base;
        $suffix = 2;

        while (in_array($key, $taken, true)) {
            $key = $base.'-'.$suffix++;
        }

        return $key;
    }

    /**
     * Résumé lisible des changements (pour le journal et l'e-mail d'alerte).
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, array<string, mixed>>  $after
     * @return list<string>
     */
    private static function describeMethodChanges(array $before, array $after): array
    {
        $changes = [];
        $labels = ['number' => 'numéro', 'beneficiary' => 'bénéficiaire', 'name' => 'nom', 'country' => 'pays', 'ussd' => 'code USSD'];

        foreach ($after as $key => $method) {
            $old = is_array($before[$key] ?? null) ? $before[$key] : null;

            if ($old === null) {
                $changes[] = 'nouveau : '.$method['name'].' '.$method['number'].' ('.$method['beneficiary'].')';

                continue;
            }

            foreach ($labels as $field => $label) {
                if ((string) ($old[$field] ?? '') !== (string) $method[$field]) {
                    $changes[] = $method['name'].', '.$label.' : '.($old[$field] ?? '—').' → '.$method[$field];
                }
            }

            if ((bool) ($old['enabled'] ?? true) !== $method['enabled']) {
                $changes[] = $method['name'].' '.($method['enabled'] ? 'activé' : 'désactivé');
            }
        }

        return $changes;
    }

    /**
     * Code USSD collé : espaces et caractères invisibles retirés, repères en minuscules.
     */
    public static function cleanUssd(string $value): string
    {
        $value = (string) preg_replace('/[\s\x{00A0}\x{200B}-\x{200D}\x{2060}\x{FEFF}]+/u', '', $value);

        return str_ireplace(['{numéro}', '{numero}', '{montant}'], ['{numero}', '{numero}', '{montant}'], $value);
    }

    /**
     * Code USSD de transfert : chiffres, * et #, commence par * et finit par #, repères {numero} et {montant}.
     * Aucun autre texte (et donc jamais de code secret en toutes lettres).
     */
    public static function isValidUssd(string $value): bool
    {
        return preg_match('/^\*(?:[0-9*#]|\{numero\}|\{montant\})*#$/', $value) === 1;
    }

    /**
     * Pour la vue : couleurs proposées aux nouveaux moyens de paiement.
     *
     * @return list<string>
     */
    public static function palette(): array
    {
        return ['#facc15', '#3b82f6', '#f97316', '#38bdf8', '#eab308', '#2563eb', '#a855f7', '#22c55e', '#ef4444'];
    }
}
