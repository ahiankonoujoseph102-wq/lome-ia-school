<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un message WhatsApp : reçu d'un client, réponse de l'agent IA, ou réponse écrite par l'équipe dans l'admin.
 *
 * @property int $id
 * @property int $whatsapp_contact_id
 * @property string $sender
 * @property string $type
 * @property string|null $content
 * @property string|null $wa_message_id
 * @property string|null $status
 * @property string|null $error
 * @property bool $unanswered
 * @property int|null $user_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read WhatsAppContact $contact
 * @property-read User|null $user
 */
class WhatsAppMessage extends Model
{
    public const string CLIENT = 'client';

    public const string AGENT = 'agent';

    public const string TEAM = 'team';

    /**
     * Statuts d'envoi donnés par Meta, du moins avancé au plus avancé (un statut ne recule jamais).
     *
     * @var list<string>
     */
    public const array STATUSES = ['sent', 'delivered', 'read'];

    protected $table = 'whatsapp_messages';

    protected $fillable = ['whatsapp_contact_id', 'sender', 'type', 'content', 'wa_message_id', 'status', 'error', 'unanswered', 'user_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['unanswered' => 'boolean'];
    }

    /**
     * @return BelongsTo<WhatsAppContact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(WhatsAppContact::class, 'whatsapp_contact_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function senderLabel(): string
    {
        return match ($this->sender) {
            self::CLIENT => 'Client',
            self::AGENT => 'Agent IA',
            default => $this->user !== null ? $this->user->name.' (équipe)' : 'Équipe',
        };
    }

    /**
     * Texte affiché dans l'admin (un média reçu est décrit, son contenu n'est pas téléchargé).
     */
    public function displayText(): string
    {
        if ($this->type === 'text' || ($this->content !== null && $this->content !== '')) {
            return (string) $this->content;
        }

        return match ($this->type) {
            'image' => '[Image reçue]',
            'audio' => '[Note vocale reçue]',
            'video' => '[Vidéo reçue]',
            'document' => '[Fichier reçu]',
            'sticker' => '[Autocollant reçu]',
            'location' => '[Position reçue]',
            'contacts' => '[Contact reçu]',
            default => '[Message non pris en charge]',
        };
    }

    public function statusLabel(): ?string
    {
        return match ($this->status) {
            'sent' => 'Envoyé',
            'delivered' => 'Distribué',
            'read' => 'Lu',
            'failed' => 'Échec de l\'envoi',
            default => null,
        };
    }
}
