<?php

namespace Tests\Feature\Chat;

use App\Enums\ChatMessageType;
use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\ChatMessage;
use App\Models\Formation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MessagesTest extends ChatTestCase
{
    public function test_member_sends_a_text_message_with_clickable_safe_links(): void
    {
        $student = $this->student();

        $this->actingAs($student)
            ->postJson($this->url('chat.messages.store'), ['type' => 'text', 'body' => 'Voir https://meet.google.com/abc-defg-hij <script>alert(1)</script>'])
            ->assertCreated();

        $message = ChatMessage::query()->firstOrFail();
        $this->assertSame(ChatMessageType::Text, $message->type);

        $this->actingAs($student)->get($this->url('chat.show'))
            ->assertOk()
            ->assertSee('<a href="https://meet.google.com/abc-defg-hij" target="_blank" rel="noopener noreferrer nofollow"', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_empty_text_is_refused(): void
    {
        $this->actingAs($this->student())
            ->postJson($this->url('chat.messages.store'), ['type' => 'text', 'body' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }

    public function test_image_is_compressed_and_stored_privately(): void
    {
        $student = $this->student();

        $this->actingAs($student)->postJson($this->url('chat.messages.store'), [
            'type' => 'image',
            'body' => 'Ma capture',
            'attachment' => UploadedFile::fake()->image('capture.png', 3000, 2000),
        ])->assertCreated();

        $message = ChatMessage::query()->firstOrFail();
        $this->assertSame('image/jpeg', $message->attachment_mime);
        $this->assertSame(1600, $message->width);
        $this->assertSame(1067, $message->height);
        Storage::disk('chat_media')->assertExists((string) $message->attachment_path);
        $this->assertStringNotContainsString('public', (string) config('filesystems.disks.chat_media.root'));
        $this->assertFalse((bool) config('filesystems.disks.chat_media.serve'));
    }

    public function test_voice_note_is_accepted_up_to_five_minutes(): void
    {
        $student = $this->student();

        $this->actingAs($student)->postJson($this->url('chat.messages.store'), [
            'type' => 'voice',
            'duration' => 42,
            'attachment' => UploadedFile::fake()->create('note-vocale.m4a', 300, 'audio/mp4'),
        ])->assertCreated();

        $message = ChatMessage::query()->firstOrFail();
        $this->assertSame(ChatMessageType::Voice, $message->type);
        $this->assertSame(42, $message->duration_seconds);
        $this->assertSame('audio/mp4', $message->attachment_mime);

        $this->actingAs($student)->postJson($this->url('chat.messages.store'), [
            'type' => 'voice',
            'duration' => 400,
            'attachment' => UploadedFile::fake()->create('note-vocale.m4a', 300, 'audio/mp4'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['duration' => 'Une note vocale dure 5 minutes au maximum.']);
    }

    public function test_short_video_up_to_50_mb_is_stored_without_compression(): void
    {
        $student = $this->student();

        $this->actingAs($student)->postJson($this->url('chat.messages.store'), [
            'type' => 'video',
            'attachment' => UploadedFile::fake()->create('demo.mp4', 1024, 'video/mp4'),
        ])->assertCreated();

        $message = ChatMessage::query()->firstOrFail();
        $this->assertSame(ChatMessageType::Video, $message->type);
        $this->assertSame(1024 * 1024, $message->attachment_size);
        $this->assertSame('video/mp4', $message->attachment_mime);
        Storage::disk('chat_media')->assertExists((string) $message->attachment_path);

        $this->actingAs($student)->postJson($this->url('chat.messages.store'), [
            'type' => 'video',
            'attachment' => UploadedFile::fake()->create('longue.mp4', 51 * 1024, 'video/mp4'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['attachment' => 'Vidéo trop lourde : 50 Mo au maximum.']);
    }

    public function test_only_whitelisted_files_are_accepted(): void
    {
        $student = $this->student();
        $this->actingAs($student);

        $this->postJson($this->url('chat.messages.store'), [
            'type' => 'file',
            'attachment' => UploadedFile::fake()->create('cours.pdf', 200, 'application/pdf'),
        ])->assertCreated();

        foreach ([
            UploadedFile::fake()->create('virus.exe', 20, 'application/x-msdownload'),
            UploadedFile::fake()->create('script.php', 1, 'application/x-php'),
            UploadedFile::fake()->create('installeur.apk', 20, 'application/vnd.android.package-archive'),
            UploadedFile::fake()->createWithContent('facture.pdf.exe', 'MZ'.str_repeat("\0", 200)),
            $this->realUpload('lisez-moi.txt', '<?php echo shell_exec($_GET["c"]); ?>'),
            $this->realUpload('photo-de-vacances.pdf', 'MZ'.str_repeat("\0", 300).'This program cannot be run in DOS mode'),
        ] as $file) {
            $this->postJson($this->url('chat.messages.store'), ['type' => 'file', 'attachment' => $file])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('attachment');
        }

        $this->postJson($this->url('chat.messages.store'), [
            'type' => 'file',
            'attachment' => UploadedFile::fake()->create('enorme.zip', 21 * 1024, 'application/zip'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['attachment' => 'Fichier trop lourd : 20 Mo au maximum.']);

        $this->postJson($this->url('chat.messages.store'), [
            'type' => 'image',
            'attachment' => UploadedFile::fake()->create('photo.jpg.exe', 20, 'application/x-msdownload'),
        ])->assertUnprocessable()->assertJsonValidationErrors('attachment');

        $this->assertSame(1, ChatMessage::query()->count());
    }

    /**
     * Fichier envoyé avec son vrai contenu : le type est détecté à partir du contenu, pas du nom.
     */
    private function realUpload(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'chat');
        file_put_contents((string) $path, $content);

        return new UploadedFile((string) $path, $name, null, null, true);
    }

    public function test_reply_quotes_the_original_message(): void
    {
        $author = $this->student(['name' => 'Kodjo Mensah']);
        $original = $this->message($author, 'Comment enregistrer une conversation ?');
        $student = $this->student();

        $this->actingAs($student)->postJson($this->url('chat.messages.store'), [
            'type' => 'text',
            'body' => 'Menu à gauche, puis Renommer.',
            'reply_to_id' => $original->id,
        ])->assertCreated();

        $reply = ChatMessage::query()->latest('id')->firstOrFail();
        $this->assertSame($original->id, $reply->reply_to_id);

        $this->get($this->url('chat.show'))->assertSee('data-jump="'.$original->id.'"', false);
    }

    public function test_reply_to_a_message_of_another_group_is_ignored(): void
    {
        $other = Formation::factory()->create();
        $foreign = ChatGroup::forFormation($other)->messages()->create(['user_id' => $this->student([], $other)->id, 'type' => ChatMessageType::Text, 'body' => 'Ailleurs']);

        $this->actingAs($this->student())->postJson($this->url('chat.messages.store'), [
            'type' => 'text', 'body' => 'Réponse', 'reply_to_id' => $foreign->id,
        ])->assertCreated();

        $this->assertNull(ChatMessage::query()->latest('id')->firstOrFail()->reply_to_id);
    }

    public function test_member_deletes_own_message_but_not_others(): void
    {
        $student = $this->student();
        $mine = $this->message($student, 'Mon message');
        $theirs = $this->message($this->student(), 'Son message');

        $this->actingAs($student)->deleteJson($this->url('chat.messages.destroy', ['message' => $theirs]))->assertForbidden();
        $this->actingAs($student)->deleteJson($this->url('chat.messages.destroy', ['message' => $mine]))->assertOk();

        $mine->refresh();
        $this->assertTrue($mine->isDeleted());
        $this->assertNull($mine->body);
        $this->assertFalse($theirs->refresh()->isDeleted());

        $this->get($this->url('chat.show'))->assertSee('Ce message a été supprimé')->assertDontSee('Mon message');
    }

    public function test_deleting_a_message_removes_its_file(): void
    {
        $student = $this->student();
        $this->actingAs($student)->postJson($this->url('chat.messages.store'), [
            'type' => 'image',
            'attachment' => UploadedFile::fake()->image('photo.jpg', 800, 600),
        ])->assertCreated();
        $message = ChatMessage::query()->firstOrFail();
        $path = (string) $message->attachment_path;

        $this->deleteJson($this->url('chat.messages.destroy', ['message' => $message]))->assertOk();

        Storage::disk('chat_media')->assertMissing($path);
        $this->get($this->url('chat.attachments.show', ['message' => $message]))->assertNotFound();
    }

    public function test_unread_counter_and_jump_to_first_unread(): void
    {
        $reader = $this->student();
        $author = $this->student(['name' => 'Kodjo Mensah']);
        $this->message($reader, 'Déjà lu');
        ChatGroupMember::query()->where('user_id', $reader->id)->update(['last_read_message_id' => ChatMessage::query()->max('id')]);

        $first = $this->message($author, 'Premier non lu');
        $this->message($author, 'Deuxième non lu');
        $this->message($reader, 'Mon propre message ne compte pas');

        $this->actingAs($reader)->get(route('chat.index'))->assertOk()->assertSee('aria-label="2 non lus"', false);
        $this->actingAs($reader)->get(route('dashboard'))->assertSee('data-test="nav-unread"', false);

        $this->actingAs($reader)->get($this->url('chat.show'))
            ->assertOk()
            ->assertSee('2 messages non lus')
            ->assertSeeInOrder(['id="premier-non-lu"', 'id="m-'.$first->id.'"'], false);

        // Ouvert : tout est lu.
        $this->actingAs($reader)->get(route('chat.index'))->assertDontSee('non lus"', false);
    }

    public function test_history_loads_progressively_when_scrolling_up(): void
    {
        config(['chat.page_size' => 10]);
        $student = $this->student();
        $messages = collect(range(1, 25))->map(fn (int $i) => $this->message($student, 'Message numéro '.$i.' fin'));

        $page = $this->actingAs($student)->get($this->url('chat.show'));
        $page->assertSee('Message numéro 25 fin')->assertSee('Message numéro 16 fin')->assertDontSee('Message numéro 15 fin');

        $older = $this->getJson($this->url('chat.messages.index', ['before' => $messages[15]->id]))->assertOk();
        $this->assertStringContainsString('Message numéro 15 fin', $older->json('html'));
        $this->assertStringContainsString('Message numéro 6 fin', $older->json('html'));
        $this->assertStringNotContainsString('Message numéro 5 fin', $older->json('html'));
        $this->assertTrue($older->json('has_more'));

        $last = $this->getJson($this->url('chat.messages.index', ['before' => $messages[5]->id]))->assertOk();
        $this->assertFalse($last->json('has_more'));
    }

    public function test_updates_return_new_messages_and_changed_ones(): void
    {
        $reader = $this->student();
        $author = $this->student();
        $old = $this->message($author, 'Ancien');
        $since = now()->toIso8601String();
        $this->travel(5)->seconds();

        $new = $this->message($author, 'Tout nouveau');
        $old->wipe($author);

        $response = $this->actingAs($reader)->getJson($this->url('chat.messages.index', ['after' => $old->id, 'since' => $since, 'visible' => 1]))->assertOk();

        $this->assertStringContainsString('Tout nouveau', $response->json('html'));
        $this->assertSame($new->id, $response->json('last_id'));
        $this->assertSame($old->id, $response->json('updates.0.id'));
        $this->assertStringContainsString('Ce message a été supprimé', $response->json('updates.0.html'));
        $this->assertTrue($response->json('can_post'));
        $this->assertSame($new->id, ChatGroupMember::query()->where('user_id', $reader->id)->value('last_read_message_id'));
    }

    public function test_messages_are_separated_by_day(): void
    {
        $student = $this->student();
        $this->travelTo(now()->subDays(2)->setTime(10, 0));
        $this->message($student, 'Avant-hier');
        $this->travelBack();
        $this->message($student, 'Maintenant');

        $this->actingAs($student)->get($this->url('chat.show'))
            ->assertSeeInOrder(['data-day', 'Avant-hier', 'Aujourd&#039;hui', 'Maintenant'], false);
    }

    public function test_report_reaches_the_moderators(): void
    {
        $author = $this->student(['name' => 'Mawuli Spam']);
        $message = $this->message($author, 'Achetez mes abonnements pas chers');
        $reporter = $this->student();

        $this->actingAs($reporter)->postJson($this->url('chat.messages.report', ['message' => $message]), ['reason' => 'spam', 'details' => 'Publicité'])->assertOk();
        $this->actingAs($author)->postJson($this->url('chat.messages.report', ['message' => $message]), ['reason' => 'spam'])->assertForbidden();

        $this->actingAs($reporter)->get(route('moderation.reports.index'))->assertForbidden();

        $this->actingAs($this->moderator())->get(route('moderation.reports.index'))
            ->assertOk()
            ->assertSee('Achetez mes abonnements pas chers')
            ->assertSee('Publicité ou messages répétés')
            ->assertSee('1 à traiter');
    }
}
