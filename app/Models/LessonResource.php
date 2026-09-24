<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

/**
 * Fichier téléchargeable d'une leçon (PDF, image, document…), stocké sur le disque privé « lesson_resources ».
 *
 * @property int $id
 * @property int $lesson_id
 * @property string $name Nom du fichier affiché et proposé au téléchargement.
 * @property string|null $path Fichier envoyé sur le site (disque privé), ou null pour un lien.
 * @property string|null $url Lien Google Drive, ouvert via une redirection réservée aux élèves ayant accès.
 * @property string|null $mime_type
 * @property int $size Octets.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Lesson $lesson
 */
class LessonResource extends Model
{
    public const string DISK = 'lesson_resources';

    protected $fillable = ['lesson_id', 'name', 'path', 'url', 'mime_type', 'size'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleted(function (LessonResource $resource): void {
            if ($resource->path) {
                Storage::disk(self::DISK)->delete($resource->path);
            }
        });
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function isLink(): bool
    {
        return $this->url !== null && $this->url !== '';
    }

    public function formattedSize(): string
    {
        return (string) Number::fileSize($this->size, precision: 1, maxPrecision: 1);
    }

    public function extension(): string
    {
        if ($this->isLink()) {
            return 'DRIVE';
        }

        return mb_strtoupper(pathinfo($this->name, PATHINFO_EXTENSION) ?: 'fichier');
    }

    /**
     * Taille ou nature de la ressource, pour l'affichage.
     */
    public function details(): string
    {
        return $this->isLink() ? 'Lien Google Drive' : $this->extension().' · '.$this->formattedSize();
    }
}
