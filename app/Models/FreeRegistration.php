<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Inscription d'une personne à une formation gratuite, avec l'option choisie (une seule par formation).
 * Une inscription annulée garde sa ligne (cancelled_at) et libère sa place ; une nouvelle inscription la réutilise.
 *
 * @property int $id
 * @property int $formation_id
 * @property int $user_id
 * @property int|null $free_registration_option_id
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Formation $formation
 * @property-read User $user
 * @property-read FreeRegistrationOption|null $option
 */
class FreeRegistration extends Model
{
    protected $fillable = [
        'formation_id',
        'user_id',
        'free_registration_option_id',
        'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Formation, $this>
     */
    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<FreeRegistrationOption, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(FreeRegistrationOption::class, 'free_registration_option_id');
    }

    public function isActive(): bool
    {
        return $this->cancelled_at === null;
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('cancelled_at');
    }
}
