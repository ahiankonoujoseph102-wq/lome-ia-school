<?php

namespace App\Chat;

use App\Enums\ChatMessageType;
use App\Models\ChatMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Enregistre la pièce jointe d'un message sur le disque privé « chat_media ».
 *
 * - Images et captures : remises à l'endroit, réduites (1600 px) et compressées en JPEG.
 * - Notes vocales, vidéos et fichiers : enregistrés tels quels (aucune compression sur le serveur).
 */
class AttachmentStore
{
    /**
     * Types servis pour les notes vocales, selon le format enregistré par le navigateur.
     */
    private const array VOICE_FORMATS = [
        'webm' => 'audio/webm',
        'ogg' => 'audio/ogg',
        'mp3' => 'audio/mpeg',
        'm4a' => 'audio/mp4',
    ];

    /**
     * @return array{attachment_path: string, attachment_name: string, attachment_mime: string, attachment_size: int, width: int|null, height: int|null}
     */
    public function store(UploadedFile $file, ChatMessageType $type, int $groupId): array
    {
        return match ($type) {
            ChatMessageType::Image => $this->storeImage($file, $groupId),
            ChatMessageType::Voice => $this->storeVoice($file, $groupId),
            ChatMessageType::Video => $this->storeAsIs($file, $groupId, (string) ($file->guessExtension() ?: 'mp4'), (string) $file->getMimeType()),
            default => $this->storeAsIs($file, $groupId, Str::lower($file->getClientOriginalExtension()), (string) $file->getMimeType()),
        };
    }

    /**
     * @return array{attachment_path: string, attachment_name: string, attachment_mime: string, attachment_size: int, width: int, height: int}
     */
    private function storeImage(UploadedFile $file, int $groupId): array
    {
        $source = imagecreatefromstring((string) file_get_contents((string) $file->getRealPath()));

        if ($source === false) {
            throw new RuntimeException('Image illisible.');
        }

        $source = $this->orient($source, $file);
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, (int) config('chat.attachments.image.max_side', 1600) / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $image = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($image === false) {
            throw new RuntimeException('Impossible de préparer l\'image.');
        }

        // Fond blanc pour les images transparentes (PNG, WebP, GIF).
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 255, 255));
        imagecopyresampled($image, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagejpeg($image, null, (int) config('chat.attachments.image.quality', 82));
        $jpeg = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($image);

        $path = $this->path($groupId, 'jpg');
        Storage::disk(ChatMessage::DISK)->put($path, $jpeg);

        return [
            'attachment_path' => $path,
            'attachment_name' => Str::beforeLast($this->cleanName($file), '.').'.jpg',
            'attachment_mime' => 'image/jpeg',
            'attachment_size' => strlen($jpeg),
            'width' => $targetWidth,
            'height' => $targetHeight,
        ];
    }

    /**
     * @return array{attachment_path: string, attachment_name: string, attachment_mime: string, attachment_size: int, width: null, height: null}
     */
    private function storeVoice(UploadedFile $file, int $groupId): array
    {
        $declared = Str::lower($file->getClientMimeType().' '.$file->getMimeType());

        $extension = match (true) {
            str_contains($declared, 'webm') => 'webm',
            str_contains($declared, 'ogg') => 'ogg',
            str_contains($declared, 'mpeg') => 'mp3',
            default => 'm4a',
        };

        return $this->storeAsIs($file, $groupId, $extension, self::VOICE_FORMATS[$extension], 'Note vocale.'.$extension);
    }

    /**
     * @return array{attachment_path: string, attachment_name: string, attachment_mime: string, attachment_size: int, width: null, height: null}
     */
    private function storeAsIs(UploadedFile $file, int $groupId, string $extension, string $mime, ?string $name = null): array
    {
        $extension = preg_replace('/[^a-z0-9]/', '', Str::lower($extension)) ?: 'bin';
        $path = $this->path($groupId, $extension);

        Storage::disk(ChatMessage::DISK)->putFileAs(dirname($path), $file, basename($path));

        return [
            'attachment_path' => $path,
            'attachment_name' => $name ?? $this->cleanName($file),
            'attachment_mime' => $mime,
            'attachment_size' => (int) $file->getSize(),
            'width' => null,
            'height' => null,
        ];
    }

    private function path(int $groupId, string $extension): string
    {
        return $groupId.'/'.now()->format('Y-m').'/'.Str::lower(Str::random(32)).'.'.$extension;
    }

    /**
     * Nom affiché : sans chemin ni caractères de contrôle, 120 caractères au plus.
     */
    private function cleanName(UploadedFile $file): string
    {
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', ' ', $file->getClientOriginalName()));
        $extension = Str::lower($file->getClientOriginalExtension());
        $base = Str::limit(Str::beforeLast($name, '.') ?: 'fichier', 110, '');

        return $extension !== '' ? $base.'.'.$extension : $base;
    }

    /**
     * Photos de téléphone : applique l'orientation EXIF (sinon l'image apparaît couchée).
     */
    private function orient(\GdImage $image, UploadedFile $file): \GdImage
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

        return $rotated instanceof \GdImage ? $rotated : $image;
    }
}
