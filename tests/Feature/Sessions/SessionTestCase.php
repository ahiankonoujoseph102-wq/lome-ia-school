<?php

namespace Tests\Feature\Sessions;

use App\Enums\EnrollmentSource;
use App\Enums\UserRole;
use App\Events\FormationAccessGranted;
use App\Models\Formation;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

abstract class SessionTestCase extends TestCase
{
    use RefreshDatabase;

    protected Formation $formation;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('chat_media');
        Storage::fake('payment_proofs');
        $this->formation = Formation::factory()->create(['slug' => 'automatisation-ia', 'title' => 'Automatisation avec l\'IA', 'price' => 25000]);
    }

    protected function student(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        FormationAccessGranted::dispatch($user->enroll($this->formation, EnrollmentSource::Admin));

        return $user;
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin, 'name' => 'Joseph Formateur']);
    }

    protected function moderator(): User
    {
        return User::factory()->create(['role' => UserRole::Moderator]);
    }

    protected function online(array $attributes = []): LiveSession
    {
        return LiveSession::factory()->for($this->formation)->create($attributes);
    }

    protected function onsite(int $capacity = 20, ?int $supplement = null, array $attributes = []): LiveSession
    {
        return LiveSession::factory()->for($this->formation)->onsite($capacity, $supplement)->create($attributes);
    }
}
