<?php

namespace App\Branding;

use GdImage;
use RuntimeException;

/**
 * Déclinaisons du logo et de l'icône, sans jamais modifier leur dessin : uniquement recadrer les marges
 * transparentes, redimensionner, compléter avec la couleur du fond d'origine et compresser.
 *
 * - Icône : favicons (16, 32, 48 px et favicon.ico), apple-touch-icon (180 px), icônes du site installable
 *   (192 et 512 px, versions « any » et « maskable » avec marge de sécurité), badge des notifications
 *   (silhouette blanche exigée par Android).
 * - Logo horizontal (fond transparent) : marges transparentes retirées, hauteur 120 px (écrans 3x).
 * - Logo vertical : 720 px (page À propos) et image de partage 1200 × 630 (logo centré sur son fond).
 */
class BrandAssets
{
    /**
     * Génère le jeu d'icônes dans un dossier.
     *
     * @return list<string> fichiers créés
     */
    public function icons(string $source, string $directory): array
    {
        $image = $this->load($source);
        $background = $this->backgroundColor($image);
        $this->ensureDirectory($directory);
        $files = [];

        // Onglet du navigateur (16 à 48 px) : recadrage serré autour du dessin pour qu'il reste lisible en tout petit.
        $tight = $this->tightSquare($image, $background);

        foreach ([16, 32, 48] as $size) {
            $files[] = $this->savePng($this->square($tight, $size), $directory.'/favicon-'.$size.'.png');
        }

        $files[] = $this->saveIco([16, 32, 48], $tight, $directory.'/favicon.ico');
        $files[] = $this->savePng($this->square($image, 180), $directory.'/apple-touch-icon.png');

        foreach ([192, 512] as $size) {
            $files[] = $this->savePng($this->square($image, $size), $directory.'/icon-'.$size.'.png');
            // « maskable » : le téléphone peut découper l'icône en cercle ; le dessin est réduit à 80 % sur son propre fond.
            $files[] = $this->savePng($this->padded($image, $size, 0.8, $background), $directory.'/icon-maskable-'.$size.'.png');
        }

        $files[] = $this->savePng($this->silhouette($image, 96, $background), $directory.'/badge-96.png');

        return $files;
    }

    /**
     * Logo horizontal : marges transparentes retirées, puis hauteur ramenée à $height px.
     */
    public function horizontalLogo(string $source, string $target, int $height = 120): string
    {
        $image = $this->trimTransparent($this->load($source));
        $width = (int) round(imagesx($image) * $height / imagesy($image));

        return $this->savePng($this->resize($image, $width, $height, transparent: true), $target);
    }

    /**
     * Logo vertical (fond opaque) en carré de $size px, JPEG de très bonne qualité.
     */
    public function verticalLogo(string $source, string $target, int $size = 720): string
    {
        return $this->saveJpeg($this->square($this->load($source), $size), $target);
    }

    /**
     * Image de partage 1200 × 630 : logo vertical centré, fond complété avec la couleur du fond d'origine.
     */
    public function shareImage(string $source, string $target): string
    {
        $image = $this->load($source);
        $canvas = $this->canvas(1200, 630, $this->backgroundColor($image));
        $logo = $this->square($image, 630);
        imagecopy($canvas, $logo, (1200 - 630) / 2, 0, 0, 0, 630, 630);

        return $this->saveJpeg($canvas, $target);
    }

    public function load(string $path): GdImage
    {
        $image = @imagecreatefromstring((string) @file_get_contents($path));

        if (! $image instanceof GdImage) {
            throw new RuntimeException('Image illisible : '.$path);
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }

    /**
     * Couleur du fond : celle du coin supérieur gauche.
     *
     * @return array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}
     */
    public function backgroundColor(GdImage $image): array
    {
        $rgb = imagecolorat($image, 2, 2);

        return [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
    }

    /**
     * Carré centré sur le dessin (tout ce qui diffère nettement du fond), avec 8 % de marge.
     *
     * @param  array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}  $background
     */
    private function tightSquare(GdImage $image, array $background): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        [$left, $top, $right, $bottom] = [$width, $height, 0, 0];
        $step = max(1, intdiv(min($width, $height), 400));

        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                $rgb = imagecolorat($image, $x, $y);
                $distance = abs((($rgb >> 16) & 0xFF) - $background[0]) + abs((($rgb >> 8) & 0xFF) - $background[1]) + abs(($rgb & 0xFF) - $background[2]);

                if ($distance > 60) {
                    $left = min($left, $x);
                    $right = max($right, $x);
                    $top = min($top, $y);
                    $bottom = max($bottom, $y);
                }
            }
        }

        if ($right <= $left || $bottom <= $top) {
            return $image;
        }

        $side = (int) min(min($width, $height), round(max($right - $left, $bottom - $top) * 1.16));
        $x = (int) max(0, min($width - $side, round(($left + $right) / 2 - $side / 2)));
        $y = (int) max(0, min($height - $side, round(($top + $bottom) / 2 - $side / 2)));

        $crop = $this->canvas($side, $side, $background);
        imagecopy($crop, $image, 0, 0, $x, $y, $side, $side);

        return $crop;
    }

    private function square(GdImage $image, int $size): GdImage
    {
        $side = min(imagesx($image), imagesy($image));
        $canvas = $this->canvas($size, $size, null);
        imagecopyresampled($canvas, $image, 0, 0, intdiv(imagesx($image) - $side, 2), intdiv(imagesy($image) - $side, 2), $size, $size, $side, $side);

        return $canvas;
    }

    /**
     * @param  array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}  $background
     */
    private function padded(GdImage $image, int $size, float $scale, array $background): GdImage
    {
        $canvas = $this->canvas($size, $size, $background);
        $inner = (int) round($size * $scale);
        $offset = intdiv($size - $inner, 2);
        $side = min(imagesx($image), imagesy($image));
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $image, $offset, $offset, 0, 0, $inner, $inner, $side, $side);

        return $canvas;
    }

    /**
     * Badge des notifications (Android) : silhouette blanche du dessin sur fond transparent.
     *
     * @param  array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}  $background
     */
    private function silhouette(GdImage $image, int $size, array $background): GdImage
    {
        $small = $this->padded($image, $size, 0.9, $background);
        $badge = $this->canvas($size, $size, null);

        for ($x = 0; $x < $size; $x++) {
            for ($y = 0; $y < $size; $y++) {
                $rgb = imagecolorat($small, $x, $y);
                $distance = abs((($rgb >> 16) & 0xFF) - $background[0]) + abs((($rgb >> 8) & 0xFF) - $background[1]) + abs(($rgb & 0xFF) - $background[2]);
                $opacity = min(1.0, max(0.0, ($distance - 40) / 160));
                imagesetpixel($badge, $x, $y, (int) imagecolorallocatealpha($badge, 255, 255, 255, max(0, min(127, (int) round(127 * (1 - $opacity))))));
            }
        }

        return $badge;
    }

    private function trimTransparent(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        [$left, $top, $right, $bottom] = [$width, $height, 0, 0];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) < 120) {
                    $left = min($left, $x);
                    $right = max($right, $x);
                    $top = min($top, $y);
                    $bottom = max($bottom, $y);
                }
            }
        }

        if ($right < $left) {
            return $image;
        }

        // Petite marge pour ne pas couper l'anticrénelage.
        $left = max(0, $left - 4);
        $top = max(0, $top - 4);
        $right = min($width - 1, $right + 4);
        $bottom = min($height - 1, $bottom + 4);

        $cropped = $this->canvas($right - $left + 1, $bottom - $top + 1, null);
        imagecopy($cropped, $image, 0, 0, $left, $top, $right - $left + 1, $bottom - $top + 1);

        return $cropped;
    }

    private function resize(GdImage $image, int $width, int $height, bool $transparent): GdImage
    {
        $canvas = $this->canvas($width, $height, $transparent ? null : [255, 255, 255]);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        return $canvas;
    }

    /**
     * @param  array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}|null  $color  null : transparent
     */
    private function canvas(int $width, int $height, ?array $color): GdImage
    {
        $canvas = imagecreatetruecolor(max(1, $width), max(1, $height));
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $fill = $color === null
            ? (int) imagecolorallocatealpha($canvas, 0, 0, 0, 127)
            : (int) imagecolorallocate($canvas, $color[0], $color[1], $color[2]);
        imagefilledrectangle($canvas, 0, 0, max(1, $width) - 1, max(1, $height) - 1, $fill);

        return $canvas;
    }

    private function savePng(GdImage $image, string $path): string
    {
        $this->ensureDirectory(dirname($path));
        imagesavealpha($image, true);
        imagepng($image, $path, 9);

        return $path;
    }

    private function saveJpeg(GdImage $image, string $path): string
    {
        $this->ensureDirectory(dirname($path));
        imageinterlace($image, true);
        imagejpeg($image, $path, 90);

        return $path;
    }

    /**
     * Fichier .ico contenant des images PNG (format accepté par tous les navigateurs actuels).
     *
     * @param  list<int>  $sizes
     */
    private function saveIco(array $sizes, GdImage $image, string $path): string
    {
        $pngs = [];

        foreach ($sizes as $size) {
            ob_start();
            imagepng($this->square($image, $size), null, 9);
            $pngs[$size] = (string) ob_get_clean();
        }

        $header = pack('vvv', 0, 1, count($pngs));
        $entries = '';
        $data = '';
        $offset = 6 + 16 * count($pngs);

        foreach ($pngs as $size => $png) {
            $entries .= pack('CCCCvvVV', $size >= 256 ? 0 : $size, $size >= 256 ? 0 : $size, 0, 0, 1, 32, strlen($png), $offset + strlen($data));
            $data .= $png;
        }

        file_put_contents($path, $header.$entries.$data);

        return $path;
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}
