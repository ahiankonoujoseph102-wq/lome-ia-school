<?php

namespace Tests\Feature\Chat;

use App\Chat\ChatFeatures;
use App\Contracts\PushSender;
use App\Enums\ChatMessageType;
use App\Enums\UserRole;
use App\Models\ChatMessage;
use App\Models\ChatPoll;
use App\Models\ChatPollVote;
use App\Models\Formation;
use App\Models\User;
use App\Settings\SiteSettings;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\Fakes\FakePushSender;

/**
 * Sondages : interrupteur éteint par défaut, création par les administrateurs seulement, vote, changement et retrait
 * du vote par tous les membres du groupe (jamais par un non-membre), résultats, clôture par l'auteur ou un administrateur.
 */
class PollsTest extends ChatTestCase
{
    private function enable(): void
    {
        SiteSettings::save(['site.chat_features' => [ChatFeatures::POLLS => true]]);
    }

    /**
     * @param  list<string>  $options
     */
    private function create(User $user, array $options = ['Samedi matin', 'Samedi soir', 'Dimanche'], bool $multiple = false, string $question = 'Quel horaire vous convient ?'): TestResponse
    {
        return $this->actingAs($user)->postJson($this->url('chat.polls.store'), ['question' => $question, 'options' => $options, 'multiple' => $multiple]);
    }

    /**
     * @param  list<int>  $options
     */
    private function vote(User $user, ChatPoll $poll, array $options): TestResponse
    {
        return $this->actingAs($user)->postJson($this->url('chat.polls.vote', ['poll' => $poll]), ['options' => $options]);
    }

    private function optionId(ChatPoll $poll, int $index): int
    {
        return $poll->options()->get()[$index]->id;
    }

    public function test_switched_off_by_default_nothing_appears_and_routes_refuse(): void
    {
        $admin = $this->admin();

        $this->assertFalse(ChatFeatures::polls());
        $this->actingAs($admin)->get($this->url('chat.show'))->assertOk()
            ->assertDontSee('data-test="poll-button"', false)
            ->assertDontSee('data-test="poll-dialog"', false);

        $this->create($admin)->assertNotFound();
        $this->assertSame(0, ChatMessage::query()->count());
        $this->assertFalse(Schema::hasColumn('chat_messages', 'poll_id'));
    }

    public function test_only_administrators_see_the_button_and_create_a_poll(): void
    {
        $this->enable();
        $student = $this->student();
        $moderator = $this->moderator();
        $admin = $this->admin();

        $this->actingAs($student)->get($this->url('chat.show'))->assertOk()->assertDontSee('data-test="poll-button"', false);
        $this->actingAs($moderator)->get($this->url('chat.show'))->assertOk()->assertDontSee('data-test="poll-button"', false);
        $this->actingAs($admin)->get($this->url('chat.show'))->assertOk()
            ->assertSee('data-test="poll-button"', false)
            ->assertSee('data-test="poll-dialog"', false)
            ->assertSee('Plusieurs réponses possibles');

        $this->create($student)->assertForbidden();
        $this->create($moderator)->assertForbidden();

        $this->create($admin, ['  Samedi   matin ', 'Samedi soir', '', 'Dimanche'], true)->assertCreated();

        $message = ChatMessage::query()->sole();
        $this->assertSame(ChatMessageType::Poll, $message->type);
        $this->assertSame('📊 Sondage · Quel horaire vous convient ?', $message->preview());
        $this->assertTrue($message->poll->allows_multiple);
        $this->assertSame(['Samedi matin', 'Samedi soir', 'Dimanche'], $message->poll->options->pluck('label')->all());
    }

    public function test_an_administrator_without_two_factor_cannot_create_a_poll(): void
    {
        $this->enable();
        $admin = User::factory()->withoutTwoFactor()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get($this->url('chat.show'))->assertOk()->assertDontSee('data-test="poll-button"', false);
        $this->create($admin)->assertForbidden();
        $this->assertSame(0, ChatPoll::query()->count());
    }

    public function test_question_and_two_to_ten_distinct_choices_are_required(): void
    {
        $this->enable();
        $admin = $this->admin();

        $this->create($admin, ['Oui'])->assertUnprocessable()->assertJsonValidationErrors(['options' => 'Ajoutez au moins 2 choix.']);
        $this->create($admin, array_map(fn (int $i): string => 'Choix '.$i, range(1, 11)))->assertUnprocessable()->assertJsonValidationErrors('options');
        $this->create($admin, ['Oui', 'oui'])->assertUnprocessable()->assertJsonValidationErrors(['options' => 'Deux choix sont identiques : modifiez-en un.']);
        $this->create($admin, ['Oui', 'Non'], question: '  ')->assertUnprocessable()->assertJsonValidationErrors(['question' => 'Écrivez la question du sondage.']);
        $this->create($admin, ['Oui', str_repeat('a', 151)])->assertUnprocessable()->assertJsonValidationErrors('options.1');
        $this->create($admin, array_map(fn (int $i): string => 'Choix '.$i, range(1, 10)))->assertCreated();

        $this->assertSame(1, ChatPoll::query()->count());
    }

    public function test_members_vote_change_and_remove_their_vote_with_live_results(): void
    {
        $this->enable();
        Mail::fake();
        $this->app->instance(PushSender::class, $push = new FakePushSender);
        $this->create($this->admin())->assertCreated();
        $poll = ChatPoll::query()->sole();
        [$first, $second] = [$this->optionId($poll, 0), $this->optionId($poll, 1)];
        $ama = $this->student(['name' => 'Ama Lawson']);
        $kossi = $this->student(['name' => 'Kossi Agbeko']);

        $this->vote($ama, $poll, [$first])->assertOk();
        $response = $this->vote($kossi, $poll, [$second])->assertOk();

        // Résultats renvoyés tout de suite : barre, nombre, pourcentage ; choix de la personne mis en évidence.
        $html = (string) $response->json('html');
        $this->assertStringContainsString('data-test="poll"', $html);
        $this->assertStringContainsString('2 votants', $html);
        $this->assertStringContainsString('1 vote · 50 %', $html);
        $this->assertStringContainsString('style="width: 50%"', $html);
        $this->assertMatchesRegularExpression('/chat-poll-option chat-poll-option-mine"\s+data-poll-option="'.$second.'"/', $html);
        $this->assertStringContainsString('data-poll-mine="'.$second.'"', $html);

        // Changer d'avis (une seule réponse) : le vote est remplacé.
        $this->vote($kossi, $poll, [$first])->assertOk();
        $this->assertSame([$first, $first], ChatPollVote::query()->orderBy('id')->pluck('chat_poll_option_id')->all());

        // Retirer son vote.
        $this->vote($kossi, $poll, [])->assertOk();
        $this->assertSame(1, ChatPollVote::query()->count());

        // Une seule réponse : deux choix refusés ; choix d'un autre sondage refusé.
        $this->vote($ama, $poll, [$first, $second])->assertUnprocessable()->assertJsonValidationErrors(['options' => 'Un seul choix possible pour ce sondage.']);
        $this->vote($ama, $poll, [999999])->assertUnprocessable()->assertJsonValidationErrors(['options' => 'Choix inconnu pour ce sondage.']);

        // Les autres membres voient les résultats à jour par la mise à jour régulière (message modifié).
        $this->assertNotNull($poll->message->fresh()->updated_at);
        $page = $this->actingAs($this->student())->get($this->url('chat.show'))->assertOk();
        $page->assertSee('1 votant')->assertSee('1 vote · 100 %');

        // Aucun vote ne prévient personne.
        Mail::assertNothingQueued();
        $this->assertSame([], $push->sent);
    }

    public function test_several_answers_when_allowed(): void
    {
        $this->enable();
        $this->create($this->admin(), multiple: true)->assertCreated();
        $poll = ChatPoll::query()->sole();
        $student = $this->student();

        $html = (string) $this->vote($student, $poll, [$this->optionId($poll, 0), $this->optionId($poll, 2)])->assertOk()->json('html');

        $this->assertSame(2, ChatPollVote::query()->count());
        $this->assertStringContainsString('1 votant', $html);
        // Pourcentage des votants : chaque choix coché par l'unique votant compte 100 %.
        $this->assertSame(2, substr_count($html, '1 vote · 100 %'));
        $this->assertStringContainsString('Plusieurs réponses possibles', $html);
    }

    public function test_only_members_of_the_group_can_vote(): void
    {
        $this->enable();
        $this->create($this->admin())->assertCreated();
        $poll = ChatPoll::query()->sole();
        $option = $this->optionId($poll, 0);

        // Élève d'une autre formation : jamais dans ce groupe.
        $other = Formation::factory()->create();
        $outsider = $this->student([], $other);
        $this->vote($outsider, $poll, [$option])->assertForbidden();

        // Même en passant par l'adresse de son propre groupe : le sondage n'en fait pas partie.
        $this->actingAs($outsider)->postJson(route('chat.polls.vote', ['formation' => $other, 'poll' => $poll]), ['options' => [$option]])->assertNotFound();

        // Membre retiré du groupe.
        $removed = $this->student();
        $this->group()->members()->where('user_id', $removed->id)->update(['removed_at' => now()]);
        $this->vote($removed, $poll, [$option])->assertForbidden();

        // Visiteur sans compte.
        $this->assertContains($this->postJson($this->url('chat.polls.vote', ['poll' => $poll]), ['options' => [$option]])->status(), [401, 403]);

        $this->assertSame(0, ChatPollVote::query()->count());
    }

    public function test_author_or_administrators_close_the_poll_and_it_refuses_votes(): void
    {
        $this->enable();
        $author = $this->admin();
        $this->create($author)->assertCreated();
        $poll = ChatPoll::query()->sole();
        $student = $this->student();
        $this->vote($student, $poll, [$this->optionId($poll, 0)])->assertOk();

        // Élève et modérateur : pas de bouton, refus.
        $this->actingAs($student)->get($this->url('chat.show'))->assertDontSee('data-test="poll-close"', false);
        $this->actingAs($student)->postJson($this->url('chat.polls.close', ['poll' => $poll]))->assertForbidden();
        $this->actingAs($this->moderator())->postJson($this->url('chat.polls.close', ['poll' => $poll]))->assertForbidden();

        // Un autre administrateur peut clôturer.
        $this->actingAs($author)->get($this->url('chat.show'))->assertSee('data-test="poll-close"', false);
        $html = (string) $this->actingAs($this->admin())->postJson($this->url('chat.polls.close', ['poll' => $poll]))->assertOk()->json('html');

        $this->assertTrue($poll->fresh()->isClosed());
        $this->assertStringContainsString('data-test="poll-closed"', $html);
        $this->assertStringContainsString('1 vote · 100 %', $html);
        $this->assertStringNotContainsString('data-poll-option=', $html);
        $this->assertStringNotContainsString('data-test="poll-close"', $html);

        $this->vote($student, $poll, [])->assertUnprocessable()->assertJsonValidationErrors(['options' => 'Ce sondage est clôturé : il n\'accepte plus de vote.']);
        $this->assertSame(1, ChatPollVote::query()->count());
    }

    public function test_deleting_the_poll_removes_it_and_switched_off_old_polls_stay_readable(): void
    {
        $this->enable();
        $admin = $this->admin();
        $this->create($admin, question: 'Sondage gardé')->assertCreated();
        $this->create($admin, question: 'Sondage supprimé')->assertCreated();
        [$kept, $gone] = ChatPoll::query()->orderBy('id')->get()->all();
        $student = $this->student();
        $this->vote($student, $gone, [$this->optionId($gone, 0)]);

        $this->actingAs($admin)->deleteJson($this->url('chat.messages.destroy', ['message' => $gone->message]))->assertOk();
        $this->assertSame(1, ChatPoll::query()->count());
        $this->assertSame(0, ChatPollVote::query()->count());

        // Interrupteur éteint : plus de bouton ni de vote ; le sondage reste lisible, sans choix à toucher.
        SiteSettings::save(['site.chat_features' => [ChatFeatures::POLLS => false]]);
        $page = $this->actingAs($student)->get($this->url('chat.show'))->assertOk();
        $page->assertSee('Sondage gardé')->assertDontSee('Sondage supprimé')->assertDontSee('data-poll-option=', false);
        $this->vote($student, $kept, [$this->optionId($kept, 0)])->assertNotFound();
    }

    public function test_the_usual_message_route_never_accepts_the_poll_type(): void
    {
        $this->enable();

        $this->actingAs($this->admin())->postJson($this->url('chat.messages.store'), ['type' => 'poll', 'body' => 'Question ?'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type' => 'Type de message inconnu.']);
    }

    public function test_the_migration_can_run_again_and_index_names_are_short(): void
    {
        $migration = require database_path('migrations/2026_10_20_100001_create_chat_polls_tables.php');

        // Tout existe déjà : aucune erreur.
        $migration->up();

        // Tables à moitié créées (sans index ni clé) : la reprise ajoute ce qui manque.
        Schema::drop('chat_poll_votes');
        Schema::create('chat_poll_votes', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('chat_poll_id');
            $table->unsignedBigInteger('chat_poll_option_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
        $migration->up();

        $names = collect(['chat_polls', 'chat_poll_options', 'chat_poll_votes'])
            ->flatMap(fn (string $table) => [
                ...collect(Schema::getIndexes($table))->pluck('name'),
                ...collect(Schema::getForeignKeys($table))->pluck('name'),
            ]);

        $this->assertContains('chat_poll_votes_option_user_unique', $names);
        $this->assertContains('chat_poll_votes_poll_user_idx', $names);
        $this->assertCount(3, Schema::getForeignKeys('chat_poll_votes'));
        foreach ($names->filter() as $name) {
            $this->assertLessThanOrEqual(64, strlen((string) $name));
        }
    }

    public function test_vote_logic_with_node(): void
    {
        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->markTestSkipped('Node.js est nécessaire pour exécuter resources/js/chat-poll.js.');
        }

        $module = json_encode('file://'.resource_path('js/chat-poll.js'));
        $program = <<<JS
            const { nextPollChoice, parsePollMine } = await import({$module});
            console.log(JSON.stringify({
                singleFirst: nextPollChoice([], '3', false),
                singleSwitch: nextPollChoice([3], '5', false),
                singleRemove: nextPollChoice([3], '3', false),
                multiAdd: nextPollChoice([3], '5', true),
                multiRemove: nextPollChoice([3, 5], '3', true),
                parsed: parsePollMine('3, 5,,x'),
                empty: parsePollMine(''),
            }));
            JS;

        $process = new Process([$node, '--input-type=module', '-e', $program]);
        $process->mustRun();
        $out = json_decode(trim($process->getOutput()), true);

        $this->assertSame([3], $out['singleFirst']);
        $this->assertSame([5], $out['singleSwitch']);
        $this->assertSame([], $out['singleRemove']);
        $this->assertSame([3, 5], $out['multiAdd']);
        $this->assertSame([5], $out['multiRemove']);
        $this->assertSame([3, 5], $out['parsed']);
        $this->assertSame([], $out['empty']);
    }
}
