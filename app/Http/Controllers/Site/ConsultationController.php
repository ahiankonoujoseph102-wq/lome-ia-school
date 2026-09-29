<?php

namespace App\Http\Controllers\Site;

use App\Consultations\ConsultationBooker;
use App\Consultations\ConsultationCalendar;
use App\Consultations\SlotFinder;
use App\Enums\ConsultationRequesterType;
use App\Http\Controllers\Controller;
use App\LiveSessions\SessionTime;
use App\Models\Consultation;
use App\Models\ConsultationSettings;
use App\Models\Formation;
use App\Models\User;
use App\Rules\WhatsAppNumber;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Consultation gratuite : réservation sans compte, puis page de gestion par lien sécurisé
 * (voir, déplacer, annuler, ajouter à l'agenda). Le lien Google Meet n'apparaît que sur cette page de gestion.
 */
class ConsultationController extends Controller
{
    public function show(Request $request): View
    {
        /** @var User|null $user */
        $user = $request->user();

        return view('site.consultation', [
            'days' => self::daysForView((new SlotFinder)->available()),
            'formations' => Formation::query()->published()->orderBy('title')->get(['id', 'title']),
            'user' => $user,
            // Depuis la page Entreprises (?profil=entreprise) : « Une entreprise » présélectionné, modifiable.
            'requesterType' => ConsultationRequesterType::fromQuery(is_string($request->query('profil')) ? $request->query('profil') : null),
        ]);
    }

    public function store(Request $request, ConsultationBooker $booker): RedirectResponse
    {
        // Champ piège invisible : seuls les robots le remplissent. Réponse neutre, rien n'est enregistré.
        if (filled($request->input('website'))) {
            return to_route('consultation')->with('status', 'Merci, votre demande a bien été reçue.');
        }

        $input = $request->all();
        $input['phone'] = PhoneNumber::normalize(is_string($input['phone'] ?? null) ? $input['phone'] : '', is_string($input['phone_country'] ?? null) ? $input['phone_country'] : null) ?? ($input['phone'] ?? '');

        $validated = validator($input, [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', new WhatsAppNumber],
            'email' => ['required', 'string', 'email', 'max:255'],
            'requester_type' => ['required', Rule::enum(ConsultationRequesterType::class)],
            'organization_name' => [Rule::requiredIf(fn (): bool => ConsultationRequesterType::tryFrom((string) ($input['requester_type'] ?? ''))?->isOrganization() ?? false), 'nullable', 'string', 'max:150'],
            'topic' => ['required', 'string', 'min:5', 'max:600'],
            'formation_id' => ['nullable', 'integer', Rule::exists('formations', 'id')->where('is_active', true)],
            'starts_at' => ['required', 'date_format:Y-m-d H:i'],
        ], [
            'name.required' => 'Indiquez votre nom.',
            'email.required' => 'Indiquez votre adresse e-mail : la confirmation et le lien de l\'appel vidéo y sont envoyés.',
            'requester_type.required' => 'Indiquez si vous êtes un particulier, un centre de formation ou une entreprise.',
            'requester_type.enum' => 'Indiquez si vous êtes un particulier, un centre de formation ou une entreprise.',
            'organization_name.required' => 'Indiquez le nom de votre centre ou de votre entreprise.',
            'topic.required' => 'Dites-nous en quelques mots ce dont vous voulez parler.',
            'topic.min' => 'Dites-nous en quelques mots ce dont vous voulez parler.',
            'starts_at.required' => 'Choisissez un jour et une heure.',
            'starts_at.date_format' => 'Choisissez un jour et une heure.',
        ])->validate();

        $type = ConsultationRequesterType::from((string) $validated['requester_type']);

        [$consultation, $token] = $booker->book([
            'name' => trim((string) $validated['name']),
            'phone' => (string) $validated['phone'],
            'email' => strtolower(trim((string) $validated['email'])),
            'requester_type' => $type,
            // Particulier : aucun nom de structure enregistré, même si le champ masqué avait été rempli.
            'organization_name' => $type->isOrganization() ? trim((string) $validated['organization_name']) : null,
            'topic' => trim((string) $validated['topic']),
            'formation_id' => isset($validated['formation_id']) ? (int) $validated['formation_id'] : null,
        ], self::parseSlot((string) $validated['starts_at']));

        return to_route('consultation.manage', $token)->with('booked', true);
    }

    public function manage(string $token): View
    {
        $consultation = $this->findOrFail($token);
        $consultation->loadMissing('formation');

        return view('site.consultation-manage', [
            'consultation' => $consultation,
            'token' => $token,
            'meetUrl' => ConsultationSettings::current()->meet_url,
            'days' => $consultation->canBeChangedByVisitor() ? self::daysForView((new SlotFinder)->available($consultation->id)) : [],
        ]);
    }

    public function move(Request $request, string $token, ConsultationBooker $booker): RedirectResponse
    {
        $consultation = $this->findOrFail($token);
        $validated = $request->validate(['starts_at' => ['required', 'date_format:Y-m-d H:i']], ['starts_at.*' => 'Choisissez un jour et une heure.']);

        $booker->move($consultation, self::parseSlot((string) $validated['starts_at']));

        return to_route('consultation.manage', $token)->with('status', 'Votre consultation est déplacée. Un e-mail de confirmation vous a été envoyé.');
    }

    public function cancel(string $token, ConsultationBooker $booker): RedirectResponse
    {
        $booker->cancel($this->findOrFail($token), 'visitor');

        return to_route('consultation.manage', $token)->with('status', 'Votre consultation est annulée. Le créneau est de nouveau disponible.');
    }

    public function calendar(string $token): Response
    {
        $consultation = $this->findOrFail($token);

        return response(ConsultationCalendar::for($consultation, ConsultationSettings::current()->meet_url), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="consultation-lome-ia-school.ics"',
        ]);
    }

    /**
     * Rendez-vous d'un élève connecté (rattaché à son compte) : ouvre sa page de gestion.
     */
    public function student(Consultation $consultation): RedirectResponse
    {
        Gate::authorize('view', $consultation);

        return redirect()->to($consultation->manageUrl());
    }

    /**
     * Jours et créneaux pour le sélecteur (Alpine) : heure de Lomé et équivalent au Bénin.
     *
     * @param  array<string, list<CarbonImmutable>>  $available
     * @return list<array{date: string, weekday: string, day: string, month: string, title: string, slots: list<array{value: string, label: string, neighbours: string}>}>
     */
    public static function daysForView(array $available): array
    {
        $days = [];

        foreach ($available as $date => $slots) {
            $day = CarbonImmutable::parse($date);
            $days[] = [
                'date' => $date,
                'weekday' => Str::ucfirst(rtrim($day->translatedFormat('D'), '.')),
                'day' => $day->format('j'),
                'month' => rtrim($day->translatedFormat('M'), '.'),
                'title' => Str::ucfirst($day->translatedFormat('l j F')),
                'slots' => array_map(fn (CarbonImmutable $slot): array => [
                    'value' => $slot->format('Y-m-d H:i'),
                    'label' => SessionTime::hour($slot),
                    'neighbours' => SessionTime::neighboursAt($slot),
                ], $slots),
            ];
        }

        return $days;
    }

    private static function parseSlot(string $value): CarbonImmutable
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d H:i', $value, (string) config('app.timezone'));

        if (! $start instanceof CarbonImmutable) {
            throw ValidationException::withMessages(['starts_at' => 'Choisissez un jour et une heure.']);
        }

        return $start->setSecond(0);
    }

    private function findOrFail(string $token): Consultation
    {
        return Consultation::findByToken($token) ?? abort(404);
    }
}
