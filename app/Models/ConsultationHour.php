<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Plage horaire de consultation pour un jour de la semaine (heure de Lomé).
 *
 * @property int $id
 * @property int $weekday 1 = lundi … 7 = dimanche.
 * @property string $starts_at « 08:00 ».
 * @property string $ends_at « 20:00 ».
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ConsultationHour extends Model
{
    public const WEEKDAYS = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];

    protected $fillable = ['weekday', 'starts_at', 'ends_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }

    public function label(): string
    {
        $format = fn (string $time): string => (int) substr($time, 0, 2).' h '.substr($time, 3, 2);

        return $format($this->starts_at).' – '.$format($this->ends_at);
    }
}
