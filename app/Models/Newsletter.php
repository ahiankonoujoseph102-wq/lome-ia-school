<?php

namespace App\Models;

use App\Enums\NewsletterAudience;
use App\Enums\NewsletterRecipientStatus;
use App\Enums\NewsletterStatus;
use App\Newsletters\NewsletterLinks;
use App\Support\FileStorage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * E-mail groupé envoyé depuis l'admin (« Envoi d'e-mails ») : texte, image, lien vidéo et bouton facultatifs.
 * Les destinataires sont figés au lancement (table « newsletter_recipients »), puis l'envoi est étalé sur
 * plusieurs jours selon le plafond quotidien (App\Newsletters\NewsletterQuota).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $subject
 * @property string $body
 * @property string|null $image_path Fichier sur le disque « public » (dossier newsletters/).
 * @property string|null $video_url Lien YouTube ou page du site, déjà nettoyé.
 * @property string|null $button_label
 * @property string|null $button_url
 * @property NewsletterAudience $audience
 * @property NewsletterStatus $status
 * @property int $recipients_count
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $author
 * @property-read Collection<int, NewsletterRecipient> $recipients
 */
class Newsletter extends Model
{
    protected $fillable = ['user_id', 'subject', 'body', 'image_path', 'video_url', 'button_label', 'button_url', 'audience'];

    protected $attributes = [
        'status' => 'draft',
        'audience' => 'all',
    ];

    protected function casts(): array
    {
        return [
            'audience' => NewsletterAudience::class,
            'status' => NewsletterStatus::class,
            'recipients_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<NewsletterRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(NewsletterRecipient::class);
    }

    public function isDraft(): bool
    {
        return $this->status === NewsletterStatus::Draft;
    }

    public function imageUrl(): ?string
    {
        return FileStorage::publicUrl($this->image_path);
    }

    /**
     * Vidéo affichée dans l'e-mail (image cliquable et bouton « Regarder la vidéo »), ou null.
     *
     * @return array{url: string, thumbnail: string|null, youtube: bool}|null
     */
    public function video(): ?array
    {
        return NewsletterLinks::video($this->video_url);
    }

    /**
     * Bouton d'action, ou null s'il n'a pas de texte ou pas de lien.
     *
     * @return array{label: string, url: string}|null
     */
    public function button(): ?array
    {
        $url = NewsletterLinks::absolute($this->button_url);

        return filled($this->button_label) && $url !== null ? ['label' => (string) $this->button_label, 'url' => $url] : null;
    }

    /**
     * Compteurs par état des destinataires (une seule requête).
     *
     * @return array{sent: int, pending: int, failed: int, skipped: int, cancelled: int, unsubscribed: int}
     */
    public function stats(): array
    {
        $counts = $this->recipients()->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn (mixed $total): int => (int) $total);

        $pending = 0;

        foreach (NewsletterRecipientStatus::open() as $status) {
            $pending += $counts->get($status, 0);
        }

        return [
            'sent' => $counts->get(NewsletterRecipientStatus::Sent->value, 0),
            'pending' => $pending,
            'failed' => $counts->get(NewsletterRecipientStatus::Failed->value, 0),
            'skipped' => $counts->get(NewsletterRecipientStatus::Skipped->value, 0),
            'cancelled' => $counts->get(NewsletterRecipientStatus::Cancelled->value, 0),
            'unsubscribed' => $this->recipients()->whereNotNull('unsubscribed_at')->count(),
        ];
    }
}
