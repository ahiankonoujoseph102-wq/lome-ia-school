<?php

namespace Tests\Feature\Chat;

use App\Enums\ChatMessageType;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Fichiers des groupes sur un stockage objet (Laravel Cloud) : lien temporaire signé pour les membres seulement.
 */
class ObjectStorageAttachmentTest extends ChatTestCase
{
    public function test_members_get_a_signed_temporary_link_and_others_nothing(): void
    {
        config(['filesystems.disks.chat_media' => ['driver' => 'scoped', 'disk' => 'prive', 'prefix' => 'chat']]);
        Storage::disk('chat_media')->buildTemporaryUrlsUsing(
            fn (string $path, $expiration, array $options): string => 'https://stockage.example/'.$path.'?type='.rawurlencode((string) ($options['ResponseContentType'] ?? '')).'&expire='.$expiration->getTimestamp(),
        );

        $student = $this->student();
        Storage::disk('chat_media')->put('1/note.m4a', 'audio');
        $message = $this->message($student, null, [
            'type' => ChatMessageType::Voice,
            'attachment_path' => '1/note.m4a',
            'attachment_name' => 'note-vocale.m4a',
            'attachment_mime' => 'audio/mp4',
            'attachment_size' => 5,
        ]);

        $response = $this->actingAs($student)->get($this->url('chat.attachments.show', ['message' => $message]));

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://stockage.example/1/note.m4a?type=audio%2Fmp4', $location);
        // Notes vocales et vidéos : lien valable 30 minutes, pour reprendre une lecture mise en pause.
        $this->assertStringContainsString('&expire='.now()->addMinutes(30)->getTimestamp(), $location);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get($this->url('chat.attachments.show', ['message' => $message]))->assertForbidden();
    }
}
