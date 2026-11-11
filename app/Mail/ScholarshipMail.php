<?php

namespace App\Mail;

use App\Models\ScholarshipApplication;
use App\Scholarships\ScholarshipCampaign;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

/**
 * E-mails de la bourse « Créer des agents IA », envoyés juste après la réponse (jamais par la file d'attente : le
 * lien d'accès n'est jamais écrit dans la table « jobs ») : candidature reçue, retenue (lien pour choisir son mot de
 * passe), refusée avec remboursement, refusée faute de paiement.
 */
class ScholarshipMail extends Mailable
{
    public const string RECEIVED = 'received';

    public const string VALIDATED = 'validated';

    public const string REFUSED_REFUND = 'refused_refund';

    public const string REFUSED_UNPAID = 'refused_unpaid';

    /** Durée de validité du lien d'accès envoyé à une personne retenue. */
    public const int ACCESS_LINK_DAYS = 7;

    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(public ScholarshipApplication $application, public string $kind, public array $extra = []) {}

    public function envelope(): Envelope
    {
        $title = '« '.(ScholarshipCampaign::formation()->title ?? 'Créer des agents IA').' »';

        return new Envelope(subject: match ($this->kind) {
            self::VALIDATED => 'Tu es retenu(e) pour la bourse '.$title,
            self::REFUSED_REFUND, self::REFUSED_UNPAID => 'Ta candidature à la bourse '.$title,
            default => 'Candidature reçue : bourse '.$title,
        });
    }

    public function content(): Content
    {
        return new Content(
            view: match ($this->kind) {
                self::VALIDATED => 'emails.scholarship.validated',
                self::REFUSED_REFUND => 'emails.scholarship.refused-refund',
                self::REFUSED_UNPAID => 'emails.scholarship.refused-unpaid',
                default => 'emails.scholarship.received',
            },
            with: [
                'formationTitle' => ScholarshipCampaign::formation()->title ?? 'Créer des agents IA',
                'accessUrl' => $this->kind === self::VALIDATED
                    ? URL::temporarySignedRoute('scholarship.access', now()->addDays(self::ACCESS_LINK_DAYS), ['application' => $this->application->id])
                    : null,
                'newAccount' => (bool) ($this->extra['newAccount'] ?? false),
            ],
        );
    }
}
