<?php

namespace Tests\Feature\Chat;

use App\Chat\ChatFeatures;
use App\Enums\AdminAction;
use App\Models\AdminActivity;
use App\Settings\SiteSettings;
use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Pause pendant l'enregistrement d'une note vocale : interrupteur éteint par défaut (zone d'écriture inchangée),
 * allumé : « Pause » / « Reprendre », « Annuler », puis écouter avant « Envoyer » ou « Supprimer ».
 */
class VoicePauseTest extends ChatTestCase
{
    public function test_the_switch_is_off_by_default_and_the_composer_is_unchanged(): void
    {
        $this->assertFalse(ChatFeatures::voicePause());

        $page = $this->actingAs($this->student())->get($this->url('chat.show'))->assertOk();

        $page->assertDontSee('data-test="voice-recording-pause"', false)
            ->assertDontSee('data-test="voice-review"', false)
            ->assertDontSee('data-test="voice-pause"', false)
            // Enregistrement d'avant : même bloc, même condition.
            ->assertSee('<div x-show="recording" x-cloak', false)
            ->assertSee('<div x-show="!recording" class="flex items-end gap-1.5">', false)
            ->assertSee('\\u0022voicePause\\u0022:false', false);
    }

    public function test_switched_on_the_composer_offers_pause_cancel_listen_send_and_delete(): void
    {
        SiteSettings::save(['site.chat_features' => [ChatFeatures::VOICE_PAUSE => true]]);

        $page = $this->actingAs($this->student())->get($this->url('chat.show'))->assertOk();

        $page->assertSee('data-test="voice-recording-pause"', false)
            ->assertSee('data-test="voice-pause"', false)
            ->assertSee('data-test="voice-cancel"', false)
            ->assertSee('data-test="voice-finish"', false)
            ->assertSee('data-test="voice-review"', false)
            ->assertSee('data-test="voice-listen"', false)
            ->assertSee('data-test="voice-send"', false)
            ->assertSee('data-test="voice-discard"', false)
            ->assertSee('Reprendre')
            ->assertSee('\\u0022voicePause\\u0022:true', false)
            // Navigateur sans pause : l'ancien bloc reste disponible.
            ->assertSee('<div x-show="recording &amp;&amp; !pauseMode" x-cloak', false);
    }

    public function test_a_voice_note_is_still_sent_the_same_way(): void
    {
        SiteSettings::save(['site.chat_features' => [ChatFeatures::VOICE_PAUSE => true]]);
        $student = $this->student();

        $this->actingAs($student)->postJson($this->url('chat.messages.store'), [
            'type' => 'voice',
            'duration' => 12,
            'attachment' => UploadedFile::fake()->create('note-vocale.m4a', 300, 'audio/mp4'),
        ])->assertCreated();

        $this->assertSame(12, $this->group()->messages()->latest('id')->first()->duration_seconds);
    }

    public function test_admin_switches_each_feature_and_the_change_is_logged(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.settings.edit', 'groupes'))
            ->assertOk()
            ->assertSee('Pause des notes vocales')
            ->assertSee('data-test="chat-feature-voice_pause"', false);

        $this->actingAs($admin)->put(route('admin.settings.update', 'groupes'), ['features' => [ChatFeatures::VOICE_PAUSE => '1']])
            ->assertRedirect(route('admin.settings.edit', 'groupes'));

        $this->assertTrue(ChatFeatures::voicePause());
        $this->assertTrue(AdminActivity::query()->where('action', AdminAction::SettingsUpdated)->where('description', 'like', '%Pause des notes vocales activée%')->exists());

        $this->actingAs($admin)->put(route('admin.settings.update', 'groupes'), ['features' => [ChatFeatures::VOICE_PAUSE => '0']]);
        $this->assertFalse(ChatFeatures::voicePause());
    }

    public function test_moderators_and_students_cannot_change_the_switches(): void
    {
        $this->actingAs($this->moderator())->put(route('admin.settings.update', 'groupes'), ['features' => [ChatFeatures::VOICE_PAUSE => '1']])->assertForbidden();
        $this->actingAs($this->student())->put(route('admin.settings.update', 'groupes'), ['features' => [ChatFeatures::VOICE_PAUSE => '1']])->assertForbidden();

        $this->assertFalse(ChatFeatures::voicePause());
    }

    /**
     * Chronomètre arrêté pendant la pause et détection de la pause (resources/js/voice-recording.js) avec Node.js.
     */
    public function test_recording_clock_and_pause_support_with_node(): void
    {
        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->markTestSkipped('Node.js est nécessaire pour exécuter resources/js/voice-recording.js.');
        }

        $module = json_encode('file://'.resource_path('js/voice-recording.js'));
        $program = <<<JS
            const { canPauseRecording, createRecordingClock } = await import({$module});
            const clock = createRecordingClock(1000);
            const out = { a: clock.elapsed(4000) };
            clock.pause(4000);
            out.paused = clock.paused;
            out.b = clock.elapsed(60000);
            clock.pause(61000);
            out.c = clock.elapsed(61000);
            clock.resume(70000);
            out.d = clock.elapsed(72500);
            class WithPause { pause() {} resume() {} }
            class WithoutPause { start() {} stop() {} }
            out.support = [canPauseRecording(WithPause), canPauseRecording(WithoutPause), canPauseRecording(undefined)];
            console.log(JSON.stringify(out));
            JS;

        $process = new Process([$node, '--input-type=module', '-e', $program]);
        $process->mustRun();
        $out = json_decode(trim($process->getOutput()), true);

        $this->assertSame(3000, $out['a']);
        $this->assertTrue($out['paused']);
        // Pendant la pause, le chronomètre ne bouge pas (une deuxième pause ne change rien).
        $this->assertSame(3000, $out['b']);
        $this->assertSame(3000, $out['c']);
        $this->assertSame(5500, $out['d']);
        // Sans pause dans le navigateur : bouton caché, enregistrement comme avant.
        $this->assertSame([true, false, false], $out['support']);
    }
}
