<?php

namespace App\Support;

class Testimonials
{
    /**
     * Témoignages de config/site.php prêts à afficher. Les entrées incomplètes
     * (texte vide, image introuvable, lien YouTube invalide) sont ignorées.
     *
     * @return list<array{type: string, name: ?string, formation: ?string, text: ?string, photo: ?string, image: ?string, youtube: ?string}>
     */
    public static function all(): array
    {
        $testimonials = [];

        foreach ((array) config('site.testimonials', []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $testimonial = [
                'type' => (string) ($item['type'] ?? 'text'),
                'name' => self::string($item['name'] ?? null),
                'formation' => self::string($item['formation'] ?? null),
                'text' => self::string($item['text'] ?? null),
                'photo' => self::image($item['photo'] ?? null),
                'image' => self::image($item['image'] ?? null),
                'youtube' => YouTube::id($item['youtube'] ?? null),
            ];

            $isValid = match ($testimonial['type']) {
                'text' => $testimonial['text'] !== null && $testimonial['name'] !== null,
                'screenshot' => $testimonial['image'] !== null,
                'video' => $testimonial['youtube'] !== null,
                default => false,
            };

            if ($isValid) {
                $testimonials[] = $testimonial;
            }
        }

        return $testimonials;
    }

    private static function string(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Nom d'un fichier présent dans public/images/temoignages/, sinon null.
     */
    private static function image(mixed $file): ?string
    {
        $file = basename(trim((string) $file));

        return $file !== '' && is_file(public_path('images/temoignages/'.$file)) ? $file : null;
    }
}
