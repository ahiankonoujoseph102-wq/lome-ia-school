<?php

namespace App\Support;

use App\Models\Testimonial;

class Testimonials
{
    /**
     * Témoignages actifs (gérés dans l'admin), dans l'ordre choisi, prêts à afficher.
     * Les entrées incomplètes (texte vide, image introuvable, lien YouTube invalide) sont ignorées.
     *
     * @return list<array{type: string, name: ?string, formation: ?string, text: ?string, photo: ?string, image: ?string, youtube: ?string, video_format?: string}>
     */
    public static function all(): array
    {
        $testimonials = [];

        foreach (Testimonial::query()->where('is_active', true)->ordered()->get() as $item) {
            $testimonial = [
                'type' => $item->type,
                'name' => self::string($item->name),
                'formation' => self::string($item->formation),
                'text' => self::string($item->text),
                'photo' => self::image($item->photo_path),
                'image' => self::image($item->image_path),
                'youtube' => YouTube::id($item->youtube_url),
                'video_format' => self::videoFormat($item->youtube_url, $item->video_format),
            ];

            if (self::isValid($testimonial)) {
                $testimonials[] = $testimonial;
            }
        }

        return $testimonials;
    }

    /**
     * @param  array{type: string, name: ?string, formation: ?string, text: ?string, photo: ?string, image: ?string, youtube: ?string, video_format?: string}  $testimonial
     */
    public static function isValid(array $testimonial): bool
    {
        return match ($testimonial['type']) {
            'text' => $testimonial['text'] !== null && $testimonial['name'] !== null,
            'screenshot' => $testimonial['image'] !== null,
            'video' => $testimonial['youtube'] !== null,
            default => false,
        };
    }

    /**
     * Format d'un témoignage vidéo : celui choisi dans l'admin, sinon « vertical » pour un lien Shorts, sinon
     * « auto » (le navigateur regarde la miniature au format d'origine de YouTube : verticale = vidéo verticale).
     */
    public static function videoFormat(?string $url, ?string $choice): string
    {
        if (in_array($choice, YouTube::FORMATS, true)) {
            return $choice;
        }

        return YouTube::isShorts($url) ? 'vertical' : 'auto';
    }

    private static function string(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Adresse d'une image envoyée depuis l'admin (disque « public »), si le fichier existe.
     */
    private static function image(?string $path): ?string
    {
        return FileStorage::publicUrl($path);
    }
}
