<?php

namespace App\Providers;

use App\Contracts\PushSender;
use App\Models\User;
use App\Push\NullPushSender;
use App\Push\WebPushSender;
use App\Settings\SiteSettings;
use App\Support\FileStorage;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Fichiers envoyés : dossiers du serveur (Hostinger) ou stockage objet (Laravel Cloud), selon .env.
        FileStorage::configure();

        // Notifications sur téléphone : envoi réel seulement si les clés VAPID sont dans .env.
        $this->app->singleton(PushSender::class, function (): PushSender {
            /** @var array{public_key: string|null, private_key: string|null, subject: string|null} $config */
            $config = (array) config('services.webpush');
            $sender = new WebPushSender($config);

            return $sender->isConfigured() ? $sender : new NullPushSender;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Réglages modifiés depuis l'admin : ils remplacent les valeurs des fichiers config/*.php.
        SiteSettings::apply();

        $this->configureDefaults();

        // Derrière un proxy (Laravel Cloud) : vraie adresse IP des visiteurs et https.
        if (filled($proxies = config('app.trusted_proxies'))) {
            TrustProxies::at((string) $proxies === '*' ? '*' : array_map('trim', explode(',', (string) $proxies)));
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Règle volontairement simple pour une inscription rapide sur téléphone.
        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(8)->letters()->numbers()
            : null,
        );

        Gate::define('access-admin', fn (User $user): bool => $user->isAdmin());
        Gate::define('moderate-chat', fn (User $user): bool => $user->isStaff());
        Gate::define('manage-sessions', fn (User $user): bool => $user->isStaff());

        // Messages des groupes : largement assez pour discuter, trop peu pour inonder un groupe.
        RateLimiter::for('chat-messages', fn (Request $request): Limit => Limit::perMinute(30)->by((string) ($request->user()?->id ?: $request->ip())));
    }
}
