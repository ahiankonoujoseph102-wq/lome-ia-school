<?php

namespace Tests\Feature\Chat;

use App\Enums\ChatMessageType;
use App\Enums\EnrollmentSource;
use App\Enums\UserRole;
use App\Events\FormationAccessGranted;
use App\Models\ChatGroup;
use App\Models\ChatMessage;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

abstract class ChatTestCase extends TestCase
{
    use RefreshDatabase;

    protected Formation $formation;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('chat_media');
        $this->formation = Formation::factory()->create(['slug' => 'automatisation-ia', 'title' => 'Automatisation avec l\'IA']);
    }

    /**
     * Élève ayant accès à la formation (ajouté au groupe par l'événement d'accès, comme en vrai).
     */
    protected function student(array $attributes = [], ?Formation $formation = null): User
    {
        $user = User::factory()->create($attributes);
        FormationAccessGranted::dispatch($user->enroll($formation ?? $this->formation, EnrollmentSource::Admin));

        return $user;
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin, 'name' => 'Joseph Formateur']);
    }

    protected function moderator(): User
    {
        return User::factory()->create(['role' => UserRole::Moderator, 'name' => 'Esi Équipe']);
    }

    protected function group(?Formation $formation = null): ChatGroup
    {
        return ChatGroup::forFormation($formation ?? $this->formation);
    }

    protected function message(User $author, ?string $body = 'Bonjour à tous', array $attributes = []): ChatMessage
    {
        return $this->group()->messages()->create([
            'user_id' => $author->id,
            'type' => ChatMessageType::Text,
            'body' => $body,
            ...$attributes,
        ]);
    }

    protected function url(string $name, array $parameters = []): string
    {
        return route($name, ['formation' => $this->formation, ...$parameters]);
    }
}
