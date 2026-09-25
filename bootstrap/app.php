<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\LogoutBlockedUser;
use App\Http\Middleware\PreventPrivateCaching;
use App\Http\Middleware\RequireStaffTwoFactor;
use App\Http\Middleware\TrackAffiliateReferral;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'no-store' => PreventPrivateCaching::class,
            'staff-2fa' => RequireStaffTwoFactor::class,
        ]);

        $middleware->web(append: [
            LogoutBlockedUser::class,
            TrackAffiliateReferral::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
