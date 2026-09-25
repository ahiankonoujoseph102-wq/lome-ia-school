<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Jour ou plage bloqués (congés, empêchements) : aucune consultation possible.
 *
 * @property int $id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string|null $reason Visible par l'admin uniquement.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ConsultationBlock extends Model
{
    protected $fillable = ['starts_at', 'ends_at', 'reason'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }
}
