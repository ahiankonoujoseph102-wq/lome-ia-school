<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Chat\Concerns\ResolvesChatMember;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Formation;
use App\Support\FileStorage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Fichiers des groupes : servis uniquement aux membres du groupe, jamais mis en cache.
 * Les réponses gèrent la lecture par morceaux (indispensable pour lire une vidéo sur iPhone).
 * Sur un stockage objet (Laravel Cloud) : lien temporaire signé (le stockage gère aussi la lecture par morceaux).
 */
class AttachmentController extends Controller
{
    use ResolvesChatMember;

    /**
     * Types lus directement dans la conversation ; tout le reste est proposé au téléchargement.
     */
    private const array INLINE_TYPES = ['image/jpeg', 'audio/mp4', 'audio/webm', 'audio/ogg', 'audio/mpeg', 'video/mp4', 'video/quicktime', 'video/webm', 'video/3gpp'];

    public function show(Request $request, Formation $formation, ChatMessage $message): Response
    {
        $viewer = $this->viewer($request, $formation);
        $message = $this->messageOf($viewer, $message);

        abort_unless($message->hasAttachment(), 404);

        $path = (string) $message->attachment_path;
        $mime = (string) $message->attachment_mime;
        $inline = in_array($mime, self::INLINE_TYPES, true) && ! $request->boolean('telecharger');
        $filename = (string) $message->attachment_name ?: 'fichier.'.strtolower($message->extension());

        return FileStorage::privateResponse(ChatMessage::DISK, $path, $filename, $inline, $inline ? $mime : 'application/octet-stream', function (Filesystem $disk) use ($message, $path, $mime, $inline): Response {
            $response = new BinaryFileResponse($disk->path($path), 200, [
                'Content-Type' => $inline ? $mime : 'application/octet-stream',
                'Cache-Control' => 'no-store, private',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; media-src 'self'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
                'Referrer-Policy' => 'no-referrer',
            ], true, null, false, false);

            $response->setContentDisposition(
                $inline ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                (string) $message->attachment_name,
                'fichier.'.strtolower($message->extension()),
            );

            return $response;
        }, FileStorage::MEDIA_TEMPORARY_URL_MINUTES);
    }
}
