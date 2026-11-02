<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Demande de devis envoyée depuis la page « Entreprises et réalisations sur mesure » (formulaire #devis).
 * Envoyée aussi par e-mail à l'admin ; « À traiter » / « Traitée » dans l'admin ; effacée après 12 mois.
 *
 * @property int $id
 * @property string $name
 * @property string|null $organization
 * @property string $email
 * @property string|null $phone
 * @property string $service
 * @property string $message
 * @property string $status
 * @property Carbon|null $handled_at
 * @property int|null $handled_by
 * @property Carbon|null $created_at
 * @property-read User|null $handler
 */
class QuoteRequest extends Model
{
    use Prunable;

    public const string PENDING = 'pending';

    public const string DONE = 'done';

    public const int RETENTION_MONTHS = 12;

    protected $fillable = ['name', 'organization', 'email', 'phone', 'service', 'message'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subMonths(self::RETENTION_MONTHS));
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function serviceLabel(): string
    {
        return (string) (config('site.custom_work.services')[$this->service] ?? $this->service);
    }

    /**
     * Lien wa.me vers le numéro laissé par la personne, ou null.
     */
    public function whatsappUrl(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->phone) ?? '';

        return strlen($digits) >= 8 ? 'https://wa.me/'.$digits : null;
    }

    public function phoneLabel(): ?string
    {
        return $this->phone ? PhoneNumber::format($this->phone) : null;
    }
}
