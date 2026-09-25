<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ActivityLog;
use App\Enums\AdminAction;
use App\Http\Controllers\Controller;
use App\Services\ImageUploader;
use App\Settings\SiteSettings;
use App\Support\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        'image' => ['title' => 'Logo et image de partage', 'text' => 'Logo du site et image affichée lors d\'un partage sur WhatsApp ou Facebook.', 'icon' => 'photo'],
        'paiements' => ['title' => 'Moyens de paiement', 'text' => 'Numéros Mobile Money, bénéficiaires, pays, mention du représentant, adresse des alertes.', 'icon' => 'banknotes', 'sensitive' => true],
        'regles' => ['title' => 'Seuils et délais', 'text' => 'Seuil d\'accès provisoire, délai de vérification, réglages des sessions.', 'icon' => 'adjustments-horizontal'],
        'affiliation' => ['title' => 'Affiliation', 'text' => 'Taux de commission, durée des liens, délai de disponibilité, retrait minimum.', 'icon' => 'share', 'sensitive' => true],
        'textes' => ['title' => 'Textes du site', 'text' => 'Accroche de l\'accueil, présentation du formateur, questions fréquentes.', 'icon' => 'document-text'],
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
        };

        return to_route('admin.settings.edit', $section)->with('status', 'Réglages enregistrés : ils sont déjà visibles sur le site.');
    }

    private function updateContact(Request $request): void
    {
        $validated = $request->validate([
            'phones' => ['array', 'max:5'],
            'phones.*' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'map_url' => ['nullable', 'url:https', 'max:500'],
            'hours' => ['required', 'string', 'max:120'],
            'whatsapp_number' => ['required', 'string', 'max:30'],
            'whatsapp_message' => ['required', 'string', 'max:300'],
            'consultation_message' => ['required', 'string', 'max:300'],
            'social' => ['array'],
            'social.facebook' => ['nullable', 'url:https', 'max:255'],
            'social.tiktok' => ['nullable', 'url:https', 'max:255'],
            'social.youtube' => ['nullable', 'url:https', 'max:255'],
            'trained' => ['required', 'string', 'max:20'],
            'founded' => ['required', 'integer', 'between:2000,2100'],
        ], [
            'map_url.url' => 'Le lien de la carte doit commencer par https://.',
            'social.*.url' => 'Le lien doit être une adresse complète commençant par https://.',
        ]);

        $whatsapp = PhoneNumber::normalize((string) $validated['whatsapp_number']);

        if ($whatsapp === null) {
            throw ValidationException::withMessages(['whatsapp_number' => 'Numéro WhatsApp invalide. Exemple : +228 72 24 02 31.']);
        }

        $contact = (array) SiteSettings::get('site.contact');
        $stats = (array) SiteSettings::get('site.stats');
        $stats[0] = ['value' => trim((string) $validated['trained']), 'label' => (string) ($stats[0]['label'] ?? 'personnes formées')];
        $stats[1] = ['value' => (string) $validated['founded'], 'label' => (string) ($stats[1]['label'] ?? 'année de création de l\'école')];
        $about = (array) SiteSettings::get('site.about');
        $about['founded'] = (string) $validated['founded'];

        SiteSettings::save([
            'site.contact' => [
                ...$contact,
                'phones' => self::lines($validated['phones'] ?? []),
                'email' => strtolower(trim((string) $validated['email'])),
                'address' => trim((string) $validated['address']),
                'map_url' => $validated['map_url'] ?: 'https://www.google.com/maps/search/?api=1&query='.rawurlencode((string) $validated['address']),
                'hours' => trim((string) $validated['hours']),
            ],
            'site.whatsapp' => [
                'number' => ltrim($whatsapp, '+'),
                'message' => trim((string) $validated['whatsapp_message']),
                'consultation_message' => trim((string) $validated['consultation_message']),
            ],
            'site.social' => [
                'facebook' => (string) ($validated['social']['facebook'] ?? ''),
                'tiktok' => (string) ($validated['social']['tiktok'] ?? ''),
                'youtube' => (string) ($validated['social']['youtube'] ?? ''),
            ],
            'site.stats' => array_values($stats),
            'site.about' => $about,
        ]);

        ActivityLog::record(AdminAction::SettingsUpdated, 'Coordonnées et chiffres clés modifiés.');
    }

    private function updateImages(Request $request, ImageUploader $images): void
    {
        $request->validate([
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'og_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_og_image' => ['nullable', 'boolean'],
        ], [
            'logo.image' => 'Le logo doit être une image (PNG, JPEG ou WebP).',
            'og_image.image' => 'L\'image de partage doit être une image (JPEG, PNG ou WebP).',
        ]);

        $brand = (array) SiteSettings::get('site.brand');
        $changes = [];

        if ($request->boolean('remove_logo') && ! empty($brand['logo'])) {
            $images->delete((string) $brand['logo']);
            $brand['logo'] = null;
            $changes[] = 'logo retiré';
        }

        if ($request->boolean('remove_og_image') && ! empty($brand['og_image'])) {
            $images->delete((string) $brand['og_image']);
            $brand['og_image'] = null;
            $changes[] = 'image de partage retirée';
        }

        if ($request->hasFile('logo')) {
            $images->delete($brand['logo'] ?? null);
            // Logo : réduit (hauteur 144 px, affiché à 36 px), transparence conservée.
            $brand['logo'] = $images->fit($request->file('logo'), 'site', 640, 144, keepTransparency: true, field: 'logo');
            $changes[] = 'nouveau logo';
        }

        if ($request->hasFile('og_image')) {
            $images->delete($brand['og_image'] ?? null);
            $brand['og_image'] = $images->cover($request->file('og_image'), 'site', 1200, 630, field: 'og_image');
            $changes[] = 'nouvelle image de partage';
        }

        if ($changes === []) {
            throw ValidationException::withMessages(['logo' => 'Choisissez une image à envoyer.']);
        }

        SiteSettings::save(['site.brand' => $brand]);
        ActivityLog::record(AdminAction::SettingsUpdated, 'Logo et image de partage : '.implode(', ', $changes).'.');
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

            $methods[$key] = [
                'country' => (string) $method['country'],
                'name' => trim((string) $method['name']),
                'number' => trim((string) $method['number']),
                'beneficiary' => trim((string) $method['beneficiary']),
                'color' => strtolower((string) $method['color']),
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

        SiteSettings::save(collect($validated)->mapWithKeys(fn ($value, $key): array => ['affiliate.'.$key => (int) $value])->all());

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
        $labels = ['number' => 'numéro', 'beneficiary' => 'bénéficiaire', 'name' => 'nom', 'country' => 'pays'];

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
     * Pour la vue : couleurs proposées aux nouveaux moyens de paiement.
     *
     * @return list<string>
     */
    public static function palette(): array
    {
        return ['#facc15', '#3b82f6', '#f97316', '#38bdf8', '#eab308', '#2563eb', '#a855f7', '#22c55e', '#ef4444'];
    }

    /**
     * Règles des pays pour la liste de sélection.
     *
     * @return array<string, string>
     */
    public static function countryOptions(): array
    {
        return collect((array) SiteSettings::get('site.payments.countries'))
            ->map(fn ($country, $code): string => trim(($country['flag'] ?? '').' '.($country['name'] ?? $code)))
            ->all();
    }
}
