<?php

namespace App\Services;

use App\Models\Formation;
use App\Support\Brand;
use App\Support\Founder;
use App\Support\LiveOffer;
use GdImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
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
    private const int VERSION = 5;

    private const int PADDING = 72;

    public function forFormation(Formation $formation): string
    {
        // Formation proposée en direct : dates et horaires à la place de la catégorie, Google Meet à côté du prix.
        $liveOffer = LiveOffer::for($formation);

        $key = sprintf(
            'formation-%d-%s',
            $formation->id,
            md5(implode('|', [self::VERSION, $formation->title, $formation->price, (int) $formation->needsPaymentVerification(), $formation->category->value, $formation->updated_at?->timestamp, $liveOffer?->fingerprint(), $formation->event_date?->toDateString()]))
        );

        // Formation gratuite : date de l'événement à la place de la catégorie, aucune mention de paiement.
        if ($formation->isFree()) {
            return $this->cached($key, fn (): GdImage => $this->render(
                eyebrow: $formation->eventDateLabel() ?? $formation->category->label(),
                title: $formation->title,
                badge: 'Gratuit',
                footer: 'Inscription gratuite sur le site',
            ));
        }

        return $this->cached($key, fn (): GdImage => $this->render(
            eyebrow: $liveOffer ? str_replace("\u{00A0}", ' ', 'En direct · '.$liveOffer->shortPeriod().' · '.$liveOffer->hours()) : $formation->category->label(),
            title: $formation->title,
            badge: str_replace("\u{00A0}", ' ', $formation->formatted_price),
            footer: $liveOffer
                ? 'Sur Google Meet · heure de Lomé (GMT)'
                : ($formation->needsPaymentVerification() ? 'Accès après vérification du paiement · Mobile Money' : 'Accès immédiat · Paiement Mobile Money'),
        ));
    }

    /**
     * Image de la formation envoyée depuis l'admin, recadrée au centre en 1200 × 630 (JPEG),
     * ou null si la formation n'a pas d'image lisible (le visuel généré est alors utilisé).
     */
    public function forFormationPhoto(Formation $formation): ?string
    {
        if (blank($formation->image)) {
            return null;
        }

        $key = 'formation-photo-'.$formation->id.'-'.md5(self::VERSION.'|'.$formation->image);
        $path = storage_path('framework/cache/og/'.$key.'.jpg');

        if (File::exists($path)) {
            return $path;
        }

        $bytes = $this->formationImageBytes((string) $formation->image);
        $source = $bytes === null ? false : @imagecreatefromstring($bytes);

        if (! $source instanceof GdImage) {
            return null;
        }

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        if ($canvas === false) {
            throw new RuntimeException('Impossible de créer l\'image d\'aperçu.');
        }

        // Plus grande zone au format 1200 × 630, centrée.
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $cropWidth = $sourceWidth;
        $cropHeight = (int) round($sourceWidth * self::HEIGHT / self::WIDTH);

        if ($cropHeight > $sourceHeight) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * self::WIDTH / self::HEIGHT);
        }

        imagecopyresampled($canvas, $source, 0, 0, intdiv($sourceWidth - $cropWidth, 2), intdiv($sourceHeight - $cropHeight, 2), self::WIDTH, self::HEIGHT, $cropWidth, $cropHeight);
        imagedestroy($source);

        File::ensureDirectoryExists(dirname($path));
        imagejpeg($canvas, $path, 85);
        imagedestroy($canvas);

        return $path;
    }

    public function forSite(): string
    {
        $title = (string) config('site.hero.title');

        return $this->cached('site-'.md5(self::VERSION.'|'.$title), fn (): GdImage => $this->render(
            eyebrow: 'Formations en intelligence artificielle',
            title: $title,
            badge: 'Formations enregistrées',
            footer: 'Paiement Mobile Money · Accès à vie',
        ));
    }

    /**
     * Page de Joseph Ahiankonou : nom et surnom, métier, « Fondateur de Lomé IA School », photo ronde à droite
     * (photo envoyée depuis l'admin, sinon celle du formateur ; sans photo, image sans portrait). JPEG.
     */
    public function forFounder(): string
    {
        $name = Founder::text('name');
        $nickname = Founder::text('nickname');
        $job = Founder::text('job_title');
        $photo = Founder::text('photo') ?: (string) config('site.trainer.photo');
        $trainer = public_path((string) config('site.trainer.photo'));
        $key = 'founder-'.md5(implode('|', [self::VERSION, $name, $nickname, $job, $photo, is_file($trainer) ? filemtime($trainer) : 0]));
        $path = storage_path('framework/cache/og/'.$key.'.jpg');

        if (File::exists($path)) {
            return $path;
        }

        $bytes = Founder::photoBytes();
        $portrait = $bytes === null ? false : @imagecreatefromstring($bytes);

        $image = $this->render(
            // Métier en entier s'il tient à gauche du portrait, sinon sa forme courte.
            eyebrow: $job !== '' && $this->textWidth(24, resource_path('fonts/InstrumentSans_500Medium.ttf'), mb_strtoupper($job)) <= 620 ? $job : 'Formateur et consultant en IA',
            title: $nickname !== '' ? $name.' ('.$nickname.')' : $name,
            badge: 'Fondateur de Lomé IA School',
            footer: 'Lomé, Togo',
            portrait: $portrait instanceof GdImage ? $portrait : null,
        );

        File::ensureDirectoryExists(dirname($path));
        imagejpeg($image, $path, 85);
        imagedestroy($image);

        return $path;
    }

    /**
     * Contenu de l'image d'une formation : disque « public » (envoyée depuis l'admin, y compris sur un
     * stockage objet) ou ancien fichier de public/images/formations.
     */
    private function formationImageBytes(string $image): ?string
    {
        if (str_contains($image, '/')) {
            return Storage::disk('public')->get($image) ?: null;
        }

        $path = public_path('images/formations/'.basename($image));

        return is_file($path) ? ((string) file_get_contents($path) ?: null) : null;
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

    private function render(string $eyebrow, string $title, string $badge, string $footer, ?GdImage $portrait = null): GdImage
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        if ($image === false) {
            throw new RuntimeException('Impossible de créer l\'image d\'aperçu.');
        }

        imagealphablending($image, true);
        imageantialias($image, true);

        $this->drawBackground($image);

        // Portrait rond à droite : le texte s'arrête avant lui.
        $right = self::WIDTH - self::PADDING;

        if ($portrait !== null) {
            $size = 380;
            $x = self::WIDTH - self::PADDING - $size;
            $this->drawPortrait($image, $portrait, $x, intdiv(self::HEIGHT - $size, 2), $size);
            $right = $x - 40;
        }

        $bold = resource_path('fonts/InstrumentSans_700Bold.ttf');
        $medium = resource_path('fonts/InstrumentSans_500Medium.ttf');

        $white = $this->color($image, '#FFFFFF');
        $muted = $this->color($image, '#C4B5FD');
        $soft = $this->color($image, '#E0E7FF', 30);
        $gold = $this->color($image, '#FBBF24');
        $night = $this->color($image, '#0B1030');

        // Marque : icône du site (jeu en place), sinon trait doré
        if ($this->drawIcon($image, self::PADDING, 58, 56)) {
            imagettftext($image, 26, 0, self::PADDING + 72, 97, $white, $bold, 'Lomé IA School');
        } else {
            imagefilledrectangle($image, self::PADDING, 70, self::PADDING + 14, 104, $gold);
            imagettftext($image, 26, 0, self::PADDING + 30, 100, $white, $bold, 'Lomé IA School');
        }

        // Catégorie (raccourcie si elle passerait sous le portrait)
        $eyebrow = mb_strtoupper($eyebrow);

        while (mb_strlen($eyebrow) > 10 && self::PADDING + $this->textWidth(24, $medium, $eyebrow) > $right) {
            $eyebrow = rtrim(mb_substr($eyebrow, 0, -2), ' ,.·').'…';
        }

        imagettftext($image, 24, 0, self::PADDING, 190, $muted, $medium, $eyebrow);

        // Titre sur 3 lignes maximum, taille adaptée à la longueur
        [$size, $lines] = $this->fitTitle($title, $bold, $right - self::PADDING);
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

        if ($footerX + $this->textWidth(22, $medium, $footer) <= $right) {
            imagettftext($image, 22, 0, $footerX, $badgeTop + 46, $soft, $medium, $footer);
        }

        return $image;
    }

    /**
     * Dessine l'icône de Lomé IA School (jeu d'icônes en place), ou renvoie false si elle est introuvable.
     */
    private function drawIcon(GdImage $image, int $x, int $y, int $size): bool
    {
        $directory = trim((string) config('site.brand.icons', 'icons'), '/');
        $path = Brand::path($directory.'/icon-192.png') ?? Brand::path('icons/icon-192.png');
        $icon = $path === null ? false : @imagecreatefrompng($path);

        if (! $icon instanceof GdImage) {
            return false;
        }

        imagecopyresampled($image, $icon, $x, $y, 0, 0, $size, $size, imagesx($icon), imagesy($icon));
        imagedestroy($icon);

        return true;
    }

    /**
     * @return array{0: int, 1: list<string>}
     */
    private function fitTitle(string $title, string $font, int $maxWidth = self::WIDTH - self::PADDING * 2): array
    {
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

    /**
     * Photo recadrée en carré au centre, dessinée en rond avec un anneau doré et un bord adouci.
     *
     * @param  int<1, max>  $size
     */
    private function drawPortrait(GdImage $image, GdImage $source, int $x, int $y, int $size): void
    {
        $ring = 8;
        imagefilledellipse($image, $x + intdiv($size, 2), $y + intdiv($size, 2), $size + $ring * 2, $size + $ring * 2, $this->color($image, '#F59E0B'));

        $square = imagecreatetruecolor($size, $size);

        if ($square === false) {
            return;
        }

        $side = min(imagesx($source), imagesy($source));
        imagecopyresampled($square, $source, 0, 0, intdiv(imagesx($source) - $side, 2), intdiv(imagesy($source) - $side, 2), $size, $size, $side, $side);

        $radius = $size / 2;

        for ($py = 0; $py < $size; $py++) {
            for ($px = 0; $px < $size; $px++) {
                $distance = sqrt(($px + 0.5 - $radius) ** 2 + ($py + 0.5 - $radius) ** 2);

                if ($distance > $radius) {
                    continue;
                }

                $rgb = imagecolorat($square, $px, $py);
                // Dernier pixel du bord à moitié transparent : contour sans escalier.
                $alpha = $distance > $radius - 1 ? (int) round(127 * ($distance - $radius + 1)) : 0;
                imagesetpixel($image, $x + $px, $y + $py, (int) imagecolorallocatealpha($image, ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF, max(0, min(127, $alpha))));
            }
        }

        imagedestroy($square);
        imagedestroy($source);
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
