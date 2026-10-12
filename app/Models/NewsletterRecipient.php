<?php

namespace App\Models;

use App\Enums\NewsletterRecipientStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un destinataire d'un envoi groupé : une seule ligne par personne et par envoi (index unique), donc un seul
 * e-mail. L'adresse utilisée est celle du compte au moment de l'envoi.
 *
 * @property int $id
 * @property int $newsletter_id
 * @property int|null $user_id
 * @property NewsletterRecipientStatus $status
 * @property int $attempts
 * @property Carbon|null $retry_at
 * @property Carbon|null $queued_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $unsubscribed_at
 * @property string|null $last_error
 * @property Carbon $created_at
 * @property Carbon|null $updated_at
 * @property-read Newsletter $newsletter
 * @property-read User|null $user
 */
class NewsletterRecipient extends Model
{
    protected $fillable = ['newsletter_id', 'user_id', 'status'];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => NewsletterRecipientStatus::class,
            'attempts' => 'integer',
            'retry_at' => 'datetime',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Newsletter, $this>
     */
    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(Newsletter::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
