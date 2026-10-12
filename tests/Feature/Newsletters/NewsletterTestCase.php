<?php

namespace Tests\Feature\Newsletters;

use App\Enums\NewsletterAudience;
use App\Enums\UserRole;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Base des tests des envois groupés : aucun vrai e-mail (Mail::fake), quota de Brevo réglé comme en production.
 */
abstract class NewsletterTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['site.mail_quota' => ['daily_limit' => 300, 'reserve' => 100, 'bulk_daily_limit' => 200]]);
        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Joseph Admin', 'email' => 'admin@example.com']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function draft(array $attributes = []): Newsletter
    {
        return Newsletter::query()->create($attributes + [
            'user_id' => $this->admin->id,
            'subject' => 'Nouvelle vidéo de Coach Joseph',
            'body' => "Une nouvelle vidéo est en ligne.\nElle explique les agents IA.\n\nBonne découverte !",
            'audience' => NewsletterAudience::All->value,
        ]);
    }

    /**
     * @return list<User>
     */
    protected function students(int $count): array
    {
        return User::factory()->count($count)->create()->all();
    }
}
