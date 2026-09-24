<?php

namespace App\Payments;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Capture d'écran d'une preuve de paiement : empreinte du fichier envoyé (pour repérer une capture
 * déjà utilisée), réduction à 1600 px maximum, compression en JPEG et stockage sur le disque privé.
 */
class ProofImageProcessor
{
    public const string DISK = 'payment_proofs';

    public const int MAX_SIDE = 1600;

    /**
     * Empreinte SHA-256 du fichier tel qu'envoyé par l'élève.
     */
    public function hash(UploadedFile $file): string
    {
        return (string) hash_file('sha256', (string) $file->getRealPath());
    }

    /**
     * Enregistre la capture compressée et retourne son chemin sur le disque privé.
     */
    public function store(UploadedFile $file, int $userId): string
    {
        $source = imagecreatefromstring((string) file_get_contents((string) $file->getRealPath()));

        if ($source === false) {
            throw new RuntimeException('Image illisible.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $image = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($image === false) {
            throw new RuntimeException('Impossible de préparer la capture.');
        }

        // Fond blanc pour les images transparentes (PNG, WebP).
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 255, 255));
        imagecopyresampled($image, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagejpeg($image, null, 80);
        $jpeg = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($image);

        $path = $userId.'/'.now()->format('Ymd-His').'-'.Str::lower(Str::random(16)).'.jpg';
        Storage::disk(self::DISK)->put($path, $jpeg);

        return $path;
    }
}
