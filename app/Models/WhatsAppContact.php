<?php

namespace App\Models;

use App\Support\PhoneNumber;
use App\WhatsApp\AgentSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un client qui a écrit au numéro WhatsApp de l'API (agent WhatsApp). Son numéro est nécessaire pour lui répondre ;
 * il n'est jamais envoyé à Gemini. Effacé avec ses messages 90 jours après le dernier message.
 *
 * @property int $id
 * @property string $wa_id
 * @property string|null $profile_name
 * @property CarbonImmutable|null $last_inbound_at
 * @property CarbonImmutable|null $last_message_at
 * @property int $messages_count
 * @property CarbonImmutable|null $needs_human_at
 * @property string|null $needs_human_reason
 * @property CarbonImmutable|null $agent_paused_until
 * @property bool $agent_stopped
 * @property int|null $last_answered_message_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, WhatsAppMessage> $messages
 */
class WhatsAppContact extends Model
{
    use Prunable;

    public const int RETENTION_DAYS = 90;

    /**
     * Durée pendant laquelle WhatsApp autorise une réponse libre après le dernier message du client.
     */
    public const int SERVICE_WINDOW_HOURS = 24;

    protected $table = 'whatsapp_contacts';

    protected $fillable = ['wa_id', 'profile_name', 'last_inbound_at', 'last_message_at', 'messages_count', 'needs_human_at', 'needs_human_reason', 'agent_paused_until', 'agent_stopped', 'last_answered_message_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_inbound_at' => 'datetime',
            'last_message_at' => 'datetime',
            'messages_count' => 'integer',
            'needs_human_at' => 'datetime',
            'agent_paused_until' => 'datetime',
            'agent_stopped' => 'boolean',
            'last_answered_message_id' => 'integer',
        ];
    }

    /**
     * @return HasMany<WhatsAppMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_contact_id')->orderBy('id');
    }

    /**
     * Numéro lisible (« +228 90 12 34 56 »).
     */
    public function phone(): string
    {
        return PhoneNumber::format('+'.$this->wa_id);
    }

    /**
     * Nom à afficher dans l'admin : nom du profil WhatsApp, sinon le numéro.
     */
    public function displayName(): string
    {
        $name = trim((string) $this->profile_name);

        return $name !== '' ? $name : $this->phone();
    }

    /**
     * L'agent peut-il répondre à ce client maintenant ? (pas arrêté par l'admin, pas en pause après l'équipe)
     */
    public function agentActive(): bool
    {
        return ! $this->agent_stopped && ($this->agent_paused_until === null || $this->agent_paused_until->isPast());
    }

    /**
     * Met l'agent en pause pour ce client (réponse de l'équipe, transmission à l'équipe).
     */
    public function pauseAgent(): void
    {
        $this->agent_paused_until = now()->addHours(AgentSettings::pauseHours());
    }

    /**
     * La réponse libre est-elle encore permise (moins de 24 h depuis le dernier message du client) ?
     */
    public function windowOpen(): bool
    {
        return $this->last_inbound_at !== null && $this->last_inbound_at->greaterThan(now()->subHours(self::SERVICE_WINDOW_HOURS));
    }

    /**
     * Lien wa.me pour répondre depuis l'application WhatsApp (double usage, ou autre numéro de l'école).
     */
    public function whatsappUrl(): string
    {
        return 'https://wa.me/'.$this->wa_id;
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where(fn (Builder $query) => $query
            ->where('last_message_at', '<', now()->subDays(self::RETENTION_DAYS))
            ->orWhere(fn (Builder $query) => $query->whereNull('last_message_at')->where('created_at', '<', now()->subDays(self::RETENTION_DAYS))));
    }
}
