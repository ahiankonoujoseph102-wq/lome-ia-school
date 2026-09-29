<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Code de confirmation de l'adresse e-mail en cours (voir App\Accounts\EmailConfirmation).
 *
 * @property int $id
 * @property int $user_id
 * @property string $email Adresse à laquelle le code a été envoyé.
 * @property string $code_hash Empreinte du code à 6 chiffres (jamais le code en clair).
 * @property string $token_hash Empreinte SHA-256 du jeton du bouton de l'e-mail.
 * @property int $attempts Codes erronés saisis.
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
class EmailVerification extends Model
{
    protected $fillable = ['user_id', 'email', 'code_hash', 'token_hash', 'attempts', 'expires_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
