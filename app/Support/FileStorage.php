<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Emplacement des fichiers envoyés, choisi uniquement par le fichier .env :
 *
 * - Hostinger (par défaut) : dossiers du serveur (storage/app/private/… et storage/app/public).
 * - Laravel Cloud ou tout stockage compatible S3 : PRIVATE_FILES_DISK et PUBLIC_FILES_DISK désignent
 *   le disque du stockage objet (nom choisi dans le tableau de bord Laravel Cloud, ou « s3 » avec les
 *   variables AWS_*). Chaque disque privé devient un sous-dossier de ce stockage.
 *
 * Les fichiers privés ne sont jamais publics : l'application vérifie les droits, puis sert le fichier
 * elle-même (stockage local) ou redirige vers un lien temporaire signé de quelques minutes (stockage objet).
 */
class FileStorage
{
    /**
     * Disques privés et leur sous-dossier dans le stockage objet.
     */
    public const array PRIVATE_DISKS = [
        'payment_proofs' => 'payment-proofs',
        'lesson_resources' => 'lesson-resources',
        'chat_media' => 'chat',
        'ama_voice' => 'ama-voice',
    ];

    /**
     * Durée de validité des liens temporaires signés vers un fichier privé (5 minutes ; 30 pour les notes
     * vocales et vidéos des groupes, pour qu'une lecture mise en pause puisse reprendre).
     */
    public const int TEMPORARY_URL_MINUTES = 5;

    public const int MEDIA_TEMPORARY_URL_MINUTES = 30;

    /**
     * Redirige les disques de l'application vers le stockage choisi dans .env (appelé au démarrage).
     */
    public static function configure(): void
    {
        $private = self::remoteDisk((string) config('filesystems.private_files_disk'));

        if ($private !== null) {
            foreach (self::PRIVATE_DISKS as $disk => $folder) {
                config(["filesystems.disks.{$disk}" => [
                    'driver' => 'scoped',
                    'disk' => $private,
                    'prefix' => $folder,
                    'visibility' => 'private',
                    'throw' => false,
                ]]);
            }
        }

        $public = self::remoteDisk((string) config('filesystems.public_files_disk'));

        if ($public !== null) {
            // Aucune « visibility » : le stockage de Laravel Cloud (Cloudflare R2) la refuse ; c'est le
            // bucket, créé en « public », qui rend ces fichiers (photos, images des formations) visibles.
            config(['filesystems.disks.public' => array_merge(
                (array) config("filesystems.disks.{$public}"),
                ['throw' => false, 'report' => false],
            )]);
        }
    }

    /**
     * Nom d'un disque de stockage objet valable, ou null pour garder les dossiers du serveur.
     */
    private static function remoteDisk(string $name): ?string
    {
        $name = trim($name);

        if ($name === '' || in_array($name, ['local', 'public', ...array_keys(self::PRIVATE_DISKS)], true)) {
            return null;
        }

        return in_array(config("filesystems.disks.{$name}.driver"), ['s3', 'scoped'], true) ? $name : null;
    }

    /**
     * Le disque écrit-il dans les dossiers du serveur ?
     */
    public static function isLocal(string $disk): bool
    {
        return config("filesystems.disks.{$disk}.driver") === 'local';
    }

    /**
     * Adresse publique d'un fichier du disque « public », ou null s'il n'existe pas.
     * Sur un stockage objet, l'existence n'est pas vérifiée (une requête réseau par image et par page).
     */
    public static function publicUrl(?string $path): ?string
    {
        $path = trim((string) $path, '/');

        if ($path === '') {
            return null;
        }

        $disk = Storage::disk('public');

        return ! self::isLocal('public') || $disk->exists($path) ? $disk->url($path) : null;
    }

    /**
     * Réponse pour un fichier privé, une fois les droits vérifiés par l'appelant.
     *
     * - Stockage local : $local() construit la réponse (lecture par morceaux, en-têtes de sécurité).
     * - Stockage objet : redirection vers un lien signé valable quelques minutes, jamais mise en cache.
     *
     * @param  \Closure(Filesystem): Response  $local
     */
    public static function privateResponse(string $disk, string $path, string $filename, bool $inline, ?string $mime, \Closure $local, int $minutes = self::TEMPORARY_URL_MINUTES): Response
    {
        $storage = Storage::disk($disk);

        abort_unless($path !== '' && $storage->exists($path), 404);

        if (self::isLocal($disk)) {
            return $local($storage);
        }

        $options = [
            'ResponseContentDisposition' => ($inline ? 'inline' : 'attachment').'; '.self::dispositionFilename($filename),
            'ResponseCacheControl' => 'private, no-store, max-age=0',
        ];

        if ($mime !== null && $mime !== '') {
            $options['ResponseContentType'] = $mime;
        }

        $url = $storage->temporaryUrl($path, now()->addMinutes($minutes), $options);

        return redirect()->away($url)->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /**
     * Nom de fichier pour l'en-tête Content-Disposition (version simple + version UTF-8).
     */
    private static function dispositionFilename(string $filename): string
    {
        $filename = str_replace(["\r", "\n", '"', '\\', '/'], '', $filename) ?: 'fichier';
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $filename) ?: 'fichier';

        return 'filename="'.$ascii.'"; filename*=UTF-8\'\''.rawurlencode($filename);
    }
}
