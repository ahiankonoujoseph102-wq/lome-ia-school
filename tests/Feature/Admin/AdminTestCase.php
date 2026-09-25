<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

abstract class AdminTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        // Mot de passe « password » (fabrique), double authentification activée (obligatoire pour l'équipe).
        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Joseph Admin']);
    }

    protected function moderator(): User
    {
        return User::factory()->create(['role' => UserRole::Moderator]);
    }
}
