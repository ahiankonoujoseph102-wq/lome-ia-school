<?php

use App\Http\Controllers\Student\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    // Sécurité du compte : mot de passe, double authentification et passkeys
    Route::get('mon-espace/securite', [SecurityController::class, 'edit'])
        ->middleware('password.confirm')
        ->name('security.edit');
    Route::put('mon-espace/securite/mot-de-passe', [SecurityController::class, 'updatePassword'])
        ->name('security.password.update');

    // Anciennes adresses des paramètres du kit
    Route::redirect('settings', '/mon-espace/profil');
    Route::redirect('settings/profile', '/mon-espace/profil')->name('profile.edit');
    Route::redirect('settings/security', '/mon-espace/securite');
    Route::redirect('settings/appearance', '/mon-espace/profil');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
