<?php

namespace Tests\Feature\Chat;

use App\Enums\ChatMessageType;
use App\Models\ChatMessage;
use Illuminate\Support\Facades\Storage;

class MediaExpiryTest extends ChatTestCase
{
    private function withFile(ChatMessageType $type, string $path, int $monthsAgo): ChatMessage
    {
        Storage::disk('chat_media')->put($path, 'contenu');
        $this->travelTo(now()->subMonths($monthsAgo)->subDay());
        $message = $this->message($this->student(), $type === ChatMessageType::Text ? 'Texte ancien' : null, [
            'type' => $type,
            'attachment_path' => $type === ChatMessageType::Text ? null : $path,
            'attachment_name' => basename($path),
            'attachment_mime' => 'application/octet-stream',
            'attachment_size' => 7,
            'duration_seconds' => $type === ChatMessageType::Voice ? 30 : null,
        ]);
        $this->travelBack();

        return $message;
    }

    public function test_old_voice_notes_and_videos_expire_but_texts_images_and_documents_stay(): void
    {
        $voice = $this->withFile(ChatMessageType::Voice, '1/vieille-note.m4a', 12);
        $video = $this->withFile(ChatMessageType::Video, '1/vieille-video.mp4', 13);
        $recentVoice = $this->withFile(ChatMessageType::Voice, '1/note-recente.m4a', 11);
        $image = $this->withFile(ChatMessageType::Image, '1/vieille-image.jpg', 24);
        $document = $this->withFile(ChatMessageType::File, '1/vieux-document.pdf', 24);
        $text = $this->withFile(ChatMessageType::Text, '1/rien.txt', 24);

        $this->artisan('app:expire-chat-media')->assertSuccessful();

        foreach ([$voice, $video] as $message) {
            $message->refresh();
            $this->assertTrue($message->isExpired());
            $this->assertNull($message->attachment_path);
            $this->assertFalse($message->isDeleted());
        }
        Storage::disk('chat_media')->assertMissing('1/vieille-note.m4a');
        Storage::disk('chat_media')->assertMissing('1/vieille-video.mp4');

        foreach ([$recentVoice, $image, $document] as $message) {
            $this->assertFalse($message->refresh()->isExpired());
            Storage::disk('chat_media')->assertExists((string) $message->attachment_path);
        }
        $this->assertSame('Texte ancien', $text->refresh()->body);
    }

    public function test_expired_message_stays_in_the_conversation_with_a_clear_mention(): void
    {
        $voice = $this->withFile(ChatMessageType::Voice, '1/note.m4a', 12);
        $this->artisan('app:expire-chat-media')->assertSuccessful();

        $reader = $this->student();
        $this->actingAs($reader)->get($this->url('chat.show'))
            ->assertOk()
            ->assertSee('id="m-'.$voice->id.'"', false)
            ->assertSee('Fichier expiré')
            ->assertSee('supprimée automatiquement 12 mois après son envoi');

        $this->actingAs($reader)->get($this->url('chat.attachments.show', ['message' => $voice]))->assertNotFound();
        $this->assertSame('🎤 Note vocale (fichier expiré)', $voice->refresh()->preview());
    }

    public function test_retention_is_configurable_and_can_be_disabled(): void
    {
        config(['chat.media_retention_months' => 3]);
        $video = $this->withFile(ChatMessageType::Video, '1/video.mp4', 4);
        $this->artisan('app:expire-chat-media')->assertSuccessful();
        $this->assertTrue($video->refresh()->isExpired());

        config(['chat.media_retention_months' => 0]);
        $kept = $this->withFile(ChatMessageType::Video, '1/garde.mp4', 60);
        $this->artisan('app:expire-chat-media')->assertSuccessful();
        $this->assertFalse($kept->refresh()->isExpired());
        Storage::disk('chat_media')->assertExists('1/garde.mp4');
    }

    public function test_expiry_is_scheduled_and_announced_to_members(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('app:expire-chat-media')->assertSuccessful();

        $this->assertStringContainsString('supprimées automatiquement 12 mois après leur envoi', (string) $this->group()->rules);
        $this->get(route('legal.privacy'))->assertOk()->assertSee('Les notes vocales et les vidéos sont supprimées automatiquement 12 mois après leur envoi');
    }
}
