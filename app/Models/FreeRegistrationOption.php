<?php

namespace App\Models;

use App\Enums\FreeOptionMode;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Option d'inscription d'une formation gratuite (ex. « Présentiel journée », 20 places).
 *
 * @property int $id
 * @property int $formation_id
 * @property string $label
 * @property string|null $schedule Horaires affichés (ex. « 9 h à 16 h (pause de 13 h à 14 h) »).
 * @property FreeOptionMode $mode
 * @property int $capacity Nombre de places.
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $taken_count Places prises (chargé par withCount, voir withTakenCount()).
 * @property-read Formation $formation
 * @property-read EloquentCollection<int, FreeRegistration> $registrations
 */
class FreeRegistrationOption extends Model
{
    protected $fillable = [
        'formation_id',
        'label',
        'schedule',
        'mode',
        'capacity',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => FreeOptionMode::class,
            'capacity' => 'integer',
            'position' => 'integer',
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
     * @return HasMany<FreeRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(FreeRegistration::class);
    }

    /**
     * Ajoute le nombre de places prises (inscriptions non annulées) dans « taken_count ».
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withTakenCount(Builder $query): void
    {
        $query->withCount(['registrations as taken_count' => fn ($registrations) => $registrations->whereNull('cancelled_at')]);
    }

    public function seatsTaken(): int
    {
        return $this->taken_count ?? $this->registrations()->whereNull('cancelled_at')->count();
    }

    public function seatsLeft(): int
    {
        return max(0, $this->capacity - $this->seatsTaken());
    }

    public function isFull(): bool
    {
        return $this->seatsLeft() === 0;
    }

    public function isOnsite(): bool
    {
        return $this->mode === FreeOptionMode::Onsite;
    }

    /**
     * « 12 places restantes », « 1 place restante ».
     */
    public function seatsLeftLabel(): string
    {
        $left = $this->seatsLeft();

        return $left.' '.($left > 1 ? 'places restantes' : 'place restante');
    }
}
