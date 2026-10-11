<?php

use App\Http\Middleware\BlockSearchEngines;
use App\Http\Middleware\EnsureEmailIsConfirmed;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\LogoutBlockedUser;
use App\Http\Middleware\PreventPrivateCaching;
use App\Http\Middleware\RequireStaffTwoFactor;
use App\Http\Middleware\TrackAffiliateReferral;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Notifications de services extérieurs (Meta), hors du groupe « web » : voir routes/webhooks.php.
        then: function (): void {
            Route::group([], base_path('routes/webhooks.php'));
        },
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'email-confirmed' => EnsureEmailIsConfirmed::class,
            'no-store' => PreventPrivateCaching::class,
            'staff-2fa' => RequireStaffTwoFactor::class,
        ]);

        $middleware->web(append: [
            LogoutBlockedUser::class,
            TrackAffiliateReferral::class,
        ]);

        // Sur toutes les réponses (pages, redirections vers la connexion, erreurs) : « noindex » hors pages publiques.
        $middleware->append(BlockSearchEngines::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
