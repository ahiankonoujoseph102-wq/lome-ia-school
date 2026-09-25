<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Réglages de la consultation gratuite (une seule ligne), modifiables dans l'admin « Disponibilités ».
 * Le lien Google Meet fixe est enregistré ici, jamais dans un fichier, et n'apparaît sur aucune page publique.
 *
 * @property int $id
 * @property string|null $meet_url
 * @property int $duration_minutes
 * @property int $buffer_minutes Pause entre deux consultations.
 * @property int $min_notice_hours Délai minimum avant un rendez-vous.
 * @property int $max_days_ahead Réservation possible jusqu'à ce nombre de jours à l'avance.
 * @property int|null $max_per_day Nombre maximum de consultations par jour (vide : pas de limite).
 * @property int $manage_deadline_hours Déplacement ou annulation par le visiteur jusqu'à ce délai avant.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ConsultationSettings extends Model
{
    protected $table = 'consultation_settings';

    protected $fillable = ['meet_url', 'duration_minutes', 'buffer_minutes', 'min_notice_hours', 'max_days_ahead', 'max_per_day', 'manage_deadline_hours'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'buffer_minutes' => 'integer',
            'min_notice_hours' => 'integer',
            'max_days_ahead' => 'integer',
            'max_per_day' => 'integer',
            'manage_deadline_hours' => 'integer',
        ];
    }

    public static function current(): self
    {
        return self::query()->oldest('id')->first() ?? self::query()->create((array) config('consultations.defaults'));
    }
}
