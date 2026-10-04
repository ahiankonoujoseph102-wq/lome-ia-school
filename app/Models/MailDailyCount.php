<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Nombre d'e-mails envoyés par le site sur une journée de Lomé (tous les e-mails : codes, paiements,
 * groupes…), pour respecter le quota quotidien du fournisseur d'e-mails (App\Mail\MailQuota).
 *
 * @property int $id
 * @property Carbon $day
 * @property int $sent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MailDailyCount extends Model
{
    protected $fillable = ['day', 'sent'];

    protected function casts(): array
    {
        return ['day' => 'date', 'sent' => 'integer'];
    }
}
