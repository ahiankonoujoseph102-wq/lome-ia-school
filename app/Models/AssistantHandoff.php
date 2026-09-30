<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Question transmise à Coach Joseph par Ama (« Transmettre au fondateur »), à la demande du visiteur.
 * Contient les coordonnées que le visiteur a choisi de donner pour recevoir la réponse (e-mail ou WhatsApp).
 * Effacée après 90 jours, comme les conversations d'Ama (App\Assistant\FounderHandoff).
 *
 * @property int $id
 * @property int|null $assistant_conversation_id
 * @property string $visitor_hash
 * @property string $first_name
 * @property string $channel
 * @property string|null $email
 * @property string|null $phone
 * @property string $question
 * @property string|null $page
 * @property string|null $formation
 * @property string|null $excerpt
 * @property string $source
 * @property string $status
 * @property CarbonImmutable|null $handled_at
 * @property int|null $handled_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read AssistantConversation|null $conversation
 * @property-read User|null $handler
 */
class AssistantHandoff extends Model
{
    use Prunable;

    public const string EMAIL = 'email';

    public const string WHATSAPP = 'whatsapp';

    /**
     * D'où vient la demande : carte proposée par Ama, fin d'un appel, formulaire de relève (Ama indisponible) ou
     * lien permanent « Poser ma question à Coach Joseph » de la fenêtre d'Ama.
     *
     * @var list<string>
     */
    public const array SOURCES = ['chat', 'call', 'form', 'link'];

    public const string PENDING = 'pending';

    public const string DONE = 'done';

    protected $fillable = [
        'assistant_conversation_id', 'visitor_hash', 'first_name', 'channel', 'email', 'phone', 'question',
        'page', 'formation', 'excerpt', 'source', 'status', 'handled_at', 'handled_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<AssistantConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'assistant_conversation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /**
     * Moyen de réponse choisi, pour l'affichage : « E-mail » ou « WhatsApp ».
     */
    public function channelLabel(): string
    {
        return $this->channel === self::EMAIL ? 'E-mail' : 'WhatsApp';
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            'chat' => 'Carte proposée par Ama',
            'call' => 'Fin d\'un appel avec Ama',
            'link' => 'Lien « Poser ma question à Coach Joseph »',
            default => 'Formulaire (Ama indisponible)',
        };
    }

    /**
     * Coordonnée choisie par le visiteur (adresse e-mail ou numéro WhatsApp).
     */
    public function contact(): string
    {
        return (string) ($this->channel === self::EMAIL ? $this->email : $this->phone);
    }

    /**
     * Lien wa.me vers le numéro du visiteur (réponse sur WhatsApp choisie), avec un début de réponse prérempli.
     */
    public function whatsappReplyUrl(): ?string
    {
        if ($this->channel !== self::WHATSAPP || ! $this->phone) {
            return null;
        }

        return 'https://wa.me/'.ltrim($this->phone, '+').'?text='.rawurlencode('Bonjour '.$this->first_name.', c\'est Coach Joseph de Lomé IA School. Vous avez posé une question à Ama sur notre site : ');
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(AssistantConversation::RETENTION_DAYS));
    }
}
