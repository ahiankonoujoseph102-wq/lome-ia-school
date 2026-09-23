<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Recadre la photo de profil en carré, la réduit à 320 × 320 px et l'enregistre en JPEG
 * sur le disque « public », pour des pages légères même avec une connexion moyenne.
 */
class AvatarProcessor
{
    public const int SIZE = 320;

    public function store(UploadedFile $file, int $userId): string
    {
        $contents = (string) file_get_contents($file->getRealPath());
        $source = imagecreatefromstring($contents);

        if ($source === false) {
            throw new RuntimeException('Image illisible.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);

        $avatar = imagecreatetruecolor(self::SIZE, self::SIZE);

        if ($avatar === false) {
            throw new RuntimeException('Impossible de préparer la photo.');
        }

        // Fond blanc pour les images transparentes (PNG, WebP).
        imagefill($avatar, 0, 0, (int) imagecolorallocate($avatar, 255, 255, 255));
        imagecopyresampled(
            $avatar,
            $source,
            0,
            0,
            intdiv($width - $side, 2),
            intdiv($height - $side, 2),
            self::SIZE,
            self::SIZE,
            $side,
            $side,
        );

        ob_start();
        imagejpeg($avatar, null, 85);
        $jpeg = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($avatar);

        $path = 'avatars/'.$userId.'-'.Str::lower(Str::random(12)).'.jpg';
        Storage::disk('public')->put($path, $jpeg);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
