<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Images envoyées depuis l'admin (téléphone) : orientation corrigée, recadrage ou réduction,
 * compression, puis enregistrement sur le disque « public ».
 */
class ImageUploader
{
    public const string DISK = 'public';

    /**
     * Recadre au centre au format demandé (ex. 1200 × 630) et enregistre en JPEG.
     */
    public function cover(UploadedFile $file, string $directory, int $width, int $height, string $field = 'image'): string
    {
        $source = $this->read($file, $field);
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        // Plus grande zone au bon format, centrée.
        $ratio = $width / $height;
        $cropWidth = $sourceWidth;
        $cropHeight = (int) round($sourceWidth / $ratio);

        if ($cropHeight > $sourceHeight) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * $ratio);
        }

        $canvas = $this->canvas($width, $height, opaque: true);
        imagecopyresampled($canvas, $source, 0, 0, intdiv($sourceWidth - $cropWidth, 2), intdiv($sourceHeight - $cropHeight, 2), $width, $height, $cropWidth, $cropHeight);

        return $this->put($canvas, $directory, 'jpg');
    }

    /**
     * Réduit l'image pour qu'elle tienne dans le cadre, sans la recadrer. Garde la transparence (PNG), sinon JPEG.
     */
    public function fit(UploadedFile $file, string $directory, int $maxWidth, int $maxHeight, bool $keepTransparency = false, string $field = 'image'): string
    {
        $source = $this->read($file, $field);
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1, $maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));

        $canvas = $this->canvas($width, $height, opaque: ! $keepTransparency);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        return $this->put($canvas, $directory, $keepTransparency ? 'png' : 'jpg');
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public static function url(?string $path): ?string
    {
        return $path ? Storage::disk(self::DISK)->url($path) : null;
    }

    private function read(UploadedFile $file, string $field): GdImage
    {
        $image = @imagecreatefromstring((string) file_get_contents((string) $file->getRealPath()));

        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages([$field => 'Image illisible. Envoyez une photo au format JPEG, PNG ou WebP.']);
        }

        return $this->orient($image, $file);
    }

    private function canvas(int $width, int $height, bool $opaque): GdImage
    {
        $canvas = imagecreatetruecolor(max(1, $width), max(1, $height));

        if ($opaque) {
            // Fond blanc pour les images transparentes enregistrées en JPEG.
            imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        } else {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, (int) imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        }

        return $canvas;
    }

    private function put(GdImage $image, string $directory, string $extension): string
    {
        ob_start();
        $extension === 'png' ? imagepng($image, null, 8) : imagejpeg($image, null, 82);
        $contents = (string) ob_get_clean();

        $path = trim($directory, '/').'/'.Str::lower(Str::random(20)).'.'.$extension;
        Storage::disk(self::DISK)->put($path, $contents);

        return $path;
    }

    /**
     * Photos de téléphone : applique l'orientation EXIF (sinon l'image apparaît couchée).
     */
    private function orient(GdImage $image, UploadedFile $file): GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }

        $exif = @exif_read_data((string) $file->getRealPath());
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated instanceof GdImage ? $rotated : $image;
    }
}
