<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ScholarshipApplication;
use App\Payments\PaymentMethods;
use App\Scholarships\ScholarshipCampaign;
use App\Scholarships\ScholarshipSubmitter;
use App\Support\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Page publique de la bourse « Créer des agents IA » (/bourse-agents-ia) : candidature et paiement des frais
 * d'inscription par Mobile Money avec capture, SANS compte (demande de Joseph). Compte à rebours jusqu'à la
 * clôture et places restantes (5 femmes, 5 hommes, premiers payés), remis à jour par le navigateur.
 * Bourse éteinte dans l'admin : page introuvable (sauf pour l'admin, qui la relit).
 */
class ScholarshipController extends Controller
{
    public const string SENT = 'scholarship_sent';

    public function show(Request $request): View
    {
        abort_unless(ScholarshipCampaign::enabled() || $request->user()?->isAdmin(), 404);

        return view('site.scholarship.show', [
            'counter' => ScholarshipCampaign::counter(),
            'formation' => ScholarshipCampaign::formation(),
            'paymentMethods' => PaymentMethods::enabled(),
            'countries' => PaymentMethods::countries(),
        ]);
    }

    /**
     * Places restantes et état (compteur de la page, sans aucune donnée personnelle).
     */
    public function places(): JsonResponse
    {
        abort_unless(ScholarshipCampaign::enabled(), 404);

        return response()->json(ScholarshipCampaign::counter())->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, ScholarshipSubmitter $submitter): RedirectResponse
    {
        abort_unless(ScholarshipCampaign::enabled(), 404);
        $back = route('scholarship').'#candidature';

        // Champ piège : réponse neutre, rien d'enregistré.
        if (filled($request->input('website'))) {
            return redirect()->to($back)->with(self::SENT, true);
        }

        try {
            $answers = $this->validated($request);
            $answers['phone'] = PhoneNumber::normalize((string) $answers['phone'])
                ?? throw ValidationException::withMessages(['phone' => 'Numéro WhatsApp invalide : écris-le avec l\'indicatif du pays (ex. +228 90 00 00 00).']);
            $answers['email'] = Str::lower(trim((string) $answers['email']));
            $answers['available'] = $answers['available'] === 'yes';

            $application = $submitter->submit($answers, $request->file('proof'));
        } catch (ValidationException $exception) {
            throw $exception->redirectTo($back);
        }

        return redirect()->to($back)->with(self::SENT, $application->firstName());
    }

    /**
     * Lien de l'e-mail « Tu es retenu(e) » (signé, valable 7 jours) : page « Choisir mon mot de passe ».
     */
    public function access(ScholarshipApplication $application): RedirectResponse
    {
        abort_unless($application->isValidated() && $application->user !== null, 404);

        $token = Password::broker()->createToken($application->user);

        return redirect()->route('password.reset', ['token' => $token, 'email' => $application->user->email]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $maxKb = (int) config('site.payments.proof_max_kb', 8192);

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email:rfc,filter', 'max:255'],
            'gender' => ['required', Rule::in(array_keys(ScholarshipApplication::GENDERS))],
            'age' => ['required', 'integer', 'between:20,40'],
            'city' => ['required', 'string', 'max:120'],
            'level' => ['required', Rule::in(array_keys(ScholarshipApplication::LEVELS))],
            'tools' => ['required', 'string', 'max:1000'],
            'device' => ['required', Rule::in(array_keys(ScholarshipApplication::DEVICES))],
            'motivation' => ['required', 'string', 'min:30', 'max:3000'],
            'first_agent' => ['required', 'string', 'min:10', 'max:2000'],
            'available' => ['required', Rule::in(['yes', 'no'])],
            'accepts_video' => ['accepted'],
            'accepts_terms' => ['accepted'],
            'payment_method' => ['required', 'string', Rule::in(PaymentMethods::enabled()->keys()->all())],
            'proof' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxKb],
        ], [
            'name.required' => 'Indique ton nom et ton prénom.',
            'phone.required' => 'Indique ton numéro WhatsApp.',
            'email.required' => 'Indique ton adresse e-mail : tu y recevras la réponse.',
            'email.email' => 'Cette adresse e-mail ne semble pas valide.',
            'gender.required' => 'Choisis : Femme ou Homme.',
            'gender.in' => 'Choisis : Femme ou Homme.',
            'age.required' => 'Indique ton âge.',
            'age.integer' => 'Écris ton âge en chiffres.',
            'age.between' => 'La bourse est réservée aux personnes de 20 à 40 ans.',
            'city.required' => 'Indique ta ville et ton pays.',
            'level.required' => 'Choisis ton niveau en digital et en IA.',
            'level.in' => 'Choisis ton niveau en digital et en IA.',
            'tools.required' => 'Indique les outils numériques et d\'IA que tu utilises déjà (ou « aucun »).',
            'device.required' => 'Indique ton matériel.',
            'device.in' => 'Indique ton matériel.',
            'motivation.required' => 'Explique ton grand « pourquoi ».',
            'motivation.min' => 'Explique ton grand « pourquoi » en quelques phrases (30 caractères au moins).',
            'first_agent.required' => 'Décris le premier agent IA que tu aimerais créer.',
            'first_agent.min' => 'Décris le premier agent IA que tu aimerais créer (10 caractères au moins).',
            'available.required' => 'Réponds : peux-tu suivre la formation pendant un mois ?',
            'available.in' => 'Réponds : peux-tu suivre la formation pendant un mois ?',
            'accepts_video.accepted' => 'Coche cette case pour candidater.',
            'accepts_terms.accepted' => 'Coche cette case après avoir lu les conditions.',
            'payment_method.required' => 'Choisis le moyen de paiement que tu as utilisé.',
            'payment_method.in' => 'Choisis le moyen de paiement que tu as utilisé.',
            'proof.required' => 'Ajoute la capture d\'écran de ton paiement.',
            'proof.uploaded' => 'La capture n\'a pas pu être envoyée. Réessaie avec une image plus légère.',
            'proof.image' => 'La preuve doit être une image (capture d\'écran).',
            'proof.mimes' => 'La capture doit être au format JPG, PNG ou WebP.',
            'proof.max' => 'La capture ne doit pas dépasser '.intdiv($maxKb, 1024).' Mo.',
        ]);
    }
}
