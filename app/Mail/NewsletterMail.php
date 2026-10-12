<?php

namespace App\Mail;

use App\Models\Newsletter;
use App\Models\User;
use App\Newsletters\BulkEmailSubscription;
use App\Support\AppUrl;
use App\Support\Brand;
use App\Support\PhoneNumber;
use App\Support\WhatsApp;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * E-mail d'un envoi groupé de l'admin (« Envoi d'e-mails »), envoyé par App\Jobs\SendNewsletterEmail.
 * Version mise en forme et version texte, lien de désabonnement en bas et en-têtes « List-Unsubscribe »
 * (désabonnement en un clic reconnu par Gmail). Aucune vidéo jointe : un lien vers la vidéo.
 */
class NewsletterMail extends Mailable
{
    public string $unsubscribeUrl;

    public function __construct(public Newsletter $newsletter, public User $recipient, public bool $test = false)
    {
        $this->unsubscribeUrl = BulkEmailSubscription::unsubscribeUrl($recipient, $test ? null : $newsletter);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->test ? '[Test] ' : '').$this->newsletter->subject);
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter',
            text: 'emails.newsletter-text',
            with: $this->details(),
        );
    }

    /**
     * Tout ce que les deux versions de l'e-mail affichent.
     *
     * @return array<string, mixed>
     */
    public function details(): array
    {
        $logo = Brand::logoUrl();
        $number = (string) config('site.whatsapp.number');

        return [
            'firstName' => $this->recipient->first_name,
            'paragraphs' => self::paragraphs($this->newsletter->body),
            'imageUrl' => $this->newsletter->imageUrl(),
            'video' => $this->newsletter->video(),
            'button' => $this->newsletter->button(),
            'logoUrl' => $logo !== null ? AppUrl::to($logo) : null,
            'siteUrl' => rtrim((string) config('app.url'), '/'),
            'siteHost' => (string) preg_replace('#^https?://#', '', rtrim((string) config('app.url'), '/')),
            'address' => (string) config('site.contact.address'),
            'whatsappNumber' => $number !== '' ? PhoneNumber::format('+'.$number) : null,
            'whatsappUrl' => $number !== '' ? WhatsApp::url() : null,
            'unsubscribeUrl' => $this->unsubscribeUrl,
        ];
    }

    /**
     * Texte de l'admin découpé en paragraphes (ligne vide = nouveau paragraphe, retour à la ligne gardé).
     *
     * @return list<string>
     */
    public static function paragraphs(string $body): array
    {
        $body = str_replace(["\r\n", "\r"], "\n", trim($body));

        return array_values(array_filter(
            array_map('trim', preg_split('/\n\s*\n/', $body) ?: []),
            fn (string $paragraph): bool => $paragraph !== '',
        ));
    }
}
