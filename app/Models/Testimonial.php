<?php

namespace App\Models;

use App\Services\ImageUploader;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Témoignage d'élève géré depuis l'admin (Réglages > Témoignages).
 *
 * Trois types : « text » (nom, formation, texte, photo facultative), « screenshot » (capture WhatsApp)
 * et « video » (lien YouTube). Tant qu'aucun témoignage n'est actif, la section est masquée partout.
 *
 * @property int $id
 * @property string $type
 * @property string|null $name
 * @property string|null $formation
 * @property string|null $text
 * @property string|null $photo_path Photo de l'élève (disque « public »).
 * @property string|null $image_path Capture WhatsApp (disque « public »).
 * @property string|null $youtube_url
 * @property bool $is_active
 * @property int $position
 */
class Testimonial extends Model
{
    /**
     * @var array<string, string>
     */
    public const array TYPES = [
        'text' => 'Texte (avec photo facultative)',
        'screenshot' => 'Capture WhatsApp',
        'video' => 'Vidéo YouTube',
    ];

    protected $fillable = ['type', 'name', 'formation', 'text', 'photo_path', 'image_path', 'youtube_url', 'video_format', 'is_active', 'position'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['is_active' => true, 'position' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'position' => 'integer'];
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function photoUrl(): ?string
    {
        return ImageUploader::url($this->photo_path);
    }

    public function imageUrl(): ?string
    {
        return ImageUploader::url($this->image_path);
    }
}
