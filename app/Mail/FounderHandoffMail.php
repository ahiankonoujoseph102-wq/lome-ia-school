<?php

namespace App\Mail;

use App\Models\AssistantHandoff;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Question transmise par Ama à Coach Joseph (« Transmettre au fondateur »). Envoyée tout de suite (jamais par la
 * file d'attente) : prénom, moyen de réponse choisi, question, page d'origine et court extrait de la conversation.
 * Réponse par e-mail choisie : l'adresse du visiteur est mise en « Répondre à » (bouton « Répondre » de Gmail).
 * Réponse sur WhatsApp choisie : bouton « Répondre sur WhatsApp » (lien wa.me vers son numéro).
 */
class FounderHandoffMail extends Mailable
{
    public function __construct(public AssistantHandoff $handoff) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Question transmise par Ama : '.$this->handoff->first_name.' ('.$this->handoff->channelLabel().')',
            replyTo: $this->handoff->channel === AssistantHandoff::EMAIL && $this->handoff->email
                ? [new Address($this->handoff->email, $this->handoff->first_name)]
                : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.assistant.handoff', text: 'emails.assistant.handoff-text', with: ['whatsappUrl' => $this->handoff->whatsappReplyUrl()]);
    }
}
