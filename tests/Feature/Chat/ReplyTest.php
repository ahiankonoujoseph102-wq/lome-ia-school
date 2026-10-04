<?php

namespace Tests\Feature\Chat;

use App\Enums\ChatMessageType;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Répondre à un message précis, comme sur WhatsApp : geste vers la droite ou « Répondre » dans le menu,
 * encadré pendant la saisie, citation au-dessus de la réponse (toucher = défiler jusqu'à l'original),
 * « Message supprimé » si l'original a été supprimé. Geste : resources/js/chat-swipe.js.
 */
class ReplyTest extends ChatTestCase
{
    /**
     * Message de chaque type accepté par les groupes, envoyé par l'interface (avec ou sans citation).
     */
    private function send(User $author, string $type, ?int $replyTo = null, ?string $body = null): ChatMessage
    {
        $attachment = match ($type) {
            'image' => UploadedFile::fake()->image('capture.png', 800, 600),
            'voice' => UploadedFile::fake()->create('note-vocale.m4a', 300, 'audio/mp4'),
            'video' => UploadedFile::fake()->create('demo.mp4', 1024, 'video/mp4'),
            'file' => UploadedFile::fake()->create('cours.pdf', 200, 'application/pdf'),
            default => null,
        };

        $this->actingAs($author)->post($this->url('chat.messages.store'), array_filter([
            'type' => $type,
            'body' => $body ?? ($type === 'text' ? 'Message texte' : null),
            'attachment' => $attachment,
            'duration' => $type === 'voice' ? 12 : null,
            'reply_to_id' => $replyTo,
        ]), ['Accept' => 'application/json'])->assertCreated();

        return ChatMessage::query()->latest('id')->firstOrFail();
    }

    public function test_every_message_type_can_be_quoted_and_can_reply(): void
    {
        $kodjo = $this->student(['name' => 'Kodjo Exemple']);
        $ama = $this->student(['name' => 'Ama Exemple']);
        $types = ['text', 'image', 'voice', 'video', 'file'];

        foreach ($types as $originalType) {
            $original = $this->send($kodjo, $originalType);

            foreach ($types as $replyType) {
                $reply = $this->send($ama, $replyType, $original->id);
                $this->assertSame($original->id, $reply->reply_to_id, "Réponse {$replyType} à un message {$originalType}");
            }
        }

        $page = $this->actingAs($ama)->get($this->url('chat.show'))->assertOk();
        $page->assertSee('data-test="reply-quote"', false)
            ->assertSee('Kodjo Exemple')
            ->assertSee('Message texte')
            ->assertSee('📷 Photo')
            ->assertSee('🎤 Note vocale')
            ->assertSee('🎬 Vidéo')
            ->assertSee('📄 cours.pdf');

        // Citation d'une photo : petite miniature à droite de l'extrait, comme sur WhatsApp.
        $image = ChatMessage::query()->where('type', ChatMessageType::Image)->whereNull('reply_to_id')->firstOrFail();
        $this->assertStringContainsString('data-jump="'.$image->id.'"', $page->getContent());
        $this->assertMatchesRegularExpression('/data-jump="'.$image->id.'".*?<img src="'.preg_quote(e($image->attachmentUrl()), '/').'"/s', $page->getContent());
    }

    public function test_the_quote_shows_the_author_and_a_short_extract_and_jumps_to_the_original(): void
    {
        $kodjo = $this->student(['name' => 'Kodjo Exemple']);
        $original = $this->message($kodjo, str_repeat('Comment créer un agent IA pour répondre aux clients ? ', 6));
        $ama = $this->student(['name' => 'Ama Exemple']);
        $this->send($ama, 'text', $original->id, 'Regarde la leçon 3.');

        $html = $this->actingAs($ama)->get($this->url('chat.show'))->assertOk()->getContent();

        $this->assertStringContainsString('data-jump="'.$original->id.'"', $html);
        $this->assertStringContainsString('aria-label="Voir le message cité de Kodjo Exemple"', $html);
        // Extrait court (120 caractères au plus), jamais le message entier.
        $this->assertStringContainsString(e($original->preview(120)), $html);
        $this->assertStringContainsString('id="m-'.$original->id.'"', $html);

        // Sa propre citation : « Vous ».
        $mine = $this->message($ama, 'Ma question');
        $this->send($kodjo, 'text', $mine->id, 'Ma réponse');
        $this->actingAs($ama)->get($this->url('chat.show'))->assertSee('Voir le message cité de Vous');
    }

    public function test_a_deleted_original_is_quoted_as_message_supprime_without_its_content(): void
    {
        $kodjo = $this->student(['name' => 'Kodjo Exemple']);
        $ama = $this->student(['name' => 'Ama Exemple']);
        $text = $this->message($kodjo, 'Contenu confidentiel à ne plus montrer');
        $image = $this->send($kodjo, 'image', null, 'Légende de la capture');
        $this->send($ama, 'text', $text->id, 'Réponse au texte');
        $this->send($ama, 'text', $image->id, 'Réponse à la photo');
        $imageUrl = $image->attachmentUrl();

        // Suppression par l'auteur, puis par la modération.
        $this->actingAs($kodjo)->deleteJson($this->url('chat.messages.destroy', ['message' => $text]))->assertOk();
        $this->actingAs($this->moderator())->deleteJson($this->url('chat.messages.destroy', ['message' => $image]))->assertOk();

        $html = $this->actingAs($ama)->get($this->url('chat.show'))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'data-test="reply-quote-deleted"'));
        $this->assertStringContainsString('Message supprimé', $html);
        $this->assertStringNotContainsString('Contenu confidentiel', $html);
        $this->assertStringNotContainsString('Légende de la capture', $html);
        $this->assertStringNotContainsString((string) $imageUrl, $html);
        $this->assertStringContainsString('Réponse au texte', $html);
    }

    public function test_replies_already_on_screen_are_refreshed_when_the_original_is_deleted(): void
    {
        $kodjo = $this->student(['name' => 'Kodjo Exemple']);
        $ama = $this->student(['name' => 'Ama Exemple']);
        $original = $this->message($kodjo, 'Texte qui va disparaître');
        $reply = $this->send($ama, 'text', $original->id, 'Réponse');
        $since = now()->toIso8601String();

        $this->travel(5)->seconds();
        $this->actingAs($this->admin())->deleteJson($this->url('chat.messages.destroy', ['message' => $original]))->assertOk();

        $updates = collect($this->actingAs($ama)->getJson($this->url('chat.messages.index', ['after' => $reply->id, 'since' => $since]))->assertOk()->json('updates'));

        $this->assertEqualsCanonicalizing([$original->id, $reply->id], $updates->pluck('id')->all());
        $replyHtml = $updates->firstWhere('id', $reply->id)['html'];
        $this->assertStringContainsString('data-test="reply-quote-deleted"', $replyHtml);
        $this->assertStringNotContainsString('Texte qui va disparaître', $replyHtml);

        // Une simple réaction sur l'original ne fait pas recharger toutes ses réponses.
        $other = $this->message($kodjo, 'Autre message');
        $otherReply = $this->send($ama, 'text', $other->id, 'Réponse 2');
        $this->travel(5)->seconds();
        $since = now()->toIso8601String();
        $this->travel(5)->seconds();
        $this->actingAs($kodjo)->postJson($this->url('chat.messages.react', ['message' => $other]), ['emoji' => 'like'])->assertOk();

        $ids = collect($this->actingAs($ama)->getJson($this->url('chat.messages.index', ['after' => $otherReply->id, 'since' => $since]))->json('updates'))->pluck('id')->all();
        $this->assertContains($other->id, $ids);
        $this->assertNotContains($otherReply->id, $ids);
    }

    public function test_replying_to_an_already_deleted_message_sends_a_normal_message(): void
    {
        $kodjo = $this->student();
        $original = $this->message($kodjo, 'Supprimé avant la réponse');
        $original->wipe($kodjo);

        $reply = $this->send($this->student(), 'text', $original->id, 'Trop tard');

        $this->assertNull($reply->reply_to_id);
    }

    public function test_muted_member_and_announcements_mode_cannot_reply(): void
    {
        $kodjo = $this->student();
        $original = $this->message($this->admin(), 'Annonce');
        $this->group()->update(['announcements_only' => true]);

        $this->actingAs($kodjo)->postJson($this->url('chat.messages.store'), ['type' => 'text', 'body' => 'Merci', 'reply_to_id' => $original->id])
            ->assertUnprocessable();
        $this->assertSame(1, ChatMessage::query()->count());
    }

    public function test_page_has_the_reply_draft_the_menu_entry_and_swipe_data(): void
    {
        $kodjo = $this->student(['name' => 'Kodjo Exemple']);
        $image = $this->send($kodjo, 'image');
        $deleted = $this->message($kodjo, 'Effacé');
        $deleted->wipe($kodjo);

        $html = $this->actingAs($this->student())->get($this->url('chat.show'))->assertOk()->getContent();

        // Encadré au-dessus de la zone de saisie (auteur, extrait, croix) et « Répondre » dans le menu de l'appui long.
        $this->assertStringContainsString('data-test="reply-draft"', $html);
        $this->assertStringContainsString('aria-label="Annuler la réponse"', $html);
        $this->assertStringContainsString('@keydown.escape="cancelReply()"', $html);
        $this->assertMatchesRegularExpression('/@click="reply\(\)"[^>]*>.*?Répondre/s', $html);

        // Données lues par le geste : miniature d'une photo ; un message supprimé n'en a pas.
        $this->assertStringContainsString('data-thumb="'.e($image->attachmentUrl()).'"', $html);
        $this->assertMatchesRegularExpression('/id="m-'.$deleted->id.'"[^>]*data-thumb=""[^>]*data-deleted="1"/s', $html);
        // Le défilement horizontal de la conversation ne peut pas apparaître pendant le geste.
        $this->assertStringContainsString('overflow-x-hidden overflow-y-auto', $html);
    }

    public function test_the_gesture_never_blocks_scrolling_long_press_or_back_navigation(): void
    {
        $js = (string) file_get_contents(resource_path('js/chat.js'));
        $css = (string) file_get_contents(resource_path('css/site.css'));

        $this->assertStringContainsString("import { createSwipeGesture } from './chat-swipe.js';", $js);
        // Écouteurs « passifs » : le navigateur fait défiler sans attendre le script, jamais de preventDefault.
        $swipe = substr($js, (int) strpos($js, 'listenSwipe() {'), 6000);
        $this->assertStringNotContainsString('preventDefault()', substr($swipe, 0, (int) strpos($swipe, 'const finish')));
        $this->assertSame(substr_count($swipe, "addEventListener(\n                'touch") + 2, substr_count($swipe, 'passive: true'));
        // L'appui long (menu, réactions) est inchangé.
        $this->assertStringContainsString('this.listenLongPress();', $js);
        $this->assertStringContainsString("['touchend', 'touchmove', 'touchcancel'].forEach((name) => this.els.list.addEventListener(name, cancel, { passive: true }));", $js);
        // Défilement vertical et zoom laissés au navigateur sur les bulles.
        $this->assertStringContainsString('touch-action: pan-y pinch-zoom;', $css);
    }

    /**
     * Logique du geste (resources/js/chat-swipe.js) exécutée avec Node.js.
     */
    public function test_swipe_logic_with_node(): void
    {
        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->markTestSkipped('Node.js est nécessaire pour exécuter resources/js/chat-swipe.js.');
        }

        $module = json_encode('file://'.resource_path('js/chat-swipe.js'));
        $program = <<<JS
            const { createSwipeGesture, swipeOffset, SWIPE } = await import({$module});
            const width = 360;
            const run = (points) => {
                const g = createSwipeGesture();
                const [x0, y0] = points[0];
                const steps = [g.start(x0, y0, width)];
                for (const [x, y] of points.slice(1)) steps.push(g.move(x, y));
                return { steps, end: g.end() };
            };
            const out = {};

            // Glisser vers la droite au-delà du seuil : réponse préparée, vibration une seule fois.
            const right = run([[100, 300], [106, 301], [120, 302], [170, 303], [190, 303], [210, 304]]);
            out.right = { end: right.end, states: right.steps.map((s) => s.state), crossed: right.steps.filter((s) => s.crossed).length, max: Math.max(...right.steps.map((s) => s.offset)) };

            // Pas assez loin : la bulle revient, aucune réponse.
            out.short = run([[100, 300], [130, 302], [150, 303]]).end;

            // Défilement vertical (même un peu de travers) : jamais de réponse, la bulle ne bouge pas.
            const scroll = run([[100, 300], [104, 280], [140, 200], [220, 150]]);
            out.scroll = { end: scroll.end, last: scroll.steps.at(-1) };

            // Vers la gauche : ignoré.
            out.left = run([[200, 300], [170, 300], [100, 300]]).end;

            // Commencé au bord de l'écran : laissé au retour en arrière du téléphone.
            out.edgeLeft = run([[10, 300], [60, 300], [150, 300]]);
            out.edgeRight = run([[350, 300], [300, 300]]).steps[0].state;

            // Repasser sous le seuil avant de lever le doigt annule.
            out.back = run([[100, 300], [120, 300], [180, 300], [130, 300]]).end;

            out.offsets = [swipeOffset(-20), swipeOffset(0), swipeOffset(40), swipeOffset(SWIPE.threshold), swipeOffset(500)];
            console.log(JSON.stringify(out));
            JS;

        $process = new Process([$node, '--input-type=module', '-e', $program]);
        $process->mustRun();
        $out = json_decode(trim($process->getOutput()), true);

        $this->assertSame(['reply' => true, 'swiped' => true], $out['right']['end']);
        $this->assertSame(['pending', 'pending', 'swiping', 'swiping', 'swiping', 'swiping'], $out['right']['states']);
        $this->assertSame(1, $out['right']['crossed']);
        $this->assertLessThanOrEqual(96, $out['right']['max']);

        $this->assertSame(['reply' => false, 'swiped' => true], $out['short']);
        $this->assertSame(['reply' => false, 'swiped' => false], $out['scroll']['end']);
        $this->assertSame('scrolling', $out['scroll']['last']['state']);
        $this->assertSame(0, $out['scroll']['last']['offset']);
        $this->assertSame(['reply' => false, 'swiped' => false], $out['left']);

        $this->assertSame('ignored', $out['edgeLeft']['steps'][0]['state']);
        $this->assertSame(['reply' => false, 'swiped' => false], $out['edgeLeft']['end']);
        $this->assertSame('ignored', $out['edgeRight']);

        $this->assertSame(['reply' => false, 'swiped' => true], $out['back']);
        $this->assertSame([0, 0, 40, 64, 96], $out['offsets']);
    }
}
