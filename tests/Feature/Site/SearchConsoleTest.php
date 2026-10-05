<?php

namespace Tests\Feature\Site;

use App\Enums\UserRole;
use App\Models\AdminActivity;
use App\Models\User;
use App\Support\SearchConsole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Code de vérification Google Search Console saisi dans l'admin : balise ajoutée dans toutes les pages ;
 * adresse du plan du site à déclarer, fondée sur APP_URL.
 */
class SearchConsoleTest extends TestCase
{
    use RefreshDatabase;

    private const string CODE = 'AbCdEf0123456789_-xYzAbCdEf0123456789abcde';

    private function save(string $value): TestResponse
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->from(route('admin.settings.edit', 'google'))
            ->put(route('admin.settings.update', 'google'), ['google_verification' => $value]);
    }

    public function test_no_tag_until_a_code_is_saved(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('google-site-verification', false);
    }

    public function test_the_whole_google_tag_pasted_from_a_phone_is_accepted_and_added_to_every_page(): void
    {
        $this->save("\u{00A0}<meta name=\"google-site-verification\" content=\"".self::CODE.'" /> ')->assertSessionHasNoErrors();
        auth()->logout();

        $this->assertSame(self::CODE, config('site.seo.google_verification'));
        $tag = '<meta name="google-site-verification" content="'.self::CODE.'">';

        foreach (['home', 'founder', 'about', 'formations.index', 'login', 'legal.terms'] as $name) {
            $this->get(route($name))->assertOk()->assertSee($tag, false);
        }

        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertSee($tag, false);
        $this->assertSame(1, AdminActivity::query()->where('description', 'Code de vérification Google Search Console enregistré.')->count());
    }

    public function test_the_code_alone_is_accepted_and_an_empty_field_removes_the_tag(): void
    {
        $this->save(self::CODE)->assertSessionHasNoErrors();
        $this->get(route('home'))->assertSee(self::CODE, false);

        $this->save('')->assertSessionHasNoErrors();
        $this->get(route('home'))->assertDontSee('google-site-verification', false);
    }

    public function test_an_invalid_code_is_refused(): void
    {
        $this->save('<script>alert(1)</script>')->assertSessionHasErrors(['google_verification' => SearchConsole::invalidMessage()]);
        $this->save('abc')->assertSessionHasErrors('google_verification');
        $this->assertSame('', config('site.seo.google_verification'));
    }

    public function test_the_admin_screen_shows_the_sitemap_address_on_the_app_url(): void
    {
        config(['app.url' => 'https://lomeiaschool.laravel.cloud']);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.settings.edit', 'google'))->assertOk()
            ->assertSee('Code de vérification Google Search Console')
            ->assertSee('https://lomeiaschool.laravel.cloud/sitemap.xml');

        $this->assertSame('https://lomeiaschool.laravel.cloud/sitemap.xml', SearchConsole::sitemapUrl());
    }

    public function test_students_and_moderators_cannot_change_it(): void
    {
        $this->actingAs(User::factory()->create())->put(route('admin.settings.update', 'google'), ['google_verification' => self::CODE])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Moderator]))->put(route('admin.settings.update', 'google'), ['google_verification' => self::CODE])->assertForbidden();
        $this->assertSame('', config('site.seo.google_verification'));
    }
}
