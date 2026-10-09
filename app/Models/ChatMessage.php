<?php

namespace App\Models;

use App\Enums\ChatMessageType;
use App\Enums\ChatReaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Message d'un groupe de discussion. Les fichiers sont sur le disque privé « chat_media »
 * et ne sont servis qu'aux membres du groupe (App\Http\Controllers\Chat\AttachmentController).
 *
 * @property int $id
 * @property int $chat_group_id
 * @property int $user_id
 * @property ChatMessageType $type
 * @property string|null $body
 * @property int|null $reply_to_id
 * @property string|null $attachment_path
 * @property string|null $attachment_name
 * @property string|null $attachment_mime
 * @property int|null $attachment_size Taille en octets.
 * @property int|null $width
 * @property int|null $height
 * @property int|null $duration_seconds
 * @property Carbon|null $attachment_expired_at Note vocale ou vidéo supprimée après la durée de conservation.
 * @property Carbon|null $pinned_at
 * @property int|null $pinned_by
 * @property Carbon|null $deleted_at
 * @property int|null $deleted_by
 * @property Carbon $created_at
 * @property Carbon|null $updated_at
 * @property-read ChatGroup $group
 * @property-read User $author
 * @property-read ChatMessage|null $replyTo
 * @property-read Collection<int, ChatMessageReport> $reports
 * @property-read Collection<int, ChatMessageReaction> $reactions
 * @property-read ChatMessageLocation|null $location
 */
class ChatMessage extends Model
{
    public const string DISK = 'chat_media';

    protected $fillable = [
        'chat_group_id',
        'user_id',
        'type',
        'body',
        'reply_to_id',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
        'width',
        'height',
        'duration_seconds',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ChatMessageType::class,
            'attachment_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration_seconds' => 'integer',
            'attachment_expired_at' => 'datetime',
            'pinned_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ChatGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ChatGroup::class, 'chat_group_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<ChatMessage, $this>
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    /**
     * @return HasMany<ChatMessageReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ChatMessageReport::class);
    }

    /**
     * @return HasMany<ChatMessageReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(ChatMessageReaction::class);
    }

    /**
     * Lieu partagé (message de type « location » seulement).
     *
     * @return HasOne<ChatMessageLocation, $this>
     */
    public function location(): HasOne
    {
        return $this->hasOne(ChatMessageLocation::class);
    }

    /**
     * Réactions sous le message, dans l'ordre des six emojis : emoji => nombre (emojis sans réaction absents).
     *
     * @return array<string, int>
     */
    public function reactionCounts(): array
    {
        $counts = $this->reactions->countBy(fn (ChatMessageReaction $reaction): string => $reaction->emoji->value);
        $ordered = [];

        foreach (ChatReaction::cases() as $reaction) {
            if (($counts[$reaction->value] ?? 0) > 0) {
                $ordered[$reaction->value] = (int) $counts[$reaction->value];
            }
        }

        return $ordered;
    }

    /**
     * Réaction de cette personne à ce message (null si aucune).
     */
    public function reactionOf(User $user): ?ChatReaction
    {
        return $this->reactions->firstWhere('user_id', $user->id)?->emoji;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    public function isPinned(): bool
    {
        return $this->pinned_at !== null && ! $this->isDeleted();
    }

    /**
     * Fichier supprimé automatiquement après la durée de conservation (le message reste).
     */
    public function isExpired(): bool
    {
        return $this->attachment_expired_at !== null && ! $this->isDeleted();
    }

    public function hasAttachment(): bool
    {
        return $this->attachment_path !== null && ! $this->isDeleted();
    }

    /**
     * Aperçu d'une ligne : texte raccourci, ou type de pièce jointe.
     */
    public function preview(int $limit = 90): string
    {
        if ($this->isDeleted()) {
            return 'Message supprimé';
        }

        $text = Str::squish((string) $this->body);

        if ($this->type === ChatMessageType::Text) {
            return Str::limit($text, $limit);
        }

        $label = $this->type === ChatMessageType::File && $this->attachment_name
            ? '📄 '.$this->attachment_name
            : $this->type->preview();

        if ($this->isExpired()) {
            $label .= ' (fichier expiré)';
        }

        return Str::limit($text !== '' ? $label.' · '.$text : $label, $limit);
    }

    public function attachmentUrl(): ?string
    {
        return $this->hasAttachment()
            ? route('chat.attachments.show', ['formation' => $this->group->formation->slug, 'message' => $this->id])
            : null;
    }

    public function downloadUrl(): ?string
    {
        return $this->hasAttachment()
            ? route('chat.attachments.show', ['formation' => $this->group->formation->slug, 'message' => $this->id, 'telecharger' => 1])
            : null;
    }

    /**
     * Taille lisible : « 850 Ko », « 12,4 Mo ».
     */
    public function formattedSize(): string
    {
        $bytes = (int) $this->attachment_size;

        return $bytes >= 1024 * 1024
            ? number_format($bytes / 1024 / 1024, 1, ',', ' ').' Mo'
            : max(1, (int) round($bytes / 1024)).' Ko';
    }

    public function formattedDuration(): string
    {
        $seconds = (int) $this->duration_seconds;

        return intdiv($seconds, 60).':'.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
    }

    public function extension(): string
    {
        return Str::upper(pathinfo((string) $this->attachment_name, PATHINFO_EXTENSION)) ?: 'FICHIER';
    }

    /**
     * Supprime le contenu (texte et fichier) : il ne reste que « Ce message a été supprimé ».
     */
    public function wipe(User $by): void
    {
        if ($this->attachment_path) {
            Storage::disk(self::DISK)->delete($this->attachment_path);
        }

        $this->forceFill([
            'body' => null,
            'attachment_path' => null,
            'attachment_name' => null,
            'attachment_mime' => null,
            'attachment_size' => null,
            'width' => null,
            'height' => null,
            'duration_seconds' => null,
            'attachment_expired_at' => null,
            'pinned_at' => null,
            'pinned_by' => null,
            'deleted_at' => now(),
            'deleted_by' => $by->id,
        ])->save();

        // Lieu partagé : nom, adresse et lien effacés aussi (seulement pour ce type : rien de plus pour les autres messages).
        if ($this->type === ChatMessageType::Location) {
            $this->location()->delete();
            $this->unsetRelation('location');
        }

        // Message supprimé : ses réactions disparaissent aussi.
        $this->reactions()->delete();
        $this->unsetRelation('reactions');
    }

    protected static function booted(): void
    {
        // Suppression définitive (compte supprimé, formation supprimée) : le fichier part aussi.
        static::deleting(function (self $message): void {
            if ($message->attachment_path) {
                Storage::disk(self::DISK)->delete($message->attachment_path);
            }
        });
    }
}
