<?php

namespace App\Mail;

use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Agent WhatsApp : un client attend l'équipe (il l'a demandé, question sans réponse, limite atteinte, panne de
 * Gemini). Envoyé tout de suite (jamais par la file d'attente) à l'adresse des alertes : nom du profil, numéro,
 * raison, derniers messages et lien vers la conversation dans l'admin.
 */
class WhatsAppHandoffMail extends Mailable
{
    /**
     * Messages repris dans l'e-mail.
     */
    public const int EXCERPT_MESSAGES = 8;

    public function __construct(public WhatsAppContact $contact) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'WhatsApp : '.$this->contact->displayName().' attend une réponse de l\'équipe');
    }

    public function content(): Content
    {
        $messages = WhatsAppMessage::query()
            ->where('whatsapp_contact_id', $this->contact->id)
            ->latest('id')
            ->limit(self::EXCERPT_MESSAGES)
            ->get()
            ->reverse();

        $excerpt = $messages
            ->map(fn (WhatsAppMessage $message): string => $message->senderLabel().' : '.mb_strimwidth(trim((string) preg_replace('/\s+/u', ' ', $message->displayText())), 0, 400, '…'))
            ->implode("\n");

        return new Content(view: 'emails.whatsapp.handoff', text: 'emails.whatsapp.handoff-text', with: ['excerpt' => $excerpt]);
    }
}
