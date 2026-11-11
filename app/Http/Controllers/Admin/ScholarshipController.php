<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ActivityLog;
use App\Enums\AdminAction;
use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\ScholarshipApplication;
use App\Models\User;
use App\Payments\ProofImageProcessor;
use App\Scholarships\ScholarshipCampaign;
use App\Scholarships\ScholarshipDecisions;
use App\Settings\SiteSettings;
use App\Support\FileStorage;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * « Bourse Agents IA » (admin uniquement) : réglages (allumer, dates d'ouverture et de clôture à l'heure de Lomé,
 * formation, frais, places), compteur des places, candidatures avec toutes les réponses et filtres (genre, âge,
 * niveau, statut), capture du paiement, « Valider » / « Refuser » (ScholarshipDecisions).
 */
class ScholarshipController extends Controller
{
    public const array STATUS_FILTERS = [
        'a-verifier' => 'À vérifier',
        'validees' => 'Validées',
        'refusees' => 'Refusées',
        'a-rembourser' => 'À rembourser',
        'toutes' => 'Toutes',
    ];

    public const array AGE_BANDS = ['20-25' => [20, 25], '26-30' => [26, 30], '31-35' => [31, 35], '36-40' => [36, 40]];

    public function index(Request $request): View
    {
        $status = array_key_exists((string) $request->query('statut'), self::STATUS_FILTERS) ? (string) $request->query('statut') : 'a-verifier';
        $gender = array_key_exists((string) $request->query('genre'), ScholarshipApplication::GENDERS) ? (string) $request->query('genre') : null;
        $age = array_key_exists((string) $request->query('age'), self::AGE_BANDS) ? (string) $request->query('age') : null;
        $ageRange = $age !== null ? self::AGE_BANDS[$age] : null;
        $level = array_key_exists((string) $request->query('niveau'), ScholarshipApplication::LEVELS) ? (string) $request->query('niveau') : null;

        $base = fn (): Builder => ScholarshipApplication::query()->where('campaign', ScholarshipCampaign::campaign());

        $applications = $base()
            ->with('decider:id,name')
            ->when($status === 'a-verifier', fn ($query) => $query->where('status', ScholarshipApplication::RESERVED))
            ->when($status === 'validees', fn ($query) => $query->where('status', ScholarshipApplication::VALIDATED))
            ->when($status === 'refusees', fn ($query) => $query->where('status', ScholarshipApplication::REFUSED))
            ->when($status === 'a-rembourser', fn ($query) => $query->where('status', ScholarshipApplication::REFUSED)->where('refund_due', true)->whereNull('refunded_at'))
            ->when($gender !== null, fn ($query) => $query->where('gender', $gender))
            ->when($ageRange, fn ($query, array $range) => $query->whereBetween('age', $range))
            ->when($level !== null, fn ($query) => $query->where('level', $level))
            ->oldest('id')
            ->paginate(20)->withQueryString();

        $validated = $base()->where('status', ScholarshipApplication::VALIDATED)->selectRaw('gender, count(*) as total')->groupBy('gender')->pluck('total', 'gender');

        return view('site.admin.scholarship.index', [
            'applications' => $applications,
            'filters' => compact('status', 'gender', 'age', 'level'),
            'counts' => [
                'a-verifier' => $base()->where('status', ScholarshipApplication::RESERVED)->count(),
                'validees' => $base()->where('status', ScholarshipApplication::VALIDATED)->count(),
                'refusees' => $base()->where('status', ScholarshipApplication::REFUSED)->count(),
                'a-rembourser' => $base()->where('status', ScholarshipApplication::REFUSED)->where('refund_due', true)->whereNull('refunded_at')->count(),
                'toutes' => $base()->count(),
            ],
            'taken' => ScholarshipCampaign::taken(),
            'validatedByGender' => $validated,
            'counter' => ScholarshipCampaign::counter(),
            'settings' => ScholarshipCampaign::settings(),
            'formations' => Formation::query()->orderBy('title')->get(['id', 'slug', 'title', 'price']),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'formation' => ['required', Rule::exists('formations', 'slug')],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
            'fee' => ['required', 'integer', 'min:100', 'max:1000000'],
            'value' => ['required', 'integer', 'min:0', 'max:10000000'],
            'seats_per_gender' => ['required', 'integer', 'min:1', 'max:100'],
        ], [
            'formation.required' => 'Choisissez la formation offerte.',
            'formation.exists' => 'Choisissez la formation offerte.',
            'opens_at.date' => 'Date d\'ouverture invalide.',
            'closes_at.date' => 'Date de clôture invalide.',
            'closes_at.after' => 'La clôture doit venir après l\'ouverture.',
            'fee.*' => 'Frais d\'inscription : un montant en FCFA (100 au moins).',
            'value.*' => 'Valeur de la formation : un montant en FCFA.',
            'seats_per_gender.*' => 'Places par genre : un nombre de 1 à 100.',
        ]);

        $enabled = (bool) ($validated['enabled'] ?? false);

        if ($enabled && (blank($validated['opens_at'] ?? null) || blank($validated['closes_at'] ?? null))) {
            return back()->withErrors(['opens_at' => 'Indiquez l\'ouverture et la clôture avant d\'allumer la bourse.'])->withInput();
        }

        $date = fn (?string $value): ?string => filled($value) ? CarbonImmutable::parse($value, 'Africa/Lome')->format('Y-m-d H:i') : null;

        $settings = [
            ...ScholarshipCampaign::settings(),
            'enabled' => $enabled,
            'formation' => (string) $validated['formation'],
            'opens_at' => $date($validated['opens_at'] ?? null),
            'closes_at' => $date($validated['closes_at'] ?? null),
            'fee' => (int) $validated['fee'],
            'value' => (int) $validated['value'],
            'seats_per_gender' => (int) $validated['seats_per_gender'],
        ];
        SiteSettings::save(['site.scholarship' => $settings]);

        ActivityLog::record(
            AdminAction::ScholarshipSettingsUpdated,
            'Bourse '.($enabled ? 'allumée' : 'éteinte').' : du '.($settings['opens_at'] ?? '?').' au '.($settings['closes_at'] ?? '?').' (heure de Lomé), '.$settings['seats_per_gender'].' places par genre, frais '.$settings['fee'].' FCFA.',
        );

        return back()->with('status', 'Réglages de la bourse enregistrés.');
    }

    public function validateApplication(Request $request, ScholarshipApplication $application, ScholarshipDecisions $decisions): RedirectResponse
    {
        $decisions->validate($application, $this->admin($request));

        return back()->with('status', 'Candidature de '.$application->name.' validée : compte, accès et groupe ouverts, e-mail envoyé.')
            ->with('scholarship_whatsapp', $application->id);
    }

    public function refuse(Request $request, ScholarshipApplication $application, ScholarshipDecisions $decisions): RedirectResponse
    {
        $refund = $request->input('refund') === '1';
        $decisions->refuse($application, $this->admin($request), $refund);

        return back()->with('status', 'Candidature de '.$application->name.' refusée'.($refund ? ' : à rembourser.' : ' (paiement non reçu).').' La place est libérée.');
    }

    public function refunded(Request $request, ScholarshipApplication $application, ScholarshipDecisions $decisions): RedirectResponse
    {
        $decisions->markRefunded($application, $this->admin($request));

        return back()->with('status', 'Remboursement de '.$application->name.' noté.');
    }

    public function proof(ScholarshipApplication $application): Response
    {
        $path = $application->proof_path;
        $filename = 'bourse-'.$application->id.'.jpg';

        return FileStorage::privateResponse(ProofImageProcessor::DISK, $path, $filename, true, 'image/jpeg', fn (Filesystem $disk): Response => $disk->response($path, $filename, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-Content-Type-Options' => 'nosniff',
        ]));
    }

    private function admin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
