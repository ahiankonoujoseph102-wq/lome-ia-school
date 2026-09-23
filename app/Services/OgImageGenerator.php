<?php

namespace App\Services;

use App\Models\Formation;
use GdImage;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Génère les images d'aperçu (1200 × 630) affichées lors d'un partage de lien
 * sur WhatsApp, Facebook ou LinkedIn. Les images sont mises en cache sur le disque
 * et régénérées automatiquement quand la formation change.
 */
class OgImageGenerator
{
    public const int WIDTH = 1200;

    public const int HEIGHT = 630;

    /**
     * À incrémenter pour régénérer toutes les images après un changement de design.
     */
    private const int VERSION = 2;

    private const int PADDING = 72;

    public function forFormation(Formation $formation): string
    {
        $key = sprintf(
            'formation-%d-%s',
            $formation->id,
            md5(implode('|', [self::VERSION, $formation->title, $formation->price, $formation->category->value, $formation->updated_at?->timestamp]))
        );

        return $this->cached($key, fn (): GdImage => $this->render(
            eyebrow: $formation->category->label(),
            title: $formation->title,
            badge: str_replace("\u{00A0}", ' ', $formation->formatted_price),
            footer: 'Accès immédiat · Paiement Mobile Money',
        ));
    }

    public function forSite(): string
    {
        $title = (string) config('site.hero.title');

        return $this->cached('site-'.md5(self::VERSION.'|'.$title), fn (): GdImage => $this->render(
            eyebrow: 'Formations en intelligence artificielle',
            title: $title,
            badge: 'En ligne · En direct · À Lomé',
            footer: 'Paiement Mobile Money',
        ));
    }

    /**
     * @param  callable(): GdImage  $render
     */
    private function cached(string $key, callable $render): string
    {
        $directory = storage_path('framework/cache/og');
        $path = $directory.'/'.$key.'.png';

        if (! File::exists($path)) {
            File::ensureDirectoryExists($directory);
            $image = $render();
            imagepng($image, $path, 6);
            imagedestroy($image);
        }

        return $path;
    }

    private function render(string $eyebrow, string $title, string $badge, string $footer): GdImage
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        if ($image === false) {
            throw new RuntimeException('Impossible de créer l\'image d\'aperçu.');
        }

        imagealphablending($image, true);
        imageantialias($image, true);

        $this->drawBackground($image);

        $bold = resource_path('fonts/InstrumentSans_700Bold.ttf');
        $medium = resource_path('fonts/InstrumentSans_500Medium.ttf');

        $white = $this->color($image, '#FFFFFF');
        $muted = $this->color($image, '#C4B5FD');
        $soft = $this->color($image, '#E0E7FF', 30);
        $gold = $this->color($image, '#FBBF24');
        $night = $this->color($image, '#0B1030');

        // Marque
        imagefilledrectangle($image, self::PADDING, 70, self::PADDING + 14, 104, $gold);
        imagettftext($image, 26, 0, self::PADDING + 30, 100, $white, $bold, 'Lomé IA School');

        // Catégorie
        imagettftext($image, 24, 0, self::PADDING, 190, $muted, $medium, mb_strtoupper($eyebrow));

        // Titre sur 3 lignes maximum, taille adaptée à la longueur
        [$size, $lines] = $this->fitTitle($title, $bold);
        $lineHeight = (int) round($size * 1.22);
        $y = 190 + 30 + $lineHeight;

        foreach ($lines as $line) {
            imagettftext($image, $size, 0, self::PADDING, $y, $white, $bold, $line);
            $y += $lineHeight;
        }

        // Badge doré (prix) et pied
        $badgeWidth = $this->textWidth(30, $bold, $badge);
        $badgeTop = self::HEIGHT - 150;
        $this->roundedRectangle($image, self::PADDING, $badgeTop, self::PADDING + $badgeWidth + 56, $badgeTop + 72, 36, $gold);
        imagettftext($image, 30, 0, self::PADDING + 28, $badgeTop + 49, $night, $bold, $badge);

        // Le texte du pied n'est affiché que s'il tient entièrement dans l'image.
        $footerX = self::PADDING + $badgeWidth + 88;

        if ($footerX + $this->textWidth(22, $medium, $footer) <= self::WIDTH - self::PADDING) {
            imagettftext($image, 22, 0, $footerX, $badgeTop + 46, $soft, $medium, $footer);
        }

        return $image;
    }

    /**
     * @return array{0: int, 1: list<string>}
     */
    private function fitTitle(string $title, string $font): array
    {
        $maxWidth = self::WIDTH - self::PADDING * 2;
        // Hauteur disponible entre la catégorie et le badge du bas.
        $maxHeight = 200;
        $lines = [];

        foreach ([62, 56, 50, 46, 42, 40] as $size) {
            $lines = $this->wrap($title, $font, $size, $maxWidth);

            if (count($lines) <= 3 && count($lines) * (int) round($size * 1.22) <= $maxHeight) {
                return [$size, $lines];
            }
        }

        $truncated = array_slice($lines, 0, 3);
        $last = (string) array_pop($truncated);
        $truncated[] = rtrim($last, ' ,.').'…';

        return [40, $truncated];
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, string $font, int $size, int $maxWidth): array
    {
        $lines = [];
        $current = '';

        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            if ($current !== '' && $this->textWidth($size, $font, $candidate) > $maxWidth) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private function drawBackground(GdImage $image): void
    {
        // Dégradé diagonal bleu nuit vers violet
        [$r1, $g1, $b1] = [11, 16, 48];
        [$r2, $g2, $b2] = [59, 28, 140];

        for ($x = 0; $x < self::WIDTH; $x += 2) {
            $t = $x / self::WIDTH;
            $t = $t * $t;
            $color = imagecolorallocate(
                $image,
                $this->channel($r1 + ($r2 - $r1) * $t),
                $this->channel($g1 + ($g2 - $g1) * $t),
                $this->channel($b1 + ($b2 - $b1) * $t),
            );
            imagefilledrectangle($image, $x, 0, $x + 1, self::HEIGHT, (int) $color);
        }

        // Halos lumineux
        for ($i = 0; $i < 12; $i++) {
            $halo = $this->color($image, '#8B5CF6', 118 - $i);
            imagefilledellipse($image, 1080, 90, 760 - $i * 50, 760 - $i * 50, $halo);
        }

        for ($i = 0; $i < 8; $i++) {
            $halo = $this->color($image, '#F59E0B', 124 - $i);
            imagefilledellipse($image, 60, 640, 520 - $i * 50, 520 - $i * 50, $halo);
        }

        // Cercles décoratifs
        $line = $this->color($image, '#FFFFFF', 112);
        imagesetthickness($image, 2);
        imageellipse($image, 1050, 470, 300, 300, $line);
        imageellipse($image, 1050, 470, 420, 420, $line);
        imagesetthickness($image, 1);
    }

    private function roundedRectangle(GdImage $image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
        imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    }

    /**
     * @param  int  $alpha  0 (opaque) à 127 (transparent)
     */
    private function color(GdImage $image, string $hex, int $alpha = 0): int
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x') ?? [0, 0, 0];

        return (int) imagecolorallocatealpha(
            $image,
            $this->channel((int) $r),
            $this->channel((int) $g),
            $this->channel((int) $b),
            max(0, min(127, $alpha)),
        );
    }

    /**
     * Composante de couleur ramenée entre 0 et 255.
     *
     * @return int<0, 255>
     */
    private function channel(int|float $value): int
    {
        return max(0, min(255, (int) round($value)));
    }

    private function textWidth(int $size, string $font, string $text): int
    {
        $box = imagettfbbox($size, 0, $font, $text);

        return $box === false ? 0 : (int) abs($box[2] - $box[0]);
    }
}
