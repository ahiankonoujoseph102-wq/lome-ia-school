<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Chat\Concerns\ResolvesChatMember;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Formation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Fichiers des groupes : servis uniquement aux membres du groupe, jamais mis en cache.
 * Les réponses gèrent la lecture par morceaux (indispensable pour lire une vidéo sur iPhone).
 */
class AttachmentController extends Controller
{
    use ResolvesChatMember;

    /**
     * Types lus directement dans la conversation ; tout le reste est proposé au téléchargement.
     */
    private const array INLINE_TYPES = ['image/jpeg', 'audio/mp4', 'audio/webm', 'audio/ogg', 'audio/mpeg', 'video/mp4', 'video/quicktime', 'video/webm', 'video/3gpp'];

    public function show(Request $request, Formation $formation, ChatMessage $message): BinaryFileResponse
    {
        $viewer = $this->viewer($request, $formation);
        $message = $this->messageOf($viewer, $message);

        abort_unless($message->hasAttachment(), 404);

        $disk = Storage::disk(ChatMessage::DISK);
        abort_unless($disk->exists((string) $message->attachment_path), 404);

        $mime = (string) $message->attachment_mime;
        $inline = in_array($mime, self::INLINE_TYPES, true) && ! $request->boolean('telecharger');

        $response = new BinaryFileResponse($disk->path((string) $message->attachment_path), 200, [
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
    }
}
