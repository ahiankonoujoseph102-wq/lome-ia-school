<?php

namespace Tests\Feature\Chat;

use App\Enums\ChatMessageType;
use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class ChatAccessAndPrivacyTest extends ChatTestCase
{
    public function test_guest_is_sent_to_login(): void
    {
        $this->get($this->url('chat.show'))->assertRedirect(route('login'));
        $this->get($this->url('chat.messages.index'))->assertRedirect(route('login'));
    }

    public function test_non_member_cannot_read_write_or_download_anything(): void
    {
        $member = $this->student();
        Storage::disk('chat_media')->put('1/fiche.pdf', '%PDF-1.4 contenu');
        $message = $this->message($member, 'Secret', [
            'type' => ChatMessageType::File,
            'attachment_path' => '1/fiche.pdf',
            'attachment_name' => 'fiche.pdf',
            'attachment_mime' => 'application/pdf',
            'attachment_size' => 16,
        ]);

        $outsider = User::factory()->create();
        $this->actingAs($outsider);

        $this->get($this->url('chat.show'))->assertForbidden()->assertDontSee('Secret')->assertSee('réservé aux élèves');
        $this->getJson($this->url('chat.messages.index', ['after' => 0]))->assertForbidden();
        $this->getJson($this->url('chat.messages.index', ['before' => $message->id + 1]))->assertForbidden();
        $this->postJson($this->url('chat.messages.store'), ['type' => 'text', 'body' => 'Intrus'])->assertForbidden();
        $this->get($this->url('chat.attachments.show', ['message' => $message]))->assertForbidden();
        $this->get($this->url('chat.members'))->assertForbidden();
        $this->postJson($this->url('chat.messages.report', ['message' => $message]), ['reason' => 'spam'])->assertForbidden();

        $this->assertSame(1, $this->group()->messages()->count());
    }

    public function test_file_of_another_group_cannot_be_read_through_this_group(): void
    {
        $other = Formation::factory()->create();
        $foreignAuthor = $this->student([], $other);
        $foreign = ChatGroup::forFormation($other)->messages()->create(['user_id' => $foreignAuthor->id, 'type' => ChatMessageType::Text, 'body' => 'Autre groupe']);

        $this->actingAs($this->student())
            ->get($this->url('chat.attachments.show', ['message' => $foreign]))
            ->assertNotFound();
    }

    public function test_member_downloads_files_privately(): void
    {
        $student = $this->student();
        Storage::disk('chat_media')->put('1/fiche.pdf', '%PDF-1.4 contenu');
        $message = $this->message($student, 'La fiche', [
            'type' => ChatMessageType::File,
            'attachment_path' => '1/fiche.pdf',
            'attachment_name' => 'Fiche de cours.pdf',
            'attachment_mime' => 'application/pdf',
            'attachment_size' => 16,
        ]);

        $response = $this->actingAs($this->student())->get($this->url('chat.attachments.show', ['message' => $message]));

        $response->assertOk();
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_phone_numbers_and_emails_of_members_are_never_shown(): void
    {
        $author = $this->student(['name' => 'Kodjo Mensah', 'email' => 'kodjo.prive@example.com', 'phone' => '+22890112233']);
        $reader = $this->student(['name' => 'Afi Dossou', 'email' => 'afi@example.com', 'phone' => '+22891445566']);
        $this->message($author, 'Bonjour, je suis Kodjo');
        $this->message($this->admin(), 'Bienvenue');

        $this->actingAs($reader);

        foreach ([
            $this->get($this->url('chat.show')),
            $this->get($this->url('chat.members')),
            $this->getJson($this->url('chat.messages.index', ['after' => 0])),
        ] as $response) {
            $response->assertOk()
                ->assertSee('Kodjo Mensah')
                ->assertDontSee('kodjo.prive@example.com')
                ->assertDontSee('+22890112233')
                ->assertDontSee('90 11 22 33')
                ->assertDontSee('90112233');
        }
    }

    public function test_excluded_member_sees_an_explanation(): void
    {
        $student = $this->student();
        ChatGroupMember::query()->where('user_id', $student->id)->update(['removed_at' => now(), 'removal_reason' => 'excluded']);

        $this->actingAs($student)->get($this->url('chat.show'))
            ->assertForbidden()
            ->assertSee('Vous avez été retiré de ce groupe');
    }

    public function test_private_chat_pages_are_never_cached(): void
    {
        $student = $this->student();

        foreach ([route('chat.index'), $this->url('chat.show'), $this->url('chat.messages.index', ['after' => 0])] as $url) {
            $cacheControl = (string) $this->actingAs($student)->get($url)->headers->get('Cache-Control');
            $this->assertStringContainsString('no-store', $cacheControl, $url);
            $this->assertStringContainsString('private', $cacheControl, $url);
        }
    }

    public function test_legal_pages_explain_group_rules_and_privacy(): void
    {
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('Groupes de discussion')
            ->assertSee('mettre un membre en sourdine')
            ->assertSee('l\'exclure du groupe', false);

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('ne sont jamais visibles des autres membres')
            ->assertSee('conservés tant que le groupe de la formation existe')
            ->assertSee('E-mail récapitulatif');
    }
}
