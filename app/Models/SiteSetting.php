<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un réglage du site modifié depuis l'admin (voir App\Settings\SiteSettings).
 *
 * @property int $id
 * @property string $key Chemin de configuration remplacé (ex. « site.contact »).
 * @property mixed $value
 */
class SiteSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
